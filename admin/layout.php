<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Admin') ?> — پنل مدیریت</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/tailwind.css">
</head>
<body class="bg-gray-50 text-body">

    <!-- Sidebar -->
    <aside class="fixed top-0 right-0 h-screen w-64 bg-surface text-white flex flex-col z-50 shadow-xl">
        <!-- Brand -->
        <div class="px-6 py-5 border-b border-white/10">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-brand flex items-center justify-center shadow-lg shadow-brand/30">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.076-4.076a1.526 1.526 0 0 1 1.037-.443 48.282 48.282 0 0 0 5.68-.494c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="font-bold text-sm">پنل ارسال هشدار</h2>
                    <p class="text-xs text-gray-400">پنل مدیریت</p>
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 py-4 px-3 space-y-1 overflow-y-auto">
            <a href="index.php" class="nav-link <?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?> flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25a2.25 2.25 0 0 1-2.25-2.25v-2.25Z"/>
                </svg>
                داشبورد
            </a>

            <a href="contacts.php" class="nav-link <?= ($activePage ?? '') === 'contacts' ? 'active' : '' ?> flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/>
                </svg>
                مخاطبین
            </a>

            <a href="messages.php" class="nav-link <?= ($activePage ?? '') === 'messages' ? 'active' : '' ?> flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/>
                </svg>
                پیام‌ها
            </a>

            <a href="apps.php" class="nav-link <?= ($activePage ?? '') === 'apps' ? 'active' : '' ?> flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-2.25-1.313M21 7.5v2.25m0-2.25l-2.25 1.313M3 7.5l2.25-1.313M3 7.5l2.25 1.313M3 7.5v2.25m9 3l2.25-1.313M12 12.75l-2.25-1.313M12 12.75V15m0 6.75l2.25-1.313M12 21.75V19.5m0 2.25l-2.25-1.313m0-16.875L12 2.25l2.25 1.313M21 14.25v2.25l-2.25 1.313m-13.5 0L3 16.5v-2.25"/>
                </svg>
                اپلیکیشن‌ها
            </a>
        </nav>

        <!-- User / Logout -->
        <div class="px-4 py-4 border-t border-white/10">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-brand/20 text-brand flex items-center justify-center text-sm font-bold">
                    <?= mb_strtoupper(mb_substr($adminDisplayName, 0, 1)) ?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium truncate"><?= htmlspecialchars($adminDisplayName) ?></p>
                    <p class="text-xs text-gray-400 truncate">@<?= htmlspecialchars($adminUsername) ?></p>
                </div>
                <a href="logout.php" title="خروج" class="p-2 rounded-lg hover:bg-white/10 transition text-gray-400 hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9"/>
                    </svg>
                </a>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="mr-64 min-h-screen">
        <!-- Top Header -->
        <header class="bg-white border-b border-gray-100 px-8 py-5 sticky top-0 z-40">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-bold text-body"><?= htmlspecialchars($pageTitle ?? '') ?></h1>
                    <?php if (!empty($pageSubtitle)): ?>
                        <p class="text-sm text-gray-500 mt-0.5"><?= htmlspecialchars($pageSubtitle) ?></p>
                    <?php endif; ?>
                </div>
                <div>
                    <?php if (!empty($headerAction)): ?>
                        <?= $headerAction ?>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <main class="p-8">
            <?= $content ?>
        </main>
    </div>

</body>
</html>
