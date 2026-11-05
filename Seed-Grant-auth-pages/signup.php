<?php
declare(strict_types=1);

$currentAuthPage = 'signup';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="description" content="Create the first account for your PSG iTech Seed Grant Challenge 2027 team.">
    <meta name="theme-color" content="#f2f5ef">
    <title>Team Registration | PSG iTech Seed Grant Challenge 2027</title>
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
                    <h1 id="auth-title">Create your team account</h1>
                    <p class="auth-intro">Start with the team leader's email. Your team can continue registration together after the first step.</p>

                    <form class="auth-form" data-auth-form>
                        <div class="auth-field">
                            <label for="team-leader-email">Team Leader Email</label>
                            <input class="auth-input" id="team-leader-email" name="team_leader_email" type="email" autocomplete="email" placeholder="Enter team leader email" required>
                        </div>

                        <div class="auth-field">
                            <label for="team-password">Team Password</label>
                            <div class="auth-input-wrap">
                                <input class="auth-input auth-input--password" id="team-password" name="team_password" type="password" autocomplete="new-password" placeholder="Create a team password" aria-describedby="team-password-help" required>
                                <button class="password-toggle" type="button" data-password-toggle aria-controls="team-password" aria-pressed="false" aria-label="Show password">
                                    <svg class="password-toggle__eye" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2.8 12s3.2-6.1 9.2-6.1 9.2 6.1 9.2 6.1-3.2 6.1-9.2 6.1S2.8 12 2.8 12Z" stroke="currentColor" stroke-width="1.6"/><circle cx="12" cy="12" r="2.7" stroke="currentColor" stroke-width="1.6"/></svg>
                                    <svg class="password-toggle__eye-off" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m3 3 18 18M10.6 5.9A9.7 9.7 0 0 1 12 5.8c6 0 9.2 6.2 9.2 6.2a15.3 15.3 0 0 1-3.1 3.7M6.2 6.3C3.9 7.9 2.8 12 2.8 12s3.2 6.2 9.2 6.2c1 0 1.9-.2 2.7-.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                                    <span class="visually-hidden" data-password-toggle-label>Show password</span>
                                </button>
                            </div>
                            <p class="auth-field__help" id="team-password-help">Choose a strong password your team can access securely.</p>
                        </div>

                        <div class="auth-field">
                            <label for="team-password-confirm">Confirm Password</label>
                            <div class="auth-input-wrap">
                                <input class="auth-input auth-input--password" id="team-password-confirm" name="team_password_confirm" type="password" autocomplete="new-password" placeholder="Re-enter your team password" required>
                                <button class="password-toggle" type="button" data-password-toggle aria-controls="team-password-confirm" aria-pressed="false" aria-label="Show password">
                                    <svg class="password-toggle__eye" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2.8 12s3.2-6.1 9.2-6.1 9.2 6.1 9.2 6.1-3.2 6.1-9.2 6.1S2.8 12 2.8 12Z" stroke="currentColor" stroke-width="1.6"/><circle cx="12" cy="12" r="2.7" stroke="currentColor" stroke-width="1.6"/></svg>
                                    <svg class="password-toggle__eye-off" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m3 3 18 18M10.6 5.9A9.7 9.7 0 0 1 12 5.8c6 0 9.2 6.2 9.2 6.2a15.3 15.3 0 0 1-3.1 3.7M6.2 6.3C3.9 7.9 2.8 12 2.8 12s3.2 6.2 9.2 6.2c1 0 1.9-.2 2.7-.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                                    <span class="visually-hidden" data-password-toggle-label>Show password</span>
                                </button>
                            </div>
                        </div>

                        <p class="auth-info" id="registration-note">
                            <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="10" cy="10" r="7.2" stroke="currentColor" stroke-width="1.5"/><path d="M10 9v4m0-6h.01" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                            <span>Team members will be able to login after the leader finishes registration step 1</span>
                        </p>

                        <button class="button button--primary auth-submit" type="submit" disabled>Create Team Account</button>
                    </form>

                    <p class="auth-switch">Already have an account? <a href="login.php">Sign In</a></p>
                </div>
            </section>

            <aside class="auth-visual auth-visual--signup" aria-labelledby="visual-title">
                <div class="auth-visual__glow" aria-hidden="true"></div>
                <div class="auth-visual__copy">
                    <p class="auth-visual__eyebrow">Ideas take a team</p>
                    <h2 id="visual-title">Build something that matters.</h2>
                    <p>Bring interdisciplinary ideas to life and turn real problems into working prototypes.</p>
                    <div class="grant-highlight">
                        <span class="grant-highlight__label">Grant opportunity</span>
                        <strong>₹1,00,000</strong>
                        <span>to move your idea forward</span>
                    </div>
                </div>
                <div class="team-illustration team-illustration--signup" aria-hidden="true">
                    <span class="team-illustration__orbit team-illustration__orbit--one"></span>
                    <span class="team-illustration__orbit team-illustration__orbit--two"></span>
                    <span class="team-illustration__core"><span></span></span>
                    <span class="team-illustration__node team-illustration__node--one"><i></i></span>
                    <span class="team-illustration__node team-illustration__node--two"><i></i></span>
                    <span class="team-illustration__node team-illustration__node--three"><i></i></span>
                    <span class="team-illustration__node team-illustration__node--four"><i></i></span>
                </div>
                <div class="auth-visual__footnote"><span aria-hidden="true"></span> PSG iTech Seed Grant Challenge 2027</div>
            </aside>
        </div>
    </main>

    <?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
