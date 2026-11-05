<?php
/**
 * iTech Seed Grand Challenge 2027
 * Official Complete Application Report & Sanction Dossier
 * Printable & Downloadable PDF View
 * PSG iTech Launchpad: Innozone & Startupzone
 */

declare(strict_types=1);

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
            $stmtM = $pdo->prepare("SELECT * FROM `team_members` WHERE team_id = :tid ORDER BY member_order ASC, is_lead DESC");
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
        }
    } catch (PDOException $e) {
        error_log("Dossier generation lookup error: " . $e->getMessage());
        $errorMessage = "Database connection error.";
    }
} else {
    $errorMessage = "Invalid request. Please provide a valid team ID or application reference number.";
}

if (!$team):
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Application Record Not Found | iTech Seed Grand Challenge 2027</title>
  <link rel="stylesheet" href="style.css">
</head>
<body style="display: flex; align-items: center; justify-content: center; min-height: 100vh; background: #f0fdf4; font-family: sans-serif;">
  <div style="background: #ffffff; padding: 3rem; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.08); text-align: center; max-width: 500px;">
    <h2 style="color: #991b1b; margin-bottom: 1rem;">Record Not Found</h2>
    <p style="color: #4b5563; margin-bottom: 1.5rem;"><?= htmlspecialchars($errorMessage ?? 'The requested registration dossier could not be located.') ?></p>
    <a href="register.php" style="background: #16a34a; color: #ffffff; text-decoration: none; padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 700;">Return to Portal</a>
  </div>
</body>
</html>
<?php
exit;
endif;

// Calculate budget totals
$totalBudget = 0.0;
foreach ($budgetItems as $bi) {
    $totalBudget += (float)($bi['amount'] ?? 0);
}
if ($totalBudget <= 0 && $proposal && !empty($proposal['budget_requested'])) {
    $totalBudget = (float)$proposal['budget_requested'];
}

