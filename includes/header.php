<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn = isset($_SESSION['user_id']);
$currentPage = basename($_SERVER['PHP_SELF']);

// Count total simulated emails in email_logs.txt
$emailCount = 0;
if ($isLoggedIn) {
    $emailLogPath = __DIR__ . '/../email_logs.txt';
    if (file_exists($emailLogPath)) {
        $logContent = file_get_contents($emailLogPath);
        $emailCount = substr_count($logContent, "SUBJECT:");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Task & Expense Manager</title>

    <script>
        // Apply saved theme before page rendering
        const savedTheme = localStorage.getItem('appTheme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
    </script>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/smart-task-expense-manager/assets/css/style.css">
</head>
<body>

<?php if ($isLoggedIn): ?>
<div class="app-layout">

    <aside class="sidebar">

        <div class="sidebar-brand">
            <h4>Smart Manager</h4>
            <small>Task & Expense Tracker</small>
        </div>

        <div class="sidebar-menu">

            <a href="/smart-task-expense-manager/dashboard.php"
               class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
                Dashboard
            </a>

            <a href="/smart-task-expense-manager/tasks/index.php"
               class="<?= $currentPage === 'index.php' && str_contains($_SERVER['PHP_SELF'], '/tasks/') ? 'active' : '' ?>">
                My Tasks
            </a>

            <a href="/smart-task-expense-manager/expenses/index.php"
               class="<?= $currentPage === 'index.php' && str_contains($_SERVER['PHP_SELF'], '/expenses/') ? 'active' : '' ?>">
                My Expenses
            </a>

            <a href="/smart-task-expense-manager/reports.php"
               class="<?= $currentPage === 'reports.php' ? 'active' : '' ?>">
                Reports
            </a>

            <a href="/smart-task-expense-manager/view_emails.php"
               class="d-flex justify-content-between align-items-center <?= $currentPage === 'view_emails.php' ? 'active' : '' ?>">
                <span>Email Inbox</span>
                <?php if ($emailCount > 0): ?>
                    <span class="badge bg-danger rounded-pill"><?= $emailCount ?></span>
                <?php endif; ?>
            </a>

            <a href="/smart-task-expense-manager/profile.php"
               class="<?= $currentPage === 'profile.php' ? 'active' : '' ?>">
                My Profile
            </a>

        </div>

        <div class="sidebar-bottom">

            <button type="button" id="themeToggle" class="theme-toggle">
                <span id="themeIcon">🌙</span>
                <span id="themeText">Dark Mode</span>
            </button>

            <div class="sidebar-user">
                <?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?>
            </div>

            <a href="/smart-task-expense-manager/logout.php" class="logout-link">
                Logout
            </a>

        </div>
    </aside>

    <main class="main-content">

<?php else: ?>

    <main class="auth-content">

<?php endif; ?>

    <div class="container-fluid py-4">
