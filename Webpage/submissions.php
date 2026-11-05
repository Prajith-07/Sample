<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!empty($_SESSION['must_change_password'])) {
    header("Location: change_password.php?first_login=1");
    exit;
}
/**
 * iTech Seed Grand Challenge 2027
 * Registrations & Submissions Management Viewer
 * For Launchpad Coordinators (Innozone & Startupzone)
 */
require_once __DIR__ . '/config/db.php';

$pdo = getDatabaseConnection();
$teams = [];
$subscribers = [];
$totalTeams = 0;
$totalSubscribers = 0;
$dbStatus = 'Connected to MySQL';
$dbConnected = true;

if ($pdo) {
    try {
        // Fetch Teams with their members, mentors, and proposals
        $stmtTeams = $pdo->query("
            SELECT t.*, 
                   lead_m.full_name AS lead_name,
                   lead_m.department AS lead_dept,
                   lead_m.college_email AS lead_email,
                   lead_m.phone_number AS lead_phone,
                   lead_m.roll_number AS lead_roll,
                   p.project_title,
                   p.poc_type,
                   p.trl_level,
                   p.ip_status,
                   p.prior_funding,
                   p.deliverables,
                   p.budget_requested,
                   p.target_users,
                   p.lead_signature_name,
                   p.lead_declared_at
            FROM `teams` t
            LEFT JOIN `team_members` lead_m ON t.id = lead_m.team_id AND lead_m.is_lead = 1
            LEFT JOIN `proposals` p ON t.id = p.team_id
            ORDER BY t.created_at DESC
        ");
        $teams = $stmtTeams->fetchAll();
        $totalTeams = count($teams);

        // Fetch Alert Subscribers
        $stmtSubs = $pdo->query("SELECT * FROM `registration_alerts` ORDER BY `created_at` DESC");
        $subscribers = $stmtSubs->fetchAll();
        $totalSubscribers = count($subscribers);

    } catch (PDOException $e) {
        $dbStatus = "Query error: " . $e->getMessage();
    }
} else {
    $dbConnected = false;
    $dbStatus = "Database offline (Start MySQL in XAMPP)";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Coordinator Dashboard | iTech Seed Grand Challenge 2027</title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
  
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
    .admin-container {
      max-width: 1200px;
      margin: 2rem auto;
      padding: 0 1.5rem;
    }
    .admin-header-card {
      background: var(--card-bg);
      border: 1.5px solid var(--card-border);
      border-radius: var(--radius-lg);
      padding: 2rem;
      margin-bottom: 2rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 1.25rem;
    }
    .status-badge-ok {
      background: #dcfce7;
      color: #15803d;
      padding: 0.35rem 0.85rem;
      border-radius: var(--radius-full);
      font-size: 0.8rem;
      font-weight: 700;
    }
    .status-badge-err {
      background: #fee2e2;
      color: #b91c1c;
      padding: 0.35rem 0.85rem;
      border-radius: var(--radius-full);
      font-size: 0.8rem;
      font-weight: 700;
    }
    .tab-nav-bar {
      display: flex;
      gap: 1rem;
      margin-bottom: 1.5rem;
    }
    .tab-btn {
      background: rgba(255, 255, 255, 0.8);
      border: 1.5px solid rgba(22, 163, 74, 0.2);
      padding: 0.75rem 1.5rem;
      border-radius: var(--radius-full);
      font-family: var(--font-heading);
      font-weight: 700;
      color: var(--text-forest);
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      transition: all 0.2s ease;
    }
    .tab-btn.active {
      background: var(--clover-green);
      color: #ffffff;
      border-color: var(--clover-green);
      box-shadow: 0 4px 14px rgba(22, 163, 74, 0.3);
    }
    .data-table-wrapper {
      background: var(--card-bg);
      border: 1.5px solid var(--card-border);
      border-radius: var(--radius-lg);
      overflow-x: auto;
      box-shadow: var(--shadow-sm);
      margin-bottom: 3rem;
    }
    .data-table {
      width: 100%;
      border-collapse: collapse;
      text-align: left;
      font-size: 0.9rem;
    }
    .data-table th {
      background: #f0fdf4;
      color: var(--text-forest);
      font-family: var(--font-heading);
      font-weight: 700;
      padding: 1rem 1.25rem;
      border-bottom: 1.5px solid var(--card-border);
    }
    .data-table td {
      padding: 1rem 1.25rem;
      border-bottom: 1px solid rgba(0,0,0,0.06);
      color: var(--text-body);
    }
    .data-table tr:hover {
      background: rgba(34, 197, 94, 0.04);
    }
    .app-badge {
      font-family: var(--font-mono-tech);
      font-weight: 700;
      color: var(--clover-green);
      background: rgba(22, 163, 74, 0.1);
      padding: 0.25rem 0.6rem;
      border-radius: var(--radius-sm);
    }
    .diversity-badge-ok {
      background: #dcfce7;
      color: #15803d;
      font-size: 0.78rem;
      font-weight: 700;
      padding: 0.25rem 0.65rem;
      border-radius: var(--radius-full);
      display: inline-flex;
      align-items: center;
      gap: 0.3rem;
    }
    .diversity-badge-fail {
      background: #fee2e2;
      color: #b91c1c;
      font-size: 0.78rem;
      font-weight: 700;
      padding: 0.25rem 0.65rem;
      border-radius: var(--radius-full);
    }
  </style>
</head>
<body>
  <div class="admin-container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
      <a href="register.php" style="color: var(--clover-green); text-decoration: none; font-weight: 700;">← Registration Portal</a>
      <div style="display: flex; gap: 0.75rem; align-items: center;">
        <a href="/phpmyadmin/index.php?route=/database/structure&db=itech_seed_2027" target="_blank" style="background: rgba(22, 163, 74, 0.1); color: var(--text-forest); border: 1.5px solid rgba(22, 163, 74, 0.3); text-decoration: none; font-weight: 700; padding: 0.5rem 1.25rem; border-radius: var(--radius-full); font-size: 0.9rem;">
          Database (phpMyAdmin) ➔
        </a>
        <a href="register.php" style="background: var(--clover-green); color: white; text-decoration: none; font-weight: 700; padding: 0.5rem 1.25rem; border-radius: var(--radius-full); font-size: 0.9rem;">
          + Open Registration Portal
        </a>
        <button class="theme-toggle" type="button" aria-pressed="false" aria-label="Switch to dark mode" style="width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: var(--surface); border: 1px solid var(--line-strong); color: var(--text); cursor: pointer;">
          <svg class="theme-toggle__sun" viewBox="0 0 24 24" width="20" height="20" fill="none" aria-hidden="true">
            <circle cx="12" cy="12" r="3.5" stroke="currentColor" stroke-width="1.6"/>
            <path d="M12 2.5v2M12 19.5v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2.5 12h2m15 0h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
          </svg>
          <svg class="theme-toggle__moon" viewBox="0 0 24 24" width="20" height="20" fill="none" aria-hidden="true">
            <path d="M19.5 15.1A8 8 0 0 1 8.9 4.5 8.1 8.1 0 1 0 19.5 15.1Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="m16.8 4.5.4 1.2 1.3.4-1.3.4-.4 1.3-.4-1.3-1.3-.4 1.3-.4.4-1.2Z" fill="currentColor"/>
          </svg>
        </button>
      </div>
    </div>

    <div class="admin-header-card">
      <div>
        <span class="nature-tag">PSG iTech Launchpad • Innozone & Startupzone</span>
        <h1 style="font-family: var(--font-heading); font-size: 1.85rem; color: var(--text-forest); margin-top: 0.25rem;">
          Coordinator Management Portal
        </h1>
        <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 0.25rem;">
          Registered Teams: <strong><?= (int)$totalTeams ?></strong> &nbsp;|&nbsp; 
          Early Alert Inquiries: <strong><?= (int)$totalSubscribers ?></strong>
        </p>
      </div>
      <div>
        <span class="<?= $dbConnected ? 'status-badge-ok' : 'status-badge-err' ?>">
          ● <?= htmlspecialchars($dbStatus) ?>
        </span>
      </div>
    </div>

    <!-- TABS -->
    <div class="tab-nav-bar">
      <button class="tab-btn active" onclick="showTab('teamsTab', this)">
        Registered Teams (<?= (int)$totalTeams ?>)
      </button>
      <button class="tab-btn" onclick="showTab('subscribersTab', this)">
        Early Alert List (<?= (int)$totalSubscribers ?>)
      </button>
    </div>

    <!-- TAB 1: REGISTERED TEAMS -->
    <div id="teamsTab" class="data-table-wrapper">
      <?php if (empty($teams)): ?>
        <div style="padding: 3.5rem; text-align: center; color: var(--text-muted);">
          <p style="font-size: 1.2rem; margin-bottom: 0.5rem; color: var(--text-forest); font-weight: 700;">No challenge teams registered yet.</p>
          <p style="font-size: 0.9rem; max-width: 500px; margin: 0 auto 1.5rem;">
            When students fill out Step 1 (Team & Account Details), their teams and mentor links will appear here with full roster details.
          </p>
          <a href="register.php" class="btn-save-step" style="text-decoration: none; display: inline-flex;">
            Register First Team ➔
          </a>
        </div>
      <?php else: ?>
        <table class="data-table">
          <thead>
            <tr>
              <th>App Reference</th>
              <th>Team Name</th>
              <th>Team Lead</th>
              <th>Dept / Roll No</th>
              <th>Team Size</th>
              <th>Diversity</th>
              <th>Mentors</th>
              <th>Status</th>
              <th>Date Registered</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($teams as $t): 
                // Fetch members for this team
                $stmtM = $pdo->prepare("SELECT full_name, roll_number, gender, is_lead FROM `team_members` WHERE team_id = :tid ORDER BY member_order ASC");
                $stmtM->execute([':tid' => $t['id']]);
                $teamMembers = $stmtM->fetchAll();

                // Fetch mentors
                $stmtMen = $pdo->prepare("SELECT name, mentor_type, organisation, approval_status, approval_token FROM `team_mentors` WHERE team_id = :tid");
                $stmtMen->execute([':tid' => $t['id']]);
                $teamMentors = $stmtMen->fetchAll();
            ?>
              <tr>
                <td><span class="app-badge"><?= htmlspecialchars($t['application_no']) ?></span></td>
                <td>
                  <strong><?= htmlspecialchars($t['team_name']) ?></strong>
                  <?php if (!empty($t['project_title'])): ?>
                    <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">
                      Idea: <strong><?= htmlspecialchars($t['project_title']) ?></strong>
                      <span class="file-size-tag-nature" style="font-size: 0.72rem; padding: 0.15rem 0.45rem;"><?= htmlspecialchars($t['poc_type'] ?? '') ?></span>
                      <?php if (!empty($t['trl_level'])): ?>
                        <span class="file-size-tag-nature" style="font-size: 0.72rem; padding: 0.15rem 0.45rem; background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe;">TRL <?= (int)$t['trl_level'] ?></span>
                      <?php endif; ?>
                      <?php if (!empty($t['ip_status']) && $t['ip_status'] !== 'None'): ?>
                        <span class="file-size-tag-nature" style="font-size: 0.72rem; padding: 0.15rem 0.45rem; background: #fdf4ff; color: #86198f; border: 1px solid #f5d0fe;"><?= htmlspecialchars($t['ip_status']) ?></span>
                      <?php endif; ?>
                      <?php if (!empty($t['deliverables'])): ?>
                        <span class="file-size-tag-nature" style="font-size: 0.72rem; padding: 0.15rem 0.45rem; background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0;">PoC Plan Set</span>
                      <?php endif; ?>
                      <?php if (!empty($t['budget_requested']) && (float)$t['budget_requested'] > 0): ?>
                        <span class="file-size-tag-nature" style="font-size: 0.72rem; padding: 0.15rem 0.45rem; background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0;">Budget: ₹<?= number_format((float)$t['budget_requested'], 0) ?></span>
                      <?php endif; ?>
                      <?php if (!empty($t['target_users'])): ?>
                        <span class="file-size-tag-nature" style="font-size: 0.72rem; padding: 0.15rem 0.45rem; background: #fdf2f8; color: #9d174d; border: 1px solid #fbcfe8;">Market Set</span>
                      <?php endif; ?>
                      <?php if (!empty($t['lead_signature_name'])): ?>
                        <span class="file-size-tag-nature" style="font-size: 0.72rem; padding: 0.15rem 0.45rem; background: #f0fdf4; color: #166534; border: 1px solid #86efac;">Lead Signed</span>
                      <?php endif; ?>
                    </div>
                  <?php else: ?>
                    <div style="font-size: 0.78rem; color: #d97706; margin-top: 0.25rem;">
                      Step 2: Idea pending
                    </div>
                  <?php endif; ?>
                  <div style="margin-top: 0.35rem; display: flex; gap: 0.4rem; flex-wrap: wrap;">
                    <a href="register.php?step=2&team_id=<?= (int)$t['id'] ?>" style="font-size: 0.72rem; color: var(--clover-green); font-weight: 600; text-decoration: none;">
                      Step 2 ➔
                    </a>
                    <a href="register.php?step=3&team_id=<?= (int)$t['id'] ?>" style="font-size: 0.72rem; color: #2563eb; font-weight: 600; text-decoration: none;">
                      Step 3 ➔
                    </a>
                    <a href="register.php?step=4&team_id=<?= (int)$t['id'] ?>" style="font-size: 0.72rem; color: #7c3aed; font-weight: 600; text-decoration: none;">
                      Step 4 ➔
                    </a>
                    <a href="register.php?step=5&team_id=<?= (int)$t['id'] ?>" style="font-size: 0.72rem; color: #059669; font-weight: 600; text-decoration: none;">
                      Step 5 ➔
                    </a>
                    <a href="register.php?step=6&team_id=<?= (int)$t['id'] ?>" style="font-size: 0.72rem; color: #db2777; font-weight: 600; text-decoration: none;">
                      Step 6 ➔
                    </a>
                    <a href="register.php?step=7&team_id=<?= (int)$t['id'] ?>" style="font-size: 0.72rem; color: #15803d; font-weight: 700; text-decoration: none;">
                      Step 7 (Approval) ➔
                    </a>
                  </div>
                </td>
                <td>
                  <strong><?= htmlspecialchars($t['lead_name'] ?: '—') ?></strong><br>
                  <a href="mailto:<?= htmlspecialchars($t['lead_email']) ?>" style="color: var(--clover-green); font-size: 0.8rem;"><?= htmlspecialchars($t['lead_email']) ?></a>
                </td>
                <td>
                  <span style="font-size: 0.85rem;"><?= htmlspecialchars($t['lead_dept'] ?: '—') ?></span><br>
                  <span style="font-family: var(--font-mono-tech); font-size: 0.8rem; color: var(--text-muted);"><?= htmlspecialchars($t['lead_roll'] ?: '—') ?></span>
                </td>
                <td>
                  <span class="nature-chip">
                    <?= (int)$t['team_size'] ?> Members
                  </span>
                </td>
                <td>
                  <?php if (!empty($t['has_woman_member'])): ?>
                    <span class="diversity-badge-ok">
                      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
                      Woman Member
                    </span>
                  <?php else: ?>
                    <span style="font-size: 0.8rem; color: var(--text-muted, #64748b);">—</span>
                  <?php endif; ?>
                </td>
                <td>
                  <div style="font-size: 0.8rem;">
                    <?php foreach ($teamMentors as $m): 
                      $mStatus = $m['approval_status'] ?? 'Pending';
                    ?>
                      <div style="margin-bottom: 0.35rem;">
                        <strong><?= $m['mentor_type'] === 'industry' ? 'Industry: ' : 'Faculty: ' ?></strong>
                        <?= htmlspecialchars($m['name']) ?> 
                        <?php if ($mStatus === 'Approved'): ?>
                          <span style="font-size: 0.7rem; color: #15803d; font-weight: 700; background: #dcfce7; padding: 0.1rem 0.35rem; border-radius: 4px;">✓ Approved</span>
                        <?php elseif ($mStatus === 'Declined'): ?>
                          <span style="font-size: 0.7rem; color: #b91c1c; font-weight: 700; background: #fee2e2; padding: 0.1rem 0.35rem; border-radius: 4px;">✕ Declined</span>
                        <?php else: ?>
                          <span style="font-size: 0.7rem; color: #b45309; font-weight: 600; background: #fef3c7; padding: 0.1rem 0.35rem; border-radius: 4px;">Pending</span>
                          <?php if (!empty($m['approval_token'])): ?>
                            <a href="mentor_approval.php?token=<?= urlencode($m['approval_token']) ?>" target="_blank" style="font-size: 0.68rem; color: var(--clover-green); text-decoration: underline;">(Link)</a>
                          <?php endif; ?>
                        <?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </td>
                <td>
                  <span class="file-size-tag-nature">
                    <?= htmlspecialchars($t['status']) ?>
                  </span>
                </td>
                <td style="font-size: 0.85rem;">
                  <?= htmlspecialchars(date('M d, Y h:i A', strtotime($t['created_at']))) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <!-- TAB 2: EARLY ALERT SUBSCRIBERS -->
    <div id="subscribersTab" class="data-table-wrapper" style="display: none;">
      <?php if (empty($subscribers)): ?>
        <div style="padding: 3rem; text-align: center; color: var(--text-muted);">
          <p style="font-size: 1.1rem; margin-bottom: 0.5rem;">No applicant alert inquiries recorded yet.</p>
        </div>
      <?php else: ?>
        <table class="data-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Full Name</th>
              <th>Email Address</th>
              <th>Phone</th>
              <th>Department</th>
              <th>Year</th>
              <th>Role</th>
              <th>Date Registered</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($subscribers as $index => $row): ?>
              <tr>
                <td><?= $index + 1 ?></td>
                <td><strong><?= htmlspecialchars($row['full_name']) ?></strong></td>
                <td><a href="mailto:<?= htmlspecialchars($row['email']) ?>" style="color: var(--clover-green);"><?= htmlspecialchars($row['email']) ?></a></td>
                <td><?= htmlspecialchars($row['phone'] ?: '—') ?></td>
                <td><?= htmlspecialchars($row['department']) ?></td>
                <td><span class="file-size-tag-nature"><?= htmlspecialchars($row['year_of_study']) ?> Year</span></td>
                <td><span class="nature-chip"><?= htmlspecialchars($row['interest_role']) ?></span></td>
                <td><?= htmlspecialchars(date('M d, Y h:i A', strtotime($row['created_at']))) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <script>
    function showTab(tabId, btn) {
      document.getElementById('teamsTab').style.display = 'none';
      document.getElementById('subscribersTab').style.display = 'none';
      document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));

      document.getElementById(tabId).style.display = 'block';
      btn.classList.add('active');
    }

    // Theme Toggle Handler
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

      const initialTheme = root.dataset.theme || 'light';
      applyTheme(initialTheme);

      if (themeToggle) {
        themeToggle.addEventListener('click', () => {
          const currentTheme = root.dataset.theme === 'dark' ? 'dark' : 'light';
          const nextTheme = currentTheme === 'dark' ? 'light' : 'dark';
          applyTheme(nextTheme, true);
        });
      }
    })();
  </script>
</body>
</html>
