<?php

require_once __DIR__ . '/auth.php';

$db = Db::conn();

// ── Flash Messages ───────────────────────────────────────────────────
$msg = $_SESSION['flash_msg'] ?? '';
$msgType = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_msg'], $_SESSION['flash_type']);

// ── Handle Contact Creation, Update & Deletion ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', (string) ($_POST['csrf_token'] ?? ''))) {
        $_SESSION['flash_msg'] = 'نشست شما منقضی شده است؛ دوباره تلاش کنید.';
        $_SESSION['flash_type'] = 'error';
        header('Location: contacts.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create_contact' || $action === 'edit_contact') {
        $contactId = (int) ($_POST['contact_id'] ?? 0);
        $phoneRaw  = trim($_POST['phone_number'] ?? '');
        $name      = trim($_POST['display_name'] ?? '');
        $pref      = trim($_POST['preferred_platform'] ?? '');
        $tgChatId  = trim($_POST['telegram_chat_id'] ?? '');
        $tgUser    = trim($_POST['telegram_username'] ?? '');
        $baleChatId= trim($_POST['bale_chat_id'] ?? '');
        $baleUser  = trim($_POST['bale_username'] ?? '');

        // Clean usernames (strip leading @)
        $tgUser   = ltrim($tgUser, '@');
        $baleUser = ltrim($baleUser, '@');

        if (!in_array($pref, ['telegram', 'bale'], true)) {
            $pref = null;
        }

        $phoneNorm = PhoneNormalizer::normalize($phoneRaw);
        if ($phoneNorm === null) {
            $_SESSION['flash_msg'] = 'شماره تلفن وارد شده نامعتبر است (مثال معتبر: 09121234567).';
            $_SESSION['flash_type'] = 'error';
            header('Location: contacts.php');
            exit;
        }

        try {
            if ($action === 'create_contact') {
                $stmt = $db->prepare('INSERT INTO contacts (phone_number, display_name, preferred_platform) VALUES (?, ?, ?)');
                $stmt->bind_param('sss', $phoneNorm, $name, $pref);
                $stmt->execute();
                $contactId = (int) $stmt->insert_id;
                $stmt->close();
                $flashMsg = 'مخاطب جدید با موفقیت ثبت شد.';
            } else {
                $stmt = $db->prepare('UPDATE contacts SET phone_number = ?, display_name = ?, preferred_platform = ? WHERE id = ?');
                $stmt->bind_param('sssi', $phoneNorm, $name, $pref, $contactId);
                $stmt->execute();
                $stmt->close();
                $flashMsg = 'اطلاعات مخاطب با موفقیت بروزرسانی شد.';
            }

            // Sync Telegram channel
            if ($tgChatId !== '') {
                $stmt = $db->prepare(
                    'INSERT INTO contact_channels (contact_id, platform, chat_id, username)
                     VALUES (?, "telegram", ?, ?)
                     ON DUPLICATE KEY UPDATE chat_id = VALUES(chat_id), username = VALUES(username)'
                );
                $stmt->bind_param('iss', $contactId, $tgChatId, $tgUser);
                $stmt->execute();
                $stmt->close();
            } elseif ($action === 'edit_contact' && !empty($_POST['remove_telegram'])) {
                $stmt = $db->prepare('DELETE FROM contact_channels WHERE contact_id = ? AND platform = "telegram"');
                $stmt->bind_param('i', $contactId);
                $stmt->execute();
                $stmt->close();
            }

            // Sync Bale channel
            if ($baleChatId !== '') {
                $stmt = $db->prepare(
                    'INSERT INTO contact_channels (contact_id, platform, chat_id, username)
                     VALUES (?, "bale", ?, ?)
                     ON DUPLICATE KEY UPDATE chat_id = VALUES(chat_id), username = VALUES(username)'
                );
                $stmt->bind_param('iss', $contactId, $baleChatId, $baleUser);
                $stmt->execute();
                $stmt->close();
            } elseif ($action === 'edit_contact' && !empty($_POST['remove_bale'])) {
                $stmt = $db->prepare('DELETE FROM contact_channels WHERE contact_id = ? AND platform = "bale"');
                $stmt->bind_param('i', $contactId);
                $stmt->execute();
                $stmt->close();
            }

            $_SESSION['flash_msg'] = $flashMsg;
            $_SESSION['flash_type'] = 'success';
        } catch (Throwable $e) {
            if (str_contains($e->getMessage(), 'uq_contacts_phone') || str_contains($e->getMessage(), 'Duplicate entry')) {
                $_SESSION['flash_msg'] = 'مخاطبی با این شماره تلفن از قبل وجود دارد.';
            } elseif (str_contains($e->getMessage(), 'uq_platform_chat')) {
                $_SESSION['flash_msg'] = 'این شناسه چت قبلاً به مخاطب دیگری متصل شده است.';
            } else {
                $_SESSION['flash_msg'] = 'خطا در ثبت مخاطب: ' . $e->getMessage();
            }
            $_SESSION['flash_type'] = 'error';
        }

        header('Location: contacts.php');
        exit;
    } elseif ($action === 'delete_contact') {
        $contactId = (int) ($_POST['contact_id'] ?? 0);
        if ($contactId > 0) {
            $stmt = $db->prepare('DELETE FROM contacts WHERE id = ?');
            $stmt->bind_param('i', $contactId);
            $stmt->execute();
            $stmt->close();
            $_SESSION['flash_msg'] = 'مخاطب با موفقیت حذف شد.';
            $_SESSION['flash_type'] = 'success';
        }
        header('Location: contacts.php');
        exit;
    }
}

