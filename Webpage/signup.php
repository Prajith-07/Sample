<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';

$authError = null;
$currentAuthPage = 'signup';

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
    $leaderEmail = strtolower(trim((string)($_POST['team_leader_email'] ?? '')));
    $password = (string)($_POST['team_password'] ?? '');
    $confirmPassword = (string)($_POST['team_password_confirm'] ?? '');

    if (empty($leaderEmail) || empty($password)) {
        $authError = "Please fill in all required fields.";
    } elseif (!filter_var($leaderEmail, FILTER_VALIDATE_EMAIL)) {
        $authError = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $authError = "Team password must be at least 6 characters in length.";
    } elseif ($password !== $confirmPassword) {
        $authError = "Passwords do not match. Please re-enter your team password.";
    } else {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            $authError = "Database service unavailable. Please check that MySQL is running in XAMPP.";
        } else {
            // Check if entered email is already present anywhere in DB (teams, team_members, or team_mentors)
            $isEmailTaken = false;

            // 1. Check in teams
            $stmtTeam = $pdo->prepare("SELECT id, team_name FROM `teams` WHERE LOWER(TRIM(leader_email)) = :e LIMIT 1");
            $stmtTeam->execute([':e' => $leaderEmail]);
            if ($stmtTeam->fetch(PDO::FETCH_ASSOC)) {
                $isEmailTaken = true;
            }

            // 2. Check in team_members
            if (!$isEmailTaken) {
                $stmtMem = $pdo->prepare("SELECT id FROM `team_members` WHERE LOWER(TRIM(college_email)) = :e LIMIT 1");
                $stmtMem->execute([':e' => $leaderEmail]);
                if ($stmtMem->fetch(PDO::FETCH_ASSOC)) {
                    $isEmailTaken = true;
                }
            }

            // 3. Check in team_mentors
            if (!$isEmailTaken) {
                $stmtMen = $pdo->prepare("SELECT id FROM `team_mentors` WHERE LOWER(TRIM(email)) = :e LIMIT 1");
                $stmtMen->execute([':e' => $leaderEmail]);
                if ($stmtMen->fetch(PDO::FETCH_ASSOC)) {
                    $isEmailTaken = true;
                }
            }

            if ($isEmailTaken) {
                $authError = "An account with the email <strong>" . htmlspecialchars($leaderEmail) . "</strong> is already registered. Please <a href='login.php' style='color:#15803d; font-weight:700; text-decoration:underline;'>move to the Login page</a> to sign in.";
            } else {
                // Generate Unique Application Number (e.g. SGC27-XXXXXX)
                $applicationNo = 'SGC27-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
                $tempTeamName = 'Draft Team ' . substr($applicationNo, -4);
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                // Immediately create the team in MySQL so it exists and can be tracked
                $stmtNewTeam = $pdo->prepare("
                    INSERT INTO `teams` (`application_no`, `team_name`, `leader_email`, `password_hash`, `team_size`, `has_woman_member`, `current_step`, `status`)
                    VALUES (:app_no, :tname, :lemail, :phash, 2, 0, 1, 'Draft')
                ");
                $stmtNewTeam->execute([
                    ':app_no' => $applicationNo,
                    ':tname'  => $tempTeamName,
                    ':lemail' => $leaderEmail,
                    ':phash'  => $passwordHash
                ]);
                $newTeamId = (int)$pdo->lastInsertId();

                // Insert initial team_member record for the Team Leader
                $stmtLeadMem = $pdo->prepare("
                    INSERT INTO `team_members` (`team_id`, `is_lead`, `member_order`, `full_name`, `roll_number`, `department`, `year_of_study`, `college_email`, `phone_number`, `gender`, `password_hash`, `is_dummy_password`)
                    VALUES (:tid, 1, 1, '', '', '', 'II', :email, '', 'Male', :phash, 0)
                ");
                $stmtLeadMem->execute([
                    ':tid'   => $newTeamId,
                    ':email' => $leaderEmail,
                    ':phash' => $passwordHash
                ]);

                // Clear any prior staged data and establish active Team Leader session
                unset($_SESSION['staged_lead_email']);
                unset($_SESSION['staged_lead_password_hash']);
                unset($_SESSION['is_new_registration']);
                unset($_SESSION['must_change_password']);

                $_SESSION['team_id']    = $newTeamId;
                $_SESSION['user_email'] = $leaderEmail;
                $_SESSION['team_name']  = $tempTeamName;
                $_SESSION['is_lead']    = true;
                $_SESSION['user_role']  = 'student';

                header("Location: register.php?step=1&team_id={$newTeamId}");
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
    <meta name="description" content="Create the first account for your PSG iTech Seed Grant Challenge 2027 team.">
    <meta name="theme-color" content="#f2f5ef">
    <title>Team Registration | PSG iTech Seed Grant Challenge 2027</title>
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
                    <h1 id="auth-title">Create your team account</h1>
                    <p class="auth-intro">Start with the team leader's email. Your team can continue registration together after the first step.</p>

                    <?php if (!empty($authError)): ?>
                        <div style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.4); color: #dc2626; padding: 0.85rem 1.15rem; border-radius: 12px; margin-bottom: 1.5rem; font-size: 0.88rem; display: flex; align-items: flex-start; gap: 0.65rem; line-height: 1.45;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink: 0; margin-top: 2px;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                            <div><?= $authError ?></div>
                        </div>
                    <?php endif; ?>

                    <form class="auth-form" method="POST" action="signup.php" data-auth-form>
                        <div class="auth-field">
                            <label for="team-leader-email">Team Leader Email</label>
                            <input class="auth-input" id="team-leader-email" name="team_leader_email" type="email" autocomplete="email" placeholder="e.g. 23cs045@psgitech.ac.in or leader@gmail.com" value="<?= htmlspecialchars($_POST['team_leader_email'] ?? '') ?>" required>
                            <div id="email-duplicate-feedback" style="font-size: 0.83rem; margin-top: 0.35rem; display: none;"></div>
                        </div>

                        <div class="auth-field">
                            <label for="team-password">Team Password</label>
                            <div class="auth-input-wrap">
                                <input class="auth-input auth-input--password" id="team-password" name="team_password" type="password" autocomplete="new-password" placeholder="Create a team password (min 6 chars)" aria-describedby="team-password-help" required>
                                <button class="password-toggle" type="button" data-password-toggle aria-controls="team-password" aria-pressed="false" aria-label="Show password">
                                    <svg class="password-toggle__eye" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2.8 12s3.2-6.1 9.2-6.1 9.2 6.1 9.2 6.1-3.2 6.1-9.2 6.1S2.8 12 2.8 12Z" stroke="currentColor" stroke-width="1.6"/><circle cx="12" cy="12" r="2.7" stroke="currentColor" stroke-width="1.6"/></svg>
                                    <svg class="password-toggle__eye-off" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m3 3 18 18M10.6 5.9A9.7 9.7 0 0 1 12 5.8c6 0 9.2 6.2 9.2 6.2a15.3 15.3 0 0 1-3.1 3.7M6.2 6.3C3.9 7.9 2.8 12 2.8 12s3.2 6.2 9.2 6.2c1 0 1.9-.2 2.7-.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                                    <span class="visually-hidden" data-password-toggle-label>Show password</span>
                                </button>
                            </div>
                            <p class="auth-field__help" id="team-password-help">Choose a password your team members can share to collaborate.</p>
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
                            <span>Team members will be able to login with their college email after the leader completes step 1</span>
                        </p>

                        <button class="button button--primary auth-submit" type="submit">Create Team Account</button>
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
                <div class="auth-visual__footnote"><span aria-hidden="true"></span> Team leaders and members, together</div>
            </aside>
        </div>
    </main>

    <?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
