<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$isLoggedIn = !empty($_SESSION['team_id']);
?>
<header class="site-header">
    <div class="site-header__inner page-shell">
        <a class="brand-strip" href="index.php" aria-label="PSG iTech Seed Grant Challenge home">
            <span class="brand-strip__item"><img src="assets/college-logo-transparent.png" width="1136" height="1385" alt="PSG iTech"></span>
            <span class="brand-strip__item"><img src="assets/innovation-council-transparent.png" width="1831" height="859" alt="Institution's Innovation Council"></span>
            <span class="brand-strip__item"><img src="assets/psg-step-transparent.png" width="1230" height="1279" alt="PSG STEP"></span>
            <span class="brand-strip__item"><img src="assets/itech_launchpad-transparent.png" width="1683" height="935" alt="iTech Launchpad"></span>
        </a>

        <nav class="header-actions" aria-label="Account and display options">
            <?php if ($isLoggedIn): ?>
                <a class="button button--primary button--small" href="register.php">My Application</a>
                <a class="button button--quiet button--small" href="logout.php">Sign Out</a>
            <?php elseif (isset($currentAuthPage) && in_array($currentAuthPage, ['login', 'signup'], true)): ?>
                <a class="button button--quiet button--small<?php echo $currentAuthPage === 'login' ? ' header-action--active' : ''; ?>" href="login.php"<?php echo $currentAuthPage === 'login' ? ' aria-current="page"' : ''; ?>>Sign In</a>
                <a class="button button--primary button--small<?php echo $currentAuthPage === 'signup' ? ' header-action--active' : ''; ?>" href="signup.php"<?php echo $currentAuthPage === 'signup' ? ' aria-current="page"' : ''; ?>>Create Account</a>
            <?php else: ?>
                <a class="button button--quiet button--small" href="login.php">Sign In</a>
                <a class="button button--primary button--small" href="signup.php">Create Account</a>
            <?php endif; ?>
            <button class="theme-toggle" type="button" aria-pressed="false" aria-label="Switch to dark mode">
                <svg class="theme-toggle__sun" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle cx="12" cy="12" r="3.5" stroke="currentColor" stroke-width="1.6"/>
                    <path d="M12 2.5v2M12 19.5v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2.5 12h2m15 0h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
                <svg class="theme-toggle__moon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M19.5 15.1A8 8 0 0 1 8.9 4.5 8.1 8.1 0 1 0 19.5 15.1Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="m16.8 4.5.4 1.2 1.3.4-1.3.4-.4 1.3-.4-1.3-1.3-.4 1.3-.4.4-1.2Z" fill="currentColor"/>
                </svg>
            </button>
        </nav>
    </div>
</header>
