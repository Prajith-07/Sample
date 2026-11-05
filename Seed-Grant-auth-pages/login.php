<?php
declare(strict_types=1);

$currentAuthPage = 'login';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="description" content="Sign in to the PSG iTech Seed Grant Challenge 2027 team account.">
    <meta name="theme-color" content="#f2f5ef">
    <title>Team Login | PSG iTech Seed Grant Challenge 2027</title>
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
    <link rel="stylesheet" href="css/auth.css">
    <script src="js/landing.js" defer></script>
    <script src="js/auth.js" defer></script>
</head>
<body id="top" class="auth-page">
    <a class="skip-link" href="#main">Skip to content</a>

    <?php require __DIR__ . '/includes/header.php'; ?>

    <main class="auth-main page-shell" id="main">
        <div class="auth-layout">
            <section class="glass-panel auth-card" aria-labelledby="auth-title">
                <div class="auth-card__content">
                    <p class="auth-eyebrow"><span class="auth-eyebrow__dot" aria-hidden="true"></span> PSG iTech · Seed Grant Challenge 2027</p>
                    <h1 id="auth-title">Welcome back to your team</h1>
                    <p class="auth-intro">Team leaders and registered team members use the same sign-in to continue their Seed Grant Challenge journey.</p>

                    <form class="auth-form" data-auth-form>
                        <div class="auth-field">
                            <label for="login-email">Member/Leader Email</label>
                            <input class="auth-input" id="login-email" name="email" type="email" autocomplete="email" placeholder="Enter your registered email" required>
                        </div>

                        <div class="auth-field">
                            <div class="auth-field__label-row">
                                <label for="login-password">Team Password</label>
                                <span class="auth-forgot" aria-label="Password recovery is not available yet">Forgot Password?</span>
                            </div>
                            <div class="auth-input-wrap">
                                <input class="auth-input auth-input--password" id="login-password" name="password" type="password" autocomplete="current-password" placeholder="Enter your team password" required>
                                <button class="password-toggle" type="button" data-password-toggle aria-controls="login-password" aria-pressed="false" aria-label="Show password">
                                    <svg class="password-toggle__eye" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2.8 12s3.2-6.1 9.2-6.1 9.2 6.1 9.2 6.1-3.2 6.1-9.2 6.1S2.8 12 2.8 12Z" stroke="currentColor" stroke-width="1.6"/><circle cx="12" cy="12" r="2.7" stroke="currentColor" stroke-width="1.6"/></svg>
                                    <svg class="password-toggle__eye-off" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m3 3 18 18M10.6 5.9A9.7 9.7 0 0 1 12 5.8c6 0 9.2 6.2 9.2 6.2a15.3 15.3 0 0 1-3.1 3.7M6.2 6.3C3.9 7.9 2.8 12 2.8 12s3.2 6.2 9.2 6.2c1 0 1.9-.2 2.7-.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                                    <span class="visually-hidden" data-password-toggle-label>Show password</span>
                                </button>
                            </div>
                        </div>

                        <button class="button button--primary auth-submit" type="submit" disabled>Sign In</button>
                    </form>

                    <p class="auth-switch">New to the Seed Grant Challenge? <a href="signup.php">Create Account</a></p>
                </div>
            </section>

            <aside class="auth-visual auth-visual--login" aria-labelledby="visual-title">
                <div class="auth-visual__glow" aria-hidden="true"></div>
                <div class="auth-visual__copy">
                    <p class="auth-visual__eyebrow">One team. Many possibilities.</p>
                    <h2 id="visual-title">Your team's journey continues.</h2>
                    <p>Pick up where your team left off and keep moving your idea toward a working prototype.</p>
                </div>
                <div class="team-illustration team-illustration--login" aria-hidden="true">
                    <span class="team-illustration__orbit team-illustration__orbit--one"></span>
                    <span class="team-illustration__orbit team-illustration__orbit--two"></span>
                    <span class="team-illustration__core"><span></span></span>
                    <span class="team-illustration__node team-illustration__node--one"><i></i></span>
                    <span class="team-illustration__node team-illustration__node--two"><i></i></span>
                    <span class="team-illustration__node team-illustration__node--three"><i></i></span>
                    <span class="team-illustration__node team-illustration__node--four"><i></i></span>
                </div>
                <div class="auth-visual__footnote"><span aria-hidden="true"></span> Team leaders and members, together</div>
            </aside>
        </div>
    </main>

    <?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
