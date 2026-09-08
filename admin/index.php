<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../PhoneNormalizer.php';
require_once __DIR__ . '/../Jalali.php';

$db = Db::conn();

// ── Gather stats ─────────────────────────────────────────────────────
$totalContacts = $db->query('SELECT COUNT(*) AS c FROM contacts')->fetch_assoc()['c'];
$totalSent     = $db->query("SELECT COUNT(*) AS c FROM message_logs WHERE status = 'sent'")->fetch_assoc()['c'];
$totalFailed   = $db->query("SELECT COUNT(*) AS c FROM message_logs WHERE status = 'failed'")->fetch_assoc()['c'];
$totalApps     = $db->query("SELECT COUNT(*) AS c FROM sender_apps WHERE is_active = 1")->fetch_assoc()['c'];

// ── Recent messages ──────────────────────────────────────────────────
$recentMessages = $db->query(
    'SELECT ml.*, sa.app_name
     FROM message_logs ml
     JOIN sender_apps sa ON sa.id = ml.sender_app_id
     ORDER BY ml.sent_at DESC
     LIMIT 10'
)->fetch_all(MYSQLI_ASSOC);

// ── Messages per platform ────────────────────────────────────────────
$platformStats = $db->query(
    "SELECT platform, COUNT(*) AS total,
            SUM(status = 'sent') AS sent,
            SUM(status = 'failed') AS failed
     FROM message_logs
     GROUP BY platform"
)->fetch_all(MYSQLI_ASSOC);

// ── Page setup ───────────────────────────────────────────────────────
$pageTitle    = 'داشبورد';
$pageSubtitle = 'نمای کلی سیستم ارسال پیام';
$activePage   = 'dashboard';

ob_start();
?>

<!-- Stats Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <!-- Total Contacts -->
    <div class="stat-card bg-white rounded-2xl p-6 border border-gray-100 shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 rounded-xl bg-brand-light flex items-center justify-center">
                <svg class="w-6 h-6 text-brand" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/>
                </svg>
            </div>
        </div>
        <p class="text-3xl font-bold text-body"><?= number_format($totalContacts) ?></p>
        <p class="text-sm text-gray-500 mt-1">مخاطبین ثبت شده</p>
    </div>

    <!-- Sent -->
    <div class="stat-card bg-white rounded-2xl p-6 border border-gray-100 shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 rounded-xl bg-green-50 flex items-center justify-center">
                <svg class="w-6 h-6 text-success" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
            </div>
        </div>
        <p class="text-3xl font-bold text-body"><?= number_format($totalSent) ?></p>
        <p class="text-sm text-gray-500 mt-1">پیام ارسال شده</p>
    </div>

    <!-- Failed -->
    <div class="stat-card bg-white rounded-2xl p-6 border border-gray-100 shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 rounded-xl bg-red-50 flex items-center justify-center">
                <svg class="w-6 h-6 text-danger" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
            </div>
        </div>
        <p class="text-3xl font-bold text-body"><?= number_format($totalFailed) ?></p>
        <p class="text-sm text-gray-500 mt-1">پیام ناموفق</p>
    </div>

    <!-- Active Apps -->
    <div class="stat-card bg-white rounded-2xl p-6 border border-gray-100 shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 rounded-xl bg-accent-light flex items-center justify-center">
                <svg class="w-6 h-6 text-accent" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-2.25-1.313M21 7.5v2.25m0-2.25l-2.25 1.313M3 7.5l2.25-1.313M3 7.5l2.25 1.313M3 7.5v2.25m9 3l2.25-1.313M12 12.75l-2.25-1.313M12 12.75V15m0 6.75l2.25-1.313M12 21.75V19.5m0 2.25l-2.25-1.313m0-16.875L12 2.25l2.25 1.313M21 14.25v2.25l-2.25 1.313m-13.5 0L3 16.5v-2.25"/>
                </svg>
            </div>
        </div>
        <p class="text-3xl font-bold text-body"><?= number_format($totalApps) ?></p>
        <p class="text-sm text-gray-500 mt-1">اپلیکیشن فعال</p>
    </div>
</div>

