<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../PhoneNormalizer.php';
require_once __DIR__ . '/../Jalali.php';

$db = Db::conn();

// ── Search ───────────────────────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
$page   = max(1, (int) ($_GET['page'] ?? 1));
$limit  = 20;
$offset = ($page - 1) * $limit;

$where  = '';
$params = [];
$types  = '';

if ($search !== '') {
    // Match phone numbers regardless of stored ('98912…') vs entered ('0912…') format.
    $clauses = ['c.phone_number LIKE ?'];
    $params  = ["%{$search}%"];
    $types   = 's';

    $normalizedSearch = PhoneNormalizer::normalize($search);
    if ($normalizedSearch !== null && $normalizedSearch !== $search) {
        $clauses[] = 'c.phone_number LIKE ?';
        $params[]  = "%{$normalizedSearch}%";
        $types    .= 's';
    }

    $localSearch = PhoneNormalizer::toLocalDisplay($search);
    if ($localSearch !== $search && preg_match('/^\d+$/', $localSearch)) {
        $clauses[] = 'c.phone_number LIKE ?';
        $params[]  = "%{$localSearch}%";
        $types    .= 's';
    }

    $clauses[] = 'c.display_name LIKE ?';
    $params[]  = "%{$search}%";
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
               GROUP_CONCAT(CONCAT(cc.platform, ':', IFNULL(cc.username,'')) ORDER BY cc.platform SEPARATOR ',') AS platform_usernames
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

    <a href="messages.php?open_send=1" class="btn inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-brand text-white text-sm font-medium hover:bg-brand-dark shadow-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/>
        </svg>
        <span>ارسال پیام جدید</span>
    </a>
</div>

<!-- Contacts Table -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <?php if (empty($contacts)): ?>
        <div class="p-12 text-center text-gray-400">
            <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/>
            </svg>
            <p><?= $search !== '' ? 'نتیجه‌ای یافت نشد' : 'هنوز مخاطبی ثبت نشده' ?></p>
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
                                        <span class="text-gray-400">—</span>
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
                                <a href="messages.php?open_send=1&phone=<?= urlencode(PhoneNormalizer::toLocalDisplay($c['phone_number'])) ?>"
                                   class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium text-brand border border-brand/20 bg-brand/5 hover:bg-brand hover:text-white transition">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/>
                                    </svg>
                                    <span>ارسال پیام</span>
                                </a>
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

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