$trlDefinitions = [
    1 => 'TRL 1: Basic Principles Observed and Reported',
    2 => 'TRL 2: Technology Concept and/or Application Formulated',
    3 => 'TRL 3: Analytical & Experimental Critical Function / Proof of Concept',
    4 => 'TRL 4: Component / Subsystem Validation in Laboratory Environment'
];
$trlLevel = (int)($proposal['trl_level'] ?? 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($team['application_no']) ?> — Official Proposal Dossier | iTech Seed Grand Challenge 2027</title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
  
  <style>
    :root {
      --primary: #15803d;
      --primary-dark: #064e3b;
      --accent: #16a34a;
      --accent-soft: #dcfce7;
      --text-main: #0f172a;
      --text-muted: #475569;
      --border: #cbd5e1;
      --bg-page: #f8fafc;
      --bg-card: #ffffff;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      background: var(--bg-page);
      color: var(--text-main);
      line-height: 1.55;
      font-size: 13.5px;
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
    }

    /* Floating Toolbar for interactive users */
    .no-print-toolbar {
      position: sticky;
      top: 0;
      z-index: 9999;
      background: rgba(15, 23, 42, 0.94);
      backdrop-filter: blur(12px);
      color: #ffffff;
      padding: 0.85rem 2rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
    }
    .toolbar-title {
      font-weight: 700;
      font-size: 0.95rem;
      display: flex;
      align-items: center;
      gap: 0.6rem;
    }
    .toolbar-actions {
      display: flex;
      align-items: center;
      gap: 0.85rem;
    }
    .btn-toolbar-print {
      background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
      color: #ffffff;
      border: none;
      padding: 0.6rem 1.4rem;
      border-radius: 9999px;
      font-weight: 700;
      font-size: 0.9rem;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      box-shadow: 0 4px 12px rgba(22, 163, 74, 0.4);
      transition: all 0.2s ease;
    }
    .btn-toolbar-print:hover {
      transform: translateY(-1px);
      box-shadow: 0 6px 16px rgba(22, 163, 74, 0.5);
    }
    .btn-toolbar-back {
      color: #94a3b8;
      text-decoration: none;
      font-size: 0.88rem;
      font-weight: 600;
      transition: color 0.2s ease;
    }
    .btn-toolbar-back:hover {
      color: #ffffff;
    }

    /* Paper Container (A4 styling on screen) */
    .dossier-paper {
      max-width: 900px;
      margin: 2rem auto;
      background: var(--bg-card);
      padding: 3.5rem 4rem;
      border-radius: 12px;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
      border: 1px solid rgba(226, 232, 240, 0.8);
      position: relative;
    }

    /* Watermark */
    .dossier-watermark {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%) rotate(-35deg);
      font-size: 5rem;
      font-weight: 900;
      color: rgba(22, 163, 74, 0.04);
      white-space: nowrap;
      pointer-events: none;
      z-index: 1;
      text-transform: uppercase;
      letter-spacing: 0.1em;
    }

    /* Header Section */
    .dossier-header {
      border-bottom: 2.5px solid var(--primary-dark);
      padding-bottom: 1.5rem;
      margin-bottom: 2rem;
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 1.5rem;
      position: relative;
      z-index: 2;
    }
    .header-institution {
      font-size: 0.85rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: var(--primary-dark);
      margin-bottom: 0.25rem;
    }
    .header-challenge-title {
      font-family: 'Outfit', sans-serif;
      font-size: 1.55rem;
      font-weight: 900;
      color: #064e3b;
      line-height: 1.2;
      margin-bottom: 0.35rem;
    }
    .header-subtext {
      font-size: 0.85rem;
      color: var(--text-muted);
      font-weight: 500;
    }
    .header-badge-box {
      text-align: right;
      flex-shrink: 0;
    }
    .badge-app-ref {
      display: inline-block;
      background: #f0fdf4;
      border: 1.5px solid #16a34a;
      color: #15803d;
      font-family: 'Space Grotesk', monospace;
      font-size: 0.95rem;
      font-weight: 800;
      padding: 0.4rem 0.9rem;
      border-radius: 8px;
      margin-bottom: 0.35rem;
    }
    .badge-status {
      font-size: 0.76rem;
      color: #15803d;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }

    /* Section Styling */
    .dossier-section {
      margin-bottom: 2.25rem;
      position: relative;
      z-index: 2;
    }
    .section-banner {
      background: #f1f5f9;
      border-left: 4px solid var(--primary);
      padding: 0.55rem 0.85rem;
      font-family: 'Outfit', sans-serif;
      font-size: 1.05rem;
      font-weight: 800;
      color: var(--primary-dark);
      margin-bottom: 1rem;
      text-transform: uppercase;
      letter-spacing: 0.03em;
    }

    /* Info Grid */
    .info-grid-2col {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1rem;
      margin-bottom: 1rem;
    }
    .info-grid-3col {
      display: grid;
      grid-template-columns: 1fr 1fr 1fr;
      gap: 1rem;
      margin-bottom: 1rem;
    }
    .info-field-card {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      padding: 0.75rem 1rem;
    }
    .field-label {
      font-size: 0.72rem;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: var(--text-muted);
      font-weight: 700;
      margin-bottom: 0.2rem;
    }
    .field-value {
      font-size: 0.95rem;
      color: var(--text-main);
      font-weight: 700;
    }
    .field-value-sm {
      font-size: 0.86rem;
      color: var(--text-main);
      font-weight: 600;
    }

    /* Long Text Blocks */
    .text-block-card {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      padding: 0.9rem 1.15rem;
      margin-bottom: 1rem;
    }
    .text-block-title {
      font-size: 0.82rem;
      font-weight: 800;
      color: var(--primary-dark);
      text-transform: uppercase;
      letter-spacing: 0.04em;
      margin-bottom: 0.4rem;
    }
    .text-block-body {
      font-size: 0.9rem;
      color: #1e293b;
      white-space: pre-wrap;
      line-height: 1.6;
    }

    /* Tables */
    .dossier-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 1.25rem;
      font-size: 0.86rem;
    }
    .dossier-table th {
      background: #f1f5f9;
      color: var(--primary-dark);
      font-weight: 800;
      text-align: left;
      padding: 0.65rem 0.85rem;
      border: 1px solid #cbd5e1;
      font-size: 0.78rem;
      text-transform: uppercase;
      letter-spacing: 0.03em;
    }
    .dossier-table td {
      padding: 0.65rem 0.85rem;
      border: 1px solid #e2e8f0;
      color: #1e293b;
      vertical-align: top;
    }
    .dossier-table tbody tr:nth-child(even) {
      background: #f8fafc;
    }
    .lead-tag {
      background: #dcfce7;
      color: #15803d;
      font-size: 0.7rem;
      padding: 0.15rem 0.45rem;
      border-radius: 4px;
      font-weight: 800;
      display: inline-block;
      margin-left: 0.35rem;
      border: 1px solid #86efac;
    }

    /* Endorsement Boxes */
    .endorsement-card {
      background: #f0fdf4;
      border: 1.5px solid #86efac;
      border-radius: 10px;
      padding: 1.15rem 1.4rem;
      margin-bottom: 1rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 1rem;
    }
    .endorsement-title {
      font-weight: 800;
      font-size: 0.98rem;
      color: #064e3b;
      margin-bottom: 0.2rem;
    }
    .endorsement-details {
      font-size: 0.84rem;
      color: #166534;
      line-height: 1.45;
    }
    .signature-stamp-box {
      border: 2px dashed #16a34a;
      background: #ffffff;
      padding: 0.6rem 1.1rem;
      border-radius: 8px;
      text-align: right;
      min-width: 220px;
    }
    .signature-stamp-label {
      font-size: 0.68rem;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: var(--text-muted);
      font-weight: 700;
    }
    .signature-stamp-value {
      font-family: 'Space Grotesk', cursive, sans-serif;
      font-weight: 700;
      font-size: 1.05rem;
      color: #15803d;
      margin: 0.15rem 0;
    }
    .signature-stamp-date {
      font-size: 0.74rem;
      color: #64748b;
    }

    /* Declarations list */
    .declaration-check-list {
      list-style: none;
      padding: 0;
      margin: 0 0 1.25rem 0;
    }
    .declaration-check-list li {
      padding: 0.45rem 0;
      font-size: 0.86rem;
      color: #1e293b;
      display: flex;
      align-items: flex-start;
      gap: 0.65rem;
    }
    .declaration-check-icon {
      color: #16a34a;
      font-weight: 900;
      font-size: 1rem;
      line-height: 1.2;
    }

    /* Dossier Footer */
    .dossier-footer {
      border-top: 1.5px solid #cbd5e1;
      padding-top: 1.25rem;
      margin-top: 2.5rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 0.78rem;
      color: #64748b;
      position: relative;
      z-index: 2;
    }

    /* Print Specific Styles */
    @media print {
      body {
        background: #ffffff !important;
        font-size: 12px;
      }
      .no-print-toolbar {
        display: none !important;
      }
      .dossier-paper {
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        box-shadow: none !important;
        border: none !important;
      }
      .page-break-before {
        page-break-before: always;
      }
      .dossier-table th, .dossier-table td {
        padding: 0.45rem 0.6rem !important;
      }
      .text-block-card, .endorsement-card {
        page-break-inside: avoid;
      }
    }
  </style>
