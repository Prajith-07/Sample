<?php
/**
 * iTech Seed Grand Challenge 2027
 * API Endpoint: Save Step 7 - Declaration, E-Signature & Online Mentor Approval Dispatch
 * PSG iTech Launchpad: Innozone & Startupzone
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed. Use POST.']);
    exit;
}

// Authentication & Authorization Guard
$sessionTeamId = (int)($_SESSION['team_id'] ?? 0);
if ($sessionTeamId <= 0) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized: Please sign in to submit the declaration.'
    ]);
    exit;
}

if (empty($_SESSION['is_lead'])) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Only the designated Team Leader has authority to submit the final declaration.'
    ]);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../utils/mailer.php';

$rawBody = file_get_contents('php://input');
$data    = json_decode($rawBody, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

$payloadTeamId = isset($data['team_id']) ? (int)$data['team_id'] : 0;
if ($payloadTeamId > 0 && $payloadTeamId !== $sessionTeamId) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Forbidden: Team ID parameter tampering detected.'
    ]);
    exit;
}
$teamId = $sessionTeamId;

$pdo = getDatabaseConnection();
if (!$pdo) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

// Check if team is already submitted
$stmtSt = $pdo->prepare("SELECT status FROM `teams` WHERE id = :tid");
$stmtSt->execute([':tid' => $teamId]);
$teamStatus = $stmtSt->fetchColumn();
if ($teamStatus && in_array($teamStatus, ['Submitted', 'Under Screening', 'Approved', 'Completed'], true)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'This team application has already been submitted and cannot be modified.'
    ]);
    exit;
}

$leadSignature    = trim((string)($data['lead_signature_name'] ?? ''));
$leadSigDate      = trim((string)($data['lead_signature_date'] ?? ''));

// Declaration Checkboxes (All 5 are mandatory)
$decOriginality   = !empty($data['lead_declared_originality']) ? 1 : 0;
$decNoOverlap     = !empty($data['lead_declared_no_funding_overlap']) ? 1 : 0;
$decAgreement     = !empty($data['lead_declared_grant_agreement']) ? 1 : 0;
$decIpPolicy      = !empty($data['lead_declared_ip_policy']) ? 1 : 0;
$decMentorGuide   = !empty($data['lead_declared_mentor_guidance']) ? 1 : 0;

$clientIp         = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

// ── Validation ───────────────────────────────────────────────────────────────
$errors = [];

if ($teamId <= 0) {
    $errors[] = 'Invalid or missing team ID.';
}

if (!$decOriginality) {
    $errors[] = 'You must confirm that the idea is original and not plagiarised.';
}
if (!$decNoOverlap) {
    $errors[] = 'You must confirm that this PoC has not received undisclosed duplicate funding.';
}
if (!$decAgreement) {
    $errors[] = 'You must undertake to sign the grant agreement and register under DPIIT/Udyam.';
}
if (!$decIpPolicy) {
    $errors[] = 'You must agree to the institutional IPR filing policy in the names of inventors and PSG iTech.';
}
if (!$decMentorGuide) {
    $errors[] = 'You must confirm that your designated mentors have agreed to guide this project.';
}

// ── Database Operations ──────────────────────────────────────────────────────
$pdo = getDatabaseConnection();
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

try {
    // 1. Fetch team master record
    $stmtTeam = $pdo->prepare("SELECT * FROM `teams` WHERE id = :tid LIMIT 1");
    $stmtTeam->execute([':tid' => $teamId]);
    $team = $stmtTeam->fetch(PDO::FETCH_ASSOC);

    if (!$team) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Team not found.']);
        exit;
    }

    // 2. Fetch proposal & team lead
    $stmtProp = $pdo->prepare("SELECT * FROM `proposals` WHERE team_id = :tid LIMIT 1");
    $stmtProp->execute([':tid' => $teamId]);
    $proposal = $stmtProp->fetch(PDO::FETCH_ASSOC);

    $stmtLead = $pdo->prepare("SELECT full_name, college_email FROM `team_members` WHERE team_id = :tid AND is_lead = 1 LIMIT 1");
    $stmtLead->execute([':tid' => $teamId]);
    $leadMember = $stmtLead->fetch(PDO::FETCH_ASSOC);
    $leadName = $leadMember['full_name'] ?? '';

    // E-Signature Name and Date Verification
    if (!preg_match('/^(.+?)\s+(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})$/u', $leadSignature, $sigMatch)) {
        $errors[] = "Digital signature must be in format: Team_Lead_Name DD/MM/YYYY (e.g. " . ($leadName ?: 'Your Name') . " " . date('d/m/Y') . ").";
    } else {
        $typedName = trim($sigMatch[1]);
        $typedDate = trim($sigMatch[2]);

        // Check if typed name matches registered Team Lead
        $normTyped = strtolower(trim(preg_replace('/^(dr\.|mr\.|ms\.|mrs\.|prof\.)\s*/i', '', preg_replace('/\s+/', ' ', $typedName))));
        $normLead  = strtolower(trim(preg_replace('/^(dr\.|mr\.|ms\.|mrs\.|prof\.)\s*/i', '', preg_replace('/\s+/', ' ', $leadName))));

        if (!empty($normLead) && $normTyped !== $normLead) {
            $errors[] = "The signature name '{$typedName}' does not match your registered Team Lead name '{$leadName}'. You must sign with your own full name.";
        }

        // Check if date is valid and is TODAY'S DATE
        if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{2,4})$/', $typedDate, $dm)) {
            $day   = (int)$dm[1];
            $month = (int)$dm[2];
            $year  = (int)$dm[3];
            if ($year < 100) $year += 2000;

            if (!checkdate($month, $day, $year)) {
                $errors[] = "The signature date '{$typedDate}' is not a valid calendar date.";
            } else {
                $tz = new DateTimeZone('Asia/Kolkata');
                $today = (new DateTime('now', $tz))->format('Y-m-d');
                $enteredDate = sprintf('%04d-%02d-%02d', $year, $month, $day);

                if ($enteredDate !== $today) {
                    $todayDisplay = (new DateTime('now', $tz))->format('d/m/Y');
                    $errors[] = "Signature date must be today's date ({$todayDisplay}). You entered: {$typedDate}.";
                }
                $leadSigDate = $enteredDate;
            }
        } else {
            $errors[] = "Invalid signature date format. Must be DD/MM/YYYY.";
        }
    }

    if (!empty($errors)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }

    $projectTitle = $proposal['project_title'] ?? $team['team_name'];

    // 3. Save Lead Declaration into proposals table
    if ($proposal) {
        $stmtUpdateProp = $pdo->prepare("
            UPDATE `proposals`
            SET
                lead_signature_name              = :sig_name,
                lead_signature_date              = :sig_date,
                lead_declared_at                 = NOW(),
                lead_ip_address                  = :ip,
                lead_declared_originality        = :d_orig,
                lead_declared_no_funding_overlap = :d_over,
                lead_declared_grant_agreement    = :d_agree,
                lead_declared_ip_policy          = :d_ip,
                lead_declared_mentor_guidance    = :d_mg,
                submitted_at                     = IFNULL(submitted_at, NOW()),
                updated_at                       = NOW()
            WHERE team_id = :tid
        ");
        $stmtUpdateProp->execute([
            ':sig_name' => $leadSignature,
            ':sig_date' => $leadSigDate,
            ':ip'       => $clientIp,
            ':d_orig'   => $decOriginality,
            ':d_over'   => $decNoOverlap,
            ':d_agree'  => $decAgreement,
            ':d_ip'     => $decIpPolicy,
            ':d_mg'     => $decMentorGuide,
            ':tid'      => $teamId
        ]);
    } else {
        $stmtInsertProp = $pdo->prepare("
            INSERT INTO `proposals`
                (team_id, project_title, lead_signature_name, lead_signature_date, lead_declared_at, lead_ip_address,
                 lead_declared_originality, lead_declared_no_funding_overlap, lead_declared_grant_agreement,
                 lead_declared_ip_policy, lead_declared_mentor_guidance, submitted_at, updated_at)
            VALUES
                (:tid, :title, :sig_name, :sig_date, NOW(), :ip,
                 :d_orig, :d_over, :d_agree, :d_ip, :d_mg, NOW(), NOW())
        ");
        $stmtInsertProp->execute([
            ':tid'      => $teamId,
            ':title'    => $projectTitle,
            ':sig_name' => $leadSignature,
            ':sig_date' => $leadSigDate,
            ':ip'       => $clientIp,
            ':d_orig'   => $decOriginality,
            ':d_over'   => $decNoOverlap,
            ':d_agree'  => $decAgreement,
            ':d_ip'     => $decIpPolicy,
            ':d_mg'     => $decMentorGuide
        ]);
    }

    // 4. Update team status and wizard step
    $stmtUpdateTeam = $pdo->prepare("
        UPDATE `teams`
        SET current_step = 7,
            status = 'Submitted',
            updated_at = NOW()
        WHERE id = :tid
    ");
    $stmtUpdateTeam->execute([':tid' => $teamId]);

    // 5. Fetch team mentors & generate secure approval tokens
    $stmtMentors = $pdo->prepare("SELECT * FROM `team_mentors` WHERE team_id = :tid");
    $stmtMentors->execute([':tid' => $teamId]);
    $mentors = $stmtMentors->fetchAll(PDO::FETCH_ASSOC);

    $mailer = new LaunchpadMailer();
    $dispatchedMentors = [];

    foreach ($mentors as $mentor) {
        // Generate or retain secure single-use token
        $token = $mentor['approval_token'];
        if (empty($token) || $mentor['approval_status'] === 'Declined') {
            $token = bin2hex(random_bytes(32)); // 64 hex characters
        }

        // Send approval request email
        $mailResult = $mailer->sendMentorApprovalRequest(
            mentor: $mentor,
            team: $team,
            approvalToken: $token,
            leadName: $leadName,
            projectTitle: $projectTitle
        );

        // Update database with token & dispatch timestamp
        $stmtUpdateMentor = $pdo->prepare("
            UPDATE `team_mentors`
            SET approval_token = :token,
                approval_status = IF(approval_status = 'Approved', 'Approved', 'Pending'),
                email_sent_at = NOW()
            WHERE id = :mid
        ");
        $stmtUpdateMentor->execute([
            ':token' => $token,
            ':mid'   => $mentor['id']
        ]);

        $dispatchedMentors[] = [
            'id'              => (int)$mentor['id'],
            'name'            => $mentor['name'],
            'email'           => $mentor['email'],
            'mentor_type'     => $mentor['mentor_type'],
            'approval_status' => $mentor['approval_status'] ?? 'Pending',
            'approval_url'    => $mailResult['approval_url'] ?? '',
            'mail_status'     => $mailResult['mode'] ?? 'logged'
        ];
    }

    echo json_encode([
        'success'    => true,
        'message'    => 'Declaration and digital signature recorded. Official mentor approval links have been dispatched.',
        'team_id'    => $teamId,
        'mentors'    => $dispatchedMentors,
        'lead_name'  => $leadSignature,
        'signed_date'=> $leadSigDate
    ]);

} catch (Exception $e) {
    error_log('save_declaration_step error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An unexpected server error occurred: ' . $e->getMessage()]);
    exit;
}