// ── Search ───────────────────────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
$page   = max(1, (int) ($_GET['page'] ?? 1));
$limit  = 20;
$offset = ($page - 1) * $limit;

$where  = '';
$params = [];
$types  = '';

if ($search !== '') {
    $escapedSearch = addcslashes($search, '%_\\');
    // Match phone numbers regardless of stored ('98912…') vs entered ('0912…') format.
    $clauses = ['c.phone_number LIKE ? ESCAPE \'\\\\\''];
    $params  = ["%{$escapedSearch}%"];
    $types   = 's';

    $normalizedSearch = PhoneNormalizer::normalize($search);
    if ($normalizedSearch !== null && $normalizedSearch !== $search) {
        $clauses[] = 'c.phone_number LIKE ? ESCAPE \'\\\\\'';
        $params[]  = "%" . addcslashes($normalizedSearch, '%_\\') . "%";
        $types    .= 's';
    }

    $localSearch = PhoneNormalizer::toLocalDisplay($search);
    if ($localSearch !== $search && preg_match('/^\d+$/', $localSearch)) {
        $clauses[] = 'c.phone_number LIKE ? ESCAPE \'\\\\\'';
        $params[]  = "%" . addcslashes($localSearch, '%_\\') . "%";
        $types    .= 's';
    }

    $clauses[] = 'c.display_name LIKE ? ESCAPE \'\\\\\'';
    $params[]  = "%{$escapedSearch}%";
    $types    .= 's';

    $where = 'WHERE ' . implode(' OR ', $clauses);
}

