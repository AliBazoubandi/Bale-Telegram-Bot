<?php

require_once __DIR__ . '/session_boot.php';
admin_secure_session_start();

require_once __DIR__ . '/../src/bootstrap.php';

$error = '';

// Already logged in?
if (!empty($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

// Handle POST login.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    if (!filter_var($clientIp, FILTER_VALIDATE_IP)) {
        $clientIp = '127.0.0.1';
    }

    $db  = Db::conn();
    $now = time();

    // ── Throttle Check: 5 failures within 10 minutes locks the IP ──
    $throttle = null;
    $isLocked = false;
    try {
        $stmt = $db->prepare('SELECT failures, (locked_until IS NOT NULL AND locked_until > NOW()) AS is_locked, TIMESTAMPDIFF(SECOND, last_attempt, NOW()) AS age_sec FROM login_throttles WHERE ip = ?');
        if ($stmt) {
            $stmt->bind_param('s', $clientIp);
            $stmt->execute();
            $throttle = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $isLocked = ($throttle && (int) $throttle['is_locked'] === 1);
        }
    } catch (Throwable $e) {
        error_log("Login throttle check error: " . $e->getMessage());
    }

    if ($isLocked) {
        $error = 'تلاش بیش از حد؛ چند دقیقه بعد دوباره تلاش کنید.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = 'نام کاربری و رمز عبور الزامی است.';
        } else {
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

                // Successful login — clear IP throttle record
                try {
                    $delStmt = $db->prepare('DELETE FROM login_throttles WHERE ip = ?');
                    if ($delStmt) {
                        $delStmt->bind_param('s', $clientIp);
                        $delStmt->execute();
                        $delStmt->close();
                    }
                } catch (Throwable $e) {
                    error_log("Login throttle clear error: " . $e->getMessage());
                }

                header('Location: index.php');
                exit;
            }

            // Record failed attempt for IP throttling
            try {
                $failures = 1;
                if ($throttle && (int) ($throttle['age_sec'] ?? 9999) < 600 && (int) ($throttle['is_locked'] ?? 0) === 0) {
                    $failures = (int) $throttle['failures'] + 1;
                }

                $upStmt = $db->prepare(
                    'INSERT INTO login_throttles (ip, failures, last_attempt, locked_until)
                     VALUES (?, ?, NOW(), IF(? >= 5, DATE_ADD(NOW(), INTERVAL 10 MINUTE), NULL))
                     ON DUPLICATE KEY UPDATE
                         failures = VALUES(failures),
                         last_attempt = NOW(),
                         locked_until = IF(VALUES(failures) >= 5, DATE_ADD(NOW(), INTERVAL 10 MINUTE), NULL)'
                );
                if ($upStmt) {
                    $upStmt->bind_param('sii', $clientIp, $failures, $failures);
                    $upStmt->execute();
                    $upStmt->close();
                }
            } catch (Throwable $e) {
                error_log("Login throttle update error: " . $e->getMessage());
            }

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
    <link rel="icon" type="image/jpeg" href="assets/images/send-message-icon.jpg">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/tailwind.css">
</head>
<body class="bg-gray-50 text-body min-h-screen flex flex-col justify-between">

    <div class="flex-1 flex items-center justify-center p-4">
        <div class="w-full max-w-md mx-auto">
            <!-- Logo / Title -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl overflow-hidden mb-4 shadow-xl shadow-brand/30 border border-gray-100">
                    <img src="assets/images/send-message-icon.jpg" alt="Logo" class="w-full h-full object-cover">
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
                        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2" for="username">نام کاربری</label>
                        <input type="text" id="username" name="username" required autocomplete="username"
                               value="<?= htmlspecialchars((string) ($_POST['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
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
    </div>

    <!-- Footer -->
    <footer class="py-4 px-8 border-t border-gray-100 bg-white text-center text-xs text-gray-400">
        ✨ تمام حقوق اين وبسايت مربوط به تیم هوش تجاری رهباریان رابین است. ✨
    </footer>

</body>
</html>
