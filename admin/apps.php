<?php

require_once __DIR__ . '/auth.php';

$db = Db::conn();
$msg = '';
$msgType = '';

// ── Handle Actions (Register / Toggle / Delete) ──────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check — reject forged/expired submissions before touching anything.
    if (!hash_equals($_SESSION['csrf_token'] ?? '', (string) ($_POST['csrf_token'] ?? ''))) {
        $_SESSION['flash_msg'] = 'نشست شما منقضی شده است؛ دوباره تلاش کنید.';
        $_SESSION['flash_type'] = 'error';
        header('Location: apps.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'register') {
        $appName = trim($_POST['app_name'] ?? '');
        if ($appName !== '') {
            $appKey = bin2hex(random_bytes(24));
            try {
                $stmt = $db->prepare('INSERT INTO sender_apps (app_name, app_key) VALUES (?, ?)');
                $stmt->bind_param('ss', $appName, $appKey);
                $stmt->execute();
                $stmt->close();
                $msg = 'اپلیکیشن جدید با موفقیت ثبت شد. کلید API: ' . $appKey . ' (توجه: این کلید فقط یک‌بار نمایش داده می‌شود؛ لطفاً آن را ذخیره کنید)';
                $msgType = 'success';
            } catch (Throwable $e) {
                if (str_contains($e->getMessage(), 'Duplicate entry')) {
                    $msg = 'اپلیکیشنی با این نام از قبل وجود دارد.';
                    $msgType = 'error';
                } else {
                    $msg = 'خطایی رخ داد.';
                    $msgType = 'error';
                }
            }
        }
    } elseif ($action === 'toggle') {
        $appId = (int)($_POST['app_id'] ?? 0);
        if ($appId > 0) {
            $stmt = $db->prepare('UPDATE sender_apps SET is_active = NOT is_active WHERE id = ?');
            $stmt->bind_param('i', $appId);
            $stmt->execute();
            $stmt->close();
            $msg = 'وضعیت اپلیکیشن بروزرسانی شد.';
            $msgType = 'success';
        }
    } elseif ($action === 'delete') {
        $appId = (int)($_POST['app_id'] ?? 0);
        if ($appId > 0) {
            // Because of foreign keys, deleting an app will fail if it has message logs.
            // You could cascade delete or soft delete. Since we have is_active, deletion might fail.
            try {
                $stmt = $db->prepare('DELETE FROM sender_apps WHERE id = ?');
                $stmt->bind_param('i', $appId);
                $stmt->execute();
                $stmt->close();
                $msg = 'اپلیکیشن حذف شد.';
                $msgType = 'success';
            } catch (Throwable $e) {
                $msg = 'امکان حذف اپلیکیشن وجود ندارد (احتمالا پیام‌های ثبت شده دارد). آن را غیرفعال کنید.';
                $msgType = 'error';
            }
        }
    }

    // Redirect to avoid form resubmission
    if ($msg) {
        $_SESSION['flash_msg'] = $msg;
        $_SESSION['flash_type'] = $msgType;
        header('Location: apps.php');
        exit;
    }
}

if (isset($_SESSION['flash_msg'])) {
    $msg = $_SESSION['flash_msg'];
    $msgType = $_SESSION['flash_type'];
    unset($_SESSION['flash_msg'], $_SESSION['flash_type']);
}

// ── Fetch Apps ───────────────────────────────────────────────────────
$sql = "SELECT sa.*, COUNT(ml.id) AS message_count
        FROM sender_apps sa
        LEFT JOIN message_logs ml ON ml.sender_app_id = sa.id
        GROUP BY sa.id
        ORDER BY sa.created_at DESC";
$apps = $db->query($sql)->fetch_all(MYSQLI_ASSOC);

// ── Page setup ───────────────────────────────────────────────────────
$pageTitle    = 'اپلیکیشن‌ها';
$pageSubtitle = 'مدیریت برنامه‌های مجاز برای ارسال پیام';
$activePage   = 'apps';

ob_start();
?>

<!-- Alerts -->
<?php if ($msg): ?>
    <div class="mb-6 p-4 rounded-xl flex items-center gap-2 text-sm <?= $msgType === 'success' ? 'bg-green-50 text-success border border-green-200' : 'bg-red-50 text-danger border border-red-200' ?>">
        <?= htmlspecialchars($msg) ?>
    </div>
<?php endif; ?>

<!-- Register New App -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-8">
    <h2 class="font-semibold text-body mb-4">ثبت اپلیکیشن جدید</h2>
    <form method="POST" class="flex flex-wrap gap-4 items-end">
        <input type="hidden" name="action" value="register">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <div class="flex-1 min-w-[250px]">
            <label class="block text-xs font-medium text-gray-500 mb-1" for="app_name">نام اپلیکیشن</label>
            <input type="text" id="app_name" name="app_name" required placeholder="مثلا سیستم حسابداری..."
                   class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 text-sm focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand transition">
        </div>
        <button type="submit" class="btn px-6 py-2.5 rounded-xl bg-brand text-white font-medium hover:bg-brand-dark shadow-sm">
            ایجاد اپلیکیشن
        </button>
    </form>
</div>

<!-- Apps List -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <?php if (empty($apps)): ?>
        <div class="p-12 text-center text-gray-400">
            <p>هیچ اپلیکیشنی ثبت نشده است.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm" dir="ltr">
                <thead>
                    <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                        <th class="px-6 py-3 text-left font-medium">نام</th>
                        <th class="px-6 py-3 text-left font-medium">کلید API</th>
                        <th class="px-6 py-3 text-left font-medium">پیام‌های ارسال‌شده</th>
                        <th class="px-6 py-3 text-left font-medium">وضعیت</th>
                        <th class="px-6 py-3 text-right font-medium">عملیات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach ($apps as $a): ?>
                        <tr class="table-row">
                            <td class="px-6 py-4 font-medium text-body"><?= htmlspecialchars($a['app_name']) ?></td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <code class="px-2 py-1 bg-gray-100 rounded text-xs text-gray-600 font-mono">
                                        <?= htmlspecialchars(substr($a['app_key'], 0, 6) . '…') ?>
                                    </code>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-gray-500"><?= number_format($a['message_count']) ?></td>
                            <td class="px-6 py-4">
                                <?php if ($a['is_active']): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-50 text-success border border-green-100">
                                        فعال
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500 border border-gray-200">
                                        غیرفعال
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-right flex justify-end gap-2">
                                <!-- Toggle Form -->
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="app_id" value="<?= $a['id'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <button type="submit" class="text-xs font-medium px-3 py-1.5 rounded-lg border <?= $a['is_active'] ? 'text-orange-600 border-orange-200 hover:bg-orange-50' : 'text-green-600 border-green-200 hover:bg-green-50' ?> transition">
                                        <?= $a['is_active'] ? 'غیرفعال‌سازی' : 'فعال‌سازی' ?>
                                    </button>
                                </form>

                                <!-- Delete Form -->
                                <form method="POST" class="inline" onsubmit="return confirm('آیا مطمئن هستید؟ این عمل غیرقابل بازگشت است.');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="app_id" value="<?= $a['id'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <?php $cantDelete = $a['message_count'] > 0; ?>
                                    <button type="submit" <?= $cantDelete ? 'title="حذف این اپلیکیشن امکان‌پذیر نیست؛ آن را غیرفعال کنید." disabled' : '' ?> class="text-xs font-medium px-3 py-1.5 rounded-lg border text-danger border-red-200 hover:bg-red-50 transition<?= $cantDelete ? ' opacity-50 cursor-not-allowed' : '' ?>">
                                        حذف
                                    </button>
                                </form>
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
