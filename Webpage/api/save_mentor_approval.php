<?php
/**
 * iTech Seed Grand Challenge 2027
 * API Endpoint: Save Online Mentor Consent Decision & E-Signature
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

$rawBody = file_get_contents('php://input');
$data    = json_decode($rawBody, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

$token         = trim((string)($data['token'] ?? ''));
$decision      = trim((string)($data['decision'] ?? ''));
$mentorSig     = trim((string)($data['mentor_signature'] ?? ''));
$declineReason = trim((string)($data['decline_reason'] ?? ''));

$consentAgreeGuide       = !empty($data['consent_agree_guide']) ? 1 : 0;
$consentCertifyProgress  = !empty($data['consent_certify_progress']) ? 1 : 0;
$consentOriginalIdea     = !empty($data['consent_original_idea']) ? 1 : 0;

$clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

if (empty($token) || strlen($token) < 32) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid or missing security token.']);
    exit;
}

if (!in_array($decision, ['Approved', 'Declined'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid decision value. Must be Approved or Declined.']);
    exit;
}

$pdo = getDatabaseConnection();
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

try {
    // 1. Locate mentor by token
    $stmtMentor = $pdo->prepare("SELECT * FROM `team_mentors` WHERE approval_token = :tok LIMIT 1");
    $stmtMentor->execute([':tok' => $token]);
    $mentor = $stmtMentor->fetch(PDO::FETCH_ASSOC);

    if (!$mentor) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Invalid, expired, or previously used endorsement link.']);
        exit;
    }

    $mentorId = (int)$mentor['id'];
    $teamId   = (int)$mentor['team_id'];
    $isFaculty = in_array($mentor['mentor_type'], ['faculty', 'additional_faculty'], true);

    // 2. Validate decision-specific fields
    $errors = [];

    if ($decision === 'Approved') {
        if (!$consentAgreeGuide) {
            $errors[] = 'You must tick the consent box agreeing to guide this team.';
        }
        if ($mentor['mentor_type'] === 'faculty' && !$consentCertifyProgress) {
            $errors[] = 'As Faculty Mentor, you must agree to certify progress and bills and attend monthly reviews.';
        }
        if (!$consentOriginalIdea) {
            $errors[] = 'You must confirm that you have read the proposal and verify it is original to the best of your knowledge.';
        }
        // E-Signature Name and Date Verification
        if (!preg_match('/^(.+?)\s+(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})$/u', $mentorSig, $sigMatch)) {
            $errors[] = "Digital signature must be in format: Mentor_Name DD/MM/YYYY (e.g. " . ($mentor['name'] ?? 'Your Name') . " " . date('d/m/Y') . ").";
        } else {
            $typedName = trim($sigMatch[1]);
            $typedDate = trim($sigMatch[2]);

            // Check if typed name matches registered mentor
            $normTyped  = strtolower(trim(preg_replace('/^(dr\.|mr\.|ms\.|mrs\.|prof\.)\s*/i', '', preg_replace('/\s+/', ' ', $typedName))));
            $normMentor = strtolower(trim(preg_replace('/^(dr\.|mr\.|ms\.|mrs\.|prof\.)\s*/i', '', preg_replace('/\s+/', ' ', $mentor['name'] ?? ''))));

            if (!empty($normMentor) && $normTyped !== $normMentor) {
                $errors[] = "The signature name '{$typedName}' does not match your registered Mentor name '{$mentor['name']}'. You must sign with your own full name.";
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
                }
            } else {
                $errors[] = "Invalid signature date format. Must be DD/MM/YYYY.";
            }
        }
    } else { // Declined
        if (empty($declineReason) || mb_strlen($declineReason) < 10) {
            $errors[] = 'Please provide a brief reason or feedback for declining (minimum 10 characters).';
        }
    }

    if (!empty($errors)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }

    // 3. Update mentor record
    $stmtUpdate = $pdo->prepare("
        UPDATE `team_mentors`
        SET
            approval_status          = :status,
            approved_at              = NOW(),
            mentor_signature         = :sig,
            mentor_ip_address        = :ip,
            consent_agree_guide      = :c1,
            consent_certify_progress = :c2,
            consent_original_idea    = :c3,
            decline_reason           = :reason
        WHERE id = :mid
    ");
    $stmtUpdate->execute([
        ':status' => $decision,
        ':sig'    => ($decision === 'Approved') ? $mentorSig : null,
        ':ip'     => $clientIp,
        ':c1'     => $consentAgreeGuide,
        ':c2'     => $consentCertifyProgress,
        ':c3'     => $consentOriginalIdea,
        ':reason' => ($decision === 'Declined') ? $declineReason : null,
        ':mid'    => $mentorId
    ]);

    // 4. Check overall team approval status
    // Rule from document: "The application is complete only when both mandatory mentors approve. The additional faculty mentor does not block completion."
    $stmtAllMentors = $pdo->prepare("SELECT mentor_type, approval_status FROM `team_mentors` WHERE team_id = :tid");
    $stmtAllMentors->execute([':tid' => $teamId]);
    $allMentors = $stmtAllMentors->fetchAll(PDO::FETCH_ASSOC);

    $facultyApproved  = false;
    $industryApproved = false;
    $anyDeclined      = false;

    foreach ($allMentors as $m) {
        if ($m['mentor_type'] === 'faculty' && $m['approval_status'] === 'Approved') {
            $facultyApproved = true;
        }
        if ($m['mentor_type'] === 'industry' && $m['approval_status'] === 'Approved') {
            $industryApproved = true;
        }
        if ($m['approval_status'] === 'Declined') {
            $anyDeclined = true;
        }
    }

    if ($facultyApproved && $industryApproved) {
        $stmtTeamStatus = $pdo->prepare("
            UPDATE `teams`
            SET status = 'Under Screening',
                updated_at = NOW()
            WHERE id = :tid
        ");
        $stmtTeamStatus->execute([':tid' => $teamId]);
    }

    echo json_encode([
        'success'           => true,
        'decision'          => $decision,
        'message'           => ($decision === 'Approved') 
            ? 'Thank you! Your official mentor endorsement and digital signature have been recorded.'
            : 'Your response and feedback have been recorded. The student team lead has been notified.',
        'all_mentors_ready' => ($facultyApproved && $industryApproved)
    ]);

} catch (Exception $e) {
    error_log('save_mentor_approval error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    exit;
}