<!-- Platform breakdown -->
<?php if (!empty($platformStats)): ?>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <?php foreach ($platformStats as $ps): ?>
        <?php $isTg = ($ps['platform'] === 'telegram'); ?>
        <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between gap-3 mb-5 pb-3 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <img src="<?= $isTg ? 'assets/images/telegram.webp' : 'assets/images/bale.png' ?>"
                         alt="<?= $isTg ? 'Telegram' : 'Bale' ?>"
                         class="w-8 h-8 rounded-full object-contain shadow-xs">
                    <div>
                        <h3 class="font-bold text-base <?= $isTg ? 'text-blue-600' : 'text-emerald-600' ?>">
                            <?= $isTg ? 'تلگرام (Telegram)' : 'بله (Bale)' ?>
                        </h3>
                        <p class="text-xs text-gray-400">کانال ارتباطی ربات</p>
                    </div>
                </div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold
                    <?= $isTg ? 'bg-blue-50 text-blue-600 border border-blue-100' : 'bg-emerald-50 text-emerald-600 border border-emerald-100' ?>">
                    <?= ucfirst($ps['platform']) ?>
                </span>
            </div>
            <div class="grid grid-cols-3 gap-4 text-center">
                <div class="bg-gray-50/80 p-3 rounded-xl">
                    <p class="text-2xl font-bold text-body"><?= number_format($ps['total']) ?></p>
                    <p class="text-xs text-gray-500 mt-1">کل پیام‌ها</p>
                </div>
                <div class="bg-green-50/70 p-3 rounded-xl">
                    <p class="text-2xl font-bold text-success"><?= number_format($ps['sent']) ?></p>
                    <p class="text-xs text-gray-500 mt-1">ارسال شده</p>
                </div>
                <div class="bg-red-50/70 p-3 rounded-xl">
                    <p class="text-2xl font-bold text-danger"><?= number_format($ps['failed']) ?></p>
                    <p class="text-xs text-gray-500 mt-1">ناموفق</p>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Recent Messages -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
        <h2 class="font-semibold text-body">آخرین پیام‌ها</h2>
        <a href="messages.php" class="text-sm text-brand hover:text-brand-dark font-medium transition">مشاهده همه ←</a>
    </div>

    <?php if (empty($recentMessages)): ?>
        <div class="p-12 text-center text-gray-400">
            <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/>
            </svg>
            <p>هنوز پیامی ارسال نشده است</p>
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
                        <th class="px-6 py-3 text-left font-medium">پیام</th>
                        <th class="px-6 py-3 text-left font-medium">وضعیت</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach ($recentMessages as $msg): ?>
                        <?php $isTg = ($msg['platform'] === 'telegram'); ?>
                        <tr class="table-row">
                            <td class="px-6 py-3 whitespace-nowrap text-gray-500" title="<?= htmlspecialchars($msg['sent_at']) ?>"><?= Jalali::format($msg['sent_at']) ?></td>
                            <td class="px-6 py-3 whitespace-nowrap font-medium"><?= htmlspecialchars($msg['app_name']) ?></td>
                            <td class="px-6 py-3 whitespace-nowrap font-mono text-xs" dir="ltr"><?= htmlspecialchars(PhoneNormalizer::toLocalDisplay($msg['phone_number'])) ?></td>
                            <td class="px-6 py-3 whitespace-nowrap">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium
                                    <?= $isTg ? 'bg-blue-50 text-blue-600 border border-blue-100' : 'bg-emerald-50 text-emerald-600 border border-emerald-100' ?>">
                                    <img src="<?= $isTg ? 'assets/images/telegram.webp' : 'assets/images/bale.png' ?>"
                                         alt="<?= htmlspecialchars($msg['platform']) ?>"
                                         class="w-3.5 h-3.5 rounded-full object-contain">
                                    <?= ucfirst($msg['platform']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-3 max-w-xs text-gray-600">
                                <div class="truncate"><?= htmlspecialchars(mb_substr($msg['message'], 0, 60)) ?><?= mb_strlen($msg['message']) > 60 ? '…' : '' ?></div>
                                <?php if (!empty($msg['attachment_url'])): ?>
                                    <div class="mt-1 flex items-center gap-1 text-xs text-brand font-medium">
                                        <?php if (($msg['attachment_type'] ?? '') === 'photo'): ?>
                                            <span>📷 تصویر پیوست شده</span>
                                        <?php else: ?>
                                            <span>📄 فایل (PDF) پیوست شده</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-3 whitespace-nowrap">
                                <?php if ($msg['status'] === 'sent'): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-50 text-success">
                                        <span class="w-1.5 h-1.5 rounded-full bg-success"></span> ارسال شده
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-50 text-danger">
                                        <span class="w-1.5 h-1.5 rounded-full bg-danger"></span> ناموفق
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
