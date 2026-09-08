<?php

require_once __DIR__ . '/session_boot.php';
admin_secure_session_start();

require_once __DIR__ . '/../Env.php';
require_once __DIR__ . '/../Db.php';

Env::load(__DIR__ . '/../.env');

$error = '';

// Already logged in?
if (!empty($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

// Handle POST login.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ── Throttle: 5 failures within 10 minutes lock the form (no DB touch) ──
    $failedLogins = (int) ($_SESSION['failed_logins'] ?? 0);
    $lastFailTs   = (int) ($_SESSION['last_fail_ts'] ?? 0);

    if ($failedLogins >= 5 && (time() - $lastFailTs) < 600) {
        $error = 'تلاش بیش از حد؛ چند دقیقه بعد دوباره تلاش کنید.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = 'نام کاربری و رمز عبور الزامی است.';
        } else {
            $db   = Db::conn();
            $stmt = $db->prepare('SELECT id, username, password_hash, display_name FROM admin_users WHERE username = ?');
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Rotate the session id before writing any login state.
                session_regenerate_id(true);

                $_SESSION['admin_id']           = $user['id'];
                $_SESSION['admin_username']     = $user['username'];
                $_SESSION['admin_display_name'] = $user['display_name'] ?: $user['username'];

                // Successful login — reset the throttle counters.
                unset($_SESSION['failed_logins'], $_SESSION['last_fail_ts']);

                header('Location: index.php');
                exit;
            }

            // Record the failure for throttling. A failure older than the
            // 10-minute window starts a fresh count.
            if ((time() - $lastFailTs) >= 600) {
                $_SESSION['failed_logins'] = 1;
            } else {
                $_SESSION['failed_logins'] = $failedLogins + 1;
            }
            $_SESSION['last_fail_ts'] = time();

            $error = 'نام کاربری یا رمز عبور نادرست است.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود — پنل مدیریت</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/tailwind.css">
</head>
<body class="bg-gray-50 text-body min-h-screen flex items-center justify-center">

    <div class="w-full max-w-md mx-auto p-4">
        <!-- Logo / Title -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-brand text-white mb-4 shadow-lg shadow-brand/30">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.076-4.076a1.526 1.526 0 0 1 1.037-.443 48.282 48.282 0 0 0 5.68-.494c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z"/>
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-body">پنل مدیریت</h1>
            <p class="text-gray-500 mt-1">سیستم ارسال پیام</p>
        </div>

        <!-- Login Card -->
        <div class="bg-white rounded-2xl shadow-xl shadow-gray-200/50 border border-gray-100 p-8">
            <?php if ($error): ?>
                <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm flex items-center gap-2">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                    </svg>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2" for="username">نام کاربری</label>
                    <input type="text" id="username" name="username" required autocomplete="username"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50
                                  focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand
                                  transition placeholder-gray-400"
                           placeholder="admin">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2" for="password">رمز عبور</label>
                    <input type="password" id="password" name="password" required autocomplete="current-password"
                           class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50
                                  focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-brand
                                  transition placeholder-gray-400"
                           placeholder="••••••••">
                </div>

                <button type="submit"
                        class="w-full py-3 px-4 rounded-xl bg-brand text-white font-semibold
                               hover:bg-brand-dark active:scale-[0.98] transition-all duration-150
                               shadow-lg shadow-brand/25 cursor-pointer">
                    ورود
                </button>
            </form>
        </div>

        <p class="text-center text-xs text-gray-400 mt-6">Notification Sender System v2.0</p>
    </div>

</body>
</html>
