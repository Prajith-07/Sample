<?php
/**
 * API: Save Step 2 (The Idea - Innovation & Problem Definition)
 * Validates word counts and saves to MySQL 'proposals' table
 * iTech Seed Grand Challenge 2027
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
        'message' => 'Database service currently offline. Please ensure MySQL is started in XAMPP.'
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
$projectTitle = trim($data['project_title'] ?? '');
$problemStatement = trim($data['problem_statement'] ?? '');
$existingSolutions = trim($data['existing_solutions'] ?? '');
$proposedSolution = trim($data['proposed_solution'] ?? '');
$pocType = trim($data['poc_type'] ?? '');

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

// 2. Project Title Validation
if (empty($projectTitle)) {
    $errors[] = "Project Title is mandatory.";
} elseif (strlen($projectTitle) < 5) {
    $errors[] = "Project Title must be at least 5 characters long.";
}

// Helper: Count characters excluding all whitespace (spaces, tabs, newlines)
function countNonSpaceChars($text) {
    return strlen(preg_replace('/\s+/', '', (string)$text));
}

// 3. Problem Statement Validation (150 to 250 characters, excluding spaces)
$probChars = countNonSpaceChars($problemStatement);
if ($probChars < 150) {
    $errors[] = "Problem Statement: Minimum characters not reached. Current: {$probChars} characters (excluding spaces). Minimum required: 150 characters.";
} elseif ($probChars > 250) {
    $errors[] = "Problem Statement: Maximum limit exceeded. Current: {$probChars} characters (excluding spaces). Maximum allowed: 250 characters.";
}

// 4. Existing Solutions & Gap Validation (100 to 200 characters, excluding spaces)
$existChars = countNonSpaceChars($existingSolutions);
if ($existChars < 100) {
    $errors[] = "Existing Solutions and the Gap: Minimum characters not reached. Current: {$existChars} characters (excluding spaces). Minimum required: 100 characters.";
} elseif ($existChars > 200) {
    $errors[] = "Existing Solutions and the Gap: Maximum limit exceeded. Current: {$existChars} characters (excluding spaces). Maximum allowed: 200 characters.";
}

// 5. Proposed Solution Validation (200 to 300 characters, excluding spaces)
$propChars = countNonSpaceChars($proposedSolution);
if ($propChars < 200) {
    $errors[] = "Proposed Solution: Minimum characters not reached. Current: {$propChars} characters (excluding spaces). Minimum required: 200 characters.";
} elseif ($propChars > 300) {
    $errors[] = "Proposed Solution: Maximum limit exceeded. Current: {$propChars} characters (excluding spaces). Maximum allowed: 300 characters.";
}

// 6. PoC Type Validation (Hardware / Software / Both)
$allowedPoc = ['Hardware', 'Software', 'Both'];
if (!in_array($pocType, $allowedPoc)) {
    $errors[] = "Type of PoC: Please select Hardware, Software, or Both Hardware & Software.";
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Please complete all required fields according to guidelines:',
        'errors' => $errors
    ]);
    exit;
}

// 7. Atomic Database Persistence
try {
    $pdo->beginTransaction();

    // Check if proposal record already exists for this team
    $stmtCheck = $pdo->prepare("SELECT id FROM `proposals` WHERE team_id = :tid");
    $stmtCheck->execute([':tid' => $teamId]);
    $existingProp = $stmtCheck->fetch();

    if ($existingProp) {
        $stmtUpdate = $pdo->prepare("
            UPDATE `proposals`
            SET `project_title` = :title,
                `poc_type` = :poc,
                `problem_statement` = :problem,
                `existing_solutions` = :existing,
                `proposed_solution` = :proposed,
                `updated_at` = NOW()
            WHERE `team_id` = :tid
        ");
        $stmtUpdate->execute([
            ':title' => $projectTitle,
            ':poc' => $pocType,
            ':problem' => $problemStatement,
            ':existing' => $existingSolutions,
            ':proposed' => $proposedSolution,
            ':tid' => $teamId
        ]);
    } else {
        $stmtInsert = $pdo->prepare("
            INSERT INTO `proposals`
            (`team_id`, `project_title`, `poc_type`, `problem_statement`, `existing_solutions`, `proposed_solution`)
            VALUES (:tid, :title, :poc, :problem, :existing, :proposed)
        ");
        $stmtInsert->execute([
            ':tid' => $teamId,
            ':title' => $projectTitle,
            ':poc' => $pocType,
            ':problem' => $problemStatement,
            ':existing' => $existingSolutions,
            ':proposed' => $proposedSolution
        ]);
    }

    // Update team current_step
    $stmtStep = $pdo->prepare("UPDATE `teams` SET `current_step` = GREATEST(`current_step`, 3) WHERE id = :tid");
    $stmtStep->execute([':tid' => $teamId]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => "Step 2 (The Idea) saved successfully.",
        'team_id' => $teamId,
        'next_step' => 3
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error while saving idea details: ' . $e->getMessage()
    ]);
}
