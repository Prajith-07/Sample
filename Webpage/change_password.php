<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';

// Access control: Ensure user is flagged for mandatory password change
if (empty($_SESSION['must_change_password']) || empty($_SESSION['auth_user_id']) || empty($_SESSION['auth_user_type'])) {
    header("Location: login.php");
    exit;
}

// Team Leader never updates password here; redirect directly to registration portal
if (!empty($_SESSION['is_lead'])) {
    unset($_SESSION['must_change_password']);
    $tid = (int)($_SESSION['team_id'] ?? 0);
    header("Location: register.php?step=1&team_id={$tid}");
    exit;
}

$userId = (int)$_SESSION['auth_user_id'];
$userType = (string)$_SESSION['auth_user_type'];
$userEmail = (string)($_SESSION['user_email'] ?? '');
$userName = (string)($_SESSION['user_name'] ?? 'Team Member');
$teamId = (int)($_SESSION['team_id'] ?? 0);
$teamName = (string)($_SESSION['team_name'] ?? '');

$error = null;
$currentAuthPage = 'change_password';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if (empty($newPassword) || empty($confirmPassword)) {
        $error = "Please fill in all password fields.";
    } elseif (strlen($newPassword) < 6) {
        $error = "Your new password must be at least 6 characters long.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "Passwords do not match. Please re-enter your new password.";
    } else {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            $error = "Database service unavailable. Please check that MySQL is running in XAMPP.";
        } else {
            // Retrieve user's registered phone number directly from the database for verification
            $registeredPhone = '';
            if ($userType === 'mentor') {
                $stmt = $pdo->prepare("SELECT phone FROM `team_mentors` WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $userId]);
                $registeredPhone = (string)$stmt->fetchColumn();
            } else {
                $stmt = $pdo->prepare("SELECT phone_number FROM `team_members` WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $userId]);
                $registeredPhone = (string)$stmt->fetchColumn();
            }

            // Clean digits from registered phone and password to prevent any phone number variation
            $cleanRegPhone = preg_replace('/[^\d]/', '', $registeredPhone);
            if (strlen($cleanRegPhone) > 10) {
                $cleanRegPhone = substr($cleanRegPhone, -10);
            }

            $cleanInputDigits = preg_replace('/[^\d]/', '', $newPassword);
            if (strlen($cleanInputDigits) > 10) {
                $cleanInputDigits = substr($cleanInputDigits, -10);
            }

            // CRITICAL CHECK: Reject if user tries to use their mobile number as the new password
            $isSameAsPhone = false;
            if (!empty($cleanRegPhone)) {
                if (trim($newPassword) === trim($registeredPhone) || trim($newPassword) === $cleanRegPhone) {
                    $isSameAsPhone = true;
                } elseif (!empty($cleanInputDigits) && $cleanInputDigits === $cleanRegPhone) {
                    $isSameAsPhone = true;
                }
            }

            if ($isSameAsPhone) {
                $error = "Password not available. Your new password cannot be your mobile phone number / default password. Please choose a different secure password.";
            } else {
                // Update new password in database and clear dummy flag
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

                if ($userType === 'mentor') {
                    $stmtUp = $pdo->prepare("
                        UPDATE `team_mentors` 
                        SET `password_hash` = :hash, `is_dummy_password` = 0 
                        WHERE `id` = :id
                    ");
                    $stmtUp->execute([':hash' => $newHash, ':id' => $userId]);
                } else {
                    $stmtUp = $pdo->prepare("
                        UPDATE `team_members` 
                        SET `password_hash` = :hash, `is_dummy_password` = 0, `updated_at` = NOW() 
                        WHERE `id` = :id
                    ");
                    $stmtUp->execute([':hash' => $newHash, ':id' => $userId]);
                }

                // Clear first-login enforcement flags
                unset($_SESSION['must_change_password']);
                unset($_SESSION['auth_user_id']);
                unset($_SESSION['auth_user_type']);

                // Set established session
                $_SESSION['team_id']    = $teamId;
                $_SESSION['user_email'] = $userEmail;
                $_SESSION['user_name']  = $userName;
                $_SESSION['team_name']  = $teamName;
                $_SESSION['user_role']  = $userType;

                if ($userType === 'mentor') {
                    header("Location: submissions.php");
                    exit;
                }

                // Check team status for student routing
                $stmtTeam = $pdo->prepare("SELECT current_step, status FROM `teams` WHERE id = :tid LIMIT 1");
                $stmtTeam->execute([':tid' => $teamId]);
                $teamInfo = $stmtTeam->fetch(PDO::FETCH_ASSOC);

                $step = max(1, (int)($teamInfo['current_step'] ?? 1));
                $status = $teamInfo['status'] ?? 'Draft';

                if ($status === 'Submitted' || $status === 'Under Screening') {
                    header("Location: registration_success.php?team_id={$teamId}");
                    exit;
                }

                header("Location: register.php?step={$step}&team_id={$teamId}");
                exit;
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="description" content="Update your personal password for PSG iTech Seed Grant Challenge 2027.">
    <meta name="theme-color" content="#f2f5ef">
    <title>Set Personal Password | PSG iTech Seed Grant Challenge 2027</title>
    <script>
        (() => {
            let theme = null;
            try {
                theme = window.localStorage.getItem('seed-grant-theme');
            } catch (error) {}
            if (theme !== 'light' && theme !== 'dark') theme = 'light';
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
                    <p class="auth-eyebrow"><span class="auth-eyebrow__dot" aria-hidden="true"></span> PSG iTech · First-Time Security Verification</p>
                    <h1 id="auth-title">Set Your Personal Password</h1>
                    <p class="auth-intro">
                        Welcome, <strong><?= htmlspecialchars($userName) ?></strong>! You have signed in with your initial default credentials. For your account security, you must create a new personal password before continuing.
                    </p>

                    <?php if (!empty($error)): ?>
                        <div style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.4); color: #dc2626; padding: 0.85rem 1.15rem; border-radius: 12px; margin-bottom: 1.5rem; font-size: 0.88rem; display: flex; align-items: flex-start; gap: 0.65rem; line-height: 1.45;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink: 0; margin-top: 2px;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                            <div><?= htmlspecialchars($error) ?></div>
                        </div>
                    <?php endif; ?>

                    <div style="background: rgba(21, 128, 61, 0.08); border: 1px solid rgba(21, 128, 61, 0.25); border-radius: 10px; padding: 12px 14px; margin-bottom: 1.5rem; font-size: 0.84rem; color: var(--text-main, #1e293b); line-height: 1.45;">
                        <strong>Security Policy:</strong> Your new password must be at least 6 characters and cannot be your mobile phone number.
                    </div>

                    <form class="auth-form" method="POST" action="change_password.php" data-auth-form>
                        <div class="auth-field">
                            <label for="new-password">New Personal Password</label>
                            <div class="auth-input-wrap">
                                <input class="auth-input auth-input--password" id="new-password" name="new_password" type="password" autocomplete="new-password" placeholder="Create your new password (min 6 chars)" required>
                                <button class="password-toggle" type="button" data-password-toggle aria-controls="new-password" aria-pressed="false" aria-label="Show password">
                                    <svg class="password-toggle__eye" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2.8 12s3.2-6.1 9.2-6.1 9.2 6.1 9.2 6.1-3.2 6.1-9.2 6.1S2.8 12 2.8 12Z" stroke="currentColor" stroke-width="1.6"/><circle cx="12" cy="12" r="2.7" stroke="currentColor" stroke-width="1.6"/></svg>
                                    <svg class="password-toggle__eye-off" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m3 3 18 18M10.6 5.9A9.7 9.7 0 0 1 12 5.8c6 0 9.2 6.2 9.2 6.2a15.3 15.3 0 0 1-3.1 3.7M6.2 6.3C3.9 7.9 2.8 12 2.8 12s3.2 6.2 9.2 6.2c1 0 1.9-.2 2.7-.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                                    <span class="visually-hidden" data-password-toggle-label>Show password</span>
                                </button>
                            </div>
                        </div>

                        <div class="auth-field">
                            <label for="confirm-password">Confirm New Password</label>
                            <div class="auth-input-wrap">
                                <input class="auth-input auth-input--password" id="confirm-password" name="confirm_password" type="password" autocomplete="new-password" placeholder="Re-enter your new password" required>
                                <button class="password-toggle" type="button" data-password-toggle aria-controls="confirm-password" aria-pressed="false" aria-label="Show password">
                                    <svg class="password-toggle__eye" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2.8 12s3.2-6.1 9.2-6.1 9.2 6.1 9.2 6.1-3.2 6.1-9.2 6.1S2.8 12 2.8 12Z" stroke="currentColor" stroke-width="1.6"/><circle cx="12" cy="12" r="2.7" stroke="currentColor" stroke-width="1.6"/></svg>
                                    <svg class="password-toggle__eye-off" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m3 3 18 18M10.6 5.9A9.7 9.7 0 0 1 12 5.8c6 0 9.2 6.2 9.2 6.2a15.3 15.3 0 0 1-3.1 3.7M6.2 6.3C3.9 7.9 2.8 12 2.8 12s3.2 6.2 9.2 6.2c1 0 1.9-.2 2.7-.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                                    <span class="visually-hidden" data-password-toggle-label>Show password</span>
                                </button>
                            </div>
                        </div>

                        <button class="button button--primary auth-submit" type="submit">Update Password &amp; Continue &rarr;</button>
                    </form>

                    <p class="auth-switch"><a href="logout.php">Sign out &amp; return to Login</a></p>
                </div>
            </section>

            <aside class="auth-visual auth-visual--login" aria-labelledby="visual-title">
                <div class="auth-visual__glow" aria-hidden="true"></div>
                <div class="auth-visual__copy">
                    <p class="auth-visual__eyebrow">Account Security</p>
                    <h2 id="visual-title">Protect your team's application.</h2>
                    <p>Your team is building something innovative. Setting a custom password ensures only authorized members and mentors access your proposal data.</p>
                </div>
                <div class="team-illustration team-illustration--login" aria-hidden="true">
                    <span class="team-illustration__orbit team-illustration__orbit--one"></span>
                    <span class="team-illustration__orbit team-illustration__orbit--two"></span>
                    <span class="team-illustration__core"><span></span></span>
                    <span class="team-illustration__node team-illustration__node--one"><i></i></span>
                    <span class="team-illustration__node team-illustration__node--two"><i></i></span>
                </div>
                <div class="auth-visual__footnote">PSG iTech Launchpad · Innozone &amp; Startupzone</div>
            </aside>
        </div>
    </main>

    <?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
