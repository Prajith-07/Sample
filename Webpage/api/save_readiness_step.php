<?php
/**
 * API: Save Step 3 (Readiness - TRL, Work Done, Prior Funding & IP Status)
 * iTech Seed Grand Challenge 2027
 * PSG iTech Launchpad: Innozone & Startupzone
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$pdo = getDatabaseConnection();
if (!$pdo) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'message' => 'Database service offline. Please ensure MySQL is running in XAMPP.'
    ]);
    exit;
}

// Read JSON input or POST form data
$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);
if (!$data) {
    $data = $_POST;
}

$teamId = isset($data['team_id']) ? (int)$data['team_id'] : 0;
$trlLevel = isset($data['trl_level']) ? (int)$data['trl_level'] : 0;
$workDone = trim($data['work_done_so_far'] ?? '');
$fundingHas = trim($data['prior_funding_has'] ?? 'No');
$fundingAgency = trim($data['prior_funding_agency'] ?? '');
$fundingAmount = trim($data['prior_funding_amount'] ?? '');
$fundingOverlap = trim($data['prior_funding_overlap'] ?? '');
$ipStatus = trim($data['ip_status'] ?? '');

$errors = [];

// 1. Verify Team Existence
if ($teamId <= 0) {
    $errors[] = "Valid Team ID is required. Please complete Step 1 first.";
} else {
    $stmtTeam = $pdo->prepare("SELECT id, team_name FROM `teams` WHERE id = :tid");
    $stmtTeam->execute([':tid' => $teamId]);
    $teamRow = $stmtTeam->fetch();
    if (!$teamRow) {
        $errors[] = "Team record not found. Please register your team in Step 1 first.";
    }
}

// 2. TRL Level Validation (1 to 9)
if ($trlLevel < 1 || $trlLevel > 9) {
    $errors[] = "Technology Readiness Level (TRL): Please select a valid level between 1 and 9.";
}

// 3. Work Done So Far Validation
if (empty($workDone)) {
    $errors[] = "Work Done So Far: This field is mandatory. Please describe simulations, prototypes, surveys, or literature reviewed.";
} elseif (strlen($workDone) < 15) {
    $errors[] = "Work Done So Far: Please provide a slightly more detailed summary (at least 15 characters).";
}

// 4. Prior Funding / Prize Validation
if (!in_array($fundingHas, ['No', 'Yes'])) {
    $fundingHas = 'No';
}

if ($fundingHas === 'Yes') {
    if (empty($fundingAgency)) {
        $errors[] = "Prior Prize or Funding: Funding Agency or Competition Name is required when 'Yes' is selected.";
    }
    if (empty($fundingAmount)) {
        $errors[] = "Prior Prize or Funding: Amount received is required when 'Yes' is selected.";
    }
    if (empty($fundingOverlap)) {
        $errors[] = "Prior Prize or Funding: Please provide details regarding scope and budget overlap with this grant.";
    } elseif (strlen($fundingOverlap) < 10) {
        $errors[] = "Prior Prize or Funding: Please provide a clearer explanation of work overlap (minimum 10 characters).";
    }
} else {
    // Reset if No
    $fundingAgency = null;
    $fundingAmount = null;
    $fundingOverlap = null;
}

// 5. IP Status Validation
$validIpOptions = [
    'None',
    'Prior or third-party IP',
    'Patent search done',
    'Filing done'
];

if (!in_array($ipStatus, $validIpOptions)) {
    $errors[] = "Intellectual Property (IP) Status: Please select one of the allowed options ('None', 'Prior or third-party IP', 'Patent search done', or 'Filing done').";
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Please review and correct the following fields:',
        'errors' => $errors
    ]);
    exit;
}

// Compile human-readable prior_funding summary string
$priorFundingSummary = ($fundingHas === 'Yes')
    ? "Yes - ₹{$fundingAmount} from {$fundingAgency}"
    : 'None';

// 6. Atomic Database Persistence
try {
    $pdo->beginTransaction();

    // Check if proposal row exists
    $stmtCheck = $pdo->prepare("SELECT id FROM `proposals` WHERE team_id = :tid");
    $stmtCheck->execute([':tid' => $teamId]);
    $existingProp = $stmtCheck->fetch();

    if ($existingProp) {
        $stmtUpdate = $pdo->prepare("
            UPDATE `proposals`
            SET `trl_level` = :trl,
                `work_done_so_far` = :work,
                `prior_funding_has` = :fhas,
                `prior_funding_agency` = :fagency,
                `prior_funding_amount` = :famount,
                `prior_funding_overlap` = :foverlap,
                `prior_funding` = :fsummary,
                `ip_status` = :ip,
                `updated_at` = NOW()
            WHERE `team_id` = :tid
        ");
        $stmtUpdate->execute([
            ':trl' => $trlLevel,
            ':work' => $workDone,
            ':fhas' => $fundingHas,
            ':fagency' => $fundingAgency,
            ':famount' => $fundingAmount,
            ':foverlap' => $fundingOverlap,
            ':fsummary' => $priorFundingSummary,
            ':ip' => $ipStatus,
            ':tid' => $teamId
        ]);
    } else {
        $stmtInsert = $pdo->prepare("
            INSERT INTO `proposals`
            (`team_id`, `project_title`, `poc_type`, `trl_level`, `work_done_so_far`,
             `prior_funding_has`, `prior_funding_agency`, `prior_funding_amount`, `prior_funding_overlap`,
             `prior_funding`, `ip_status`)
            VALUES (:tid, 'Untitled Project', 'Both', :trl, :work, :fhas, :fagency, :famount, :foverlap, :fsummary, :ip)
        ");
        $stmtInsert->execute([
            ':tid' => $teamId,
            ':trl' => $trlLevel,
            ':work' => $workDone,
            ':fhas' => $fundingHas,
            ':fagency' => $fundingAgency,
            ':famount' => $fundingAmount,
            ':foverlap' => $fundingOverlap,
            ':fsummary' => $priorFundingSummary,
            ':ip' => $ipStatus
        ]);
    }

    // Advance team step in teams table
    $stmtStep = $pdo->prepare("UPDATE `teams` SET `current_step` = GREATEST(`current_step`, 4) WHERE id = :tid");
    $stmtStep->execute([':tid' => $teamId]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Step 3 (Readiness) saved successfully.',
        'team_id' => $teamId,
        'next_step' => 4
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error while saving readiness details: ' . $e->getMessage()
    ]);
}
