<?php
/**
 * iTech Seed Grand Challenge 2027
 * Online Mentor Endorsement & Electronic Signature Portal
 * PSG iTech Launchpad: Innozone & Startupzone
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';

$pdo = getDatabaseConnection();
$token = trim($_GET['token'] ?? '');

$mentor = null;
$team = null;
$proposal = null;
$members = [];
$milestones = [];
$budgetItems = [];
$leadMember = null;
$errorMessage = null;

if (empty($token) || strlen($token) < 32) {
    $errorMessage = "Invalid or missing security token. Please use the secure link provided in your invitation email.";
} elseif ($pdo) {
    try {
        // 1. Fetch mentor by single-use token
        $stmtM = $pdo->prepare("SELECT * FROM `team_mentors` WHERE approval_token = :tok LIMIT 1");
        $stmtM->execute([':tok' => $token]);
        $mentor = $stmtM->fetch(PDO::FETCH_ASSOC);

        if (!$mentor) {
            $errorMessage = "The endorsement link is invalid, expired, or was already replaced. Please contact the student team lead.";
        } else {
            $teamId = (int)$mentor['team_id'];

            // 2. Fetch Team
            $stmtT = $pdo->prepare("SELECT * FROM `teams` WHERE id = :tid LIMIT 1");
            $stmtT->execute([':tid' => $teamId]);
            $team = $stmtT->fetch(PDO::FETCH_ASSOC);

            // 3. Fetch Team Members
            $stmtMem = $pdo->prepare("SELECT * FROM `team_members` WHERE team_id = :tid ORDER BY member_order ASC");
            $stmtMem->execute([':tid' => $teamId]);
            $members = $stmtMem->fetchAll(PDO::FETCH_ASSOC);
            foreach ($members as $m) {
                if ($m['is_lead']) {
                    $leadMember = $m;
                    break;
                }
            }

            // 4. Fetch Proposal
            $stmtP = $pdo->prepare("SELECT * FROM `proposals` WHERE team_id = :tid LIMIT 1");
            $stmtP->execute([':tid' => $teamId]);
            $proposal = $stmtP->fetch(PDO::FETCH_ASSOC);

            // 5. Fetch Milestones
            $stmtMs = $pdo->prepare("SELECT * FROM `proposal_milestones` WHERE team_id = :tid ORDER BY month_number ASC");
            $stmtMs->execute([':tid' => $teamId]);
            $milestones = $stmtMs->fetchAll(PDO::FETCH_ASSOC);

            // 6. Fetch Budget
            $stmtB = $pdo->prepare("SELECT * FROM `proposal_budget_items` WHERE team_id = :tid ORDER BY item_order ASC");
            $stmtB->execute([':tid' => $teamId]);
            $budgetItems = $stmtB->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log("Mentor approval lookup error: " . $e->getMessage());
        $errorMessage = "Database service error. Please try again shortly.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mentor Endorsement &amp; E-Signature | iTech Seed Grand Challenge 2027</title>
  
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

  <style>
    .endorse-header-card { margin-bottom: 2rem; border-left: 5px solid var(--accent); }
    .mentor-role-pill { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.35rem 0.85rem; border-radius: 999px; font-size: 0.82rem; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; }
    .role-faculty { background: rgba(184, 237, 69, 0.18); color: var(--accent-strong); border: 1px solid var(--accent); }
    .role-industry { background: rgba(59, 130, 246, 0.15); color: #1d4ed8; border: 1px solid rgba(59, 130, 246, 0.3); }
    .dossier-section { margin-bottom: 2rem; }
    .dossier-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem; margin-top: 1rem; }
    .dossier-field-box { background: var(--surface-strong); border: 1px solid var(--line); border-radius: 12px; padding: 1.1rem 1.25rem; }
    .dossier-field-box h4 { font-size: 0.82rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin: 0 0 0.4rem 0; }
    .dossier-field-box p { font-size: 0.95rem; color: var(--text); margin: 0; line-height: 1.55; white-space: pre-line; }
    .consent-card { background: var(--surface); border: 2px solid var(--accent); border-radius: 16px; padding: 2rem 2.25rem; margin-bottom: 2.5rem; backdrop-filter: blur(20px); }
    .consent-item { display: flex; align-items: flex-start; gap: 0.85rem; margin-bottom: 1.25rem; cursor: pointer; }
    .consent-item:last-child { margin-bottom: 0; }
    .consent-item input[type="checkbox"] { width: 22px; height: 22px; margin-top: 2px; accent-color: var(--accent-strong); cursor: pointer; flex-shrink: 0; }
    .consent-text { font-size: 0.95rem; color: var(--text); line-height: 1.5; font-weight: 500; }
    .consent-text strong { color: var(--text); }
    .sig-input-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 1.25rem; margin-top: 1.5rem; }
    @media (max-width: 640px) { .sig-input-grid { grid-template-columns: 1fr; } }
    .decline-box { display: none; background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 10px; padding: 1.25rem; margin-top: 1.25rem; }
    .btn-decline-toggle { background: transparent; border: 1px solid #ef4444; color: #ef4444; font-weight: 600; padding: 0.75rem 1.5rem; border-radius: 999px; cursor: pointer; transition: all 0.2s ease; }
    .btn-decline-toggle:hover { background: rgba(239, 68, 68, 0.15); }
    .status-badge-approved { background: rgba(184, 237, 69, 0.2); color: var(--accent-strong); border: 1px solid var(--accent); padding: 0.5rem 1.2rem; border-radius: 999px; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem; }
    .status-badge-declined { background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); padding: 0.5rem 1.2rem; border-radius: 999px; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem; }
  </style>
</head>
<body>

  <!-- Ambient Glows -->
  <div class="ambient-glow glow-emerald"></div>
  <div class="ambient-glow glow-sunlight"></div>

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
        <section class="glass-panel" style="text-align: center; padding: 4rem 2rem; max-width: 680px; margin: 3rem auto;">
          <div style="width: 60px; height: 60px; border-radius: 50%; background: #fee2e2; color: #b91c1c; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem;">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
          </div>
          <h2 style="font-family: var(--font-heading); color: var(--text-forest); margin-bottom: 0.75rem;">Authentication Link Error</h2>
          <p style="color: var(--text-body); line-height: 1.6; margin-bottom: 2rem;"><?= htmlspecialchars($errorMessage) ?></p>
          <a href="register.php" class="btn-save-step" style="text-decoration: none; display: inline-flex;">Return to Portal Home</a>
        </section>
      <?php else: 
        $isFaculty = in_array($mentor['mentor_type'], ['faculty', 'additional_faculty'], true);
        $roleName = match($mentor['mentor_type']) {
            'faculty' => 'Faculty Mentor (PSG iTech)',
            'additional_faculty' => 'Additional Faculty Mentor',
            'industry' => 'Industry / Expert Mentor',
            default => 'Project Mentor'
        };
        $isApproved = ($mentor['approval_status'] === 'Approved');
        $isDeclined = ($mentor['approval_status'] === 'Declined');
      ?>

        <!-- Header Banner -->
        <section class="glass-panel endorse-header-card">
          <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 1rem;">
            <div>
              <span class="mentor-role-pill <?= $isFaculty ? 'role-faculty' : 'role-industry' ?>">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="7" r="4"></circle><path d="M5.5 21a6.5 6.5 0 0 1 13 0"></path></svg>
                <?= htmlspecialchars($roleName) ?>
              </span>
              <h1 class="portal-title" style="margin-top: 0.5rem; margin-bottom: 0.25rem;">
                Official Proposal Review &amp; Mentor Consent
              </h1>
              <p class="portal-subtitle" style="margin-bottom: 0;">
                Endorsement requested by <strong><?= htmlspecialchars($leadMember['full_name'] ?? 'Team Lead') ?></strong> on behalf of Team <strong><?= htmlspecialchars($team['team_name']) ?></strong>.
              </p>
            </div>
            
            <div style="text-align: right;">
              <span class="app-ref-badge" style="font-size: 0.85rem;">App Ref: <?= htmlspecialchars($team['application_no']) ?></span>
              <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.35rem;">PSG iTech Launchpad: Innozone &amp; Startupzone</div>
            </div>
          </div>
        </section>

        <!-- Notification Banner if Already Acted Upon -->
        <?php if ($isApproved): ?>
          <div class="glass-panel" style="margin-bottom: 2rem; background: rgba(34, 197, 94, 0.12); border: 1.5px solid #22c55e; padding: 1.75rem 2rem;">
            <div class="status-badge-approved" style="margin-bottom: 0.75rem;">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
              Consent Recorded &amp; Proposal Endorsed
            </div>
            <p style="color: var(--text-forest); font-size: 0.95rem; margin: 0; line-height: 1.55;">
              Thank you, <strong><?= htmlspecialchars($mentor['name']) ?></strong>. Your binding e-signature (<strong><?= htmlspecialchars($mentor['mentor_signature'] ?? $mentor['name']) ?></strong>) was recorded on <strong><?= htmlspecialchars($mentor['approved_at'] ?? 'date unavailable') ?></strong>.
            </p>
          </div>
        <?php elseif ($isDeclined): ?>
          <div class="glass-panel" style="margin-bottom: 2rem; background: rgba(239, 68, 68, 0.12); border: 1.5px solid #ef4444; padding: 1.75rem 2rem;">
            <div class="status-badge-declined" style="margin-bottom: 0.75rem;">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
              Endorsement Declined
            </div>
            <p style="color: var(--text-body); font-size: 0.95rem; margin: 0; line-height: 1.55;">
              You declined endorsement on <strong><?= htmlspecialchars($mentor['approved_at'] ?? 'date unavailable') ?></strong> with note: <em><?= htmlspecialchars($mentor['decline_reason'] ?? 'No comment provided') ?></em>.
            </p>
          </div>
        <?php endif; ?>

        <!-- ================================================================
             PROPOSAL DOSSIER (READ-ONLY)
             ================================================================ -->
        
        <!-- 1. The Idea & Problem Identification -->
        <section class="glass-panel dossier-section">
          <div class="section-heading-row">
            <h2 class="section-title">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 1 1 7.072 0l-.548.547A3.374 3.374 0 0 0 14 18.469V19a2 2 0 1 1-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path></svg>
              The Innovation: <?= htmlspecialchars($proposal['project_title'] ?? $team['team_name']) ?>
            </h2>
            <span class="app-ref-badge">PoC Type: <?= htmlspecialchars($proposal['poc_type'] ?? 'Both') ?></span>
          </div>

          <div class="dossier-grid">
            <div class="dossier-field-box" style="grid-column: 1 / -1;">
              <h4>Problem Statement</h4>
              <p><?= htmlspecialchars($proposal['problem_statement'] ?? 'No statement provided') ?></p>
            </div>
            <div class="dossier-field-box">
              <h4>Existing Solutions &amp; The Gap</h4>
              <p><?= htmlspecialchars($proposal['existing_solutions'] ?? 'No gaps specified') ?></p>
            </div>
            <div class="dossier-field-box">
              <h4>Proposed Solution (PoC)</h4>
              <p><?= htmlspecialchars($proposal['proposed_solution'] ?? 'No details provided') ?></p>
            </div>
          </div>
        </section>

        <!-- 2. Technology Readiness & IP -->
        <section class="glass-panel dossier-section">
          <div class="section-heading-row">
            <h2 class="section-title">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
              Readiness &amp; Technology Maturity
            </h2>
            <span class="app-ref-badge">Current TRL: Level <?= (int)($proposal['trl_level'] ?? 2) ?></span>
          </div>

          <div class="dossier-grid">
            <div class="dossier-field-box" style="grid-column: 1 / -1;">
              <h4>Work Done So Far</h4>
              <p><?= htmlspecialchars($proposal['work_done_so_far'] ?? 'Preliminary research & concepts') ?></p>
            </div>
            <div class="dossier-field-box">
              <h4>Prior Funding / Prize Disclosure</h4>
              <p>
                <strong>Received Prior Funding:</strong> <?= htmlspecialchars($proposal['prior_funding_has'] ?? 'No') ?><br>
                <?php if (($proposal['prior_funding_has'] ?? 'No') === 'Yes'): ?>
                  <strong>Agency / Competition:</strong> <?= htmlspecialchars($proposal['prior_funding_agency'] ?? 'N/A') ?><br>
                  <strong>Amount:</strong> ₹<?= htmlspecialchars($proposal['prior_funding_amount'] ?? '0') ?><br>
                  <strong>Scope Overlap:</strong> <?= htmlspecialchars($proposal['prior_funding_overlap'] ?? 'None') ?>
                <?php else: ?>
                  No prior funding overlap.
                <?php endif; ?>
              </p>
            </div>
            <div class="dossier-field-box">
              <h4>Intellectual Property (IP) Status</h4>
              <p><?= htmlspecialchars($proposal['ip_status'] ?? 'None') ?></p>
            </div>
          </div>
        </section>

        <!-- 3. PoC Plan, Milestones & Deliverables -->
        <section class="glass-panel dossier-section">
          <div class="section-heading-row">
            <h2 class="section-title">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="9 11 12 14 22 4"></polyline><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>
              PoC Deliverables &amp; 6-Month Roadmap
            </h2>
            <span class="section-hint">Jan 1 to Jun 30, 2027</span>
          </div>

          <div class="dossier-grid" style="margin-bottom: 1.5rem;">
            <div class="dossier-field-box">
              <h4>End Deliverables</h4>
              <p><?= htmlspecialchars($proposal['deliverables'] ?? 'Working prototype') ?></p>
            </div>
            <div class="dossier-field-box">
              <h4>Test &amp; Validation Method</h4>
              <p><?= htmlspecialchars($proposal['test_method'] ?? 'Laboratory bench testing') ?></p>
            </div>
            <div class="dossier-field-box">
              <h4>Verifiable Success Measure</h4>
              <p><?= htmlspecialchars($proposal['success_rate'] ?? 'Acceptance testing') ?></p>
            </div>
          </div>

          <?php if (!empty($milestones)): ?>
            <div class="milestones-table-wrap">
              <table class="milestones-table">
                <thead>
                  <tr>
                    <th style="width: 15%;">Month</th>
                    <th style="width: 35%;">Milestone Objective</th>
                    <th style="width: 25%;">Deliverable</th>
                    <th style="width: 25%;">Verification Method</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($milestones as $ms): ?>
                    <tr>
                      <td class="col-month"><strong>Month <?= (int)$ms['month_number'] ?></strong><br><span style="font-size: 0.8rem; color: var(--text-muted);"><?= htmlspecialchars($ms['month_label']) ?></span></td>
                      <td><?= htmlspecialchars($ms['milestone_desc'] ?? '') ?></td>
                      <td><?= htmlspecialchars($ms['output_deliverable'] ?? '') ?></td>
                      <td><?= htmlspecialchars($ms['verification_method'] ?? '') ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </section>

        <!-- 4. Budget & Financial Plan -->
        <section class="glass-panel dossier-section">
          <div class="section-heading-row">
            <h2 class="section-title">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="2" y="4" width="20" height="16" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>
              Itemized Financial Allocation
            </h2>
            <span class="app-ref-badge" style="background: rgba(34, 197, 94, 0.15); color: var(--clover-green); font-size: 0.95rem;">
              Requested: ₹<?= number_format((float)($proposal['budget_requested'] ?? 0), 2) ?>
            </span>
          </div>

          <?php if (!empty($budgetItems)): ?>
            <div class="budget-table-wrap" style="margin-top: 1rem;">
              <table class="budget-table">
                <thead>
                  <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 30%;">Item</th>
                    <th style="width: 25%;">Category</th>
                    <th style="width: 10%;">Qty</th>
                    <th style="width: 15%;">Unit Cost</th>
                    <th style="width: 15%;">Amount</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($budgetItems as $idx => $bi): ?>
                    <tr>
                      <td><?= $idx + 1 ?></td>
                      <td><strong><?= htmlspecialchars($bi['item_name']) ?></strong><br><span style="font-size: 0.78rem; color: var(--text-muted);"><?= htmlspecialchars($bi['purpose'] ?? '') ?></span></td>
                      <td><span style="font-size: 0.85rem;"><?= htmlspecialchars($bi['category']) ?></span></td>
                      <td><?= (int)$bi['quantity'] ?></td>
                      <td>₹<?= number_format((float)$bi['unit_cost'], 2) ?></td>
                      <td><strong>₹<?= number_format((float)$bi['amount'], 2) ?></strong></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <p style="color: var(--text-muted); font-size: 0.9rem;">Standard ₹1,00,000 maximum allocation for component procurement and prototype fabrication.</p>
          <?php endif; ?>
        </section>

        <!-- 5. Market Potential & Societal Impact -->
        <section class="glass-panel dossier-section">
          <div class="section-heading-row">
            <h2 class="section-title">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
              Market Potential &amp; Societal Impact
            </h2>
          </div>

          <div class="dossier-grid">
            <div class="dossier-field-box">
              <h4>Target Users &amp; Customers</h4>
              <p><?= htmlspecialchars($proposal['target_users'] ?? 'Target industry/society not specified') ?></p>
            </div>
            <div class="dossier-field-box">
              <h4>Size of the Need / Market Scale</h4>
              <p><?= htmlspecialchars($proposal['market_need_size'] ?? 'Estimates pending') ?></p>
            </div>
            <div class="dossier-field-box">
              <h4>Route to Commercialisation</h4>
              <p><?= htmlspecialchars($proposal['commercialisation_route'] ?? 'PoC to startup transfer') ?></p>
            </div>
            <div class="dossier-field-box">
              <h4>Measurable Societal &amp; Industrial Impact</h4>
              <p><?= htmlspecialchars($proposal['societal_impact'] ?? 'Impact assessment pending') ?></p>
            </div>
          </div>
        </section>

        <!-- 6. Team Innovators & Lead Confirmation -->
        <section class="glass-panel dossier-section">
          <div class="section-heading-row">
            <h2 class="section-title">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
              Student Innovators &amp; Lead Undertaking
            </h2>
            <span class="section-hint"><?= count($members) ?> Team Members</span>
          </div>

          <div style="display: flex; flex-wrap: wrap; gap: 1rem; margin-top: 1rem;">
            <?php foreach ($members as $m): ?>
              <div class="dossier-field-box" style="flex: 1 1 240px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                  <strong style="color: var(--text-forest);"><?= htmlspecialchars($m['full_name']) ?></strong>
                  <?php if ($m['is_lead']): ?>
                    <span class="mentor-badge mentor-badge-req" style="font-size: 0.72rem;">Team Lead</span>
                  <?php endif; ?>
                </div>
                <div style="font-size: 0.82rem; color: var(--text-muted); line-height: 1.4;">
                  <?= htmlspecialchars($m['roll_number']) ?> · <?= htmlspecialchars($m['department']) ?> (Yr <?= htmlspecialchars($m['year_of_study']) ?>)<br>
                  <a href="mailto:<?= htmlspecialchars($m['college_email']) ?>" style="color: var(--clover-green); text-decoration: none;"><?= htmlspecialchars($m['college_email']) ?></a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <div style="margin-top: 1.25rem; padding: 1rem 1.25rem; background: rgba(34, 197, 94, 0.12); border: 1px solid rgba(34, 197, 94, 0.3); border-radius: 8px; font-size: 0.85rem; color: var(--text-forest);">
            <strong>Lead E-Signature:</strong> <?= htmlspecialchars($proposal['lead_signature_name'] ?? 'Pending') ?> 
            · <strong>Date:</strong> <?= htmlspecialchars($proposal['lead_signature_date'] ?? 'N/A') ?>
            · <em>All 5 mandatory team declarations (originality, no duplicate funding, DPIIT undertaking, IPR policy, mentor guidance) have been affirmed by the Team Lead.</em>
          </div>
        </section>

        <!-- ================================================================
             ONLINE MENTOR CONSENT & ELECTRONIC SIGNATURE FORM
             ================================================================ -->
        <?php if (!$isApproved && !$isDeclined): ?>
          <section class="consent-card" id="endorsementCard">
            <h2 style="font-family: var(--font-heading); font-size: 1.5rem; color: var(--text-forest); margin-top: 0; margin-bottom: 0.5rem;">
              Mentor Consent &amp; Binding Digital Endorsement
            </h2>
            <p style="color: var(--text-body); font-size: 0.95rem; margin-bottom: 1.75rem; line-height: 1.5;">
              As the designated <strong><?= htmlspecialchars($roleName) ?></strong>, please affirm the required consent statements below and provide your digital signature to authorize this team's application.
            </p>

            <form id="mentorConsentForm" novalidate>
              <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

              <!-- Checkbox 1: General Mentorship Agreement -->
              <label class="consent-item">
                <input type="checkbox" name="consent_agree_guide" id="consentAgreeGuide" value="1" class="nature-green-checkbox" required>
                <span class="consent-text">
                  <strong>Mentorship Agreement:</strong> I agree to guide this student team throughout the technical execution of their Proof-of-Concept under the <em>iTech Seed Grand Challenge 2027</em>.
                </span>
              </label>

              <!-- Checkbox 2: Faculty Certification (Faculty Mentor Only) -->
              <?php if ($mentor['mentor_type'] === 'faculty'): ?>
                <label class="consent-item">
                  <input type="checkbox" name="consent_certify_progress" id="consentCertifyProgress" value="1" class="nature-green-checkbox" required>
                  <span class="consent-text">
                    <strong>Progress &amp; Financial Certification:</strong> I will monitor experimental work, certify milestone deliverables &amp; procurement bills, and attend the scheduled monthly progress reviews and Demo Day.
                  </span>
                </label>
              <?php endif; ?>

              <!-- Checkbox 3: Proposal Originality & Technical Feasibility -->
              <label class="consent-item">
                <input type="checkbox" name="consent_original_idea" id="consentOriginalIdea" value="1" class="nature-green-checkbox" required>
                <span class="consent-text">
                  <strong>Originality &amp; Feasibility:</strong> I have reviewed this proposal and confirm it is original to the best of my knowledge, technically feasible within the 6-month challenge timeframe, and within the ₹1,00,000 budget scope.
                </span>
              </label>

              <!-- Digital E-Signature Fields -->
              <div class="form-group" style="margin-top: 1.5rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                  <label class="form-label" style="color: var(--text-forest); margin-bottom: 0;">
                    Mentor Electronic Signature (Name &amp; Date) <span class="req">*</span>
                  </label>

                  <!-- Information 'i' Icon with Signature Guidelines Pop-up -->
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
                          <p style="font-family: var(--font-mono-tech); font-weight: 700; color: var(--clover-green); font-size: 0.95rem; margin: 0.35rem 0;">Mentor_Name DD/MM/YYYY</p>
                          <p>Type your full name followed by date in DD/MM/YYYY format.</p>
                        </div>
                        <div class="tooltip-section">
                          <span class="tooltip-subheading">Example:</span>
                          <p style="margin: 0;"><code><?= htmlspecialchars($mentor['name']) ?> <?= date('d/m/Y') ?></code></p>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <input type="text" name="mentor_signature" id="mentorSignatureInput" class="nature-input" 
                       placeholder="Mentor_Name DD/MM/YYYY"
                       value="<?= htmlspecialchars($mentor['mentor_signature'] ?? '') ?>"
                       autocomplete="off" required>
                <span id="mentorSignatureDescription" class="field-hint" style="display: block; margin-top: 0.45rem; font-size: 0.82rem; color: var(--text-muted); transition: opacity 0.2s ease;">
                  Type your signature in format: <strong>Mentor_Name DD/MM/YYYY</strong> (e.g. <?= htmlspecialchars($mentor['name']) ?> <?= date('d/m/Y') ?>). This description text vanishes as soon as you begin typing.
                </span>
              </div>

              <!-- Notification / Alert Box -->
              <div id="consentAlertBox" style="display: none; margin-top: 1.25rem;"></div>

              <!-- Action Buttons -->
              <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid rgba(21, 67, 44, 0.15);">
                <button type="button" id="toggleDeclineBtn" class="btn-decline-toggle">
                  Decline or Request Revisions
                </button>

                <button type="submit" id="submitApproveBtn" class="btn-save-step" style="padding: 0.9rem 2.2rem; font-size: 1rem;">
                  <span>Approve &amp; Submit Digital Endorsement</span>
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </button>
              </div>

              <!-- Decline Feedback Box (Hidden by default) -->
              <div id="declineBox" class="decline-box">
                <h4 style="margin: 0 0 0.5rem 0; color: #991b1b; font-size: 0.95rem;">Reason for Declining / Feedback for Student Team:</h4>
                <textarea id="declineReasonInput" class="nature-textarea" rows="3" placeholder="Explain what technical changes, scope adjustments, or budget clarifications are needed before you can endorse this project..."></textarea>
                <div style="margin-top: 0.75rem; text-align: right;">
                  <button type="button" id="submitDeclineBtn" style="background: #dc2626; color: #ffffff; border: none; padding: 0.65rem 1.4rem; border-radius: 6px; font-weight: 700; cursor: pointer;">
                    Confirm &amp; Send Decline Notice
                  </button>
                </div>
              </div>

            </form>
          </section>
        <?php endif; ?>

      <?php endif; ?>

    </main>

    <!-- Footer -->
    <footer class="event-footer">
      <div class="footer-bottom">
        <p>© 2026–2027 PSG Institute of Technology and Applied Research · Innozone &amp; Startupzone (Launchpad)</p>
      </div>
    </footer>

  </div>

  <!-- Inline Endorsement Controller Script -->
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const form = document.getElementById('mentorConsentForm');
      if (!form) return;

      const approveBtn = document.getElementById('submitApproveBtn');
      const toggleDeclineBtn = document.getElementById('toggleDeclineBtn');
      const declineBox = document.getElementById('declineBox');
      const submitDeclineBtn = document.getElementById('submitDeclineBtn');
      const alertBox = document.getElementById('consentAlertBox');

      const sigInput = document.getElementById('mentorSignatureInput');
      const sigDesc = document.getElementById('mentorSignatureDescription');

      // 1. Signature Input Description Auto-Vanish on Typing
      if (sigInput && sigDesc) {
        const updateDescVisibility = () => {
          if (sigInput.value.trim().length > 0) {
            sigDesc.style.opacity = '0';
            sigDesc.style.maxHeight = '0';
            sigDesc.style.marginTop = '0';
            sigDesc.style.overflow = 'hidden';
          } else {
            sigDesc.style.opacity = '1';
            sigDesc.style.maxHeight = '50px';
            sigDesc.style.marginTop = '0.45rem';
          }
        };

        sigInput.addEventListener('input', updateDescVisibility);
        updateDescVisibility();
      }

      // 2. Corner Information Tooltips (Auto-dismiss on outside click, typing, focus, or escape)
      function closeAllTooltips() {
        document.querySelectorAll('.info-tooltip-pop').forEach(pop => {
          pop.classList.remove('tooltip-visible');
          pop.classList.add('tooltip-force-hidden');
        });
        document.querySelectorAll('.consent-card, .glass-panel').forEach(p => {
          p.classList.remove('panel-elevated');
          p.style.removeProperty('z-index');
        });
      }

      const tooltipWraps = document.querySelectorAll('.info-tooltip-wrap');
      tooltipWraps.forEach(wrap => {
        const btn = wrap.querySelector('.info-icon-btn');
        const pop = wrap.querySelector('.info-tooltip-pop');
        if (!btn || !pop) return;

        wrap.addEventListener('mouseenter', () => {
          pop.classList.remove('tooltip-force-hidden');
          const panel = wrap.closest('.consent-card, .glass-panel');
          if (panel) {
            panel.classList.add('panel-elevated');
            panel.style.zIndex = '1000';
          }
        });

        wrap.addEventListener('mouseleave', () => {
          if (!pop.classList.contains('tooltip-visible')) {
            const panel = wrap.closest('.consent-card, .glass-panel');
            if (panel) {
              panel.classList.remove('panel-elevated');
              panel.style.removeProperty('z-index');
            }
          }
        });

        btn.addEventListener('click', (e) => {
          e.preventDefault();
          e.stopPropagation();
          const isCurrentlyOpen = pop.classList.contains('tooltip-visible') && !pop.classList.contains('tooltip-force-hidden');
          closeAllTooltips();
          if (!isCurrentlyOpen) {
            pop.classList.remove('tooltip-force-hidden');
            pop.classList.add('tooltip-visible');
            const panel = wrap.closest('.consent-card, .glass-panel');
            if (panel) {
              panel.classList.add('panel-elevated');
              panel.style.zIndex = '1000';
            }
          }
        });
      });

      document.addEventListener('click', (e) => {
        if (!e.target.closest('.info-tooltip-wrap')) {
          closeAllTooltips();
        }
      });

      document.addEventListener('input', closeAllTooltips);
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' || e.key.length === 1) {
          closeAllTooltips();
        }
      });

      document.querySelectorAll('input, textarea, select').forEach(field => {
        field.addEventListener('focus', closeAllTooltips);
      });

      // Toggle decline drawer
      toggleDeclineBtn.addEventListener('click', () => {
        const isHidden = declineBox.style.display === 'none' || declineBox.style.display === '';
        declineBox.style.display = isHidden ? 'block' : 'none';
        if (isHidden) {
          declineBox.scrollIntoView({ behavior: 'smooth' });
        }
      });

      // Submit Approval
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        alertBox.style.display = 'none';

        const token = form.querySelector('input[name="token"]').value;
        const agreeGuide = document.getElementById('consentAgreeGuide')?.checked;
        const certifyProgress = document.getElementById('consentCertifyProgress')?.checked ?? true;
        const originalIdea = document.getElementById('consentOriginalIdea')?.checked;
        const sigName = sigInput?.value.trim() || '';

        const errors = [];
        if (!agreeGuide) errors.push('You must agree to guide this team.');
        if (!certifyProgress) errors.push('As Faculty Mentor, you must agree to certify progress & bills and attend reviews.');
        if (!originalIdea) errors.push('You must confirm proposal originality and technical feasibility.');
        if (!sigName || sigName.length < 3) errors.push('Please type your digital signature in format: Mentor_Name DD/MM/YYYY.');

        if (errors.length > 0) {
          showAlert(errors);
          return;
        }

        approveBtn.disabled = true;
        approveBtn.innerHTML = `<span>Recording Digital Endorsement...</span>`;

        try {
          const res = await fetch('api/save_mentor_approval.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              token: token,
              decision: 'Approved',
              mentor_signature: sigName,
              consent_agree_guide: 1,
              consent_certify_progress: certifyProgress ? 1 : 0,
              consent_original_idea: 1
            })
          });

          const data = await res.json();
          if (res.ok && data.success) {
            showSuccessModal(data);
          } else {
            showAlert(data.errors || [data.message || 'Error saving approval.']);
          }
        } catch (err) {
          showAlert(['Network or server communication error. Please try again.']);
        } finally {
          approveBtn.disabled = false;
          approveBtn.innerHTML = `<span>Approve &amp; Submit Digital Endorsement</span>`;
        }
      });

      // Submit Decline
      submitDeclineBtn.addEventListener('click', async () => {
        const token = form.querySelector('input[name="token"]').value;
        const reason = document.getElementById('declineReasonInput').value.trim();

        if (!reason || reason.length < 10) {
          alert('Please enter at least 10 characters explaining your feedback or reason for declining.');
          return;
        }

        if (!confirm('Are you sure you want to decline endorsement for this proposal? The student lead will be notified.')) {
          return;
        }

        submitDeclineBtn.disabled = true;
        submitDeclineBtn.textContent = 'Submitting Decline...';

        try {
          const res = await fetch('api/save_mentor_approval.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              token: token,
              decision: 'Declined',
              decline_reason: reason
            })
          });

          const data = await res.json();
          if (res.ok && data.success) {
            window.location.reload();
          } else {
            alert(data.message || 'Error recording response.');
          }
        } catch (err) {
          alert('Network error. Please try again.');
        } finally {
          submitDeclineBtn.disabled = false;
          submitDeclineBtn.textContent = 'Confirm & Send Decline Notice';
        }
      });

      function showAlert(messages) {
        alertBox.style.display = 'block';
        alertBox.className = 'form-alert-banner alert-error';
        alertBox.innerHTML = `
          <div class="alert-content-col" style="padding: 0.5rem 0;">
            <ul class="alert-list" style="margin: 0;">
              ${messages.map(m => `<li>${m}</li>`).join('')}
            </ul>
          </div>
        `;
        alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }

      function showSuccessModal(data) {
        const modalHtml = `
          <div class="success-modal-overlay" id="mentorSuccessModal">
            <div class="success-modal-card glass-panel" style="text-align: center; max-width: 540px;">
              <div class="modal-celebrate-badge" style="background: rgba(34, 197, 94, 0.2); color: #15803d; border-color: #22c55e;">Endorsement Complete</div>
              <h2 class="modal-title" style="color: var(--text-forest);">Official Mentor Endorsement Recorded</h2>
              <p class="modal-sub">
                Thank you for supporting student innovation at PSG iTech. Your digital signature and consent declarations have been permanently logged.
              </p>
              ${data.all_mentors_ready ? `
                <div style="background: rgba(34, 197, 94, 0.1); border: 1px solid #86efac; border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem; font-size: 0.88rem; color: #166534;">
                  <strong>Milestone Met:</strong> All mandatory mentors have now endorsed this proposal. It has advanced to the Screening Committee for scoring!
                </div>
              ` : ''}
              <div class="modal-actions" style="justify-content: center;">
                <button type="button" class="btn-modal-primary" onclick="window.location.reload();">
                  View Endorsed Dossier &rarr;
                </button>
              </div>
            </div>
          </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHtml);
      }
    });
  </script>

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
