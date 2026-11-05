<?php
/**
 * iTech Seed Grand Challenge 2027
 * API Endpoint: Save Step 6 - Market Potential & Societal Impact
 * PSG iTech Launchpad: Innozone & Startupzone
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed. Use POST.']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

// ── Parse JSON body ──────────────────────────────────────────────────────────
$rawBody = file_get_contents('php://input');
$data    = json_decode($rawBody, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

// ── Extract & sanitise fields ────────────────────────────────────────────────
$teamId                 = isset($data['team_id'])                 ? (int)$data['team_id']                                 : 0;
$targetUsers            = isset($data['target_users'])            ? trim((string)$data['target_users'])                   : '';
$marketNeedSize         = isset($data['market_need_size'])        ? trim((string)$data['market_need_size'])               : '';
$commercialisationRoute = isset($data['commercialisation_route']) ? trim((string)$data['commercialisation_route'])        : '';
$societalImpact         = isset($data['societal_impact'])         ? trim((string)$data['societal_impact'])                : '';

// ── Server-side validation ───────────────────────────────────────────────────
$errors = [];

if ($teamId <= 0) {
    $errors[] = 'Invalid or missing team_id.';
}
if (empty($targetUsers)) {
    $errors[] = 'Target Users or Customers is required.';
} elseif (mb_strlen($targetUsers) < 20) {
    $errors[] = 'Target Users description must be at least 20 characters.';
}
if (empty($marketNeedSize)) {
    $errors[] = 'Size of the Need is required.';
} elseif (mb_strlen($marketNeedSize) < 20) {
    $errors[] = 'Size of the Need description must be at least 20 characters.';
}
if (empty($commercialisationRoute)) {
    $errors[] = 'Route to Commercialisation is required.';
} elseif (mb_strlen($commercialisationRoute) < 20) {
    $errors[] = 'Route to Commercialisation description must be at least 20 characters.';
}
if (empty($societalImpact)) {
    $errors[] = 'Impact is required.';
} elseif (mb_strlen($societalImpact) < 20) {
    $errors[] = 'Impact description must be at least 20 characters.';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

// ── Database operations ───────────────────────────────────────────────────────
$pdo = getDatabaseConnection();

if (!$pdo) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

try {
    // Verify team exists
    $stmtCheck = $pdo->prepare("SELECT id FROM `teams` WHERE id = :tid LIMIT 1");
    $stmtCheck->execute([':tid' => $teamId]);
    $team = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$team) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Team not found. Please complete Step 1 first.']);
        exit;
    }

    // Check if proposal row exists for this team
    $stmtProp = $pdo->prepare("SELECT id FROM `proposals` WHERE team_id = :tid LIMIT 1");
    $stmtProp->execute([':tid' => $teamId]);
    $existingProposal = $stmtProp->fetch(PDO::FETCH_ASSOC);

    if ($existingProposal) {
        // UPDATE existing proposal row
        $stmtUpdate = $pdo->prepare("
            UPDATE `proposals`
            SET
                target_users            = :target_users,
                market_need_size        = :market_need_size,
                commercialisation_route = :commercialisation_route,
                societal_impact         = :societal_impact,
                updated_at              = NOW()
            WHERE team_id = :tid
        ");
        $stmtUpdate->execute([
            ':target_users'            => $targetUsers,
            ':market_need_size'        => $marketNeedSize,
            ':commercialisation_route' => $commercialisationRoute,
            ':societal_impact'         => $societalImpact,
            ':tid'                     => $teamId
        ]);
    } else {
        // INSERT a new proposal row (team skipped earlier steps — allow partial save)
        $stmtInsert = $pdo->prepare("
            INSERT INTO `proposals`
                (team_id, project_title, target_users, market_need_size, commercialisation_route, societal_impact, updated_at)
            VALUES
                (:tid, '', :target_users, :market_need_size, :commercialisation_route, :societal_impact, NOW())
        ");
        $stmtInsert->execute([
            ':tid'                     => $teamId,
            ':target_users'            => $targetUsers,
            ':market_need_size'        => $marketNeedSize,
            ':commercialisation_route' => $commercialisationRoute,
            ':societal_impact'         => $societalImpact
        ]);
    }

    // Advance team step tracker
    $stmtStep = $pdo->prepare("
        UPDATE `teams`
        SET current_step = GREATEST(current_step, 6)
        WHERE id = :tid
    ");
    $stmtStep->execute([':tid' => $teamId]);

    echo json_encode([
        'success'   => true,
        'message'   => 'Step 6 (Market & Impact) saved successfully.',
        'team_id'   => $teamId,
        'next_step' => 7
    ]);

} catch (PDOException $e) {
    error_log('save_market_step PDO error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
    exit;
}