</head>
<body>

  <!-- FLOATING INTERACTIVE TOOLBAR (HIDDEN WHEN PRINTED) -->
  <aside class="no-print-toolbar">
    <div class="toolbar-title">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
      <span>Official Registration Dossier: <strong><?= htmlspecialchars($team['application_no']) ?></strong></span>
    </div>
    <div class="toolbar-actions">
      <a href="register.php?step=7&team_id=<?= (int)$team['id'] ?>" class="btn-toolbar-back">
        &larr; Back to Portal
      </a>
      <a href="registration_success.php?team_id=<?= (int)$team['id'] ?>" class="btn-toolbar-back">
        Confirmation Page
      </a>
      <button type="button" class="btn-toolbar-print" onclick="window.print()">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
        <span>Print / Save as PDF</span>
      </button>
    </div>
  </aside>

  <!-- DOSSIER PAPER SHEET -->
  <main class="dossier-paper">
    <div class="dossier-watermark">PSG iTech · Seed 2027</div>

    <!-- DOCUMENT HEADER -->
    <header class="dossier-header">
      <div>
        <div class="header-institution">PSG Institute of Technology and Applied Research (PSG iTech)</div>
        <h1 class="header-challenge-title">iTech Seed Grand Challenge 2027</h1>
        <div class="header-subtext">Launchpad: Innozone &amp; Startupzone · Official Proposal &amp; Sanction Dossier</div>
      </div>
      <div class="header-badge-box">
        <div class="badge-app-ref"><?= htmlspecialchars($team['application_no']) ?></div>
        <div class="badge-status">Status: <?= htmlspecialchars($team['status'] ?? 'Under Screening') ?></div>
      </div>
    </header>

    <!-- 1. PROJECT IDENTIFICATION & EXECUTIVE SUMMARY -->
    <section class="dossier-section">
      <div class="section-banner">Project Overview &amp; Executive Summary</div>
      <div class="info-grid-2col">
        <div class="info-field-card">
          <div class="field-label">Project Title</div>
          <div class="field-value" style="color: var(--primary-dark); font-size: 1.05rem;">
            <?= htmlspecialchars($proposal['project_title'] ?? 'Title Not Specified') ?>
          </div>
        </div>
        <div class="info-field-card">
          <div class="field-label">Team Name</div>
          <div class="field-value" style="font-size: 1.05rem;">
            <?= htmlspecialchars($team['team_name']) ?>
          </div>
        </div>
      </div>

      <div class="info-grid-3col">
        <div class="info-field-card">
          <div class="field-label">Proof-of-Concept Nature</div>
          <div class="field-value-sm">
            <?= htmlspecialchars($proposal['poc_type'] ?? 'Both Hardware & Software') ?>
          </div>
        </div>
        <div class="info-field-card">
          <div class="field-label">Readiness Level</div>
          <div class="field-value-sm">
            <?= htmlspecialchars($trlDefinitions[$trlLevel] ?? "TRL $trlLevel") ?>
          </div>
        </div>
        <div class="info-field-card">
          <div class="field-label">Total Seed Grant Requested</div>
          <div class="field-value-sm" style="color: #15803d; font-weight: 800;">
            ₹ <?= number_format($totalBudget, 2) ?>
          </div>
        </div>
      </div>
    </section>

    <!-- 2. STUDENT INNOVATION ROSTER -->
    <section class="dossier-section">
      <div class="section-banner">Team Members Roster</div>
      <table class="dossier-table">
        <thead>
          <tr>
            <th style="width: 5%;">#</th>
            <th style="width: 25%;">Full Name</th>
            <th style="width: 15%;">Roll Number</th>
            <th style="width: 15%;">Department</th>
            <th style="width: 10%;">Year</th>
            <th style="width: 15%;">Email Address</th>
            <th style="width: 15%;">Phone</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($members as $idx => $m): ?>
            <tr>
              <td><?= $idx + 1 ?></td>
              <td>
                <strong><?= htmlspecialchars($m['full_name']) ?></strong>
                <?php if ($m['is_lead']): ?>
                  <span class="lead-tag">Team Lead</span>
                <?php endif; ?>
              </td>
              <td><code><?= htmlspecialchars($m['roll_number']) ?></code></td>
              <td><?= htmlspecialchars($m['department']) ?></td>
              <td><?= htmlspecialchars($m['year_of_study']) ?> Year</td>
              <td><?= htmlspecialchars($m['college_email']) ?></td>
              <td><?= htmlspecialchars($m['phone_number']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>

    <!-- 3. MENTORSHIP & ENDORSEMENT BOARD -->
    <section class="dossier-section">
      <div class="section-banner">Mentorship Board &amp; Binding Endorsements</div>
      <?php if (empty($mentors)): ?>
        <p style="color: var(--text-muted);">No mentor records attached.</p>
      <?php else: ?>
        <?php foreach ($mentors as $men): 
          $typeLabel = match($men['mentor_type']) {
            'faculty' => 'Faculty Mentor (PSG iTech) · Mandatory',
            'additional_faculty' => 'Additional Faculty Mentor',
            'industry' => 'Industry / External Expert Mentor · Mandatory',
            default => 'Project Mentor'
          };
        ?>
          <div class="endorsement-card">
            <div>
              <div class="endorsement-title"><?= htmlspecialchars($men['name']) ?></div>
              <div style="font-size: 0.78rem; font-weight: 700; color: #047857; text-transform: uppercase; margin-bottom: 0.3rem;">
                <?= htmlspecialchars($typeLabel) ?>
              </div>
              <div class="endorsement-details">
                <?= htmlspecialchars($men['designation'] ?? 'Mentor') ?> &bull; <?= htmlspecialchars($men['organisation'] ?? $men['department'] ?? 'PSG iTech') ?><br>
                Email: <?= htmlspecialchars($men['email']) ?> &bull; Tel: <?= htmlspecialchars($men['phone']) ?>
              </div>
            </div>
            <div class="signature-stamp-box">
              <div class="signature-stamp-label">Digitally Signed &amp; Endorsed</div>
              <div class="signature-stamp-value"><?= htmlspecialchars($men['mentor_signature'] ?? $men['name']) ?></div>
              <div class="signature-stamp-date">
                <?= !empty($men['approved_at']) ? 'Recorded: ' . htmlspecialchars($men['approved_at']) : 'Status: Endorsed' ?>
                <?php if (!empty($men['mentor_ip_address'])): ?>
                  <br><span style="font-size: 0.68rem; color: #94a3b8;">IP: <?= htmlspecialchars($men['mentor_ip_address']) ?></span>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>

    <!-- 4. THE IDEA & PROBLEM FORMULATION -->
    <section class="dossier-section">
      <div class="section-banner">Problem Statement &amp; Proposed Solution</div>
      
      <div class="text-block-card">
        <div class="text-block-title">Problem Statement</div>
        <div class="text-block-body"><?= htmlspecialchars($proposal['problem_statement'] ?? 'Not provided') ?></div>
      </div>

      <div class="text-block-card">
        <div class="text-block-title">Existing Solutions &amp; Current Limitations</div>
        <div class="text-block-body"><?= htmlspecialchars($proposal['existing_solutions'] ?? 'Not provided') ?></div>
      </div>

      <div class="text-block-card">
        <div class="text-block-title">Proposed Novel Solution &amp; Technical Approach</div>
        <div class="text-block-body"><?= htmlspecialchars($proposal['proposed_solution'] ?? 'Not provided') ?></div>
      </div>
    </section>

    <!-- 5. READINESS & TRL ASSESSMENT -->
    <section class="dossier-section">
      <div class="section-banner">Readiness &amp; Intellectual Property Status</div>
      
      <div class="info-grid-2col">
        <div class="info-field-card">
          <div class="field-label">Technology Readiness Level</div>
          <div class="field-value-sm"><?= htmlspecialchars($trlDefinitions[$trlLevel] ?? "TRL $trlLevel") ?></div>
        </div>
        <div class="info-field-card">
          <div class="field-label">IP Status / Patent Search</div>
          <div class="field-value-sm"><?= htmlspecialchars($proposal['ip_status'] ?? 'None / Provisional') ?></div>
        </div>
      </div>

      <div class="text-block-card">
        <div class="text-block-title">Work Done So Far &amp; Preliminary Feasibility</div>
        <div class="text-block-body"><?= htmlspecialchars($proposal['work_done_so_far'] ?? 'Not provided') ?></div>
      </div>

      <div class="info-grid-2col">
        <div class="info-field-card">
          <div class="field-label">Prior Funding / Prize Received</div>
          <div class="field-value-sm">
            <?= htmlspecialchars($proposal['prior_funding_has'] ?? 'No') ?>
            <?php if (($proposal['prior_funding_has'] ?? '') === 'Yes' && !empty($proposal['prior_funding_amount'])): ?>
              (Amount: ₹<?= htmlspecialchars($proposal['prior_funding_amount']) ?>)
            <?php endif; ?>
          </div>
        </div>
        <div class="info-field-card">
          <div class="field-label">Funding Agency / Source</div>
          <div class="field-value-sm">
            <?= htmlspecialchars(!empty($proposal['prior_funding_agency']) ? $proposal['prior_funding_agency'] : 'None / Not Applicable') ?>
          </div>
        </div>
      </div>
    </section>

    <!-- 6. POC PLAN & 6-MONTH MILESTONES -->
    <section class="dossier-section">
      <div class="section-banner">Proof of Concept Plan &amp; Milestones</div>
      
      <div class="info-grid-3col">
        <div class="info-field-card">
          <div class="field-label">Deliverables</div>
          <div class="field-value-sm"><?= htmlspecialchars($proposal['deliverables'] ?? 'Working prototype') ?></div>
        </div>
        <div class="info-field-card">
          <div class="field-label">Testing &amp; Validation Method</div>
          <div class="field-value-sm"><?= htmlspecialchars($proposal['test_method'] ?? 'Benchmarking & lab testing') ?></div>
        </div>
        <div class="info-field-card">
          <div class="field-label">Success Criteria</div>
          <div class="field-value-sm"><?= htmlspecialchars($proposal['success_rate'] ?? 'Functional demonstration') ?></div>
        </div>
      </div>

      <table class="dossier-table">
        <thead>
          <tr>
            <th style="width: 12%;">Month</th>
            <th style="width: 38%;">Planned Milestone &amp; Engineering Activity</th>
            <th style="width: 25%;">Expected Deliverable</th>
            <th style="width: 25%;">Verification Method</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($milestones)): ?>
            <tr><td colspan="4" style="text-align: center; color: var(--text-muted);">No milestone records stored.</td></tr>
          <?php else: ?>
            <?php foreach ($milestones as $ms): ?>
              <tr>
                <td><strong><?= htmlspecialchars($ms['month_label'] ?? 'Month ' . $ms['month_number']) ?></strong></td>
                <td><?= htmlspecialchars($ms['milestone_desc'] ?? '') ?></td>
                <td><?= htmlspecialchars($ms['output_deliverable'] ?? '') ?></td>
                <td><?= htmlspecialchars($ms['verification_method'] ?? '') ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </section>

    <!-- 7. ITEMIZED BUDGET & FINANCIAL ALLOCATION -->
    <section class="dossier-section">
      <div class="section-banner">Itemized Budget Plan</div>
      <table class="dossier-table">
        <thead>
          <tr>
            <th style="width: 5%;">#</th>
            <th style="width: 30%;">Item Description</th>
            <th style="width: 18%;">Category</th>
            <th style="width: 10%; text-align: right;">Qty</th>
            <th style="width: 15%; text-align: right;">Unit Cost (₹)</th>
            <th style="width: 15%; text-align: right;">Amount (₹)</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($budgetItems)): ?>
            <tr>
              <td colspan="5">Lump-sum PoC Grant Allocation</td>
              <td style="text-align: right; font-weight: 700;">₹ <?= number_format($totalBudget, 2) ?></td>
            </tr>
          <?php else: ?>
            <?php foreach ($budgetItems as $idx => $bi): ?>
              <tr>
                <td><?= $idx + 1 ?></td>
                <td>
                  <strong><?= htmlspecialchars($bi['item_name']) ?></strong>
                  <?php if (!empty($bi['purpose'])): ?>
                    <div style="font-size: 0.78rem; color: #64748b;"><?= htmlspecialchars($bi['purpose']) ?></div>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($bi['category']) ?></td>
                <td style="text-align: right;"><?= (int)$bi['quantity'] ?></td>
                <td style="text-align: right;">₹ <?= number_format((float)$bi['unit_cost'], 2) ?></td>
                <td style="text-align: right; font-weight: 600;">₹ <?= number_format((float)$bi['amount'], 2) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
        <tfoot>
          <tr style="background: #f0fdf4; font-weight: 800; font-size: 0.95rem; border-top: 2px solid #16a34a;">
            <td colspan="5" style="text-align: right; color: var(--primary-dark);">TOTAL REQUESTED GRANT:</td>
            <td style="text-align: right; color: #15803d;">₹ <?= number_format($totalBudget, 2) ?></td>
          </tr>
        </tfoot>
      </table>
    </section>

    <!-- 8. MARKET POTENTIAL & SOCIETAL IMPACT -->
    <section class="dossier-section">
      <div class="section-banner">Market Potential &amp; Societal Impact</div>
      
      <div class="info-grid-2col">
        <div class="text-block-card">
          <div class="text-block-title">Target Users &amp; Customers</div>
          <div class="text-block-body"><?= htmlspecialchars($proposal['target_users'] ?? 'Not provided') ?></div>
        </div>
        <div class="text-block-card">
          <div class="text-block-title">Market Need &amp; Problem Scale</div>
          <div class="text-block-body"><?= htmlspecialchars($proposal['market_need_size'] ?? 'Not provided') ?></div>
        </div>
      </div>

      <div class="info-grid-2col">
        <div class="text-block-card">
          <div class="text-block-title">Route to Commercialisation</div>
          <div class="text-block-body"><?= htmlspecialchars($proposal['commercialisation_route'] ?? 'Not provided') ?></div>
        </div>
        <div class="text-block-card">
          <div class="text-block-title">Societal &amp; Industrial Impact</div>
          <div class="text-block-body"><?= htmlspecialchars($proposal['societal_impact'] ?? 'Not provided') ?></div>
        </div>
      </div>
    </section>

    <!-- 9. BINDING DECLARATION & DIGITAL E-SIGNATURES -->
    <section class="dossier-section">
      <div class="section-banner">Regulatory Undertakings &amp; Binding Signatures</div>
      
      <ul class="declaration-check-list">
        <li>
          <span class="declaration-check-icon">✓</span>
          <span><strong>Originality Undertaking:</strong> The team certifies that the proposed idea is original and not plagiarised from existing publications or student theses.</span>
        </li>
        <li>
          <span class="declaration-check-icon">✓</span>
          <span><strong>Funding Exclusivity:</strong> The team confirms that no duplicate or overlapping funding from other government/private grants has been received for these specific deliverables.</span>
        </li>
        <li>
          <span class="declaration-check-icon">✓</span>
          <span><strong>Grant Agreement &amp; Startup Registration:</strong> The team agrees to execute the formal Seed Grant Agreement and register under DPIIT and Udyam upon sanction.</span>
        </li>
        <li>
          <span class="declaration-check-icon">✓</span>
          <span><strong>Intellectual Property Rights:</strong> The team agrees to the institutional IPR policy with joint inventors from PSG iTech as per college guidelines.</span>
        </li>
        <li>
          <span class="declaration-check-icon">✓</span>
          <span><strong>Mentorship Agreement:</strong> The team confirms that the designated Faculty Mentor and Industry Mentor have consented to provide continuous technical review.</span>
        </li>
      </ul>

      <div class="endorsement-card" style="margin-top: 1.5rem; background: #ffffff; border: 2px solid #064e3b;">
        <div>
          <div class="endorsement-title">Team Lead Binding E-Signature</div>
          <div class="endorsement-details">
            Name: <strong><?= htmlspecialchars($proposal['lead_signature_name'] ?? ($leadMember['full_name'] ?? 'Team Lead')) ?></strong><br>
            Application Ref: <?= htmlspecialchars($team['application_no']) ?><br>
            <?php if (!empty($proposal['lead_declared_at'])): ?>
              Signed At: <?= htmlspecialchars($proposal['lead_declared_at']) ?>
            <?php else: ?>
              Signed On: <?= htmlspecialchars($proposal['lead_signature_date'] ?? date('Y-m-d')) ?>
            <?php endif; ?>
            <?php if (!empty($proposal['lead_ip_address'])): ?>
              &bull; IP: <?= htmlspecialchars($proposal['lead_ip_address']) ?>
            <?php endif; ?>
          </div>
        </div>
        <div class="signature-stamp-box" style="border-color: #064e3b;">
          <div class="signature-stamp-label">Team Lead Digital Signature</div>
          <div class="signature-stamp-value" style="color: #064e3b;"><?= htmlspecialchars($proposal['lead_signature_name'] ?? ($leadMember['full_name'] ?? 'Team Lead')) ?></div>
          <div class="signature-stamp-date"><?= htmlspecialchars($proposal['lead_signature_date'] ?? date('d/m/Y')) ?></div>
        </div>
      </div>
    </section>

    <!-- DOSSIER FOOTER -->
    <footer class="dossier-footer">
      <div>
        <strong>iTech Seed Grand Challenge 2027</strong> &bull; PSG iTech Launchpad: Innozone &amp; Startupzone
      </div>
      <div>
        Generated on <?= date('d M Y, h:i A') ?> &bull; Ref: <?= htmlspecialchars($team['application_no']) ?>
      </div>
    </footer>
  </main>

</body>
</html>
