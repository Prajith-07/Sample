<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';

$authError = null;
$authSuccess = null;
$currentAuthPage = 'login';

// If user logged out message
if (isset($_GET['logged_out'])) {
    $authSuccess = "You have been safely signed out.";
} elseif (isset($_GET['password_reset'])) {
    $authSuccess = "Your password has been successfully updated. Please sign in with your new password.";
}

// If already logged in, redirect to team's registration
if (!empty($_SESSION['team_id']) && !isset($_GET['reauth'])) {
    $tid = (int)$_SESSION['team_id'];
    $pdo = getDatabaseConnection();
    if ($pdo) {
        $stmt = $pdo->prepare("SELECT current_step, status FROM teams WHERE id = :tid");
        $stmt->execute([':tid' => $tid]);
        $tm = $stmt->fetch();
        if ($tm) {
            $step = max(1, (int)$tm['current_step']);
            if ($tm['status'] === 'Submitted' || $tm['status'] === 'Under Screening') {
                header("Location: registration_success.php?team_id={$tid}");
                exit;
            }
            header("Location: register.php?step={$step}&team_id={$tid}");
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $authError = "Please enter both your registered email and password.";
    } else {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            $authError = "Database service unavailable. Please check that MySQL is running in XAMPP.";
        } else {
            $userRecord = null;
            $userType = null; // 'student' or 'mentor'
            $hashToVerify = null;

            // 1. Look in team_members (linked with teams)
            $stmt = $pdo->prepare("
                SELECT m.id AS member_id, m.team_id, m.is_lead, m.member_order, m.full_name, m.college_email, m.phone_number,
                       m.password_hash AS member_password_hash, m.is_dummy_password,
                       t.team_name, t.application_no, t.password_hash AS team_password_hash, t.current_step, t.status, t.leader_email
                FROM team_members m
                JOIN teams t ON m.team_id = t.id
                WHERE LOWER(m.college_email) = :email
                LIMIT 1
            ");
            $stmt->execute([':email' => $email]);
            $studentRow = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($studentRow) {
                $userType = 'student';
                $userRecord = $studentRow;
                $hashToVerify = !empty($studentRow['member_password_hash']) ? $studentRow['member_password_hash'] : $studentRow['team_password_hash'];
            } else {
                // 2. Look in team_mentors
                $stmtMentor = $pdo->prepare("
                    SELECT tm.id AS mentor_id, tm.team_id, tm.mentor_type, tm.name AS full_name, tm.email, tm.phone AS phone_number,
                           tm.password_hash AS mentor_password_hash, tm.is_dummy_password,
                           t.team_name, t.application_no, t.current_step, t.status
                    FROM team_mentors tm
                    JOIN teams t ON tm.team_id = t.id
                    WHERE LOWER(tm.email) = :email
                    LIMIT 1
                ");
                $stmtMentor->execute([':email' => $email]);
                $mentorRow = $stmtMentor->fetch(PDO::FETCH_ASSOC);

                if ($mentorRow) {
                    $userType = 'mentor';
                    $userRecord = $mentorRow;
                    $hashToVerify = $mentorRow['mentor_password_hash'];
                } else {
                    // 3. Fallback check in teams (for legacy or direct leader email match)
                    $stmtLead = $pdo->prepare("SELECT * FROM teams WHERE LOWER(leader_email) = :email LIMIT 1");
                    $stmtLead->execute([':email' => $email]);
                    $teamLeadRow = $stmtLead->fetch(PDO::FETCH_ASSOC);
                    if ($teamLeadRow) {
                        $userType = 'student';
                        $userRecord = $teamLeadRow;
                        $userRecord['team_id'] = $teamLeadRow['id'];
                        $hashToVerify = $teamLeadRow['password_hash'];
                    }
                }
            }

            if (!$userRecord) {
                $authError = "No account found with email \"{$email}\". Please verify your address or create a team account.";
            } elseif (empty($hashToVerify)) {
                $authError = "No password has been configured for this account. Please contact your Team Leader.";
            } elseif (!password_verify($password, $hashToVerify)) {
                $authError = "Incorrect password. Please check your credentials and try again.";
            } else {
                // Credentials are valid!
                // Determine if user is the designated Team Leader
                $isLeader = false;
                if (!empty($userRecord['is_lead'])) {
                    $isLeader = true;
                } elseif (!empty($userRecord['leader_email']) && strtolower($userRecord['leader_email']) === $email) {
                    $isLeader = true;
                } elseif ($userType === 'student' && isset($userRecord['member_order']) && (int)$userRecord['member_order'] === 1) {
                    $isLeader = true;
                }

                // If leader's record was flagged as dummy from earlier test, auto-heal to 0
                if ($isLeader && !empty($userRecord['member_id']) && (int)($userRecord['is_dummy_password'] ?? 0) === 1) {
                    $pdo->prepare("UPDATE `team_members` SET `is_dummy_password` = 0 WHERE id = :id")->execute([':id' => $userRecord['member_id']]);
                }

                // Check if entered password matches the user's registered phone number
                $registeredPhone = (string)($userRecord['phone_number'] ?? '');
                $cleanRegPhone = preg_replace('/[^\d]/', '', $registeredPhone);
                if (strlen($cleanRegPhone) > 10) {
                    $cleanRegPhone = substr($cleanRegPhone, -10);
                }

                $cleanEnteredPassword = preg_replace('/[^\d]/', '', $password);
                if (strlen($cleanEnteredPassword) > 10) {
                    $cleanEnteredPassword = substr($cleanEnteredPassword, -10);
                }

                $isPasswordMobileNumber = false;
                if (!empty($cleanRegPhone)) {
                    if (trim($password) === trim($registeredPhone) || trim($password) === $cleanRegPhone) {
                        $isPasswordMobileNumber = true;
                    } elseif (!empty($cleanEnteredPassword) && $cleanEnteredPassword === $cleanRegPhone) {
                        $isPasswordMobileNumber = true;
                    }
                }

                $isDummy = (int)($userRecord['is_dummy_password'] ?? 0);

                // TEAM LEADER: Password update NEVER happens. Always route straight into the registration portal.
                // OTHER MEMBERS / MENTORS:
                // If the entered password is the mobile number (dummy password), direct to update password portal.
                // If the entered password is NOT the mobile number, direct into registration portal.
                if (!$isLeader && ($isPasswordMobileNumber || $isDummy === 1)) {
                    $_SESSION['must_change_password'] = true;
                    $_SESSION['auth_user_id'] = (int)($userRecord['member_id'] ?? ($userRecord['mentor_id'] ?? $userRecord['id']));
                    $_SESSION['auth_user_type'] = $userType; // 'student' or 'mentor'
                    $_SESSION['user_email'] = $email;
                    $_SESSION['user_phone'] = $registeredPhone;
                    $_SESSION['user_name'] = $userRecord['full_name'] ?? 'Team Member';
                    $_SESSION['team_id'] = (int)($userRecord['team_id'] ?? $userRecord['id'] ?? 0);
                    $_SESSION['team_name'] = $userRecord['team_name'] ?? '';
                    $_SESSION['is_lead'] = false;

                    header("Location: change_password.php?first_login=1");
                    exit;
                }

                // Password has already been established OR user is the Team Leader!
                unset($_SESSION['must_change_password']);
                $_SESSION['team_id'] = (int)($userRecord['team_id'] ?? $userRecord['id'] ?? 0);
                $_SESSION['user_email'] = $email;
                $_SESSION['user_name'] = $userRecord['full_name'] ?? ($isLeader ? 'Team Leader' : 'Team Member');
                $_SESSION['team_name'] = $userRecord['team_name'] ?? '';
                $_SESSION['user_role'] = $userType;
                $_SESSION['is_lead'] = $isLeader;

                if ($userType === 'mentor') {
                    header("Location: submissions.php");
                    exit;
                }

                // Student team member or leader
                $targetStep = max(1, (int)($userRecord['current_step'] ?? 1));
                $teamStatus = $userRecord['status'] ?? 'Draft';

                if ($teamStatus === 'Submitted' || $teamStatus === 'Under Screening') {
                    header("Location: registration_success.php?team_id={$userRecord['team_id']}");
                    exit;
                }

                header("Location: register.php?step={$targetStep}&team_id={$userRecord['team_id']}");
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
    <meta name="description" content="Sign in to the PSG iTech Seed Grant Challenge 2027 team account.">
    <meta name="theme-color" content="#f2f5ef">
    <title>Team Login | PSG iTech Seed Grant Challenge 2027</title>
    <script>
        (() => {
            let theme = null;
            try {
                theme = window.localStorage.getItem('seed-grant-theme');
            } catch (error) {}

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
                    <h1 id="auth-title">Sign In to Your Account</h1>
                    <p class="auth-intro">Team leads, team members, and mentors sign in with their registered email to view and continue their application.</p>

                    <?php if (!empty($authError)): ?>
                        <div style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.4); color: #dc2626; padding: 0.85rem 1.15rem; border-radius: 12px; margin-bottom: 1.5rem; font-size: 0.88rem; display: flex; align-items: flex-start; gap: 0.65rem;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink: 0; margin-top: 2px;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                            <span><?= htmlspecialchars($authError) ?></span>
                        </div>
                    <?php elseif (!empty($authSuccess)): ?>
                        <div style="background: rgba(34, 197, 94, 0.12); border: 1px solid rgba(34, 197, 94, 0.4); color: #16a34a; padding: 0.85rem 1.15rem; border-radius: 12px; margin-bottom: 1.5rem; font-size: 0.88rem; display: flex; align-items: center; gap: 0.65rem;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            <span><?= htmlspecialchars($authSuccess) ?></span>
                        </div>
                    <?php endif; ?>

                    <form class="auth-form" method="POST" action="login.php" data-auth-form>
                        <div class="auth-field">
                            <label for="login-email">Registered Email</label>
                            <input class="auth-input" id="login-email" name="email" type="email" autocomplete="email" placeholder="Enter your registered email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                        </div>

                        <div class="auth-field">
                            <div class="auth-field__label-row">
                                <label for="login-password">Password</label>
                                <a href="forgot_password.php" class="auth-forgot" style="color:var(--primary, #15803d); text-decoration:none; font-size:0.83rem; font-weight:600;">Forgot password?</a>
                            </div>
                            <div class="auth-input-wrap">
                                <input class="auth-input auth-input--password" id="login-password" name="password" type="password" autocomplete="current-password" placeholder="Enter your password" required>
                                <button class="password-toggle" type="button" data-password-toggle aria-controls="login-password" aria-pressed="false" aria-label="Show password">
                                    <svg class="password-toggle__eye" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2.8 12s3.2-6.1 9.2-6.1 9.2 6.1 9.2 6.1-3.2 6.1-9.2 6.1S2.8 12 2.8 12Z" stroke="currentColor" stroke-width="1.6"/><circle cx="12" cy="12" r="2.7" stroke="currentColor" stroke-width="1.6"/></svg>
                                    <svg class="password-toggle__eye-off" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m3 3 18 18M10.6 5.9A9.7 9.7 0 0 1 12 5.8c6 0 9.2 6.2 9.2 6.2a15.3 15.3 0 0 1-3.1 3.7M6.2 6.3C3.9 7.9 2.8 12 2.8 12s3.2 6.2 9.2 6.2c1 0 1.9-.2 2.7-.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                                    <span class="visually-hidden" data-password-toggle-label>Show password</span>
                                </button>
                            </div>
                        </div>

                        <button class="button button--primary auth-submit" type="submit">Sign In</button>
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
