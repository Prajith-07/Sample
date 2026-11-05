<?php
declare(strict_types=1);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="description" content="Apply for up to ₹1,00,000 to build a hardware or software proof of concept with an interdisciplinary team at PSG iTech Seed Grant Challenge 2027.">
    <meta name="theme-color" content="#f2f5ef">
    <title>PSG iTech Seed Grant Challenge 2027</title>
    <script>
        (() => {
            let theme = null;
            try {
                theme = window.localStorage.getItem('seed-grant-theme');
            } catch (error) {
                // Storage can be unavailable in private or restricted browsing modes.
            }

            if (theme !== 'light' && theme !== 'dark') {
                theme = 'light';
            }

            document.documentElement.dataset.theme = theme;
        })();
    </script>
    <link rel="stylesheet" href="css/landing.css">
    <script src="js/landing.js" defer></script>
</head>
<body id="top">
    <a class="skip-link" href="#main">Skip to content</a>

    <?php require __DIR__ . '/includes/header.php'; ?>

    <main id="main">
        <?php require __DIR__ . '/includes/hero.php'; ?>
        <?php require __DIR__ . '/includes/team-dynamics.php'; ?>
        <?php require __DIR__ . '/includes/roadmap.php'; ?>
    </main>

    <?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
