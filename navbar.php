<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_role = $_SESSION['role'] ?? '';
$staff_roles = ['admin', 'finance_manager', 'inventory_manager', 'dispatch_manager', 'driver', 'service_manager', 'trainer', 'supplier'];
$is_logged_in = isset($_SESSION['user_id']);
$staffSection = match ($user_role) {
    'finance_manager' => '#finance',
    'inventory_manager' => '#inventory',
    'supplier' => '#supplier-orders',
    'dispatch_manager', 'driver' => '#dispatch',
    'service_manager', 'trainer' => '#services',
    default => '',
};
$dashboard_url = $user_role === 'admin' ? 'admin.php' : (in_array($user_role, $staff_roles, true) ? 'staff.php' . $staffSection : 'dashboard.php');
$dashboard_label = $user_role === 'admin' ? 'Admin Panel' : (in_array($user_role, $staff_roles, true) ? 'Staff Workspace' : 'Dashboard');
?>
<link rel="stylesheet" href="navigation.css?v=2">
<header class="global-topbar">
    <button class="sidebar-toggle" type="button" aria-label="Open site menu" aria-expanded="false" aria-controls="global-sidebar">
        <span></span><span></span><span></span>
    </button>
    <a class="global-brand" href="index.php" aria-label="Meru Farmers Centre home page">
        <img src="Logo.png" alt="">
        <span>Meru Farmers Centre</span>
    </a>
    <nav class="topbar-links" aria-label="Quick links">
        <a href="index.php">Home</a>
        <a href="about.php">About Us</a>
        <a href="services.php">Services</a>
        <a href="contact.php">Contact</a>
    </nav>
    <nav class="topbar-account" aria-label="Account links">
        <?php if ($is_logged_in): ?>
            <a href="<?php echo htmlspecialchars($dashboard_url); ?>"><?php echo htmlspecialchars($dashboard_label); ?></a>
            <?php if ($user_role === 'admin'): ?><a href="staff.php">Staff Workspace</a><?php endif; ?>
            <a class="topbar-logout" href="logout.php">Logout</a>
        <?php else: ?>
            <a href="login.php">Login</a>
        <?php endif; ?>
    </nav>
    <span class="topbar-note">Growing together</span>
</header>
<button class="sidebar-backdrop" type="button" aria-label="Close site menu" tabindex="-1"></button>
<aside class="global-sidebar" id="global-sidebar" aria-label="Site menu" aria-hidden="true" inert>
    <div class="sidebar-heading">
        <span class="sidebar-kicker">MERU FARMERS CENTRE</span>
        <button class="sidebar-close" type="button" aria-label="Close site menu">&times;</button>
    </div>
    <nav class="sidebar-nav" aria-label="Main navigation">
        <span class="sidebar-section-label">Explore</span>
        <a href="index.php">Home</a>
        <a href="about.php">About us</a>
        <a href="services.php">Services</a>
        <?php if (!$is_logged_in || in_array($user_role, ['farmer', 'admin', 'inventory_manager'], true)): ?>
            <a href="inventory.php">Farm inputs catalog</a>
        <?php endif; ?>
        <?php if (!$is_logged_in || in_array($user_role, ['farmer', 'admin'], true)): ?>
            <a href="bookings.php">Book a service</a>
        <?php endif; ?>
        <?php if (!$is_logged_in || in_array($user_role, ['farmer', 'admin', 'finance_manager', 'dispatch_manager', 'service_manager', 'trainer'], true)): ?>
            <a href="search.php">Search</a>
        <?php endif; ?>
        <details class="sidebar-group">
            <summary>Contact us</summary>
            <div class="sidebar-submenu">
                <a href="contact.php">Contact the centre</a>
                <a href="feedback.php">Farmer feedback</a>
                <?php if (isset($_SESSION['user_id']) && in_array($user_role, ['admin', 'service_manager'], true)): ?>
                    <a href="feedback.php">Review feedback</a>
                <?php endif; ?>
            </div>
        </details>
        <a href="help.php">Help centre</a>
        <span class="sidebar-section-label">Account</span>
        <?php if ($is_logged_in): ?>
            <?php if ($user_role === 'admin'): ?>
                <a href="staff.php<?php echo $staffSection; ?>">Staff workspace</a>
            <?php endif; ?>
            <a href="<?php echo htmlspecialchars($dashboard_url); ?>"><?php echo htmlspecialchars($dashboard_label); ?></a>
            <a class="sidebar-logout" href="logout.php">Log out</a>
        <?php else: ?>
            <a href="login.php">Login</a>
            <a href="register.php">Create an account</a>
        <?php endif; ?>
    </nav>
    <div class="sidebar-foot">Practical support for Meru farmers.</div>
</aside>
<script src="navigation.js" defer></script>
