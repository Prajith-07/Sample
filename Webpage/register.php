<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enforce mandatory password change before accessing registration
if (!empty($_SESSION['must_change_password'])) {
    header("Location: change_password.php?first_login=1");
    exit;
}

/**
 * iTech Seed Grand Challenge 2027
 * Multi-Step Challenge Registration Portal
 * Step 1: Team & Accounts | Step 2: The Idea
 * PSG iTech Launchpad: Innozone & Startupzone
 */
require_once __DIR__ . '/config/db.php';

$pdo = getDatabaseConnection();
$currentStep = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$editTeamId = isset($_GET['team_id']) ? (int)$_GET['team_id'] : 0;
if ($editTeamId <= 0 && !empty($_SESSION['team_id'])) {
    $editTeamId = (int)$_SESSION['team_id'];
}
$isLeader = !empty($_SESSION['is_lead']);
$appNo = trim($_GET['app_no'] ?? '');

$existingTeam = null;
$existingMembers = [];
$existingMentors = [];
$existingProposal = null;
$leadMember = null;
$stagedLeaderEmail = $_SESSION['staged_lead_email'] ?? ($_SESSION['user_email'] ?? '');

if ($pdo && ($editTeamId > 0 || !empty($appNo))) {
    try {
        if ($editTeamId > 0) {
            $stmt = $pdo->prepare("SELECT * FROM `teams` WHERE id = :id");
            $stmt->execute([':id' => $editTeamId]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM `teams` WHERE application_no = :app");
            $stmt->execute([':app' => $appNo]);
        }
        $existingTeam = $stmt->fetch();

        if ($existingTeam) {
            $tId = $existingTeam['id'];
            if (empty($stagedLeaderEmail) && !empty($existingTeam['leader_email'])) {
                $stagedLeaderEmail = $existingTeam['leader_email'];
            }
            
            // Members
            $stmtM = $pdo->prepare("SELECT * FROM `team_members` WHERE team_id = :tid ORDER BY member_order ASC");
            $stmtM->execute([':tid' => $tId]);
            $existingMembers = $stmtM->fetchAll();
            foreach ($existingMembers as $m) {
                if ($m['is_lead']) {
                    $leadMember = $m;
                    break;
                }
            }

            // Mentors
            $stmtMen = $pdo->prepare("SELECT * FROM `team_mentors` WHERE team_id = :tid");
            $stmtMen->execute([':tid' => $tId]);
            $existingMentors = $stmtMen->fetchAll();

            $facMentor = null;
            $addFacMentor = null;
            $indMentor = null;
            if (!empty($existingMentors)) {
                foreach ($existingMentors as $m) {
                    if ($m['mentor_type'] === 'faculty') {
                        $facMentor = $m;
                    } elseif ($m['mentor_type'] === 'additional_faculty') {
                        $addFacMentor = $m;
                    } elseif ($m['mentor_type'] === 'industry') {
                        $indMentor = $m;
                    }
                }
            }

            // Proposals
            $stmtProp = $pdo->prepare("SELECT * FROM `proposals` WHERE team_id = :tid");
            $stmtProp->execute([':tid' => $tId]);
            $existingProposal = $stmtProp->fetch();

            // Milestones (Step 4)
            $existingMilestones = [];
            $stmtMstone = $pdo->prepare("SELECT * FROM `proposal_milestones` WHERE team_id = :tid ORDER BY month_number ASC");
            $stmtMstone->execute([':tid' => $tId]);
            $mRows = $stmtMstone->fetchAll(PDO::FETCH_ASSOC);
            foreach ($mRows as $mr) {
                $existingMilestones[$mr['month_number']] = $mr;
            }

            // Budget Items (Step 5)
            $existingBudgetItems = [];
            $stmtBudget = $pdo->prepare("SELECT * FROM `proposal_budget_items` WHERE team_id = :tid ORDER BY item_order ASC, id ASC");
            $stmtBudget->execute([':tid' => $tId]);
            $existingBudgetItems = $stmtBudget->fetchAll(PDO::FETCH_ASSOC);

            // Compute Mentor Approval Status for Step 7 Finish Registration
            $facultyApproved = false;
            $industryApproved = false;
            if (!empty($existingMentors)) {
                foreach ($existingMentors as $m) {
                    if ($m['mentor_type'] === 'faculty' && ($m['approval_status'] ?? '') === 'Approved') {
                        $facultyApproved = true;
                    }
                    if ($m['mentor_type'] === 'industry' && ($m['approval_status'] ?? '') === 'Approved') {
                        $industryApproved = true;
                    }
                }
            }
            $allMentorsApproved = ($facultyApproved && $industryApproved);
        }
    } catch (PDOException $e) {
        error_log("Lookup error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $currentStep === 7 ? 'Step 7: Declaration & Mentor Approval' : ($currentStep === 6 ? 'Step 6: Market & Impact' : ($currentStep === 5 ? 'Step 5: Budget & Financial Plan' : ($currentStep === 4 ? 'Step 4: PoC Plan & Milestones' : ($currentStep === 3 ? 'Step 3: Readiness & TRL' : ($currentStep === 2 ? 'Step 2: The Idea' : 'Step 1: Team & Accounts'))))) ?> | iTech Seed Grand Challenge 2027</title>
  
  <!-- Fonts: Outfit, Plus Jakarta Sans, Space Grotesk, Syne -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com">
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=Space+Grotesk:wght@500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
  
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
        <a class="brand-strip" href="index.php" title="Return to Seed Grant Home" aria-label="PSG iTech Seed Grand Challenge">
          <span class="brand-strip__item"><img src="assets/college-logo-transparent.png" width="1136" height="1385" alt="PSG iTech"></span>
          <span class="brand-strip__item"><img src="assets/innovation-council-transparent.png" width="1831" height="859" alt="Institution's Innovation Council"></span>
          <span class="brand-strip__item"><img src="assets/psg-step-transparent.png" width="1230" height="1279" alt="PSG STEP"></span>
          <span class="brand-strip__item"><img src="assets/itech_launchpad-transparent.png" width="1683" height="935" alt="iTech Launchpad"></span>
        </a>

        <nav class="header-actions" aria-label="Portal Navigation & Display" style="display:flex; align-items:center; gap:10px;">
          <a href="index.php" class="header-nav-link" style="display:inline-flex; align-items:center; gap:6px; font-size:0.875rem; font-weight:600; text-decoration:none; padding:7px 14px; border-radius:8px; border:1px solid rgba(148, 163, 184, 0.3); background:var(--bg-card, rgba(255,255,255,0.8)); color:var(--text-main, #1e293b); transition:all 0.2s ease;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
            <span>Home</span>
          </a>
          <?php if (!empty($_SESSION['team_id'])): ?>
            <a href="logout.php" class="header-nav-link" style="display:inline-flex; align-items:center; gap:6px; font-size:0.875rem; font-weight:600; color:#ef4444; text-decoration:none; padding:7px 14px; border-radius:8px; border:1px solid rgba(239,68,68,0.3); background:rgba(239,68,68,0.08); transition:all 0.2s ease;">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
              <span>Sign Out</span>
            </a>
          <?php else: ?>
            <a href="login.php" class="header-nav-link" style="display:inline-flex; align-items:center; gap:6px; font-size:0.875rem; font-weight:600; color:var(--primary, #15803d); text-decoration:none; padding:7px 14px; border-radius:8px; border:1px solid rgba(21,128,61,0.3); background:rgba(21,128,61,0.08); transition:all 0.2s ease;">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><polyline points="10 17 15 12 10 7"></polyline><line x1="15" y1="12" x2="3" y2="12"></line></svg>
              <span>Sign In</span>
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

    <!-- MAIN PORTAL WRAPPER -->
    <main class="portal-container">
      
      <!-- HEADER CARD -->
      <section class="portal-header-card glass-panel">
        <div class="portal-header-meta">
          <div class="portal-event-badge">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2L2 7l10 5 10-5-10-5z"></path><path d="M2 17l10 5 10-5"></path><path d="M2 12l10 5 10-5"></path></svg>
            iTech Seed Grand Challenge 2027
          </div>
          <div class="portal-deadline-badge">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            Application Deadline: <strong>5 December, 2026</strong>
          </div>
        </div>

        <?php if ($currentStep === 7): ?>
          <h1 class="portal-title">
            Declaration &amp; Online Mentor Approval
          </h1>
          <p class="portal-subtitle">
            Final team lead confirmation, regulatory undertakings, digital e-signature, and live mentor consent dashboard.
          </p>
        <?php elseif ($currentStep === 6): ?>
          <h1 class="portal-title">
            Market Potential &amp; Societal Impact
          </h1>
          <p class="portal-subtitle">
            Define your target customers, market size, commercialisation path, and measurable societal impact.
          </p>
        <?php elseif ($currentStep === 5): ?>
          <h1 class="portal-title">
            Budget &amp; Itemized Financial Plan
          </h1>
          <p class="portal-subtitle">
            Item-wise cost breakdown up to <strong>₹1,00,000 in total</strong> with technical justification for each required component, raw material, or fabrication service.
          </p>
        <?php elseif ($currentStep === 4): ?>
          <h1 class="portal-title">
            PoC Plan & Milestones: Technical Feasibility
          </h1>
          <p class="portal-subtitle">
            Define concrete deliverables, testing methodology, verifiable success criteria, and your 6-month milestone roadmap (Jan 1 to Jun 30, 2027). Scored out of <strong>10 marks</strong> on the evaluation rubric.
          </p>
        <?php elseif ($currentStep === 3): ?>
          <h1 class="portal-title">
            Project Readiness & Technology Maturity
          </h1>
          <p class="portal-subtitle">
            Evaluate your Technology Readiness Level (TRL), work done so far, prior prize or funding disclosures, and intellectual property status.
          </p>
        <?php elseif ($currentStep === 2): ?>
          <h1 class="portal-title">
            The Idea: Innovation & Problem Identification
          </h1>
          <p class="portal-subtitle">
            Define your real-world industrial or societal problem, current limitations, and your proposed Proof-of-Concept (PoC). Scored out of <strong>10 marks</strong> on the evaluation rubric.
          </p>
        <?php else: ?>
          <h1 class="portal-title">
            Team & Account Registration
          </h1>
          <p class="portal-subtitle">
            Register your multidisciplinary student team and assigned mentors to begin your proposal submission. Grants up to <strong>₹1,00,000 per team</strong> are awarded to build functional Proof-of-Concepts (PoCs).
          </p>
        <?php endif; ?>
      </section>

      <!-- ==================================================================
           GLASSMORPHIC FLOATING CAPSULE STEP DOCK (MATCHING IMAGE 2)
           ================================================================== -->
      <?php
      $stepsConfig = [
          1 => [
              'title' => 'Team & Accounts',
              'icon'  => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>'
          ],
          2 => [
              'title' => 'The Idea',
              'icon'  => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="9" y1="18" x2="15" y2="18"></line><line x1="10" y1="22" x2="14" y2="22"></line><path d="M15.09 14c.18-.98.65-1.74 1.41-2.5A4.65 4.65 0 0 0 18 8 6 6 0 0 0 6 8c0 1 .23 2.23 1.5 3.5A4.61 4.61 0 0 1 8.91 14"></path></svg>'
          ],
          3 => [
              'title' => 'Readiness & TRL',
              'icon'  => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 20V10"></path><path d="M12 20V4"></path><path d="M6 20v-6"></path></svg>'
          ],
          4 => [
              'title' => 'PoC Milestones',
              'icon'  => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"></polygon><line x1="8" y1="2" x2="8" y2="18"></line><line x1="16" y1="6" x2="16" y2="22"></line></svg>'
          ],
          5 => [
              'title' => 'Budget (₹1L)',
              'icon'  => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>'
          ],
          6 => [
              'title' => 'Market & Impact',
              'icon'  => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><circle cx="12" cy="12" r="6"></circle><circle cx="12" cy="12" r="2"></circle></svg>'
          ],
          7 => [
              'title' => 'Declaration',
              'icon'  => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><polyline points="9 12 11 14 15 10"></polyline></svg>'
          ],
      ];
      ?>
      <nav class="step-dock-container" aria-label="Registration Steps">
        <div class="step-dock-pill">
          <?php foreach ($stepsConfig as $stepNum => $cfg): 
            $isActive    = ($currentStep === $stepNum);
            $isCompleted = ($currentStep > $stepNum && $existingTeam);
            $itemClass   = 'step-dock-item' . ($isActive ? ' step-dock-active' : ($isCompleted ? ' step-dock-completed' : ' step-dock-pending'));
            $linkUrl     = ($isCompleted) ? "register.php?step={$stepNum}&team_id=" . (int)$existingTeam['id'] : null;
          ?>
            <?php if ($linkUrl): ?>
              <a href="<?= htmlspecialchars($linkUrl) ?>" class="<?= $itemClass ?>" title="Review <?= htmlspecialchars($cfg['title']) ?>">
                <span class="step-dock-icon-wrap">
                  <?= $cfg['icon'] ?>
                  <span class="step-dock-check-badge">✓</span>
                </span>
                <span class="step-dock-label"><?= htmlspecialchars($cfg['title']) ?></span>
              </a>
            <?php else: ?>
              <div class="<?= $itemClass ?>">
                <span class="step-dock-icon-wrap">
                  <?= $cfg['icon'] ?>
                  <?php if ($isCompleted): ?>
                    <span class="step-dock-check-badge">✓</span>
                  <?php endif; ?>
                </span>
                <span class="step-dock-label"><?= htmlspecialchars($cfg['title']) ?></span>
              </div>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </nav>


      <!-- ==================================================================
           STEP 7: DECLARATION & ONLINE MENTOR APPROVAL VIEW
           ================================================================== -->
      <?php if ($currentStep === 7): ?>

        <?php if (!$existingTeam): ?>
          <div class="glass-panel" style="text-align: center; padding: 3.5rem 2rem;">
            <h2 style="font-family: var(--font-heading); font-size: 1.5rem; color: var(--text-forest); margin-bottom: 0.75rem;">
              Step 1 Registration Required
            </h2>
            <p style="color: var(--text-body); max-width: 540px; margin: 0 auto 1.5rem;">
              To submit final team declarations and request mentor consent, please register your team in Step 1 first.
            </p>
            <a href="register.php?step=1" class="btn-save-step" style="text-decoration: none; display: inline-flex;">
              Begin Step 1: Team &amp; Accounts
            </a>
          </div>
        <?php else: ?>

          <!-- Team Summary Context Bar -->
          <div class="team-context-banner">
            <div class="team-context-left">
              <span>Team: <strong><?= htmlspecialchars($existingTeam['team_name']) ?></strong></span>
              <?php if ($leadMember): ?>
                <span style="font-size: 0.9rem; color: var(--text-muted);">(Lead: <?= htmlspecialchars($leadMember['full_name']) ?>)</span>
              <?php endif; ?>
            </div>
            <div class="team-context-right">
              <span class="app-ref-badge">App Ref: <?= htmlspecialchars($existingTeam['application_no']) ?></span>
              <a href="register.php?step=6&team_id=<?= (int)$existingTeam['id'] ?>" style="font-size: 0.85rem; color: var(--clover-green); font-weight: 600; text-decoration: none;">
                Review Step 6 Market &amp; Impact
              </a>
            </div>
          </div>

          <?php if ($allMentorsApproved): ?>
            <!-- CELEBRATION HERO BANNER WHEN ALL MENTORS HAVE APPROVED -->
            <div class="mentor-approval-complete-card" style="margin-bottom: 2rem;">
              <div style="display: flex; align-items: center; gap: 1.15rem;">
                <div style="width: 52px; height: 52px; border-radius: 50%; background: #16a34a; color: #ffffff; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 800; flex-shrink: 0; box-shadow: 0 6px 16px rgba(22, 163, 74, 0.45);">
                  <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
                </div>
                <div>
                  <h3 style="font-family: var(--font-heading); font-size: 1.3rem; color: var(--text-forest); margin: 0 0 0.35rem 0; font-weight: 800;">
                    All Mentors Approved! Official Endorsement Received
                  </h3>
                  <p style="margin: 0; color: var(--text-body); font-size: 0.95rem; font-weight: 500; line-height: 1.45;">
                    Both your Faculty Mentor and Industry Mentor have approved and digitally signed your proposal. Click <strong>Finish Registration</strong> to finalize your application and download your official report!
                  </p>
                </div>
              </div>
              <a href="registration_success.php?team_id=<?= (int)$existingTeam['id'] ?>" class="btn-finish-registration">
                <span>Finish Registration</span>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
              </a>
            </div>
          <?php endif; ?>

          <!-- Step 7 Alert Box -->
          <div id="declarationAlertBox" style="display: none; margin-bottom: 1.5rem;"></div>

          <!-- Form: Step 7 Declaration -->
          <form id="declarationForm" novalidate>
            <input type="hidden" name="team_id" value="<?= (int)$existingTeam['id'] ?>">

            <!-- Card 1: Mandatory Regulatory Declarations (5 Checkboxes) -->
            <div class="glass-panel" style="margin-bottom: 2rem;">
              <div class="section-heading-row">
                <h2 class="section-title">
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                  Mandatory Team Lead Declarations <span class="req">*</span>
                </h2>
                <span class="section-hint">All 5 statements required under challenge regulations</span>
              </div>

              <div style="margin-top: 1.25rem;">
                <!-- Checkbox 1 -->
                <label class="nature-checkbox-wrap">
                  <input type="checkbox" name="lead_declared_originality" id="chkOriginality" value="1" 
                         class="nature-green-checkbox"
                         <?= ($existingProposal && !empty($existingProposal['lead_declared_originality'])) ? 'checked' : '' ?> required>
                  <span class="consent-text" style="font-size: 0.95rem; color: var(--text-forest); line-height: 1.5;">
                    <strong>Originality &amp; Anti-Plagiarism:</strong> The idea is original. Plagiarism or copied work leads to disqualification and, after sanction, recovery of the grant.
                  </span>
                </label>

                <!-- Checkbox 2 -->
                <label class="nature-checkbox-wrap">
                  <input type="checkbox" name="lead_declared_no_funding_overlap" id="chkNoOverlap" value="1"
                         class="nature-green-checkbox"
                         <?= ($existingProposal && !empty($existingProposal['lead_declared_no_funding_overlap'])) ? 'checked' : '' ?> required>
                  <span class="consent-text" style="font-size: 0.95rem; color: var(--text-forest); line-height: 1.5;">
                    <strong>No Duplicate Funding / Overlap Disclosed:</strong> The same PoC has not been funded by another agency, or any overlap is disclosed above.
                  </span>
                </label>

                <!-- Checkbox 3 -->
                <label class="nature-checkbox-wrap">
                  <input type="checkbox" name="lead_declared_grant_agreement" id="chkGrantAgreement" value="1"
                         class="nature-green-checkbox"
                         <?= ($existingProposal && !empty($existingProposal['lead_declared_grant_agreement'])) ? 'checked' : '' ?> required>
                  <span class="consent-text" style="font-size: 0.95rem; color: var(--text-forest); line-height: 1.5;">
                    <strong>Entrepreneurship &amp; DPIIT/Udyam Registration:</strong> If sanctioned, the team will sign a grant agreement that includes an undertaking to pursue entrepreneurship and register under DPIIT and the Udyam Portal.
                  </span>
                </label>

                <!-- Checkbox 4 -->
                <label class="nature-checkbox-wrap">
                  <input type="checkbox" name="lead_declared_ip_policy" id="chkIpPolicy" value="1"
                         class="nature-green-checkbox"
                         <?= ($existingProposal && !empty($existingProposal['lead_declared_ip_policy'])) ? 'checked' : '' ?> required>
                  <span class="consent-text" style="font-size: 0.95rem; color: var(--text-forest); line-height: 1.5;">
                    <strong>Intellectual Property Rights (IPR):</strong> IP created under the grant is filed in the names of the mentor, student inventors and PSG iTech, as per the institute's IPR policy.
                  </span>
                </label>

                <!-- Checkbox 5 -->
                <label class="nature-checkbox-wrap">
                  <input type="checkbox" name="lead_declared_mentor_guidance" id="chkMentorGuidance" value="1"
                         class="nature-green-checkbox"
                         <?= ($existingProposal && !empty($existingProposal['lead_declared_mentor_guidance'])) ? 'checked' : '' ?> required>
                  <span class="consent-text" style="font-size: 0.95rem; color: var(--text-forest); line-height: 1.5;">
                    <strong>Mentor Commitment:</strong> The faculty mentor and the industry mentor agree to guide the team.
                  </span>
                </label>
              </div>
            </div>

            <!-- Card 2: Team Lead Binding Electronic Signature -->
            <div class="glass-panel" style="margin-bottom: 2rem;">
              <div class="section-heading-row">
                <h2 class="section-title">
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                  Team Lead E-Signature Confirmation <span class="req">*</span>
                </h2>

                <!-- Corner Information 'i' Icon with Signature Guidelines Pop-up -->
                <div class="info-tooltip-wrap tooltip-corner">
                  <button type="button" class="info-icon-btn" aria-label="Signature Format Guidelines" title="View Guidelines">
                    i
                  </button>
                  <div class="info-tooltip-pop" role="tooltip">
                    <div class="tooltip-title">
                      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                      Signature Format Guidelines
                    </div>
                    <div class="tooltip-body-text">
                      <div class="tooltip-section">
                        <span class="tooltip-subheading">Format to Enter:</span>
                        <p style="font-family: var(--font-mono-tech); font-weight: 700; color: var(--clover-green); font-size: 0.95rem; margin: 0.35rem 0;">Team_Lead_Name DD/MM/YYYY</p>
                        <p>Type your full legal name followed by date in DD/MM/YYYY format.</p>
                      </div>
                      <div class="tooltip-section">
                        <span class="tooltip-subheading">Example:</span>
                        <p style="margin: 0;"><code><?= htmlspecialchars($leadMember['full_name'] ?? 'Karthik Raja') ?> <?= date('d/m/Y') ?></code></p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="form-group" style="margin-top: 1rem;">
                <label class="form-label">
                  Team Lead Electronic Signature (Name &amp; Date) <span class="req">*</span>
                </label>
                <input type="text" name="lead_signature_name" id="leadSignatureInput" class="nature-input"
                       placeholder="Team_Lead_Name DD/MM/YYYY"
                       value="<?= htmlspecialchars($existingProposal['lead_signature_name'] ?? '') ?>"
                       autocomplete="off" required>
                <span id="leadSignatureDescription" class="field-help-text" style="display: block; margin-top: 0.4rem; font-size: 0.82rem; color: var(--text-muted); transition: opacity 0.2s ease;">
                  Type your signature in format: <strong>Team_Lead_Name DD/MM/YYYY</strong> (e.g. <?= htmlspecialchars($leadMember['full_name'] ?? 'Karthik Raja') ?> <?= date('d/m/Y') ?>). This description text vanishes as soon as you begin typing.
                </span>
              </div>
            </div>

            <!-- FORM ACTIONS BAR -->
            <div class="form-actions-bar" style="margin-bottom: 2.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
              <a href="register.php?step=6&team_id=<?= (int)$existingTeam['id'] ?>" class="btn-back-home">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"></polyline></svg>
                Back to Step 6: Market &amp; Impact
              </a>

              <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                <button type="submit" id="declarationSubmitBtn" class="btn-save-step" style="padding: 0.85rem 1.8rem; background: linear-gradient(135deg, #15803d 0%, #16a34a 100%);">
                  <span><?= !empty($existingProposal['lead_signature_name']) ? 'Update Declaration &amp; Dispatch' : 'Submit Declaration &amp; Dispatch Mentor Links' ?></span>
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </button>

                <?php if ($allMentorsApproved): ?>
                  <a href="registration_success.php?team_id=<?= (int)$existingTeam['id'] ?>" id="btnFinishRegistrationMain" class="btn-finish-registration">
                    <span>Finish Registration</span>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
                  </a>
                <?php else: ?>
                  <button type="button" disabled title="The Finish Registration button will activate once both Faculty and Industry mentors approve" style="opacity: 0.6; cursor: not-allowed; background: #64748b; color: #ffffff; padding: 0.85rem 1.8rem; border-radius: 9999px; font-weight: 700; border: none; font-size: 1rem; display: inline-flex; align-items: center; gap: 0.5rem;">
                    <span>Finish Registration (Mentors Pending)</span>
                  </button>
                <?php endif; ?>
              </div>
            </div>
          </form>

          <!-- Card 3: Live Online Mentor Endorsement Tracking Dashboard -->
          <div class="glass-panel" style="margin-bottom: 2.5rem; padding: 2rem 2.25rem;">
            <div class="section-heading-row" style="margin-bottom: 1.25rem;">
              <div>
                <h2 class="section-title">
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><polyline points="16 11 18 13 22 9"></polyline></svg>
                  Online Mentor Consent Status Dashboard
                </h2>
                <span class="section-hint">Mandatory: Faculty Mentor + Industry Mentor endorsement required for full sanction</span>
              </div>
            </div>

            <?php if (empty($existingMentors)): ?>
              <p style="color: var(--text-muted); font-size: 0.95rem;">No mentors found for this team. Please review Step 1 Team &amp; Mentors.</p>
            <?php else: ?>
              <div style="display: flex; flex-direction: column; gap: 1rem;">
                <?php foreach ($existingMentors as $m): 
                  $mTypeLabel = match($m['mentor_type']) {
                      'faculty' => 'Faculty Mentor (PSG iTech) · Mandatory',
                      'additional_faculty' => 'Additional Faculty Mentor · Optional',
                      'industry' => 'Industry / Expert Mentor · Mandatory',
                      default => 'Project Mentor'
                  };
                  $token = $m['approval_token'] ?? '';
                  $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
                  $approvalUrl = !empty($token) ? "{$baseUrl}/mentor_approval.php?token={$token}" : '';
                  $status = $m['approval_status'] ?? 'Pending';
                ?>
                  <div class="mentor-dashboard-item" style="background: var(--surface-strong); border: 1px solid var(--line-strong); border-radius: 12px; padding: 1.25rem 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                    <div>
                      <div style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.35rem;">
                        <strong style="font-size: 1.05rem; color: var(--text-forest);"><?= htmlspecialchars($m['name']) ?></strong>
                        <span style="font-size: 0.75rem; background: var(--surface); border: 1px solid var(--line); color: var(--text-muted); padding: 0.2rem 0.55rem; border-radius: 12px; font-weight: 600;">
                          <?= htmlspecialchars($mTypeLabel) ?>
                        </span>
                      </div>
                      <div style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.45;">
                        <?= htmlspecialchars($m['designation'] ?? 'Mentor') ?> · <?= htmlspecialchars($m['organisation'] ?? $m['department'] ?? 'PSG iTech') ?><br>
                        Email: <strong><?= htmlspecialchars($m['email']) ?></strong> · Tel: <?= htmlspecialchars($m['phone']) ?>
                        <?php if ($status === 'Approved' && !empty($m['approved_at'])): ?>
                          <div style="color: #22c55e; font-size: 0.8rem; margin-top: 0.25rem; font-weight: 600;">
                            ✓ Endorsed on <?= htmlspecialchars($m['approved_at']) ?> by <?= htmlspecialchars($m['mentor_signature'] ?? $m['name']) ?>
                          </div>
                        <?php elseif ($status === 'Declined' && !empty($m['decline_reason'])): ?>
                          <div style="color: #ef4444; font-size: 0.8rem; margin-top: 0.25rem;">
                            ✕ Declined: <?= htmlspecialchars($m['decline_reason']) ?>
                          </div>
                        <?php endif; ?>
                      </div>
                    </div>

                    <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                      <!-- Status Pill -->
                      <?php if ($status === 'Approved'): ?>
                        <span style="background: rgba(34, 197, 94, 0.16); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.4); padding: 0.4rem 0.85rem; border-radius: 20px; font-size: 0.82rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.4rem;">
                          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
                          Approved
                        </span>
                      <?php elseif ($status === 'Declined'): ?>
                        <span style="background: rgba(239, 68, 68, 0.16); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.4); padding: 0.4rem 0.85rem; border-radius: 20px; font-size: 0.82rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.4rem;">
                          ✕ Declined
                        </span>
                      <?php else: ?>
                        <span style="background: rgba(245, 158, 11, 0.16); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.4); padding: 0.4rem 0.85rem; border-radius: 20px; font-size: 0.82rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.4rem;">
                          <span style="width: 8px; height: 8px; border-radius: 50%; background: #f59e0b; display: inline-block;"></span>
                          Pending Endorsement
                        </span>
                      <?php endif; ?>

                      <!-- Action Tools -->
                      <?php if (!empty($approvalUrl)): ?>
                        <button type="button" class="btn-copy-mentor-link" data-link="<?= htmlspecialchars($approvalUrl) ?>"
                                style="background: rgba(255, 255, 255, 0.06); border: 1px solid rgba(255, 255, 255, 0.15); color: var(--text-primary); padding: 0.45rem 0.85rem; border-radius: 6px; font-size: 0.82rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 0.35rem;">
                          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                          Copy Link
                        </button>
                      <?php endif; ?>

                      <button type="button" class="btn-resend-mentor" 
                              data-mentor-id="<?= (int)$m['id'] ?>"
                              data-team-id="<?= (int)$existingTeam['id'] ?>"
                              data-mentor-name="<?= htmlspecialchars($m['name']) ?>"
                              data-mentor-email="<?= htmlspecialchars($m['email']) ?>"
                              style="background: rgba(184, 237, 69, 0.12); border: 1px solid rgba(184, 237, 69, 0.35); color: var(--neon-lime, #b8ed45); padding: 0.45rem 0.85rem; border-radius: 6px; font-size: 0.82rem; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 0.35rem;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
                        Resend Link
                      </button>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>

              <?php if ($allMentorsApproved): ?>
                <!-- ENDORSEMENT SANCTIONED CALL TO ACTION -->
                <div class="mentor-approval-complete-card">
                  <div style="display: flex; align-items: center; gap: 1rem;">
                    <div style="width: 44px; height: 44px; border-radius: 50%; background: #16a34a; color: #ffffff; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; font-weight: 800; flex-shrink: 0; box-shadow: 0 4px 12px rgba(22, 163, 74, 0.35);">
                      ✓
                    </div>
                    <div>
                      <h4 style="font-family: var(--font-heading); font-size: 1.15rem; color: var(--text-forest); margin: 0 0 0.25rem 0; font-weight: 700;">
                        Official Mentorship Endorsements Completed
                      </h4>
                      <p style="margin: 0; color: var(--text-body); font-size: 0.9rem;">
                        Both your Faculty Mentor and Industry Mentor have completed their online endorsement. Your application is fully ready for official sanction!
                      </p>
                    </div>
                  </div>
                  <a href="registration_success.php?team_id=<?= (int)$existingTeam['id'] ?>" id="btnFinishRegistrationDashboard" class="btn-finish-registration" style="padding: 0.9rem 2.2rem; font-size: 1.05rem;">
                    <span>Finish Registration</span>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
                  </a>
                </div>
              <?php else: ?>
                <div class="mentor-approval-pending-card">
                  <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2.2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    <div>
                      <strong style="color: #f59e0b; font-size: 0.95rem;">Pending Mentor Sign-Off</strong>
                      <div style="color: var(--text-body); font-size: 0.85rem;">The "Finish Registration" button will become active as soon as both Faculty and Industry mentors approve via their email links.</div>
                    </div>
                  </div>
                  <button type="button" disabled style="opacity: 0.55; cursor: not-allowed; background: #94a3b8; color: #ffffff; padding: 0.75rem 1.5rem; border-radius: 9999px; font-weight: 700; border: none; font-size: 0.92rem;">
                    Finish Registration (Locked)
                  </button>
                </div>
              <?php endif; ?>

            <?php endif; ?>
          </div>

        <?php endif; ?>

      <!-- ==================================================================
           STEP 6: MARKET POTENTIAL & SOCIETAL IMPACT VIEW
           ================================================================== -->
      <?php elseif ($currentStep === 6): ?>

        <?php if (!$existingTeam): ?>
          <div class="glass-panel" style="text-align: center; padding: 3.5rem 2rem;">
            <h2 style="font-family: var(--font-heading); font-size: 1.5rem; color: var(--text-forest); margin-bottom: 0.75rem;">
              Step 1 Registration Required
            </h2>
            <p style="color: var(--text-body); max-width: 540px; margin: 0 auto 1.5rem;">
              To submit Market &amp; Impact details, please register your team in Step 1 first.
            </p>
            <a href="register.php?step=1" class="btn-save-step" style="text-decoration: none; display: inline-flex;">
              Begin Step 1: Team &amp; Accounts
            </a>
          </div>
        <?php else: ?>

          <!-- Team Summary Context Bar -->
          <div class="team-context-banner">
            <div class="team-context-left">
              <span>Team: <strong><?= htmlspecialchars($existingTeam['team_name']) ?></strong></span>
              <?php if ($leadMember): ?>
                <span style="font-size: 0.9rem; color: var(--text-muted);">(Lead: <?= htmlspecialchars($leadMember['full_name']) ?>)</span>
              <?php endif; ?>
            </div>
            <div class="team-context-right">
              <span class="app-ref-badge">App Ref: <?= htmlspecialchars($existingTeam['application_no']) ?></span>
              <a href="register.php?step=5&team_id=<?= (int)$existingTeam['id'] ?>" style="font-size: 0.85rem; color: var(--clover-green); font-weight: 600; text-decoration: none;">
                Review Step 5 Budget
              </a>
            </div>
          </div>

          <!-- Step 6 Alert Box -->
          <div id="marketAlertBox" style="display: none; margin-bottom: 1.5rem;"></div>

          <!-- Form: Market & Impact -->
          <form id="marketForm" novalidate>
            <input type="hidden" name="team_id" value="<?= (int)$existingTeam['id'] ?>">

            <!-- 1. Target Users or Customers -->
            <div class="glass-panel" style="margin-bottom: 2rem;">
              <div class="section-heading-row">
                <h2 class="section-title">
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                  Target Users or Customers <span class="req">*</span>
                </h2>
                <!-- Corner Information 'i' Icon -->
                <div class="info-tooltip-wrap tooltip-corner">
                  <button type="button" class="info-icon-btn" aria-label="Target Users Guidelines" title="View Guidelines">i</button>
                  <div class="info-tooltip-pop" role="tooltip">
                    <div class="tooltip-title">
                      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                      Target Users Guidelines
                    </div>
                    <div class="tooltip-body-text">
                      <div class="tooltip-section">
                        <span class="tooltip-subheading">Who will use or buy it?</span>
                        <p>Identify the specific end users, customers, or industries who will directly benefit from and purchase your solution.</p>
                      </div>
                      <div class="tooltip-section">
                        <span class="tooltip-subheading">Examples:</span>
                        <ul class="tooltip-guidelines-list">
                          <li>Small-scale textile manufacturers in Tirupur cluster</li>
                          <li>Municipal water treatment plant operators</li>
                          <li>Mid-size agri-producers or cold-chain logistics firms</li>
                        </ul>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="form-group" style="margin-top: 1rem;">
                <textarea id="targetUsersInput" name="target_users" class="nature-textarea" rows="4"
                  placeholder="e.g. Small-scale textile manufacturers in Tirupur cluster, municipal water treatment plant operators, mid-size agri-producers..."><?= $existingProposal ? htmlspecialchars($existingProposal['target_users'] ?? '') : '' ?></textarea>
                <span class="field-help-text" style="display: block; margin-top: 0.4rem; font-size: 0.8rem; color: var(--text-muted);">
                  Describe the specific industries, roles, or communities who will directly use or purchase your solution.
                </span>
              </div>
            </div>

            <!-- 2. Size of the Need -->
            <div class="glass-panel" style="margin-bottom: 2rem;">
              <div class="section-heading-row">
                <h2 class="section-title">
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="12" y1="20" x2="12" y2="10"></line><line x1="18" y1="20" x2="18" y2="4"></line><line x1="6" y1="20" x2="6" y2="16"></line></svg>
                  Size of the Need <span class="req">*</span>
                </h2>
                <!-- Corner Information 'i' Icon -->
                <div class="info-tooltip-wrap tooltip-corner">
                  <button type="button" class="info-icon-btn" aria-label="Market Size Guidelines" title="View Guidelines">i</button>
                  <div class="info-tooltip-pop" role="tooltip">
                    <div class="tooltip-title">
                      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                      Market Size Guidelines
                    </div>
                    <div class="tooltip-body-text">
                      <div class="tooltip-section">
                        <span class="tooltip-subheading">How many people or businesses, how often, cost today?</span>
                        <p>Quantify the scale of the problem: total addressable users, frequency of occurrence, and what they currently spend on inadequate solutions.</p>
                      </div>
                      <div class="tooltip-section">
                        <span class="tooltip-subheading">Examples:</span>
                        <ul class="tooltip-guidelines-list">
                          <li>2.5 lakh factories in India, daily water usage, Rs 500/kL current cost</li>
                          <li>35 million farmers, twice per season, Rs 8,000/acre in current labour</li>
                        </ul>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="form-group" style="margin-top: 1rem;">
                <textarea id="marketNeedInput" name="market_need_size" class="nature-textarea" rows="4"
                  placeholder="e.g. 2.5 lakh small factories in Tirupur alone, daily wastewater discharge, current treatment cost Rs 500 per kL — representing a Rs 450 Cr annual market in Tamil Nadu alone..."><?= $existingProposal ? htmlspecialchars($existingProposal['market_need_size'] ?? '') : '' ?></textarea>
                <span class="field-help-text" style="display: block; margin-top: 0.4rem; font-size: 0.8rem; color: var(--text-muted);">
                  Provide quantitative estimates of the market scale, frequency of the problem, and current cost to end users.
                </span>
              </div>
            </div>

            <!-- 3. Route to Commercialisation -->
            <div class="glass-panel" style="margin-bottom: 2rem;">
              <div class="section-heading-row">
                <h2 class="section-title">
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 8 16 12 12 16"></polyline><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                  Route to Commercialisation <span class="req">*</span>
                </h2>
                <!-- Corner Information 'i' Icon -->
                <div class="info-tooltip-wrap tooltip-corner">
                  <button type="button" class="info-icon-btn" aria-label="Commercialisation Guidelines" title="View Guidelines">i</button>
                  <div class="info-tooltip-pop" role="tooltip">
                    <div class="tooltip-title">
                      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                      Commercialisation Guidelines
                    </div>
                    <div class="tooltip-body-text">
                      <div class="tooltip-section">
                        <span class="tooltip-subheading">PoC to product or startup?</span>
                        <p>Explain the path from your grant-funded prototype to a scalable product, licensed technology, or registered startup.</p>
                      </div>
                      <div class="tooltip-section">
                        <span class="tooltip-subheading">Examples:</span>
                        <ul class="tooltip-guidelines-list">
                          <li>License sensor module to water treatment OEMs after PoC validation</li>
                          <li>Spin-off DPIIT-registered startup and seek Seed funding in Year 2</li>
                          <li>Partner with MSME cluster for pilot deployment &amp; revenue sharing</li>
                        </ul>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="form-group" style="margin-top: 1rem;">
                <textarea id="commercialisationInput" name="commercialisation_route" class="nature-textarea" rows="4"
                  placeholder="e.g. License the sensor module to water treatment OEMs post-PoC validation; simultaneously register a DPIIT startup and seek Seed funding in Year 2..."><?= $existingProposal ? htmlspecialchars($existingProposal['commercialisation_route'] ?? '') : '' ?></textarea>
                <span class="field-help-text" style="display: block; margin-top: 0.4rem; font-size: 0.8rem; color: var(--text-muted);">
                  Outline how the PoC transitions into a scalable product, licensed IP, or venture-backed startup.
                </span>
              </div>
            </div>

            <!-- 4. Impact -->
            <div class="glass-panel" style="margin-bottom: 2rem;">
              <div class="section-heading-row">
                <h2 class="section-title">
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                  Impact <span class="req">*</span>
                </h2>
                <!-- Corner Information 'i' Icon -->
                <div class="info-tooltip-wrap tooltip-corner">
                  <button type="button" class="info-icon-btn" aria-label="Impact Guidelines" title="View Guidelines">i</button>
                  <div class="info-tooltip-pop" role="tooltip">
                    <div class="tooltip-title">
                      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                      Impact Guidelines
                    </div>
                    <div class="tooltip-body-text">
                      <div class="tooltip-section">
                        <span class="tooltip-subheading">Benefit to industry or society, and how it will be measured?</span>
                        <p>Describe the tangible positive outcomes for industries or communities, and the specific metrics you will use to measure success post-deployment.</p>
                      </div>
                      <div class="tooltip-section">
                        <span class="tooltip-subheading">Examples:</span>
                        <ul class="tooltip-guidelines-list">
                          <li>30% reduction in water wastage, measured via post-deployment consumption audits</li>
                          <li>Farmer income increase of Rs 2,000/acre, tracked via partner cooperative data</li>
                          <li>35% drop in factory downtime, measured via SCADA log analysis</li>
                        </ul>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="form-group" style="margin-top: 1rem;">
                <textarea id="societalImpactInput" name="societal_impact" class="nature-textarea" rows="4"
                  placeholder="e.g. 30% reduction in industrial wastewater discharge, measured via post-deployment consumption audits at 10 pilot factories; Rs 1.2 Cr savings to cluster annually..."><?= $existingProposal ? htmlspecialchars($existingProposal['societal_impact'] ?? '') : '' ?></textarea>
                <span class="field-help-text" style="display: block; margin-top: 0.4rem; font-size: 0.8rem; color: var(--text-muted);">
                  State quantifiable benefits to industry or society and the measurement method used to verify them.
                </span>
              </div>
            </div>

            <!-- FORM ACTIONS BAR -->
            <div class="form-actions-bar">
              <a href="register.php?step=5&team_id=<?= (int)$existingTeam['id'] ?>" class="btn-back-home">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"></polyline></svg>
                Back to Step 5: Budget
              </a>
              <button type="submit" id="marketSubmitBtn" class="btn-save-step">
                <span>Save &amp; Continue to Step 7</span>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
              </button>
            </div>
          </form>

        <?php endif; ?>

      <!-- ==================================================================
           STEP 5: BUDGET & ITEMIZED FINANCIAL PLAN VIEW
           ================================================================== -->
      <?php elseif ($currentStep === 5): ?>

        <?php if (!$existingTeam): ?>
          <div class="glass-panel" style="text-align: center; padding: 3.5rem 2rem;">
            <h2 style="font-family: var(--font-heading); font-size: 1.5rem; color: var(--text-forest); margin-bottom: 0.75rem;">
              Step 1 Registration Required
            </h2>
            <p style="color: var(--text-body); max-width: 540px; margin: 0 auto 1.5rem;">
              To submit your Itemized Budget, please register your team in Step 1 first.
            </p>
            <a href="register.php?step=1" class="btn btn-primary" style="display: inline-flex; text-decoration: none;">
              Go to Step 1: Team & Accounts
            </a>
          </div>
        <?php else: ?>

          <form id="budgetForm" novalidate>
            <input type="hidden" id="teamIdInput" name="team_id" value="<?= (int)$existingTeam['id'] ?>">

            <!-- Dynamic Notification / Error Banner -->
            <div id="budgetAlertBox" style="display: none; margin-bottom: 1.5rem;"></div>

            <!-- Rubric Ribbon -->
            <div class="step-rubric-ribbon">
              <div class="rubric-tag">
                <span>₹1,00,000 Cap</span>
                Maximum Grant Allocation per Team
              </div>
              <div class="rubric-timeline">
                Financial Allocation: <strong>Direct Component & Prototype Development</strong>
              </div>
            </div>

            <!-- Disallowed Expenditures Policy Notice -->
            <div class="budget-disallowed-box">
              <div class="budget-disallowed-header">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line></svg>
                Strictly Disallowed Grant Expenditures
              </div>
              <ul class="budget-disallowed-list">
                <li>Personal laptops, tablets, or smartphones</li>
                <li>Team stipends, wages, or honorariums</li>
                <li>Food, refreshments, or entertainment</li>
                <li>Purchases without a tax invoice in PSG iTech / Launchpad's name</li>
              </ul>
            </div>

            <!-- Itemized Budget Table Card -->
            <div class="glass-panel" style="margin-bottom: 2rem; padding: 2.25rem 2.5rem; overflow: visible;">
              <div class="section-heading-row">
                <div>
                  <h2 class="section-title" style="margin-bottom: 0.25rem;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="2" y="4" width="20" height="16" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>
                    Itemized Development Expenditure <span class="req">*</span>
                  </h2>
                  <span class="section-hint">Provide detailed component pricing, quantities, and justifications</span>
                </div>
              </div>

              <div class="budget-table-wrap">
                <table class="budget-table">
                  <thead>
                    <tr>
                      <th class="col-num">#</th>
                      <th class="col-item">Item Description <span class="req">*</span></th>
                      <th class="col-cat">Category <span class="req">*</span></th>
                      <th class="col-qty">Quantity <span class="req">*</span></th>
                      <th class="col-unit-cost">Unit Cost (₹) <span class="req">*</span></th>
                      <th class="col-amount">Amount (₹)</th>
                      <th class="col-purpose">Purpose of the Item <span class="req">*</span></th>
                      <th class="col-action">Action</th>
                    </tr>
                  </thead>
                  <tbody id="budgetTableBody">
                    <?php if (!empty($existingBudgetItems)): ?>
                      <?php foreach ($existingBudgetItems as $bIdx => $bItem): 
                        $bQty = (int)($bItem['quantity'] ?? 1);
                        $bUnitCost = (float)($bItem['unit_cost'] ?? 0);
                        $bAmount = (float)($bItem['amount'] ?? ($bQty * $bUnitCost));
                        $isHighCost = ($bUnitCost > 15000 || $bAmount > 15000);
                      ?>
                        <tr class="budget-row">
                          <td class="col-num">
                            <span class="row-num"><?= $bIdx + 1 ?></span>
                          </td>
                          <td class="col-item">
                            <input type="text" class="nature-input budget-item-name" 
                                   placeholder="e.g. ESP32-S3 Microcontroller Board"
                                   value="<?= htmlspecialchars($bItem['item_name'] ?? '') ?>" required>
                            <span class="high-cost-warning" style="<?= $isHighCost ? 'display: inline-flex;' : 'display: none;' ?>">
                              Item > ₹15,000: Needs coordinator approval
                            </span>
                          </td>
                          <td class="col-cat">
                            <select class="nature-select budget-item-category" required>
                              <option value="">Select Category...</option>
                              <?php
                              $cats = [
                                'Components, sensors, boards',
                                'Raw material',
                                'Fabrication and testing',
                                'Software licences, cloud credits, datasets',
                                'Field trials, user surveys, limited local travel',
                                'Patent search and filing (with IPR cell approval)'
                              ];
                              foreach ($cats as $cat):
                              ?>
                                <option value="<?= htmlspecialchars($cat) ?>" <?= ($bItem['category'] ?? '') === $cat ? 'selected' : '' ?>>
                                  <?= htmlspecialchars($cat) ?>
                                </option>
                              <?php endforeach; ?>
                            </select>
                          </td>
                          <td class="col-qty">
                            <input type="number" class="nature-input budget-item-qty" 
                                   min="1" step="1" value="<?= $bQty ?>" required>
                          </td>
                          <td class="col-unit-cost">
                            <div class="currency-input-wrap">
                              <span class="currency-prefix">₹</span>
                              <input type="number" class="nature-input budget-item-unit-cost" 
                                     min="0" step="0.01" placeholder="0.00" 
                                     value="<?= $bUnitCost > 0 ? htmlspecialchars($bUnitCost) : '' ?>" required>
                            </div>
                          </td>
                          <td class="col-amount">
                            <input type="text" class="nature-input budget-item-amount" 
                                   readonly value="₹<?= number_format($bAmount, 2) ?>" 
                                   data-raw-amount="<?= number_format($bAmount, 2, '.', '') ?>">
                          </td>
                          <td class="col-purpose">
                            <input type="text" class="nature-input budget-item-purpose" 
                                   placeholder="e.g. Primary compute and RF node"
                                   value="<?= htmlspecialchars($bItem['purpose'] ?? '') ?>" required>
                          </td>
                          <td class="col-action">
                            <button type="button" class="btn-remove-row" aria-label="Remove item" title="Remove item" <?= count($existingBudgetItems) <= 1 ? 'disabled' : '' ?>>
                              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                            </button>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>

              <!-- Toolbar & Live Totals -->
              <div class="budget-table-toolbar">
                <button type="button" id="addBudgetRowBtn" class="btn-add-item">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                  Add Budget Item
                </button>

                <div class="budget-total-panel">
                  <div class="budget-total-meta">
                    <span class="budget-total-label">Total Requested Grant</span>
                    <span id="grandTotalDisplay" class="budget-grand-total">₹0.00</span>
                  </div>
                  <div id="ceilingStatusDisplay" class="budget-status-pill status-ok">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    ₹1,00,000.00 remaining of ₹1,00,000 ceiling
                  </div>
                </div>
              </div>
            </div>

            <!-- FORM ACTIONS BAR -->
            <div class="form-actions-bar">
              <a href="register.php?step=4&team_id=<?= (int)$existingTeam['id'] ?>" class="btn-back-home">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"></polyline></svg>
                Back to Step 4: Milestones
              </a>

              <button type="submit" id="budgetSubmitBtn" class="btn-save-step">
                <span>Save & Continue to Step 6</span>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
              </button>
            </div>
          </form>

        <?php endif; ?>

      <!-- ==================================================================
           STEP 4: POC PLAN & 6-MONTH MILESTONES VIEW
           ================================================================== -->
      <?php elseif ($currentStep === 4): ?>

        <?php if (!$existingTeam): ?>
          <div class="glass-panel" style="text-align: center; padding: 3.5rem 2rem;">
            <h2 style="font-family: var(--font-heading); font-size: 1.5rem; color: var(--text-forest); margin-bottom: 0.75rem;">
              Step 1 Registration Required
            </h2>
            <p style="color: var(--text-body); max-width: 540px; margin: 0 auto 1.5rem;">
              To submit your PoC Plan and 6-Month Milestones, please register your team in Step 1 first.
            </p>
            <a href="register.php?step=1" class="btn btn-primary" style="display: inline-flex; text-decoration: none;">
              Go to Step 1: Team & Accounts
            </a>
          </div>
        <?php else: ?>

          <form id="pocPlanForm" novalidate>
            <input type="hidden" id="teamIdInput" name="team_id" value="<?= (int)$existingTeam['id'] ?>">

            <!-- Dynamic Notification / Error Banner -->
            <div id="pocAlertBox" style="display: none; margin-bottom: 1.5rem;"></div>

            <!-- Rubric Ribbon -->
            <div class="step-rubric-ribbon">
              <div class="rubric-tag">
                <span>10 Marks</span>
                Technical Feasibility & PoC Plan Rubric
              </div>
              <div class="rubric-timeline">
                Challenge Build Window: <strong>January 1 to June 30, 2027</strong>
              </div>
            </div>

            <!-- Deliverables -->
            <div class="glass-panel" style="margin-bottom: 2rem;">
              <div class="section-heading-row">
                <h2 class="section-title">
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                  Deliverables <span class="req">*</span>
                </h2>
                
                <!-- Corner Information 'i' Icon with Hover Guidelines Pop-up -->
                <div class="info-tooltip-wrap tooltip-corner">
                  <button type="button" class="info-icon-btn" aria-label="Deliverables Guidelines" title="View Guidelines">
                    i
                  </button>
                  <div class="info-tooltip-pop" role="tooltip">
                    <div class="tooltip-title">
                      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                      Deliverables Guidelines
                    </div>
                    <div class="tooltip-body-text">
                      <div class="tooltip-section">
                        <span class="tooltip-subheading">What exists at the end of the grant?</span>
                        <p>Specify the tangible physical or digital assets delivered at the conclusion of the grant.</p>
                      </div>
                      <div class="tooltip-section">
                        <span class="tooltip-subheading">Expected Deliverable Assets:</span>
                        <ul class="tooltip-guidelines-list">
                          <li>Working benchtop / breadboard / PCB prototype</li>
                          <li>Embedded firmware source code or deployed software</li>
                          <li>Sensor calibration data and characterization testbench</li>
                          <li>Comprehensive user manual and demonstration video</li>
                        </ul>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="form-group" style="margin-top: 1rem;">
                <textarea id="deliverablesInput" name="deliverables" class="nature-textarea" rows="4"
                          placeholder="Describe what tangible physical or digital assets will exist at the end of the 6-month grant (e.g. fully assembled hardware prototype with custom enclosure, calibrated sensor suite, functional web dashboard, test reports)..."><?= $existingProposal ? htmlspecialchars($existingProposal['deliverables'] ?? '') : '' ?></textarea>
                <span class="field-help-text" style="display: block; margin-top: 0.4rem; font-size: 0.8rem; color: var(--text-muted);">
                  Clearly detail all tangible hardware mockups, software components, testbenches, and technical reports delivered by Month 6.
                </span>
              </div>
            </div>

            <!-- Test Method -->
            <div class="glass-panel" style="margin-bottom: 2rem;">
              <div class="section-heading-row">
                <h2 class="section-title">
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>
                  Test Method <span class="req">*</span>
                </h2>
                <span class="section-hint">Validation protocols and experimental testbench setup</span>
              </div>

              <div class="form-group" style="margin-top: 1rem;">
                <textarea id="testMethodInput" name="test_method" class="nature-textarea" rows="4"
                          placeholder="Explain how your PoC will be tested and validated against operational requirements (e.g. laboratory test bench, simulated load scenarios, thermal stress testing, field pilot with user feedback, benchmark dataset comparisons)..."><?= $existingProposal ? htmlspecialchars($existingProposal['test_method'] ?? '') : '' ?></textarea>
                <span class="field-help-text" style="display: block; margin-top: 0.4rem; font-size: 0.8rem; color: var(--text-muted);">
                  Specify test apparatus, measurement equipment, operational test conditions, and benchmarking methods.
                </span>
              </div>
            </div>

            <!-- Success Rate / Success Measure -->
            <div class="glass-panel" style="margin-bottom: 2rem;">
              <div class="section-heading-row">
                <h2 class="section-title">
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                  Success Rate / Measure <span class="req">*</span>
                </h2>
                
                <!-- Corner Information 'i' Icon with Hover Guidelines Pop-up -->
                <div class="info-tooltip-wrap tooltip-corner">
                  <button type="button" class="info-icon-btn" aria-label="Success Rate Guidelines" title="View Guidelines">
                    i
                  </button>
                  <div class="info-tooltip-pop" role="tooltip">
                    <div class="tooltip-title">
                      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                      Success Measure Guidelines
                    </div>
                    <div class="tooltip-body-text">
                      <div class="tooltip-section">
                        <span class="tooltip-subheading">How the project may become success:</span>
                        <p>Provide specific numbers and measurable metrics that prove your Proof-of-Concept works effectively.</p>
                      </div>
                      <div class="tooltip-section">
                        <span class="tooltip-subheading">Examples of Measurable Metrics:</span>
                        <ul class="tooltip-guidelines-list">
                          <li>Accuracy: &ge;95% classification precision</li>
                          <li>Latency: &lt;100ms real-time inference time</li>
                          <li>Efficiency: 35% reduction in power / cycle time</li>
                          <li>Durability: MTBF &gt; 500 operating hours</li>
                        </ul>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="form-group" style="margin-top: 1rem;">
                <textarea id="successRateInput" name="success_rate" class="nature-textarea" rows="4"
                          placeholder="Provide verifiable numbers and acceptance criteria that demonstrate success (e.g. 96% recognition rate under low-light conditions, response latency below 80ms, 45% reduction in water consumption, 0.05% error margin)..."><?= $existingProposal ? htmlspecialchars($existingProposal['success_rate'] ?? '') : '' ?></textarea>
                <span class="field-help-text" style="display: block; margin-top: 0.4rem; font-size: 0.8rem; color: var(--text-muted);">
                  Include quantitative metrics that will be evaluated during the Month 3 Milestone Review and Month 6 Demo Day.
                </span>
              </div>
            </div>

            <!-- 6-Month Milestone Implementation Roadmap Table -->
            <div class="glass-panel milestones-table-card" style="margin-bottom: 2.5rem;">
              <div class="section-heading-row">
                <h2 class="section-title">
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                  6-Month Milestone Implementation Roadmap <span class="req">*</span>
                </h2>
                <span class="section-hint">Mandatory monthly progression from Jan 1 to Jun 30, 2027</span>
              </div>

              <div class="milestones-table-wrap">
                <table class="milestones-table">
                  <thead>
                    <tr>
                      <th class="col-month">Timeline / Month</th>
                      <th class="col-milestone">Milestone Objective <span class="req">*</span></th>
                      <th class="col-output">Tangible Output / Deliverable <span class="req">*</span></th>
                      <th class="col-verify">How it is Verified <span class="req">*</span></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                    $monthsConfig = [
                        1 => [
                            'name' => 'Month 1',
                            'dates' => 'January 2027',
                            'badge' => null,
                            'row_class' => '',
                            'ph_desc' => 'e.g. System architecture finalization, component procurement, CAD modeling',
                            'ph_out' => 'e.g. Detailed Bill of Materials (BOM), preliminary 3D CAD schematic',
                            'ph_ver' => 'e.g. Mentor design sign-off, component delivery receipts'
                        ],
                        2 => [
                            'name' => 'Month 2',
                            'dates' => 'February 2027',
                            'badge' => null,
                            'row_class' => '',
                            'ph_desc' => 'e.g. Subsystem breadboard prototyping, sensor interfacing, API definition',
                            'ph_out' => 'e.g. Breadboard test circuit, initial firmware code repository',
                            'ph_ver' => 'e.g. Oscilloscope waveform traces, serial communication logs'
                        ],
                        3 => [
                            'name' => 'Month 3',
                            'dates' => 'March 2027',
                            'badge' => 'Milestone 1 Review (30% Tranche)',
                            'badge_type' => 'review',
                            'row_class' => 'tr-highlight-review',
                            'ph_desc' => 'e.g. Subsystem integration, PCB fabrication, preliminary laboratory testing',
                            'ph_out' => 'e.g. Fabricated PCB v1.0, benchmark data log, Progress Report 1',
                            'ph_ver' => 'e.g. Faculty mentor evaluation committee demonstration, progress certification'
                        ],
                        4 => [
                            'name' => 'Month 4',
                            'dates' => 'April 2027',
                            'badge' => null,
                            'row_class' => '',
                            'ph_desc' => 'e.g. Enclosure 3D printing, full system wiring, algorithm optimization',
                            'ph_out' => 'e.g. Integrated hardware unit with casing, optimized processing pipeline',
                            'ph_ver' => 'e.g. End-to-end telemetry verification, power consumption audit'
                        ],
                        5 => [
                            'name' => 'Month 5',
                            'dates' => 'May 2027',
                            'badge' => null,
                            'row_class' => '',
                            'ph_desc' => 'e.g. Environmental stress testing, pilot user trials, bug fixes',
                            'ph_out' => 'e.g. User survey feedback dataset, stress test characterization report',
                            'ph_ver' => 'e.g. Pilot deployment demonstration video, error log analysis'
                        ],
                        6 => [
                            'name' => 'Month 6',
                            'dates' => 'June 2027',
                            'badge' => 'Demo Day & Project Closure (20% Tranche)',
                            'badge_type' => 'demo',
                            'row_class' => 'tr-highlight-demo',
                            'ph_desc' => 'e.g. Final product packaging, exhibition presentation prep, patent drafting',
                            'ph_out' => 'e.g. Production-ready PoC demonstration, final project closure dossier',
                            'ph_ver' => 'e.g. Live jury evaluation at Demo Day, patent provisional search filing'
                        ]
                    ];

                    for ($m = 1; $m <= 6; $m++):
                        $cfg = $monthsConfig[$m];
                        $mRow = $existingMilestones[$m] ?? null;
                    ?>
                      <tr class="<?= $cfg['row_class'] ?>">
                        <td class="col-month">
                          <div class="month-meta-block">
                            <span class="month-badge-primary"><?= htmlspecialchars($cfg['name']) ?></span>
                            <span class="month-date-sub"><?= htmlspecialchars($cfg['dates']) ?></span>
                            <?php if ($cfg['badge']): ?>
                              <span class="milestone-event-badge <?= $cfg['badge_type'] === 'review' ? 'milestone-event-review' : 'milestone-event-demo' ?>">
                                <?= htmlspecialchars($cfg['badge']) ?>
                              </span>
                            <?php endif; ?>
                          </div>
                        </td>
                        <td class="col-milestone">
                          <textarea id="milestone_desc_<?= $m ?>" name="milestones[<?= $m ?>][milestone_desc]"
                                    class="milestone-cell-textarea" rows="3"
                                    placeholder="<?= htmlspecialchars($cfg['ph_desc']) ?>"><?= $mRow ? htmlspecialchars($mRow['milestone_desc'] ?? '') : '' ?></textarea>
                        </td>
                        <td class="col-output">
                          <textarea id="milestone_output_<?= $m ?>" name="milestones[<?= $m ?>][output_deliverable]"
                                    class="milestone-cell-textarea" rows="3"
                                    placeholder="<?= htmlspecialchars($cfg['ph_out']) ?>"><?= $mRow ? htmlspecialchars($mRow['output_deliverable'] ?? '') : '' ?></textarea>
                        </td>
                        <td class="col-verify">
                          <textarea id="milestone_verify_<?= $m ?>" name="milestones[<?= $m ?>][verification_method]"
                                    class="milestone-cell-textarea" rows="3"
                                    placeholder="<?= htmlspecialchars($cfg['ph_ver']) ?>"><?= $mRow ? htmlspecialchars($mRow['verification_method'] ?? '') : '' ?></textarea>
                        </td>
                      </tr>
                    <?php endfor; ?>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- FORM ACTIONS BAR -->
            <div class="form-actions-bar">
              <a href="register.php?step=3&team_id=<?= (int)$existingTeam['id'] ?>" class="btn-back-home">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"></polyline></svg>
                Back to Step 3: Readiness
              </a>

              <button type="submit" id="pocSubmitBtn" class="btn-save-step">
                <span>Save & Continue to Step 5</span>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
              </button>
            </div>
          </form>

        <?php endif; ?>

      <!-- ==================================================================
           STEP 3: READINESS & TRL VIEW
           ================================================================== -->
      <?php elseif ($currentStep === 3): ?>

        <?php if (!$existingTeam): ?>
          <div class="glass-panel" style="text-align: center; padding: 3.5rem 2rem;">
            <h2 style="font-family: var(--font-heading); font-size: 1.5rem; color: var(--text-forest); margin-bottom: 0.75rem;">
              Step 1 Registration Required
            </h2>
            <p style="color: var(--text-body); max-width: 540px; margin: 0 auto 1.5rem;">
              To submit project readiness and TRL assessments, please register your team in Step 1 first.
            </p>
            <a href="register.php?step=1" class="btn-save-step" style="text-decoration: none; display: inline-flex;">
              Begin Step 1: Team & Accounts ➔
            </a>
          </div>
        <?php else: ?>

          <!-- Team Summary Context Bar -->
          <div class="team-context-banner">
            <div class="team-context-left">
              <span>Team: <strong><?= htmlspecialchars($existingTeam['team_name']) ?></strong></span>
              <?php if ($leadMember): ?>
                <span style="font-size: 0.9rem; color: var(--text-muted);">(Lead: <?= htmlspecialchars($leadMember['full_name']) ?>)</span>
              <?php endif; ?>
            </div>
            <div class="team-context-right">
              <span class="app-ref-badge">App Ref: <?= htmlspecialchars($existingTeam['application_no']) ?></span>
              <a href="register.php?step=2&team_id=<?= (int)$existingTeam['id'] ?>" style="font-size: 0.85rem; color: var(--clover-green); font-weight: 600; text-decoration: none;">
                Review Step 2 Idea
              </a>
            </div>
          </div>

          <!-- Step 3 Form Alert Box -->
          <div id="readinessAlertBox" class="form-alert-banner" style="display: none;"></div>

          <!-- Form: Readiness -->
          <form id="readinessRegistrationForm" novalidate>
            <input type="hidden" name="team_id" value="<?= (int)$existingTeam['id'] ?>">

            <!-- Current Technology Readiness Level (TRL) -->
            <div class="glass-panel" style="margin-bottom: 2rem;">
              <div class="section-heading-row">
                <h2 class="section-title">
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M18 20V10"></path><path d="M12 20V4"></path><path d="M6 20v-6"></path></svg>
                  Current Technology Readiness Level (TRL)
                </h2>
                <span class="section-hint">Select the current development maturity of your project</span>
              </div>

              <div class="form-group">
                <label class="form-label" for="trlLevelSelect">
                  Current Technology Readiness Level (TRL) <span class="req">*</span>
                </label>
                <select id="trlLevelSelect" name="trl_level" class="nature-input" required>
                  <option value="" disabled <?= empty($existingProposal['trl_level']) ? 'selected' : '' ?>>Select Current TRL (1 to 9)</option>
                  <option value="1" <?= (isset($existingProposal['trl_level']) && $existingProposal['trl_level'] == 1) ? 'selected' : '' ?>>Level 1 - Basic Principles Observed and Reported</option>
                  <option value="2" <?= (isset($existingProposal['trl_level']) && $existingProposal['trl_level'] == 2) ? 'selected' : '' ?>>Level 2 - Technology Concept & Application Formulated (Preferred Application Starting Point)</option>
                  <option value="3" <?= (isset($existingProposal['trl_level']) && $existingProposal['trl_level'] == 3) ? 'selected' : '' ?>>Level 3 - Analytical & Experimental Critical Function / Proof of Concept Validated</option>
                  <option value="4" <?= (isset($existingProposal['trl_level']) && $existingProposal['trl_level'] == 4) ? 'selected' : '' ?>>Level 4 - Component / Breadboard Validation in Laboratory Environment (Preferred Starting Point)</option>
                  <option value="5" <?= (isset($existingProposal['trl_level']) && $existingProposal['trl_level'] == 5) ? 'selected' : '' ?>>Level 5 - Component / Subsystem Validation in Relevant Environment (Target Grant Outcome)</option>
                  <option value="6" <?= (isset($existingProposal['trl_level']) && $existingProposal['trl_level'] == 6) ? 'selected' : '' ?>>Level 6 - System / Subsystem Prototype Demonstration in Relevant Environment</option>
                  <option value="7" <?= (isset($existingProposal['trl_level']) && $existingProposal['trl_level'] == 7) ? 'selected' : '' ?>>Level 7 - System Prototype Demonstration in Operational Environment</option>
                  <option value="8" <?= (isset($existingProposal['trl_level']) && $existingProposal['trl_level'] == 8) ? 'selected' : '' ?>>Level 8 - Actual System Completed and Qualified through Test and Demonstration</option>
                  <option value="9" <?= (isset($existingProposal['trl_level']) && $existingProposal['trl_level'] == 9) ? 'selected' : '' ?>>Level 9 - Actual System Proven in Operational / Production Environment</option>
                </select>
                <span class="field-hint" style="margin-top: 0.5rem; display: block;">
                  TRL 2 to 4 is preferred at application stage; target TRL 5 or above after completion of the seed grant.
                </span>
              </div>
            </div>

            <!-- Work Done So Far -->
            <div class="glass-panel" style="margin-bottom: 2rem;">
              <div class="section-heading-row">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                  <h2 class="section-title" style="margin-bottom: 0;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    Work Done So Far
                  </h2>
                  
                  <!-- Corner Information 'i' Icon with Hover Guidelines Pop-up -->
                  <div class="info-tooltip-wrap">
                    <button type="button" class="info-icon-btn" aria-label="Work Done So Far Guidelines" title="View Guidelines">
                      i
                    </button>
                    <div class="info-tooltip-pop" role="tooltip">
                      <div class="tooltip-title">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                        Guidelines to Include
                      </div>
                      <ul class="tooltip-guidelines-list">
                        <li>Simulations (computational models, circuit or FEM simulations)</li>
                        <li>Prototypes (early breadboards, CAD models, mockups)</li>
                        <li>Surveys (target user interviews, feedback gathered)</li>
                        <li>Literature (published papers, patent search, benchmarks)</li>
                      </ul>
                    </div>
                  </div>
                </div>

                <span class="section-hint">Summarize pre-grant foundational work</span>
              </div>

              <div class="form-group">
                <textarea id="workDoneInput" name="work_done_so_far" class="nature-textarea"
                          placeholder="Describe the simulations, prototypes, surveys, or literature reviewed and conducted so far by your team to prove technical viability..."
                          rows="6" required><?= $existingProposal ? htmlspecialchars($existingProposal['work_done_so_far'] ?? '') : '' ?></textarea>
                <span class="field-hint" style="margin-top: 0.5rem; display: block;">
                  Explain preliminary simulations, physical mockups, academic publications, or user surveys completed to date.
                </span>
              </div>
            </div>

            <!-- Prior Prize or Funding for this Idea -->
            <div class="glass-panel" style="margin-bottom: 2rem;">
              <div class="section-heading-row">
                <h2 class="section-title">
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="8" r="7"></circle><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline></svg>
                  Prior Prize or Funding for this Idea <span class="req">*</span>
                </h2>
                <span class="section-hint">Disclose whether this proposal has received external financial awards</span>
              </div>

              <div class="funding-radio-group">
                <label class="radio-option-card <?= ($existingProposal && ($existingProposal['prior_funding_has'] === 'No' || empty($existingProposal['prior_funding_has']))) ? 'radio-card-active' : '' ?>">
                  <input type="radio" name="prior_funding_has" value="No" <?= ($existingProposal && $existingProposal['prior_funding_has'] === 'Yes') ? '' : 'checked' ?>>
                  <div class="radio-card-content">
                    <strong>No</strong>
                    <span>This idea has not received any prior prize, grant, or financial funding.</span>
                  </div>
                </label>

                <label class="radio-option-card <?= ($existingProposal && $existingProposal['prior_funding_has'] === 'Yes') ? 'radio-card-active' : '' ?>">
                  <input type="radio" name="prior_funding_has" value="Yes" <?= ($existingProposal && $existingProposal['prior_funding_has'] === 'Yes') ? 'checked' : '' ?>>
                  <div class="radio-card-content">
                    <strong>Yes</strong>
                    <span>This idea has received a prior competition prize, seed grant, or external award.</span>
                  </div>
                </label>
              </div>

              <!-- Expandable Funding Details Block (Shown ONLY if 'Yes' is selected) -->
              <div id="fundingDetailsBlock" class="expandable-funding-block" style="<?= ($existingProposal && $existingProposal['prior_funding_has'] === 'Yes') ? 'display: block;' : 'display: none;' ?>">
                <div class="funding-notice-bar">
                  <strong>Full Disclosure Requirement:</strong> Receiving prior awards does not disqualify your team; however, any overlapping work or budget must be clearly disclosed and approved by the evaluation committee.
                </div>

                <div class="slot-grid-inputs" style="margin-top: 1.25rem;">
                  <div class="form-group">
                    <label class="form-label" for="fundingAgencyInput">Funding Agency / Competition Name <span class="req">*</span></label>
                    <input type="text" id="fundingAgencyInput" name="prior_funding_agency" class="nature-input"
                           placeholder="e.g. TN Student Innovator Award, SIH, DST NIDHI PRAYAS, TechFest"
                           value="<?= $existingProposal ? htmlspecialchars($existingProposal['prior_funding_agency'] ?? '') : '' ?>">
                  </div>

                  <div class="form-group">
                    <label class="form-label" for="fundingAmountInput">Amount Received (in ₹) <span class="req">*</span></label>
                    <input type="text" id="fundingAmountInput" name="prior_funding_amount" class="nature-input"
                           placeholder="e.g. 25,000"
                           value="<?= $existingProposal ? htmlspecialchars($existingProposal['prior_funding_amount'] ?? '') : '' ?>">
                  </div>
                </div>

                <div class="form-group" style="margin-top: 1rem;">
                  <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.4rem;">
                    <label class="form-label" for="fundingOverlapInput" style="margin-bottom: 0;">
                      Scope Overlap Details <span class="req">*</span>
                    </label>
                    
                    <!-- Information 'i' Icon explaining Overlap -->
                    <div class="info-tooltip-wrap">
                      <button type="button" class="info-icon-btn" aria-label="What is Overlap?" title="Learn about Overlap">
                        i
                      </button>
                      <div class="info-tooltip-pop" role="tooltip">
                        <div class="tooltip-title">
                          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                          Overlap & Grant Guidelines
                        </div>
                        <div class="tooltip-body-text">
                          <div class="tooltip-section">
                            <span class="tooltip-subheading">What is Overlap?</span>
                            <p>Refers to whether prior prizes or funding paid for any of the exact same activities, materials, hardware, or milestones proposed under this challenge.</p>
                          </div>
                          <div class="tooltip-section">
                            <span class="tooltip-subheading">What does this do?</span>
                            <p>Disclosing overlap ensures that the ₹1,00,000 iTech Seed Grant funds distinct, novel developmental milestones without duplicate claims.</p>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <textarea id="fundingOverlapInput" name="prior_funding_overlap" class="nature-textarea" rows="4"
                            placeholder="Clarify what portion of the project was funded by the prior grant, and specifically what new, distinct work or PoC phase will be funded by the iTech Seed Grand Challenge 2027..."><?= $existingProposal ? htmlspecialchars($existingProposal['prior_funding_overlap'] ?? '') : '' ?></textarea>
                </div>
              </div>
            </div>

            <!-- IP Status -->
            <div class="glass-panel" style="margin-bottom: 2.5rem;">
              <div class="section-heading-row">
                <h2 class="section-title">
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                  Intellectual Property (IP) Status <span class="req">*</span>
                </h2>
                <span class="section-hint">Select the current patent and IP standing of your innovation</span>
              </div>

              <div class="form-group" style="max-width: 680px;">
                <label class="form-label" for="ipStatusSelect">IP Status Classification <span class="req">*</span></label>
                <select id="ipStatusSelect" name="ip_status" class="nature-input" required>
                  <option value="" disabled <?= empty($existingProposal['ip_status']) ? 'selected' : '' ?>>Select IP Status</option>
                  <option value="None" <?= ($existingProposal && $existingProposal['ip_status'] === 'None') ? 'selected' : '' ?>>None (No patent searches or filings initiated)</option>
                  <option value="Prior or third-party IP" <?= ($existingProposal && $existingProposal['ip_status'] === 'Prior or third-party IP') ? 'selected' : '' ?>>Prior or third-party IP (Utilizes existing or third-party IP/patents)</option>
                  <option value="Patent search done" <?= ($existingProposal && $existingProposal['ip_status'] === 'Patent search done') ? 'selected' : '' ?>>Patent search done (Prior art search conducted across patent databases)</option>
                  <option value="Filing done" <?= ($existingProposal && $existingProposal['ip_status'] === 'Filing done') ? 'selected' : '' ?>>Filing done (Provisional or complete patent application filed)</option>
                </select>
                <span class="field-hint" style="margin-top: 0.5rem; display: block;">
                  PSG iTech Launchpad facilitates free IPR support and filing assistance for teams completing the challenge.
                </span>
              </div>
            </div>

            <!-- FORM ACTIONS BAR -->
            <div class="form-actions-bar">
              <a href="register.php?step=2&team_id=<?= (int)$existingTeam['id'] ?>" class="btn-back-home">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"></polyline></svg>
                Back to Step 2: The Idea
              </a>

              <button type="submit" id="saveReadinessBtn" class="btn-save-step">
                <span>Save & Continue to Step 4</span>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
              </button>
            </div>

          </form>

        <?php endif; ?>

      <!-- ==================================================================
           STEP 2: THE IDEA VIEW
           ================================================================== -->
      <?php elseif ($currentStep === 2): ?>

        <?php if (!$existingTeam): ?>
          <div class="glass-panel" style="text-align: center; padding: 3.5rem 2rem;">
            <h2 style="font-family: var(--font-heading); font-size: 1.5rem; color: var(--text-forest); margin-bottom: 0.75rem;">
              Step 1 Registration Required
            </h2>
            <p style="color: var(--text-body); max-width: 540px; margin: 0 auto 1.5rem;">
              To submit The Idea details, please initialize your team and member roster in Step 1 first.
            </p>
            <a href="register.php?step=1" class="btn-save-step" style="text-decoration: none; display: inline-flex;">
              Begin Step 1: Team & Accounts ➔
            </a>
          </div>
        <?php else: ?>

          <!-- Team Summary Context Bar -->
          <div class="team-context-banner">
            <div class="team-context-left">
              <span>Team: <strong><?= htmlspecialchars($existingTeam['team_name']) ?></strong></span>
              <?php if ($leadMember): ?>
                <span style="font-size: 0.9rem; color: var(--text-muted);">(Lead: <?= htmlspecialchars($leadMember['full_name']) ?>)</span>
              <?php endif; ?>
            </div>
            <div class="team-context-right">
              <span class="app-ref-badge">App Ref: <?= htmlspecialchars($existingTeam['application_no']) ?></span>
              <a href="register.php?step=1&team_id=<?= (int)$existingTeam['id'] ?>" style="font-size: 0.85rem; color: var(--clover-green); font-weight: 600; text-decoration: none;">
                Edit Team Info
              </a>
            </div>
          </div>

          <!-- Step 2 Form Alert Box -->
          <div id="ideaAlertBox" class="form-alert-banner" style="display: none;"></div>

          <!-- Form: The Idea -->
          <form id="ideaRegistrationForm" novalidate>
            <input type="hidden" name="team_id" value="<?= (int)$existingTeam['id'] ?>">

            <!-- Project Title -->
            <div class="glass-panel" style="margin-bottom: 2rem;">
              <div class="section-heading-row">
                <h2 class="section-title">
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                  Project Title
                </h2>
                <span class="section-hint">Concise, descriptive name of your PoC</span>
              </div>

              <div class="form-group">
                <label class="form-label" for="projectTitleInput">
                  Project Title <span class="req">*</span>
                </label>
                <input type="text" id="projectTitleInput" name="project_title" class="nature-input"
                       placeholder="e.g. Smart IoT Soil Nutrient Analyzer for High-Yield Precision Farming"
                       value="<?= $existingProposal ? htmlspecialchars($existingProposal['project_title']) : '' ?>"
                       required>
              </div>
            </div>

            <!-- Problem Statement -->
            <div class="glass-panel" style="margin-bottom: 2rem;">
              <div class="section-heading-row">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                  <h2 class="section-title" style="margin-bottom: 0;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    Problem Statement
                  </h2>
                  
                  <!-- Corner Information 'i' Icon with Hover Guidelines Pop-up -->
                  <div class="info-tooltip-wrap">
                    <button type="button" class="info-icon-btn" aria-label="Problem Statement Guidelines" title="View Guidelines">
                      i
                    </button>
                    <div class="info-tooltip-pop" role="tooltip">
                      <div class="tooltip-title">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                        Guidelines to Include
                      </div>
                      <ul class="tooltip-guidelines-list">
                        <li>Who faces this problem</li>
                        <li>Where it occurs (geography/industry)</li>
                        <li>How often it occurs</li>
                        <li>Why solving it matters</li>
                      </ul>
                    </div>
                  </div>
                </div>

                <span class="section-hint">150 to 250 characters (excluding spaces)</span>
              </div>

              <div class="form-group">
                <textarea id="problemStatementInput" name="problem_statement" class="nature-textarea"
                          placeholder="150 to 250 characters. Describe the specific industrial or societal challenge: who experiences it, where, how often it impacts users, and why solving it matters..."
                          rows="6" required><?= $existingProposal ? htmlspecialchars($existingProposal['problem_statement']) : '' ?></textarea>
                
                <div class="textarea-footer-row">
                  <span id="problemCharError" class="char-min-error">Minimum 150 characters required</span>
                </div>
              </div>
            </div>

            <!-- Existing Solutions and the Gap -->
            <div class="glass-panel" style="margin-bottom: 2rem;">
              <div class="section-heading-row">
                <h2 class="section-title">
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M18 20V10"></path><path d="M12 20V4"></path><path d="M6 20v-6"></path></svg>
                  Existing Solutions & The Gap
                </h2>
                <span class="section-hint">100 to 200 characters (excluding spaces)</span>
              </div>

              <div class="form-group">
                <textarea id="existingSolutionsInput" name="existing_solutions" class="nature-textarea"
                          placeholder="100 to 200 characters. Explain current existing methods, products, or academic approaches, and clearly specify the limitations, inefficiencies, or technical gap that your project addresses..."
                          rows="5" required><?= $existingProposal ? htmlspecialchars($existingProposal['existing_solutions']) : '' ?></textarea>
                
                <div class="textarea-footer-row">
                  <span id="existingCharError" class="char-min-error">Minimum 100 characters required</span>
                </div>
              </div>
            </div>

            <!-- Proposed Solution -->
            <div class="glass-panel" style="margin-bottom: 2rem;">
              <div class="section-heading-row">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                  <h2 class="section-title" style="margin-bottom: 0;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                    Proposed Solution
                  </h2>
                  
                  <!-- Corner Information 'i' Icon with Hover Guidelines Pop-up -->
                  <div class="info-tooltip-wrap">
                    <button type="button" class="info-icon-btn" aria-label="Proposed Solution Guidelines" title="View Guidelines">
                      i
                    </button>
                    <div class="info-tooltip-pop" role="tooltip">
                      <div class="tooltip-title">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                        Guidelines to Include
                      </div>
                      <ul class="tooltip-guidelines-list">
                        <li>What you build</li>
                        <li>How it works</li>
                        <li>What is new</li>
                      </ul>
                    </div>
                  </div>
                </div>

                <span class="section-hint">200 to 300 characters (excluding spaces)</span>
              </div>

              <div class="form-group">
                <textarea id="proposedSolutionInput" name="proposed_solution" class="nature-textarea"
                          placeholder="200 to 300 characters. Explain what you build, how it works, what is new, and how it directly resolves the identified gap..."
                          rows="7" required><?= $existingProposal ? htmlspecialchars($existingProposal['proposed_solution']) : '' ?></textarea>
                
                <div class="textarea-footer-row">
                  <span id="proposedCharError" class="char-min-error">Minimum 200 characters required</span>
                </div>
              </div>
            </div>

            <!-- Type of PoC -->
            <div class="glass-panel" style="margin-bottom: 2.5rem;">
              <div class="section-heading-row">
                <h2 class="section-title">
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                  Type of Proof-of-Concept (PoC) <span class="req">*</span>
                </h2>
                <span class="section-hint">Select the primary technical classification of your deliverable</span>
              </div>

              <?php 
                $selectedPoc = $existingProposal ? $existingProposal['poc_type'] : '';
              ?>
              <div class="poc-radio-grid">
                <!-- Hardware -->
                <label class="poc-radio-card <?= $selectedPoc === 'Hardware' ? 'poc-card-active' : '' ?>">
                  <input type="radio" name="poc_type" value="Hardware" <?= $selectedPoc === 'Hardware' ? 'checked' : '' ?> required>
                  <div class="poc-card-top">
                    <span class="poc-card-title">Hardware</span>
                    <span class="poc-radio-indicator"></span>
                  </div>
                  <p class="poc-card-desc">
                    Physical device, embedded electronic circuitry, sensors, mechanical mechanism, or physical fabrication prototype.
                  </p>
                </label>

                <!-- Software -->
                <label class="poc-radio-card <?= $selectedPoc === 'Software' ? 'poc-card-active' : '' ?>">
                  <input type="radio" name="poc_type" value="Software" <?= $selectedPoc === 'Software' ? 'checked' : '' ?> required>
                  <div class="poc-card-top">
                    <span class="poc-card-title">Software</span>
                    <span class="poc-radio-indicator"></span>
                  </div>
                  <p class="poc-card-desc">
                    Functional web application, mobile app, AI/ML predictive models, algorithms, cloud backend, or data system.
                  </p>
                </label>

                <!-- Both Hardware & Software -->
                <label class="poc-radio-card <?= $selectedPoc === 'Both' ? 'poc-card-active' : '' ?>">
                  <input type="radio" name="poc_type" value="Both" <?= $selectedPoc === 'Both' ? 'checked' : '' ?> required>
                  <div class="poc-card-top">
                    <span class="poc-card-title">Both Hardware & Software</span>
                    <span class="poc-radio-indicator"></span>
                  </div>
                  <p class="poc-card-desc">
                    Integrated cyber-physical system, IoT edge hardware communicating with web dashboard, mobile interface, or ML engine.
                  </p>
                </label>
              </div>
            </div>

            <!-- FORM ACTIONS BAR -->
            <div class="form-actions-bar">
              <a href="register.php?step=1&team_id=<?= (int)$existingTeam['id'] ?>" class="btn-back-home">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"></polyline></svg>
                Back to Step 1: Team & Accounts
              </a>

              <button type="submit" id="saveIdeaBtn" class="btn-save-step">
                <span>Save & Continue to Step 3</span>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
              </button>
            </div>

          </form>

        <?php endif; ?>

      <!-- ==================================================================
           STEP 1: TEAM & ACCOUNTS VIEW
           ================================================================== -->
      <?php else: ?>

        <!-- LIVE INTERDISCIPLINARY & TEAM SIZE BAR -->
        <div class="diversity-status-wrapper">
          <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
            <div id="interdisciplinaryStatusBadge" class="diversity-pill diversity-pending">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
              <span><strong>Mandatory Rule:</strong> Interdisciplinary team (min 2 departments)</span>
            </div>
          </div>
          <div class="team-size-counter-badge" id="memberCountBadge">
            2 / 4 Members
          </div>
        </div>

        <!-- FORM ERROR ALERT BOX -->
        <div id="formAlertBox" class="form-alert-banner" style="display: none;"></div>

        <?php if (!empty($_SESSION['team_id']) && empty($_SESSION['is_lead'])): ?>
          <div style="background: rgba(59, 130, 246, 0.12); border: 1px solid rgba(59, 130, 246, 0.35); color: #1d4ed8; padding: 1rem 1.25rem; border-radius: 12px; margin-bottom: 1.5rem; font-size: 0.92rem; display: flex; align-items: flex-start; gap: 0.75rem; line-height: 1.45;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="flex-shrink: 0; margin-top: 1px;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
            <div>
              <strong>Team Member Access:</strong> You are currently signed in as a team member. You can review the team details and proposal, but only the <strong>Team Leader</strong> has authority to modify the team roster or mentors.
            </div>
          </div>
        <?php endif; ?>

        <!-- MAIN REGISTRATION FORM -->
        <form id="teamRegistrationForm" novalidate>
          <input type="hidden" name="team_id" value="<?= $existingTeam ? (int)$existingTeam['id'] : 0 ?>">

          <!-- SECTION 1: TEAM DETAILS -->
          <div class="glass-panel" style="margin-bottom: 2rem;">
            <div class="section-heading-row">
              <h2 class="section-title">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                Team Identity
              </h2>
              <span class="section-hint">Choose an inspiring, unique team name</span>
            </div>

            <div class="form-group" style="max-width: 580px;">
              <label class="form-label" for="teamNameInput">
                Team Name <span class="req">*</span>
              </label>
              <?php
                $displayTeamName = '';
                if ($existingTeam && !empty($existingTeam['team_name'])) {
                    // Do not show auto-generated dummy placeholders like 'Team EB92BC'
                    if (!preg_match('/^Team\s+[A-F0-9]{4,8}$/i', trim($existingTeam['team_name']))) {
                        $displayTeamName = $existingTeam['team_name'];
                    }
                }
              ?>
              <input type="text" id="teamNameInput" name="team_name" class="nature-input ajax-check" 
                     data-field="team_name"
                     placeholder="e.g. AeroGrow Dynamics, BioSense Labs" 
                     value="<?= htmlspecialchars($displayTeamName) ?>" 
                     required>
              <div class="field-feedback"></div>
              <span class="field-hint">Must be unique across all participating teams in the challenge.</span>
            </div>
          </div>

          <!-- SECTION 2: TEAM MEMBERS (SLOT 1 IS TEAM LEAD, 2 TO 4 MEMBERS) -->
          <div class="glass-panel" style="margin-bottom: 2rem;">
            <div class="section-heading-row">
              <h2 class="section-title">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><line x1="19" y1="8" x2="19" y2="14"></line><line x1="22" y1="11" x2="16" y2="11"></line></svg>
                Team Members (2 to 4 Students)
              </h2>
              <span class="section-hint">II, III, or Final year PSG iTech students</span>
            </div>

            <!-- Dynamic Slots Container -->
            <div id="membersContainer" class="member-slots-list">
              <!-- JavaScript dynamically renders Slot 1 (Team Lead) and Slot 2 (Member 2) here on page load -->
            </div>

            <!-- Add Member Button -->
            <div class="add-member-area">
              <button type="button" id="addMemberBtn" class="btn-add-member">
                + Add Team Member (3 of 4)
              </button>
            </div>
          </div>

          <!-- SECTION 3: MENTORS BLOCK -->
          <div class="glass-panel" style="margin-bottom: 2.5rem;">
            <div class="section-heading-row">
              <h2 class="section-title">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg>
                Project Mentors
              </h2>
              <span class="section-hint">Mandatory guidance from internal faculty & external industry leader</span>
            </div>

            <div class="mentors-grid">
              
              <!-- Faculty Mentor (Mandatory) -->
              <div class="mentor-card glass-panel">
                <div class="slot-card-header">
                  <div class="slot-header-left">
                    <h3 class="member-role-title">Faculty Mentor</h3>
                    <span class="mentor-badge mentor-badge-req">Mandatory</span>
                  </div>
                  <span class="section-hint">PSG iTech Faculty who will endorse monthly progress & bill certifications</span>
                </div>

                <div class="slot-grid-inputs">
                  <div class="form-group">
                    <label class="form-label">Faculty Name with Designation <span class="req">*</span></label>
                    <input type="text" name="faculty_name" class="nature-input" placeholder="e.g. Dr. R. Ramesh, Associate Professor" value="<?= htmlspecialchars($facMentor['name'] ?? '') ?>" required>
                  </div>
                  <div class="form-group">
                    <label class="form-label">Department <span class="req">*</span></label>
                    <select name="faculty_department" class="nature-input" required>
                      <option value="" disabled <?= empty($facMentor['department']) ? 'selected' : '' ?>>Select Department</option>
                      <?php
                        $facDepts = [
                          'Artificial Intelligence & Data Science' => 'AI & Data Science (AI&DS)',
                          'Computer Science & Engineering' => 'Computer Science (CSE)',
                          'Electronics & Communication' => 'Electronics & Comm (ECE)',
                          'Electrical & Electronics' => 'Electrical & Electronics (EEE)',
                          'Mechanical Engineering' => 'Mechanical Engineering',
                          'Civil Engineering' => 'Civil Engineering',
                          'Information Technology' => 'Information Technology (IT)',
                          'Science & Humanities' => 'Science & Humanities (S&H)'
                        ];
                        foreach ($facDepts as $fdVal => $fdLabel):
                      ?>
                        <option value="<?= htmlspecialchars($fdVal) ?>" <?= (($facMentor['department'] ?? '') === $fdVal) ? 'selected' : '' ?>>
                          <?= htmlspecialchars($fdLabel) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="form-group">
                    <label class="form-label">Faculty Email (@psgitech.ac.in) <span class="req">*</span></label>
                    <input type="email" name="faculty_email" class="nature-input" placeholder="e.g. faculty.ece@psgitech.ac.in" value="<?= htmlspecialchars($facMentor['email'] ?? '') ?>" required>
                    <span class="field-hint">A secure approval link will be emailed upon final submission.</span>
                  </div>
                  <div class="form-group">
                    <label class="form-label">Mobile Phone Number <span class="req">*</span></label>
                    <input type="tel" name="faculty_phone" class="nature-input" placeholder="10-digit number" maxlength="10" value="<?= htmlspecialchars($facMentor['phone'] ?? '') ?>" required>
                  </div>
                </div>
              </div>

              <!-- Additional Faculty Mentor (Optional) -->
              <div>
                <button type="button" id="toggleAddFacultyBtn" class="btn-toggle-optional <?= !empty($addFacMentor['name']) ? 'btn-toggle-active' : '' ?>">
                  <?= !empty($addFacMentor['name']) ? '− Remove Co-Faculty Mentor' : '+ Add Co-Faculty Mentor (Optional)' ?>
                </button>

                <div id="addFacultyCard" class="mentor-card glass-panel" style="<?= !empty($addFacMentor['name']) ? 'display: block;' : 'display: none;' ?> margin-top: 1rem;">
                  <div class="slot-card-header">
                    <div class="slot-header-left">
                      <h3 class="member-role-title">Additional Faculty Mentor</h3>
                      <span class="mentor-badge mentor-badge-opt">Optional</span>
                    </div>
                    <span class="section-hint">Recommended for interdisciplinary projects</span>
                  </div>

                  <div class="slot-grid-inputs">
                    <div class="form-group">
                      <label class="form-label">Faculty Name & Designation</label>
                      <input type="text" name="add_faculty_name" class="nature-input" placeholder="e.g. Dr. P. Kavitha, Asst. Professor" value="<?= htmlspecialchars($addFacMentor['name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                      <label class="form-label">Department</label>
                      <select name="add_faculty_department" class="nature-input">
                        <option value="" <?= empty($addFacMentor['department']) ? 'selected' : '' ?>>Select Department (Optional)</option>
                        <?php foreach ($facDepts as $fdVal => $fdLabel): ?>
                          <option value="<?= htmlspecialchars($fdVal) ?>" <?= (($addFacMentor['department'] ?? '') === $fdVal) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($fdLabel) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="form-group">
                      <label class="form-label">Faculty Email</label>
                      <input type="email" name="add_faculty_email" class="nature-input" placeholder="faculty@psgitech.ac.in" value="<?= htmlspecialchars($addFacMentor['email'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                      <label class="form-label">Mobile Phone Number</label>
                      <input type="tel" name="add_faculty_phone" class="nature-input" placeholder="10-digit number" maxlength="10" value="<?= htmlspecialchars($addFacMentor['phone'] ?? '') ?>">
                    </div>
                  </div>
                </div>
              </div>

              <!-- Industry / Expert Mentor (Mandatory) -->
              <div class="mentor-card glass-panel">
                <div class="slot-card-header">
                  <div class="slot-header-left">
                    <h3 class="member-role-title">Industry or Expert Mentor</h3>
                    <span class="mentor-badge mentor-badge-req">Mandatory</span>
                  </div>
                  <span class="section-hint">Domain expert from tech/manufacturing industry to validate market fit</span>
                </div>

                <div class="slot-grid-inputs">
                  <div class="form-group">
                    <label class="form-label">Expert Full Name <span class="req">*</span></label>
                    <input type="text" name="industry_name" class="nature-input" placeholder="e.g. Mr. Rajesh Narayanan" value="<?= htmlspecialchars($indMentor['name'] ?? '') ?>" required>
                  </div>
                  <div class="form-group">
                    <label class="form-label">Organisation / Company <span class="req">*</span></label>
                    <input type="text" name="industry_org" class="nature-input" placeholder="e.g. Bosch Global / Larsen & Toubro" value="<?= htmlspecialchars($indMentor['organisation'] ?? '') ?>" required>
                  </div>
                  <div class="form-group">
                    <label class="form-label">Designation / Role <span class="req">*</span></label>
                    <input type="text" name="industry_designation" class="nature-input" placeholder="e.g. Principal Architect / Engineering Lead" value="<?= htmlspecialchars($indMentor['designation'] ?? '') ?>" required>
                  </div>
                  <div class="form-group">
                    <label class="form-label">Official Work Email <span class="req">*</span></label>
                    <input type="email" name="industry_email" class="nature-input" placeholder="e.g. rajesh@company.com" value="<?= htmlspecialchars($indMentor['email'] ?? '') ?>" required>
                    <span class="field-hint">A secure link will be sent to the expert to review and sign off.</span>
                  </div>
                  <div class="form-group">
                    <label class="form-label">Mobile Phone Number <span class="req">*</span></label>
                    <input type="tel" name="industry_phone" class="nature-input" placeholder="10-digit number" maxlength="10" value="<?= htmlspecialchars($indMentor['phone'] ?? '') ?>" required>
                  </div>
                  <div class="form-group">
                    <label class="form-label">LinkedIn Profile URL <span class="section-hint">(Optional)</span></label>
                    <input type="url" name="industry_linkedin" class="nature-input" placeholder="https://linkedin.com/in/username" value="<?= htmlspecialchars($indMentor['linkedin_url'] ?? '') ?>">
                  </div>
                </div>
              </div>

            </div>
          </div>

          <!-- FORM ACTION BAR -->
          <div class="form-actions-bar">
            <a href="submissions.php" class="btn-back-home">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"></polyline></svg>
              View Submissions Directory
            </a>

            <button type="submit" id="saveContinueBtn" class="btn-save-step">
              <span>Save & Continue to Step 2</span>
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </button>
          </div>

        </form>

      <?php endif; ?>

    </main>

    <!-- FOOTER -->
    <footer class="event-footer">
      <div class="footer-bottom">
        <p>© 2026–2027 PSG Institute of Technology and Applied Research · Innozone & Startupzone (Launchpad)</p>
      </div>
    </footer>

  </div>

  <?php if ($currentStep === 7): ?>
    <script src="js/declaration_step.js?v=<?= filemtime(__DIR__ . '/js/declaration_step.js') ?>"></script>
  <?php elseif ($currentStep === 6): ?>
    <script src="js/market_step.js?v=<?= filemtime(__DIR__ . '/js/market_step.js') ?>"></script>
  <?php elseif ($currentStep === 5): ?>
    <script src="js/budget_step.js?v=<?= filemtime(__DIR__ . '/js/budget_step.js') ?>"></script>
  <?php elseif ($currentStep === 4): ?>
    <script src="js/poc_plan_step.js?v=<?= filemtime(__DIR__ . '/js/poc_plan_step.js') ?>"></script>
  <?php elseif ($currentStep === 3): ?>
    <script src="js/readiness_step.js?v=<?= filemtime(__DIR__ . '/js/readiness_step.js') ?>"></script>
  <?php elseif ($currentStep === 2): ?>
    <script src="js/idea_step.js?v=<?= filemtime(__DIR__ . '/js/idea_step.js') ?>"></script>
  <?php else: ?>
    <script>
      window.stagedLeaderEmail = <?= json_encode($stagedLeaderEmail) ?>;
      window.existingMembers = <?= json_encode($existingMembers ?: []) ?>;
      window.isTeamLead = <?= json_encode($isLeader) ?>;
    </script>
    <script src="js/registration.js?v=<?= filemtime(__DIR__ . '/js/registration.js') ?>"></script>
  <?php endif; ?>

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
