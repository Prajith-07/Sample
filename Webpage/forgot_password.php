<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/utils/mailer.php';

$error = null;
$success = null;
$currentAuthPage = 'forgot_password';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string)($_POST['email'] ?? '')));

    if (empty($email)) {
        $error = "Please enter your registered email address.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            $error = "Database service unavailable. Please check that MySQL is running in XAMPP.";
        } else {
            $userFound = null;
            $userType = null; // 'student' or 'mentor'

            // 1. Look in team_members
            $stmtM = $pdo->prepare("SELECT id, full_name, college_email, phone_number FROM `team_members` WHERE LOWER(college_email) = :email LIMIT 1");
            $stmtM->execute([':email' => $email]);
            $student = $stmtM->fetch(PDO::FETCH_ASSOC);

            if ($student) {
                $userFound = $student;
                $userType = 'student';
            } else {
                // 2. Look in team_mentors
                $stmtMen = $pdo->prepare("SELECT id, name AS full_name, email, phone AS phone_number FROM `team_mentors` WHERE LOWER(email) = :email LIMIT 1");
                $stmtMen->execute([':email' => $email]);
                $mentor = $stmtMen->fetch(PDO::FETCH_ASSOC);

                if ($mentor) {
                    $userFound = $mentor;
                    $userType = 'mentor';
                }
            }

            if ($userFound) {
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour

                if ($userType === 'mentor') {
                    $stmtUp = $pdo->prepare("UPDATE `team_mentors` SET reset_token = :token, reset_token_expires = :exp WHERE id = :id");
                    $stmtUp->execute([':token' => $token, ':exp' => $expires, ':id' => $userFound['id']]);
                } else {
                    $stmtUp = $pdo->prepare("UPDATE `team_members` SET reset_token = :token, reset_token_expires = :exp WHERE id = :id");
                    $stmtUp->execute([':token' => $token, ':exp' => $expires, ':id' => $userFound['id']]);
                }

                // Send email with reset link
                $config = require __DIR__ . '/config/mail.php';
                $baseUrl = ($config['get_base_url'])();
                $resetUrl = "{$baseUrl}/reset_password.php?token=" . urlencode($token);

                $subject = "Password Reset Request | iTech Seed Grand Challenge 2027";
                $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<body style="font-family:sans-serif; background:#f4f7f5; padding:20px; color:#1e293b;">
  <div style="max-width:540px; margin:0 auto; background:#ffffff; border-radius:12px; padding:28px; border:1px solid #d4e5d9;">
    <h2 style="color:#0e3824; margin-top:0;">Password Reset Request</h2>
    <p>Hello <strong>{$userFound['full_name']}</strong>,</p>
    <p>A password reset request was received for your account. Click the button below to reset your password:</p>
    <div style="text-align:center; margin:24px 0;">
      <a href="{$resetUrl}" style="background:#1b5e20; color:#ffffff; padding:12px 28px; text-decoration:none; border-radius:24px; font-weight:700; display:inline-block;">Reset My Password &rarr;</a>
    </div>
    <p style="font-size:12px; color:#64748b;">This link will expire in 1 hour. If you did not make this request, you can safely ignore this email.</p>
    <hr style="border:none; border-top:1px solid #e2e8f0; margin:20px 0;">
    <p style="font-size:11px; color:#94a3b8; word-break:break-all;">Direct link: <a href="{$resetUrl}" style="color:#1b5e20;">{$resetUrl}</a></p>
  </div>
</body>
</html>
HTML;

                try {
                    $mailer = new LaunchpadMailer();
                    $mailer->dispatchEmail(
                        toEmail: $email,
                        toName: $userFound['full_name'],
                        subject: $subject,
                        htmlBody: $htmlBody
                    );
                } catch (Throwable $e) {
                    error_log("Password reset mail error: " . $e->getMessage());
                }

                $success = "If your email is registered in our database, a password reset link has been dispatched to your inbox.";
            } else {
                // Generic response to prevent user enumeration
                $success = "If your email is registered in our database, a password reset link has been dispatched to your inbox.";
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
    <meta name="description" content="Reset your password for PSG iTech Seed Grant Challenge 2027.">
    <meta name="theme-color" content="#f2f5ef">
    <title>Forgot Password | PSG iTech Seed Grant Challenge 2027</title>
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
                    <p class="auth-eyebrow"><span class="auth-eyebrow__dot" aria-hidden="true"></span> PSG iTech · Account Recovery</p>
                    <h1 id="auth-title">Forgot Password</h1>
                    <p class="auth-intro">Enter your registered email address. We'll send you a secure link to create a new password.</p>

                    <?php if (!empty($error)): ?>
                        <div style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.4); color: #dc2626; padding: 0.85rem 1.15rem; border-radius: 12px; margin-bottom: 1.5rem; font-size: 0.88rem; display: flex; align-items: flex-start; gap: 0.65rem;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink: 0; margin-top: 2px;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                            <div><?= htmlspecialchars($error) ?></div>
                        </div>
                    <?php elseif (!empty($success)): ?>
                        <div style="background: rgba(34, 197, 94, 0.12); border: 1px solid rgba(34, 197, 94, 0.4); color: #16a34a; padding: 0.85rem 1.15rem; border-radius: 12px; margin-bottom: 1.5rem; font-size: 0.88rem; display: flex; align-items: flex-start; gap: 0.65rem; line-height: 1.45;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink: 0; margin-top: 2px;"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            <div><?= htmlspecialchars($success) ?></div>
                        </div>
                    <?php endif; ?>

                    <form class="auth-form" method="POST" action="forgot_password.php" data-auth-form>
                        <div class="auth-field">
                            <label for="recovery-email">Registered Email</label>
                            <input class="auth-input" id="recovery-email" name="email" type="email" autocomplete="email" placeholder="Enter your registered email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                        </div>

                        <button class="button button--primary auth-submit" type="submit">Send Reset Link &rarr;</button>
                    </form>

                    <p class="auth-switch">Remember your password? <a href="login.php">Back to Sign In</a></p>
                </div>
            </section>

            <aside class="auth-visual auth-visual--login" aria-labelledby="visual-title">
                <div class="auth-visual__glow" aria-hidden="true"></div>
                <div class="auth-visual__copy">
                    <p class="auth-visual__eyebrow">Recovery Service</p>
                    <h2 id="visual-title">Never lose access to your innovation.</h2>
                    <p>Password reset links are cryptographically signed and expire in 60 minutes for your security.</p>
                </div>
                <div class="team-illustration team-illustration--login" aria-hidden="true">
                    <span class="team-illustration__orbit team-illustration__orbit--one"></span>
                    <span class="team-illustration__orbit team-illustration__orbit--two"></span>
                    <span class="team-illustration__core"><span></span></span>
                </div>
                <div class="auth-visual__footnote">PSG iTech Launchpad · Innozone &amp; Startupzone</div>
            </aside>
        </div>
    </main>

    <?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
