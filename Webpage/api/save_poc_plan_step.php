<?php
/**
 * API: Save Step 4 (PoC Plan & 6-Month Milestones)
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
$deliverables = trim($data['deliverables'] ?? '');
$testMethod = trim($data['test_method'] ?? '');
$successRate = trim($data['success_rate'] ?? '');
$milestones = isset($data['milestones']) && is_array($data['milestones']) ? $data['milestones'] : [];

$errors = [];

// 1. Verify Team Existence
if ($teamId <= 0) {
    $errors[] = "Valid Team ID is required. Please complete Step 1 first.";
} else {
    $stmtTeam = $pdo->prepare("SELECT id, team_name, current_step FROM `teams` WHERE id = :tid");
    $stmtTeam->execute([':tid' => $teamId]);
    $teamRow = $stmtTeam->fetch();
    if (!$teamRow) {
        $errors[] = "Team record not found. Please register your team in Step 1 first.";
    }
}

// 2. Validate Deliverables (What exists at the end of the grant)
if (empty($deliverables)) {
    $errors[] = "Deliverables: Please describe what tangible physical or digital assets will exist at the conclusion of the grant.";
} elseif (mb_strlen($deliverables) < 15) {
    $errors[] = "Deliverables: Please provide a descriptive summary of your end deliverables (minimum 15 characters).";
}

// 3. Validate Test Method
if (empty($testMethod)) {
    $errors[] = "Test Method: Please describe the testing methodology and operational benchmarks to validate your PoC.";
} elseif (mb_strlen($testMethod) < 15) {
    $errors[] = "Test Method: Please provide a descriptive summary of your test methodology (minimum 15 characters).";
}

// 4. Validate Success Rate / Success Measure
if (empty($successRate)) {
    $errors[] = "Success Rate / Measure: Please provide measurable numbers or performance metrics demonstrating PoC success.";
} elseif (mb_strlen($successRate) < 10) {
    $errors[] = "Success Rate / Measure: Please provide clear metrics or criteria (minimum 10 characters).";
}

// 5. Validate 6-Month Milestone Rows
$standardMonths = [
    1 => 'Month 1 (Jan 2027)',
    2 => 'Month 2 (Feb 2027)',
    3 => 'Month 3 (Mar 2027): Milestone 1 Review',
    4 => 'Month 4 (Apr 2027)',
    5 => 'Month 5 (May 2027)',
    6 => 'Month 6 (Jun 2027): Demo Day and Closure'
];

// Normalize milestone input: handles 1-indexed map, 0-indexed list, or items with explicit 'month_number'
$keyedMilestones = [];
foreach ($milestones as $k => $item) {
    if (is_array($item)) {
        if (!empty($item['month_number']) && (int)$item['month_number'] >= 1 && (int)$item['month_number'] <= 6) {
            $keyedMilestones[(int)$item['month_number']] = $item;
        } elseif (is_numeric($k) && (int)$k >= 1 && (int)$k <= 6) {
            $keyedMilestones[(int)$k] = $item;
        } elseif (is_numeric($k) && (int)$k >= 0 && (int)$k <= 5) {
            $keyedMilestones[(int)$k + 1] = $item;
        }
    }
}

$sanitizedMilestones = [];

for ($m = 1; $m <= 6; $m++) {
    $row = $keyedMilestones[$m] ?? null;
    $mLabel = $standardMonths[$m];
    
    $mDesc = '';
    $mOutput = '';
    $mVerify = '';
    
    if ($row && is_array($row)) {
        $mDesc = trim($row['milestone_desc'] ?? '');
        $mOutput = trim($row['output_deliverable'] ?? '');
        $mVerify = trim($row['verification_method'] ?? '');
    }
    
    // Check missing fields for each month
    if (empty($mDesc)) {
        $errors[] = "Milestone Table ($mLabel): Milestone description is required.";
    }
    if (empty($mOutput)) {
        $errors[] = "Milestone Table ($mLabel): Tangible output or deliverable is required.";
    }
    if (empty($mVerify)) {
        $errors[] = "Milestone Table ($mLabel): Verification method is required.";
    }
    
    $sanitizedMilestones[$m] = [
        'month_number' => $m,
        'month_label' => $mLabel,
        'milestone_desc' => $mDesc,
        'output_deliverable' => $mOutput,
        'verification_method' => $mVerify
    ];
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Validation failed. Please correct the highlighted entries.',
        'errors' => $errors
    ]);
    exit;
}

// 6. Database Transaction
try {
    $pdo->beginTransaction();

    // Check if proposal row exists
    $stmtCheck = $pdo->prepare("SELECT id FROM `proposals` WHERE team_id = :tid");
    $stmtCheck->execute([':tid' => $teamId]);
    $propRow = $stmtCheck->fetch();

    if ($propRow) {
        $stmtUp = $pdo->prepare("
            UPDATE `proposals`
            SET `deliverables` = :deliv,
                `test_method` = :test,
                `success_rate` = :succ,
                `updated_at` = NOW()
            WHERE `team_id` = :tid
        ");
        $stmtUp->execute([
            ':deliv' => $deliverables,
            ':test' => $testMethod,
            ':succ' => $successRate,
            ':tid' => $teamId
        ]);
    } else {
        $stmtIn = $pdo->prepare("
            INSERT INTO `proposals` (
                `team_id`, `deliverables`, `test_method`, `success_rate`, `created_at`, `updated_at`
            ) VALUES (
                :tid, :deliv, :test, :succ, NOW(), NOW()
            )
        ");
        $stmtIn->execute([
            ':tid' => $teamId,
            ':deliv' => $deliverables,
            ':test' => $testMethod,
            ':succ' => $successRate
        ]);
    }

    // Upsert 6 Milestone Rows
    $stmtUpsertMilestone = $pdo->prepare("
        INSERT INTO `proposal_milestones` (
            `team_id`, `month_number`, `month_label`, `milestone_desc`, `output_deliverable`, `verification_method`, `created_at`, `updated_at`
        ) VALUES (
            :tid, :mnum, :mlabel, :mdesc, :moutput, :mverify, NOW(), NOW()
        )
        ON DUPLICATE KEY UPDATE
            `month_label` = VALUES(`month_label`),
            `milestone_desc` = VALUES(`milestone_desc`),
            `output_deliverable` = VALUES(`output_deliverable`),
            `verification_method` = VALUES(`verification_method`),
            `updated_at` = NOW()
    ");

    foreach ($sanitizedMilestones as $mRow) {
        $stmtUpsertMilestone->execute([
            ':tid' => $teamId,
            ':mnum' => $mRow['month_number'],
            ':mlabel' => $mRow['month_label'],
            ':mdesc' => $mRow['milestone_desc'],
            ':moutput' => $mRow['output_deliverable'],
            ':mverify' => $mRow['verification_method']
        ]);
    }

    // Advance team step if needed (Step 4 completed)
    $stmtStep = $pdo->prepare("
        UPDATE `teams`
        SET `current_step` = GREATEST(`current_step`, 5),
            `updated_at` = NOW()
        WHERE `id` = :tid
    ");
    $stmtStep->execute([':tid' => $teamId]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Step 4 (PoC Plan & Milestones) saved successfully.',
        'team_id' => $teamId,
        'next_step' => 5
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Step 4 Save Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'A database error occurred while saving the PoC Plan & Milestones: ' . $e->getMessage()
    ]);
}
