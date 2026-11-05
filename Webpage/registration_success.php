<?php
/**
 * iTech Seed Grand Challenge 2027
 * Registration Completion & Official Confirmation Landing Page
 * PSG iTech Launchpad: Innozone & Startupzone
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!empty($_SESSION['must_change_password'])) {
    header("Location: change_password.php?first_login=1");
    exit;
}

require_once __DIR__ . '/config/db.php';

$pdo = getDatabaseConnection();
$teamId = isset($_GET['team_id']) ? (int)$_GET['team_id'] : 0;
$appNo  = trim($_GET['app_no'] ?? '');

$team = null;
$members = [];
$mentors = [];
$proposal = null;
$milestones = [];
$budgetItems = [];
$leadMember = null;
$errorMessage = null;

if ($pdo && ($teamId > 0 || !empty($appNo))) {
    try {
        if ($teamId > 0) {
            $stmtT = $pdo->prepare("SELECT * FROM `teams` WHERE id = :id LIMIT 1");
            $stmtT->execute([':id' => $teamId]);
        } else {
            $stmtT = $pdo->prepare("SELECT * FROM `teams` WHERE application_no = :app LIMIT 1");
            $stmtT->execute([':app' => $appNo]);
        }
        $team = $stmtT->fetch(PDO::FETCH_ASSOC);

        if (!$team) {
            $errorMessage = "Team application record not found.";
        } else {
            $tId = (int)$team['id'];

            // Fetch Members
            $stmtM = $pdo->prepare("SELECT * FROM `team_members` WHERE team_id = :tid ORDER BY member_order ASC");
            $stmtM->execute([':tid' => $tId]);
            $members = $stmtM->fetchAll(PDO::FETCH_ASSOC);
            foreach ($members as $m) {
                if ($m['is_lead']) {
                    $leadMember = $m;
                    break;
                }
            }

            // Fetch Mentors
            $stmtMen = $pdo->prepare("SELECT * FROM `team_mentors` WHERE team_id = :tid ORDER BY id ASC");
            $stmtMen->execute([':tid' => $tId]);
            $mentors = $stmtMen->fetchAll(PDO::FETCH_ASSOC);

            // Fetch Proposal
            $stmtP = $pdo->prepare("SELECT * FROM `proposals` WHERE team_id = :tid LIMIT 1");
            $stmtP->execute([':tid' => $tId]);
            $proposal = $stmtP->fetch(PDO::FETCH_ASSOC);

            // Fetch Milestones
            $stmtMs = $pdo->prepare("SELECT * FROM `proposal_milestones` WHERE team_id = :tid ORDER BY month_number ASC");
            $stmtMs->execute([':tid' => $tId]);
            $milestones = $stmtMs->fetchAll(PDO::FETCH_ASSOC);

            // Fetch Budget
            $stmtB = $pdo->prepare("SELECT * FROM `proposal_budget_items` WHERE team_id = :tid ORDER BY item_order ASC, id ASC");
            $stmtB->execute([':tid' => $tId]);
            $budgetItems = $stmtB->fetchAll(PDO::FETCH_ASSOC);

            // Calculate total requested budget
            $totalBudget = 0.0;
            foreach ($budgetItems as $bi) {
                $totalBudget += (float)($bi['amount'] ?? 0);
            }
            if ($totalBudget <= 0 && $proposal && !empty($proposal['budget_requested'])) {
                $totalBudget = (float)$proposal['budget_requested'];
            }

            // Ensure team status reflects complete registration
            if ($team['status'] === 'Draft' || empty($team['status'])) {
                $stmtUp = $pdo->prepare("UPDATE `teams` SET status = 'Under Screening', current_step = 7 WHERE id = :tid");
                $stmtUp->execute([':tid' => $tId]);
                $team['status'] = 'Under Screening';
            }
        }
    } catch (PDOException $e) {
        error_log("Registration success lookup error: " . $e->getMessage());
        $errorMessage = "Database connection error. Please try again.";
    }
} else {
    $errorMessage = "Invalid request. Please provide a valid team ID or application number.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registration Completed | iTech Seed Grand Challenge 2027</title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
  
  <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>">

  <!-- Immediate Theme Initializer to Prevent Flash -->
  <script>
    (() => {
      let theme = 'light';
      try {
        theme = window.localStorage.getItem('seed-grant-theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
      } catch (e) {}
      document.documentElement.dataset.theme = theme;
    })();
  </script>
</head>
<body>

  <!-- BOTANICAL AMBIENT GLOWS -->
  <div class="ambient-glow glow-emerald"></div>
  <div class="ambient-glow glow-sunlight"></div>
  <div class="ambient-glow glow-springlime"></div>
  <div class="ambient-glow glow-dewteal"></div>

  <!-- Canopy Texture Background -->
  <div class="botanical-canopy-layer" aria-hidden="true"></div>

  <div class="page-container">
    
    <!-- TOP OFFICIAL SITE HEADER (sdc.psgitech.ac.in) -->
    <header class="site-header">
      <div class="site-header__inner">
        <a class="brand-strip" href="register.php" aria-label="PSG iTech Seed Grand Challenge">
          <span class="brand-strip__item"><img src="assets/college-logo-transparent.png" width="1136" height="1385" alt="PSG iTech"></span>
          <span class="brand-strip__item"><img src="assets/innovation-council-transparent.png" width="1831" height="859" alt="Institution's Innovation Council"></span>
          <span class="brand-strip__item"><img src="assets/psg-step-transparent.png" width="1230" height="1279" alt="PSG STEP"></span>
          <span class="brand-strip__item"><img src="assets/itech_launchpad-transparent.png" width="1683" height="935" alt="iTech Launchpad"></span>
        </a>

        <nav class="header-actions" aria-label="Portal Navigation & Display">
          <?php if ($team): ?>
            <a href="download_report.php?team_id=<?= (int)$team['id'] ?>" target="_blank" class="button button--primary button--small">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
              <span>Dossier (PDF)</span>
            </a>
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

    <main class="portal-container">
      
      <?php if ($errorMessage): ?>
        <section class="glass-panel" style="text-align: center; padding: 4rem 2rem; max-width: 640px; margin: 3rem auto;">
          <div style="width: 60px; height: 60px; border-radius: 50%; background: rgba(239, 68, 68, 0.15); color: #ef4444; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem;">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
          </div>
          <h2 style="font-family: var(--font-heading); color: var(--text); margin-bottom: 0.75rem;">Registration Notice</h2>
          <p style="color: var(--text-muted); line-height: 1.6; margin-bottom: 2rem;"><?= htmlspecialchars($errorMessage) ?></p>
          <a href="register.php" class="btn-save-step" style="text-decoration: none; display: inline-flex;">Return to Portal Home</a>
        </section>
      <?php else: ?>

        <!-- HERO CELEBRATION CARD (EXACT MATCH TO IMAGE 1 & GLASSMORPHISM) -->
        <section class="glass-panel success-hero-panel">
          <div class="success-celebrate-badge">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
            <span>REGISTRATION SUCCESSFULLY COMPLETED</span>
          </div>

          <h1 class="success-hero-title">
            Congratulations, Team <?= htmlspecialchars($team['team_name']) ?>!
          </h1>
          <p class="success-hero-subtitle">
            Your proposal for the <strong>iTech Seed Grand Challenge 2027</strong> has been officially validated, endorsed by both your Faculty Mentor and Industry Mentor, and submitted for Screening Committee evaluation.
          </p>

          <!-- Key Application Meta Grid (Bento Grid) -->
          <div class="hero-meta-grid">
            <div class="hero-meta-card">
              <div class="hero-meta-label">APPLICATION REFERENCE</div>
              <div class="hero-meta-val val-accent" style="font-family: var(--font-mono-tech);">
                <?= htmlspecialchars($team['application_no']) ?>
              </div>
            </div>

            <div class="hero-meta-card">
              <div class="hero-meta-label">TEAM LEAD</div>
              <div class="hero-meta-val">
                <?= htmlspecialchars($leadMember['full_name'] ?? 'Designated Lead') ?>
              </div>
            </div>

            <div class="hero-meta-card">
              <div class="hero-meta-label">GRANT REQUESTED</div>
              <div class="hero-meta-val val-accent">
                ₹<?= number_format($totalBudget, 2) ?>
              </div>
            </div>

            <div class="hero-meta-card">
              <div class="hero-meta-label">CURRENT STANDING</div>
              <div class="hero-meta-val val-accent" style="font-size: 1.05rem;">
                Under Screening Panel Review
              </div>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="hero-actions-row">
            <a href="download_report.php?team_id=<?= (int)$team['id'] ?>" target="_blank" class="btn-download-dossier">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
              <span>Download Complete Application Dossier (PDF)</span>
            </a>

            <button type="button" onclick="window.print();" class="btn-print-confirmation">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
              <span>Print Confirmation</span>
            </button>
          </div>
        </section>

      <?php endif; ?>

    </main>

    <!-- Footer -->
    <footer class="event-footer">
      <div class="footer-bottom">
        <p>© 2026–2027 PSG Institute of Technology and Applied Research · Innozone &amp; Startupzone (Launchpad)</p>
      </div>
    </footer>

  </div>

  <!-- Theme Toggle Controller -->
  <script>
    (() => {
      const root = document.documentElement;
      const themeToggle = document.querySelector('.theme-toggle');
      const storageKey = 'seed-grant-theme';

      function applyTheme(theme, persist = false) {
        const nextTheme = theme === 'dark' ? 'dark' : 'light';
        root.dataset.theme = nextTheme;
        if (themeToggle) {
          themeToggle.setAttribute('aria-pressed', String(nextTheme === 'dark'));
          themeToggle.setAttribute('aria-label', nextTheme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
        }
        if (persist) {
          try {
            window.localStorage.setItem(storageKey, nextTheme);
          } catch (e) {}
        }
      }

      applyTheme(root.dataset.theme || 'light');

      themeToggle?.addEventListener('click', () => {
        applyTheme(root.dataset.theme === 'dark' ? 'light' : 'dark', true);
      });
    })();
  </script>
</body>
</html>
