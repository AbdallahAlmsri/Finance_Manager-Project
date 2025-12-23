<?php
if (!isset($currentPage)) {
    $currentPage = '';
}
if (!isset($pageTitle)) {
    $pageTitle = 'Dashboard';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Personal Finance Manager</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/style.css">

</head>
<body>
<script>
    (function() {
        var savedTheme = localStorage.getItem("theme") || "light";
        if (savedTheme === "dark") {
            document.body.classList.add("dark-mode");
        }
    })();
</script>
<script src="/Finance_Manager/public/assets/js/app_settings.js"></script>
<div class="layout">

    <aside class="sidebar">
        <div class="sidebar-logo">
            <span class="logo-icon"></span>
            <span class="logo-text">Finance Manager</span>
        </div>
        <nav class="sidebar-nav">
            <a href="index.php"
               class="nav-link <?php echo ($currentPage == 'dashboard') ? 'active' : ''; ?>">
                <span class="nav-text">Dashboard</span>
            </a>

            <a href="transactions.php"
               class="nav-link <?php echo ($currentPage == 'transactions') ? 'active' : ''; ?>">
                <span class="nav-text">Transactions</span>
            </a>

            <a href="budgets.php"
               class="nav-link <?php echo ($currentPage == 'budgets') ? 'active' : ''; ?>">
                <span class="nav-text">Budgets</span>
            </a>

            <a href="reports.php"
               class="nav-link <?php echo ($currentPage == 'reports') ? 'active' : ''; ?>">
                <span class="nav-text">Reports</span>
            </a>

            <a href="goals.php"
               class="nav-link <?php echo ($currentPage == 'goals') ? 'active' : ''; ?>">
                <span class="nav-text">Savings Goals</span>
            </a>

            <a href="payments.php"
               class="nav-link <?php echo ($currentPage == 'payments') ? 'active' : ''; ?>">
                <span class="nav-text">Payments</span>
            </a>

            <a href="notifications.php"
               class="nav-link <?php echo ($currentPage == 'notifications') ? 'active' : ''; ?>">
                <span class="nav-text">Notifications</span>
            </a>

            <a href="calendar.php"
               class="nav-link <?php echo ($currentPage == 'calendar') ? 'active' : ''; ?>">
                <span class="nav-text">Calendar</span>
            </a>
        </nav>
        <div class="sidebar-footer">
            <a href="settings.php" class="settings-link <?php echo ($currentPage == 'settings') ? 'active' : ''; ?>" title="Settings">
                <span class="settings-icon">⚙</span>
                <span class="settings-text">Settings</span>
            </a>
        </div>
    </aside>

    <div class="content">
        <header class="topbar">
            <div class="topbar-title">
                <?php echo htmlspecialchars(isset($pageTitle) ? $pageTitle : 'Dashboard'); ?>
            </div>
            <div class="topbar-actions">
                <a class="btn-ghost" href="logout.php">Logout</a>
                <button type="button" class="btn-ghost" id="themeToggle">
                    Dark Mode
                </button>
            </div>
        </header>
