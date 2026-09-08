<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../AttachmentValidator.php';
require_once __DIR__ . '/../PhoneNormalizer.php';
require_once __DIR__ . '/../Jalali.php';
require_once __DIR__ . '/../NotificationSender.php';

$db = Db::conn();
$msg = '';
$msgType = '';

// ── Handle Send Message Form Submission ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_message') {
    // CSRF check — reject forged/expired submissions before touching anything.
    if (!hash_equals($_SESSION['csrf_token'] ?? '', (string) ($_POST['csrf_token'] ?? ''))) {
        $_SESSION['flash_msg'] = 'نشست شما منقضی شده است؛ دوباره تلاش کنید.';
        $_SESSION['flash_type'] = 'error';
        header('Location: messages.php');
        exit;
    }

    $phone          = trim($_POST['phone'] ?? '');
    $appId          = (int)($_POST['app_id'] ?? 0);
    $messageText    = trim($_POST['message'] ?? '');
    $link           = trim($_POST['link'] ?? '') ?: null;
    $attachmentUrl  = trim($_POST['attachment_url'] ?? '') ?: null;
    $attachmentType = trim($_POST['attachment_type'] ?? '') ?: null;

    $attachment = $attachmentUrl;

    // Handle direct file upload — validated by the shared AttachmentValidator
    // BEFORE move_uploaded_file(); failures surface as a loud Persian flash.
    $uploadErrorKey = null;
    if (isset($_FILES['attachment_file']) && $_FILES['attachment_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $stored = AttachmentValidator::store($_FILES['attachment_file']);

        if ($stored['ok']) {
            $attachment = $stored['path'];
            if (in_array($stored['ext'], ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                $attachmentType = 'photo';
            } elseif ($stored['ext'] === 'pdf') {
                $attachmentType = 'document';
            }
        } else {
            $uploadErrorKey = $stored['error_key'];
        }
    }

    if ($phone === '' || $appId <= 0 || $messageText === '') {
        $msg = 'لطفاً شماره تلفن، اپلیکیشن و متن پیام را وارد کنید.';
        $msgType = 'error';
    } elseif ($uploadErrorKey !== null) {
        $msg = AttachmentValidator::adminErrorMessage($uploadErrorKey);
        $msgType = 'error';
    } elseif ($attachmentUrl !== null && !AttachmentValidator::isAllowedRemoteUrl($attachmentUrl)) {
        // NOTE: checks the URL FIELD only — a successful file upload replaces
        // $attachment with a stored local path, which must not be rejected here.
        $msg = 'آدرس پیوست باید یک لینک معتبر با پروتکل http یا https باشد.';
        $msgType = 'error';
    } else {
        try {
            $sender = new NotificationSender();
            $results = $sender->sendToPhone($phone, $appId, $messageText, $link, $attachment, $attachmentType);

            $anySent = false;
            $errors = [];
            foreach ($results as $r) {
                if (($r['status'] ?? '') === 'sent') {
                    $anySent = true;
                } elseif (!empty($r['error'])) {
                    $errors[] = ($r['platform'] ? "{$r['platform']}: " : '') . $r['error'];
                } elseif (($r['status'] ?? '') === 'no_channel_linked') {
                    $errors[] = 'هیچ کانال تلگرام یا بله‌ای برای این شماره ثبت نشده است.';
                }
            }

            if ($anySent) {
                $msg = 'پیام با موفقیت ارسال شد.';
                $msgType = 'success';
            } else {
                $msg = 'ارسال پیام ناموفق بود: ' . implode(' | ', $errors);
                $msgType = 'error';
            }
        } catch (Throwable $e) {
            $msg = 'خطا در ارسال پیام: ' . $e->getMessage();
            $msgType = 'error';
        }
    }

    $_SESSION['flash_msg'] = $msg;
    $_SESSION['flash_type'] = $msgType;
    header('Location: messages.php');
    exit;
}

if (isset($_SESSION['flash_msg'])) {
    $msg = $_SESSION['flash_msg'];
    $msgType = $_SESSION['flash_type'];
    unset($_SESSION['flash_msg'], $_SESSION['flash_type']);
}

// ── Filters & Pagination ─────────────────────────────────────────────
$page     = max(1, (int) ($_GET['page'] ?? 1));
$limit    = 30;
$offset   = ($page - 1) * $limit;

$fApp      = $_GET['app'] ?? '';
$fPlatform = $_GET['platform'] ?? '';
$fStatus   = $_GET['status'] ?? '';
$search    = trim($_GET['q'] ?? '');

$whereClauses = [];
$params = [];
$types  = '';

if ($fApp !== '') {
    $whereClauses[] = 'ml.sender_app_id = ?';
    $params[] = (int) $fApp;
    $types .= 'i';
}
if ($fPlatform !== '') {
    $whereClauses[] = 'ml.platform = ?';
    $params[] = $fPlatform;
    $types .= 's';
}
if ($fStatus !== '') {
    $whereClauses[] = 'ml.status = ?';
    $params[] = $fStatus;
    $types .= 's';
}
if ($search !== '') {
    // Match phone numbers regardless of stored ('98912…') vs entered ('0912…') format.
    $phoneLikes = ['ml.phone_number LIKE ?'];
    $params[] = "%{$search}%";
    $types .= 's';

    $normalizedSearch = PhoneNormalizer::normalize($search);
    if ($normalizedSearch !== null && $normalizedSearch !== $search) {
        $phoneLikes[] = 'ml.phone_number LIKE ?';
        $params[] = "%{$normalizedSearch}%";
        $types .= 's';
    }

    $localSearch = PhoneNormalizer::toLocalDisplay($search);
    if ($localSearch !== $search && preg_match('/^\d+$/', $localSearch)) {
        $phoneLikes[] = 'ml.phone_number LIKE ?';
        $params[] = "%{$localSearch}%";
        $types .= 's';
    }

    $phoneLikes[] = 'ml.message LIKE ?';
    $params[] = "%{$search}%";
    $types .= 's';

    $whereClauses[] = '(' . implode(' OR ', $phoneLikes) . ')';
}

$where = '';
if (!empty($whereClauses)) {
    $where = 'WHERE ' . implode(' AND ', $whereClauses);
}

// Count
$countSql = "SELECT COUNT(*) AS c FROM message_logs ml {$where}";
$stmt     = $db->prepare($countSql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

$totalPages = max(1, ceil($total / $limit));

// Fetch messages
$sql = "SELECT ml.*, sa.app_name
        FROM message_logs ml
        JOIN sender_apps sa ON sa.id = ml.sender_app_id
        {$where}
        ORDER BY ml.sent_at DESC
        LIMIT {$limit} OFFSET {$offset}";

$stmt = $db->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch apps for filter dropdown and send modal
$apps = $db->query('SELECT id, app_name FROM sender_apps WHERE is_active = 1 ORDER BY app_name')->fetch_all(MYSQLI_ASSOC);
$allApps = $db->query('SELECT id, app_name FROM sender_apps ORDER BY app_name')->fetch_all(MYSQLI_ASSOC);

// Fetch recent contacts for easy phone selection in modal
$recentContacts = $db->query('SELECT phone_number, display_name FROM contacts ORDER BY created_at DESC LIMIT 30')->fetch_all(MYSQLI_ASSOC);

// Pre-fill phone if coming from contacts list
$prefilledPhone = trim($_GET['phone'] ?? '');
$autoOpenSend   = isset($_GET['open_send']) || $prefilledPhone !== '';

// ── Page setup ───────────────────────────────────────────────────────
$pageTitle    = 'پیام‌ها';
$pageSubtitle = number_format($total) . ' رکورد یافت شد';
$activePage   = 'messages';

$headerAction = '<button type="button" onclick="openSendModal()" class="btn inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-brand text-white text-sm font-medium hover:bg-brand-dark shadow-sm cursor-pointer">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/>
    </svg>
    <span>ارسال پیام جدید</span>
</button>';

ob_start();
?>

<!-- Alerts -->
<?php if ($msg): ?>
    <div class="mb-6 p-4 rounded-xl flex items-center justify-between text-sm <?= $msgType === 'success' ? 'bg-green-50 text-success border border-green-200' : 'bg-red-50 text-danger border border-red-200' ?>">
        <span><?= htmlspecialchars($msg) ?></span>
    </div>
<?php endif; ?>

<!-- Filters -->
<div class="mb-6 bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
    <form method="GET" class="flex flex-wrap items-end gap-4">
        <div class="w-full sm:w-auto flex-1 min-w-[200px]">
            <label class="block text-xs font-medium text-gray-500 mb-1">جستجو</label>
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="شماره تلفن یا متن پیام..."
                   class="w-full px-3 py-2 rounded-xl border border-gray-200 bg-gray-50 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand transition">
        </div>

        <div class="w-full sm:w-auto min-w-[150px]">
            <label class="block text-xs font-medium text-gray-500 mb-1">اپلیکیشن</label>
            <select name="app" class="w-full px-3 py-2 rounded-xl border border-gray-200 bg-gray-50 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand transition">
                <option value="">همه</option>
                <?php foreach ($allApps as $a): ?>
                    <option value="<?= $a['id'] ?>" <?= $fApp == $a['id'] ? 'selected' : '' ?>><?= htmlspecialchars($a['app_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="w-full sm:w-auto min-w-[140px]">
            <label class="block text-xs font-medium text-gray-500 mb-1">پلتفرم</label>
            <select name="platform" class="w-full px-3 py-2 rounded-xl border border-gray-200 bg-gray-50 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand transition">
                <option value="">همه</option>
                <option value="telegram" <?= $fPlatform === 'telegram' ? 'selected' : '' ?>>Telegram (تلگرام)</option>
                <option value="bale" <?= $fPlatform === 'bale' ? 'selected' : '' ?>>Bale (بله)</option>
            </select>
        </div>

        <div class="w-full sm:w-auto min-w-[120px]">
            <label class="block text-xs font-medium text-gray-500 mb-1">وضعیت</label>
            <select name="status" class="w-full px-3 py-2 rounded-xl border border-gray-200 bg-gray-50 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand transition">
                <option value="">همه</option>
                <option value="sent" <?= $fStatus === 'sent' ? 'selected' : '' ?>>Sent</option>
                <option value="failed" <?= $fStatus === 'failed' ? 'selected' : '' ?>>Failed</option>
            </select>
        </div>

        <div class="w-full sm:w-auto flex gap-2">
            <button type="submit" class="btn px-5 py-2 rounded-xl bg-brand text-white text-sm font-medium hover:bg-brand-dark shadow-sm cursor-pointer">
                اعمال فیلتر
            </button>
            <?php if ($search !== '' || $fApp !== '' || $fPlatform !== '' || $fStatus !== ''): ?>
                <a href="messages.php" class="btn px-4 py-2 rounded-xl border border-gray-200 text-gray-600 text-sm font-medium hover:bg-gray-50 transition">
                    پاک کردن
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Messages Table -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <?php if (empty($messages)): ?>
        <div class="p-12 text-center text-gray-400">
            <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/>
            </svg>
            <p>رکوردی یافت نشد</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm" dir="ltr">
                <thead>
                    <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                        <th class="px-6 py-3 text-left font-medium">تاریخ</th>
                        <th class="px-6 py-3 text-left font-medium">اپلیکیشن</th>
                        <th class="px-6 py-3 text-left font-medium">شماره تلفن</th>
                        <th class="px-6 py-3 text-left font-medium">پلتفرم</th>
                        <th class="px-6 py-3 text-left font-medium max-w-md">پیام و رسانه</th>
                        <th class="px-6 py-3 text-left font-medium">وضعیت</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach ($messages as $msg): ?>
                        <?php $isTg = ($msg['platform'] === 'telegram'); ?>
                        <tr class="table-row">
                            <td class="px-6 py-3 whitespace-nowrap text-gray-500" title="<?= htmlspecialchars($msg['sent_at']) ?>">
                                <?= Jalali::format($msg['sent_at']) ?>
                            </td>
                            <td class="px-6 py-3 whitespace-nowrap font-medium"><?= htmlspecialchars($msg['app_name']) ?></td>
                            <td class="px-6 py-3 whitespace-nowrap font-mono text-xs text-gray-600" dir="ltr"><?= htmlspecialchars(PhoneNormalizer::toLocalDisplay($msg['phone_number'])) ?></td>
                            <td class="px-6 py-3 whitespace-nowrap">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium
                                    <?= $isTg ? 'bg-blue-50 text-blue-600 border border-blue-100' : 'bg-emerald-50 text-emerald-600 border border-emerald-100' ?>">
                                    <img src="<?= $isTg ? 'assets/images/telegram.webp' : 'assets/images/bale.png' ?>"
                                         alt="<?= htmlspecialchars($msg['platform']) ?>"
                                         class="w-3.5 h-3.5 rounded-full object-contain">
                                    <?= ucfirst($msg['platform']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-3 max-w-md">
                                <div class="text-gray-800 break-words" title="<?= htmlspecialchars($msg['message']) ?>">
                                    <?= nl2br(htmlspecialchars(mb_substr($msg['message'], 0, 100))) ?><?= mb_strlen($msg['message']) > 100 ? '…' : '' ?>
                                </div>
                                
                                <div class="mt-1.5 flex flex-wrap items-center gap-2">
                                    <?php if (!empty($msg['link'])): ?>
                                        <a href="<?= htmlspecialchars($msg['link']) ?>" target="_blank"
                                           class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-blue-50 text-brand text-xs hover:underline max-w-[220px] truncate"
                                           title="<?= htmlspecialchars($msg['link']) ?>">
                                            <span>🔗</span> <span class="truncate"><?= htmlspecialchars($msg['link']) ?></span>
                                        </a>
                                    <?php endif; ?>

                                    <?php if (!empty($msg['attachment_url'])): ?>
                                        <?php
                                        $attType = $msg['attachment_type'] ?? '';
                                        $attUrl = $msg['attachment_url'];
                                        $isLocal = !preg_match('/^https?:\/\//i', $attUrl);
                                        // Local files live outside the web root now — serve them
                                        // through the authenticated attachment endpoint only.
                                        $viewUrl = $isLocal ? 'attachment.php?id=' . (int) $msg['id'] : $attUrl;
                                        ?>
                                        <?php if ($attType === 'photo'): ?>
                                            <a href="<?= htmlspecialchars($viewUrl) ?>" target="_blank"
                                               class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md bg-purple-50 text-purple-700 border border-purple-200 text-xs font-medium hover:bg-purple-100 transition">
                                                <span>📷</span> <span>مشاهده تصویر</span>
                                            </a>
                                        <?php else: ?>
                                            <a href="<?= htmlspecialchars($viewUrl) ?>" target="_blank"
                                               class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md bg-amber-50 text-amber-700 border border-amber-200 text-xs font-medium hover:bg-amber-100 transition">
                                                <span>📄</span> <span>دانلود/مشاهده فایل (PDF)</span>
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-6 py-3 whitespace-nowrap">
                                <?php if ($msg['status'] === 'sent'): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-50 text-success border border-green-100">
                                        <span class="w-1.5 h-1.5 rounded-full bg-success"></span> ارسال شده
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-50 text-danger border border-red-100 cursor-help" title="<?= htmlspecialchars($msg['error_message'] ?? '') ?>">
                                        <span class="w-1.5 h-1.5 rounded-full bg-danger"></span> ناموفق
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <?php
            $qParams = $_GET;
            unset($qParams['page']);
            $baseQuery = http_build_query($qParams);
            $baseQuery = $baseQuery ? '&' . $baseQuery : '';
            ?>
            <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between" dir="ltr">
                <p class="text-sm text-gray-500">
                    صفحه <?= $page ?> از <?= $totalPages ?> (مجموع <?= number_format($total) ?>)
                </p>
                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?><?= $baseQuery ?>" class="btn px-3 py-1.5 rounded-lg border border-gray-200 text-sm hover:bg-gray-50 transition">→ قبلی</a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?><?= $baseQuery ?>" class="btn px-3 py-1.5 rounded-lg border border-gray-200 text-sm hover:bg-gray-50 transition">بعدی ←</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Send Message Modal -->
<div id="sendModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-xs <?= $autoOpenSend ? '' : 'hidden' ?> p-4 transition-all">
    <div class="bg-white rounded-3xl shadow-2xl border border-gray-100 w-full max-w-lg overflow-hidden transform transition-all">
        <!-- Modal Header -->
        <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between bg-gradient-to-r from-brand/5 to-transparent">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-brand text-white flex items-center justify-center shadow-md shadow-brand/20">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-bold text-base text-body">ارسال پیام جدید به کاربر</h3>
                    <p class="text-xs text-gray-500">ارسال متن، تصویر یا فایل PDF از طریق ربات</p>
                </div>
            </div>
            <button type="button" onclick="closeSendModal()" class="p-2 rounded-xl text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <!-- Modal Form -->
        <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
            <input type="hidden" name="action" value="send_message">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1.5" for="send_app">انتخاب اپلیکیشن فرستنده</label>
                <select id="send_app" name="app_id" required class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand transition">
                    <?php foreach ($apps as $a): ?>
                        <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['app_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1.5" for="send_phone">شماره تلفن مخاطب</label>
                <div class="relative">
                    <input type="text" id="send_phone" name="phone" required
                           value="<?= htmlspecialchars($prefilledPhone) ?>"
                           placeholder="مثلا 09121234567 یا 989121234567"
                           class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand font-mono transition" dir="ltr">
                </div>
                <?php if (!empty($recentContacts)): ?>
                    <div class="mt-1.5 flex items-center gap-1 overflow-x-auto py-1 text-xs text-gray-500">
                        <span class="text-[11px] text-gray-400 whitespace-nowrap">مخاطبین اخیر:</span>
                        <?php foreach (array_slice($recentContacts, 0, 4) as $rc): ?>
                            <button type="button" onclick="document.getElementById('send_phone').value='<?= $rc['phone_number'] ?>'"
                                    class="px-2 py-0.5 rounded-lg bg-gray-100 hover:bg-brand-light hover:text-brand transition whitespace-nowrap text-[11px]">
                                <?= htmlspecialchars($rc['display_name'] ?: $rc['phone_number']) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1.5" for="send_message_text">متن پیام (یا کپشن فایل)</label>
                <textarea id="send_message_text" name="message" rows="3" required
                          placeholder="متن پیام خود را اینجا بنویسید..."
                          class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand transition"></textarea>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1.5" for="send_link">لینک پیوست (اختیاری)</label>
                <input type="url" id="send_link" name="link" placeholder="https://example.com/invoice/123"
                       class="w-full px-4 py-2 rounded-xl border border-gray-200 bg-gray-50 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand font-mono text-xs transition" dir="ltr">
            </div>

            <!-- Direct File Attachment -->
            <div class="pt-2 border-t border-gray-100">
                <div class="flex items-center justify-between mb-2">
                    <label class="text-xs font-bold text-gray-700 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-brand" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13"/>
                        </svg>
                        <span>پیوست مستقیم فایل (تصویر یا PDF)</span>
                    </label>
                    <span class="text-[11px] text-gray-400">JPG, PNG, WEBP, PDF</span>
                </div>

                <div class="space-y-2">
                    <input type="file" id="attachment_file" name="attachment_file"
                           accept="image/*,application/pdf"
                           class="w-full text-xs text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-brand-light file:text-brand hover:file:bg-brand/20 file:cursor-pointer file:transition">
                    
                    <div class="relative flex items-center gap-2 pt-1">
                        <div class="flex-1 h-px bg-gray-100"></div>
                        <span class="text-[11px] text-gray-400">یا آدرس اینترنتی فایل (URL)</span>
                        <div class="flex-1 h-px bg-gray-100"></div>
                    </div>

                    <input type="url" name="attachment_url" placeholder="https://example.com/photo.jpg یا فایل PDF..."
                           class="w-full px-4 py-2 rounded-xl border border-gray-200 bg-gray-50 text-xs focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand font-mono transition" dir="ltr">
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="pt-4 flex items-center justify-end gap-3 border-t border-gray-100">
                <button type="button" onclick="closeSendModal()" class="btn px-4 py-2.5 rounded-xl border border-gray-200 text-gray-600 text-sm font-medium hover:bg-gray-50 transition cursor-pointer">
                    انصراف
                </button>
                <button type="submit" class="btn px-6 py-2.5 rounded-xl bg-brand text-white text-sm font-medium hover:bg-brand-dark shadow-md shadow-brand/20 transition flex items-center gap-2 cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/>
                    </svg>
                    <span>ارسال پیام</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openSendModal() {
    document.getElementById('sendModal').classList.remove('hidden');
}
function closeSendModal() {
    document.getElementById('sendModal').classList.add('hidden');
}
// Close on outside click
document.getElementById('sendModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeSendModal();
    }
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