// Count
$countSql = "SELECT COUNT(*) AS c FROM contacts c {$where}";
$stmt     = $db->prepare($countSql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

$totalPages = max(1, ceil($total / $limit));

// Fetch contacts
$sql = "SELECT c.*,
               GROUP_CONCAT(cc.platform ORDER BY cc.platform SEPARATOR ',') AS platforms,
               GROUP_CONCAT(CONCAT(cc.platform, ':', IFNULL(cc.username,'')) ORDER BY cc.platform SEPARATOR ',') AS platform_usernames,
               GROUP_CONCAT(CONCAT(cc.platform, '|', cc.chat_id, '|', IFNULL(cc.username,'')) ORDER BY cc.platform SEPARATOR ';;') AS platform_details
        FROM contacts c
        LEFT JOIN contact_channels cc ON cc.contact_id = c.id
        {$where}
        GROUP BY c.id
        ORDER BY c.created_at DESC
        LIMIT {$limit} OFFSET {$offset}";

$stmt = $db->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$contacts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Page setup ───────────────────────────────────────────────────────
$pageTitle    = 'مخاطبین';
$pageSubtitle = number_format($total) . ' مخاطب ثبت شده';
$activePage   = 'contacts';

ob_start();
?>

<!-- Alert / Flash Message -->
<?php if ($msg): ?>
    <div class="mb-6 p-4 rounded-xl flex items-center justify-between <?= $msgType === 'success' ? 'bg-emerald-50 border border-emerald-200 text-emerald-800' : 'bg-red-50 border border-red-200 text-red-800' ?>">
        <div class="flex items-center gap-2">
            <?php if ($msgType === 'success'): ?>
                <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
            <?php else: ?>
                <svg class="w-5 h-5 text-red-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>
            <?php endif; ?>
            <span class="text-sm font-medium"><?= htmlspecialchars($msg) ?></span>
        </div>
    </div>
<?php endif; ?>

<!-- Search & Header Action -->
<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <form method="GET" class="flex gap-3 max-w-lg flex-1">
        <div class="relative flex-1">
            <svg class="w-5 h-5 text-gray-400 absolute right-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
            </svg>
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                   placeholder="جستجو بر اساس شماره تلفن یا نام..."
                   class="w-full pr-10 pl-4 py-2.5 rounded-xl border border-gray-200 bg-white
                          focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand transition text-sm">
        </div>
        <button type="submit" class="btn px-5 py-2.5 rounded-xl bg-brand text-white text-sm font-medium hover:bg-brand-dark shadow-sm cursor-pointer">
            جستجو
        </button>
        <?php if ($search !== ''): ?>
            <a href="contacts.php" class="btn px-4 py-2.5 rounded-xl border border-gray-200 text-gray-600 text-sm font-medium hover:bg-gray-50 transition">
                پاک کردن
            </a>
        <?php endif; ?>
    </form>

    <div class="flex items-center gap-3">
        <button type="button" onclick="openCreateContactModal()"
                class="btn inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-brand text-white text-sm font-medium hover:bg-brand-dark shadow-sm shadow-brand/20 cursor-pointer transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z"/>
            </svg>
            <span>ثبت مخاطب جدید</span>
        </button>

        <a href="messages.php?open_send=1" class="btn inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-gray-200 bg-white text-gray-700 text-sm font-medium hover:bg-gray-50 transition shadow-sm">
            <svg class="w-4 h-4 text-brand" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/>
            </svg>
            <span>ارسال پیام جدید</span>
        </a>
    </div>
</div>

<!-- Contacts Table -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <?php if (empty($contacts)): ?>
        <div class="p-12 text-center text-gray-400">
            <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/>
            </svg>
            <p><?= $search !== '' ? 'نتیجه‌ای یافت نشد' : 'هنوز مخاطبی ثبت نشده است' ?></p>
            <button type="button" onclick="openCreateContactModal()" class="mt-4 px-4 py-2 rounded-xl bg-brand text-white text-xs font-medium hover:bg-brand-dark transition">
                + ثبت اولین مخاطب
            </button>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm" dir="ltr">
                <thead>
                    <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                        <th class="px-6 py-3 text-left font-medium">#</th>
                        <th class="px-6 py-3 text-left font-medium">شماره تلفن</th>
                        <th class="px-6 py-3 text-left font-medium">نام نمایشی</th>
                        <th class="px-6 py-3 text-left font-medium">پلتفرم‌ها</th>
                        <th class="px-6 py-3 text-left font-medium">ترجیحی</th>
                        <th class="px-6 py-3 text-left font-medium">تاریخ عضویت</th>
                        <th class="px-6 py-3 text-right font-medium">عملیات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach ($contacts as $c): ?>
                        <?php
                        $platforms = array_filter(explode(',', $c['platforms'] ?? ''));
                        $puPairs   = array_filter(explode(',', $c['platform_usernames'] ?? ''));
                        $usernames = [];
                        foreach ($puPairs as $pair) {
                            [$pl, $un] = array_pad(explode(':', $pair, 2), 2, '');
                            if ($un) $usernames[$pl] = $un;
                        }

                        // Parse platform details for modal editing
                        $detailsRaw = array_filter(explode(';;', $c['platform_details'] ?? ''));
                        $chanData = ['telegram_chat_id' => '', 'telegram_username' => '', 'bale_chat_id' => '', 'bale_username' => ''];
                        foreach ($detailsRaw as $dItem) {
                            $parts = explode('|', $dItem);
                            if (count($parts) >= 2) {
                                $pType = $parts[0];
                                $pChat = $parts[1];
                                $pUser = $parts[2] ?? '';
                                if ($pType === 'telegram') {
                                    $chanData['telegram_chat_id'] = $pChat;
                                    $chanData['telegram_username'] = $pUser;
                                } elseif ($pType === 'bale') {
                                    $chanData['bale_chat_id'] = $pChat;
                                    $chanData['bale_username'] = $pUser;
                                }
                            }
                        }

                        $contactJson = json_encode([
                            'id'                => (int)$c['id'],
                            'phone'             => PhoneNormalizer::toLocalDisplay($c['phone_number']),
                            'name'              => $c['display_name'] ?? '',
                            'preferred'         => $c['preferred_platform'] ?? '',
                            'telegram_chat_id'  => $chanData['telegram_chat_id'],
                            'telegram_username' => $chanData['telegram_username'],
                            'bale_chat_id'      => $chanData['bale_chat_id'],
                            'bale_username'     => $chanData['bale_username'],
                        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
                        ?>
                        <tr class="table-row">
                            <td class="px-6 py-3 text-gray-400"><?= $c['id'] ?></td>
                            <td class="px-6 py-3 font-mono text-xs whitespace-nowrap font-medium text-gray-800" dir="ltr"><?= htmlspecialchars(PhoneNormalizer::toLocalDisplay($c['phone_number'])) ?></td>
                            <td class="px-6 py-3 font-medium"><?= htmlspecialchars($c['display_name'] ?: '—') ?></td>
                            <td class="px-6 py-3">
                                <div class="flex gap-1.5 flex-wrap">
                                    <?php foreach ($platforms as $pl): ?>
                                        <?php $isTg = ($pl === 'telegram'); ?>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium
                                            <?= $isTg ? 'bg-blue-50 text-blue-600 border border-blue-100' : 'bg-emerald-50 text-emerald-600 border border-emerald-100' ?>"
                                            title="<?= isset($usernames[$pl]) ? '@' . htmlspecialchars($usernames[$pl]) : '' ?>">
                                            <img src="<?= $isTg ? 'assets/images/telegram.webp' : 'assets/images/bale.png' ?>"
                                                 alt="<?= htmlspecialchars($pl) ?>"
                                                 class="w-3.5 h-3.5 rounded-full object-contain">
                                            <span><?= ucfirst($pl) ?></span>
                                            <?php if (isset($usernames[$pl])): ?>
                                                <span class="text-gray-400 text-[11px] mr-0.5">(@<?= htmlspecialchars($usernames[$pl]) ?>)</span>
                                            <?php endif; ?>
                                        </span>
                                    <?php endforeach; ?>
                                    <?php if (empty($platforms)): ?>
                                        <span class="text-gray-400 text-xs">— (هنوز متصل نشده)</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-6 py-3 whitespace-nowrap">
                                <?php if ($c['preferred_platform']): ?>
                                    <?php $prefTg = ($c['preferred_platform'] === 'telegram'); ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200">
                                        <span>⭐</span>
                                        <img src="<?= $prefTg ? 'assets/images/telegram.webp' : 'assets/images/bale.png' ?>"
                                             alt="<?= htmlspecialchars($c['preferred_platform']) ?>"
                                             class="w-3.5 h-3.5 rounded-full object-contain">
                                        <span><?= ucfirst($c['preferred_platform']) ?></span>
                                    </span>
                                <?php else: ?>
                                    <span class="text-gray-400">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-3 text-gray-500 whitespace-nowrap"><?= Jalali::formatDate($c['created_at']) ?></td>
                            <td class="px-6 py-3 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-1.5">
                                    <!-- Edit Button -->
                                    <button type="button" data-contact='<?= $contactJson ?>' onclick="openEditContactModal(this)"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-medium text-gray-600 border border-gray-200 bg-white hover:bg-gray-50 hover:text-brand transition cursor-pointer"
                                            title="ویرایش اطلاعات">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125"/>
                                        </svg>
                                        <span>ویرایش</span>
                                    </button>

                                    <!-- Send Message Button -->
                                    <a href="messages.php?open_send=1&phone=<?= urlencode(PhoneNormalizer::toLocalDisplay($c['phone_number'])) ?>"
                                       class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-medium text-brand border border-brand/20 bg-brand/5 hover:bg-brand hover:text-white transition"
                                       title="ارسال پیام">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/>
                                        </svg>
                                        <span>ارسال پیام</span>
                                    </a>

                                    <!-- Delete Button -->
                                    <button type="button" onclick="deleteContact(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['display_name'] ?: PhoneNormalizer::toLocalDisplay($c['phone_number']))) ?>')"
                                            class="inline-flex items-center justify-center p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 border border-transparent hover:border-red-100 transition cursor-pointer"
                                            title="حذف مخاطب">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between" dir="ltr">
                <p class="text-sm text-gray-500">
                    صفحه <?= $page ?> از <?= $totalPages ?> (مجموع <?= number_format($total) ?>)
                </p>
                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                        <a href="?q=<?= urlencode($search) ?>&page=<?= $page - 1 ?>"
                           class="btn px-3 py-1.5 rounded-lg border border-gray-200 text-sm hover:bg-gray-50 transition">→ قبلی</a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="?q=<?= urlencode($search) ?>&page=<?= $page + 1 ?>"
                           class="btn px-3 py-1.5 rounded-lg border border-gray-200 text-sm hover:bg-gray-50 transition">بعدی ←</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Contact Create / Edit Modal -->
<div id="contactModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm hidden p-4">
    <div class="bg-white rounded-3xl shadow-2xl border border-gray-100 w-full max-w-xl overflow-hidden max-h-[90vh] flex flex-col" onclick="event.stopPropagation()">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-brand/10 text-brand flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                    </svg>
                </div>
                <h3 id="modalTitle" class="font-bold text-gray-800 text-base">ثبت مخاطب جدید</h3>
            </div>
            <button type="button" onclick="closeContactModal()" class="text-gray-400 hover:text-gray-600 transition p-1 cursor-pointer">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <!-- Form Body -->
        <form method="POST" id="contactForm" class="p-6 space-y-4 overflow-y-auto flex-1">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" id="formAction" value="create_contact">
            <input type="hidden" name="contact_id" id="contactId" value="0">

            <!-- Basic Contact Info -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1.5" for="phone_number">
                        شماره تلفن همراه <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="phone_number" name="phone_number" required placeholder="09121234567" dir="ltr"
                           class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand font-mono transition">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1.5" for="display_name">
                        نام و نام خانوادگی / عنوان
                    </label>
                    <input type="text" id="display_name" name="display_name" placeholder="مثلاً علی بازوبندی"
                           class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand transition">
                </div>
            </div>

            <!-- Preferred Platform -->
            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1.5" for="preferred_platform">
                    پلتفرم ترجیحی برای ارسال پیام
                </label>
                <select id="preferred_platform" name="preferred_platform"
                        class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand transition">
                    <option value="">بدون اولویت (ارسال به تمامی ربات‌های فعال)</option>
                    <option value="bale">پیام‌رسان بله (Bale)</option>
                    <option value="telegram">پیام‌رسان تلگرام (Telegram)</option>
                </select>
            </div>

            <!-- Messenger Channel Links -->
            <div class="pt-3 border-t border-gray-100">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-bold text-gray-700 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-brand" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/></svg>
                        <span>اتصال حساب پیام‌رسان‌ها (اختیاری)</span>
                    </span>
                    <span class="text-[11px] text-gray-400">شناسه چت را در صورت اطلاع وارد کنید</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Telegram Details -->
                    <div class="p-3.5 rounded-2xl bg-blue-50/50 border border-blue-100/70 space-y-2.5">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-blue-700 flex items-center gap-1.5">
                                <img src="assets/images/telegram.webp" class="w-4 h-4 object-contain rounded-full" alt="Telegram">
                                <span>تلگرام</span>
                            </span>
                            <label id="removeTelegramContainer" class="hidden items-center gap-1 text-[11px] text-red-600 cursor-pointer">
                                <input type="checkbox" name="remove_telegram" value="1" class="rounded text-red-600">
                                <span>حذف اتصال</span>
                            </label>
                        </div>
                        <div>
                            <label class="block text-[11px] text-gray-600 mb-1" for="telegram_chat_id">شناسه چت (Chat ID)</label>
                            <input type="text" id="telegram_chat_id" name="telegram_chat_id" placeholder="مثلاً 123456789" dir="ltr"
                                   class="w-full px-3 py-1.5 rounded-lg border border-gray-200 bg-white text-xs font-mono focus:outline-none focus:ring-2 focus:ring-blue-300">
                        </div>
                        <div>
                            <label class="block text-[11px] text-gray-600 mb-1" for="telegram_username">نام کاربری (بدون @)</label>
                            <input type="text" id="telegram_username" name="telegram_username" placeholder="username" dir="ltr"
                                   class="w-full px-3 py-1.5 rounded-lg border border-gray-200 bg-white text-xs font-mono focus:outline-none focus:ring-2 focus:ring-blue-300">
                        </div>
                    </div>

                    <!-- Bale Details -->
                    <div class="p-3.5 rounded-2xl bg-emerald-50/50 border border-emerald-100/70 space-y-2.5">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-emerald-700 flex items-center gap-1.5">
                                <img src="assets/images/bale.png" class="w-4 h-4 object-contain rounded-full" alt="Bale">
                                <span>بله</span>
                            </span>
                            <label id="removeBaleContainer" class="hidden items-center gap-1 text-[11px] text-red-600 cursor-pointer">
                                <input type="checkbox" name="remove_bale" value="1" class="rounded text-red-600">
                                <span>حذف اتصال</span>
                            </label>
                        </div>
                        <div>
                            <label class="block text-[11px] text-gray-600 mb-1" for="bale_chat_id">شناسه چت (Chat ID)</label>
                            <input type="text" id="bale_chat_id" name="bale_chat_id" placeholder="مثلاً 123456789" dir="ltr"
                                   class="w-full px-3 py-1.5 rounded-lg border border-gray-200 bg-white text-xs font-mono focus:outline-none focus:ring-2 focus:ring-emerald-300">
                        </div>
                        <div>
                            <label class="block text-[11px] text-gray-600 mb-1" for="bale_username">نام کاربری (بدون @)</label>
                            <input type="text" id="bale_username" name="bale_username" placeholder="username" dir="ltr"
                                   class="w-full px-3 py-1.5 rounded-lg border border-gray-200 bg-white text-xs font-mono focus:outline-none focus:ring-2 focus:ring-emerald-300">
                        </div>
                    </div>
                </div>

                <p class="text-[11px] text-gray-500 mt-2.5 leading-relaxed bg-gray-50 p-2.5 rounded-xl border border-gray-100">
                    💡 <strong>راهنما:</strong> در صورتی که شناسه چت مخاطب را نمی‌دانید، می‌توانید این بخش را خالی بگذارید. به محض اینکه مخاطب وارد ربات تلگرام یا بله شود و شماره تلفن خود را ارسال نماید، حساب او به صورت خودکار به این مخاطب متصل خواهد شد.
                </p>
            </div>

            <!-- Modal Action Buttons -->
            <div class="pt-4 border-t border-gray-100 flex items-center justify-end gap-3">
                <button type="button" onclick="closeContactModal()"
                        class="px-5 py-2.5 rounded-xl border border-gray-200 text-gray-600 text-sm font-medium hover:bg-gray-50 transition cursor-pointer">
                    انصراف
                </button>
                <button type="submit" id="submitBtn"
                        class="px-6 py-2.5 rounded-xl bg-brand text-white text-sm font-medium hover:bg-brand-dark shadow-sm shadow-brand/20 transition cursor-pointer">
                    ذخیره مخاطب
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Hidden Delete Form -->
<form id="deleteForm" method="POST" class="hidden">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="action" value="delete_contact">
    <input type="hidden" name="contact_id" id="deleteContactId" value="0">
</form>

<script>
const modal = document.getElementById('contactModal');
const form = document.getElementById('contactForm');
const modalTitle = document.getElementById('modalTitle');
const formAction = document.getElementById('formAction');
const contactIdInput = document.getElementById('contactId');
const phoneInput = document.getElementById('phone_number');
const nameInput = document.getElementById('display_name');
const prefSelect = document.getElementById('preferred_platform');
const tgChatInput = document.getElementById('telegram_chat_id');
const tgUserInput = document.getElementById('telegram_username');
const baleChatInput = document.getElementById('bale_chat_id');
const baleUserInput = document.getElementById('bale_username');
const removeTgBox = document.getElementById('removeTelegramContainer');
const removeBaleBox = document.getElementById('removeBaleContainer');

function openCreateContactModal() {
    form.reset();
    formAction.value = 'create_contact';
    contactIdInput.value = '0';
    modalTitle.textContent = 'ثبت مخاطب جدید';
    removeTgBox.classList.add('hidden');
    removeBaleBox.classList.add('hidden');
    modal.classList.remove('hidden');
    phoneInput.focus();
}

function openEditContactModal(btn) {
    form.reset();
    const data = JSON.parse(btn.dataset.contact);
    formAction.value = 'edit_contact';
    contactIdInput.value = data.id;
    modalTitle.textContent = 'ویرایش مخاطب: ' + (data.name || data.phone);

    phoneInput.value = data.phone;
    nameInput.value = data.name;
    prefSelect.value = data.preferred;

    tgChatInput.value = data.telegram_chat_id || '';
    tgUserInput.value = data.telegram_username || '';
    baleChatInput.value = data.bale_chat_id || '';
    baleUserInput.value = data.bale_username || '';

    if (data.telegram_chat_id) {
        removeTgBox.classList.remove('hidden');
    } else {
        removeTgBox.classList.add('hidden');
    }

    if (data.bale_chat_id) {
        removeBaleBox.classList.remove('hidden');
    } else {
        removeBaleBox.classList.add('hidden');
    }

    modal.classList.remove('hidden');
    nameInput.focus();
}

function closeContactModal() {
    modal.classList.add('hidden');
}

modal.addEventListener('click', function(e) {
    if (e.target === this) {
        closeContactModal();
    }
});

function deleteContact(id, name) {
    if (confirm('آیا از حذف مخاطب «' + name + '» اطمینان دارید؟ تمامی اتصالات پیام‌رسان این مخاطب نیز حذف خواهند شد.')) {
        document.getElementById('deleteContactId').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
