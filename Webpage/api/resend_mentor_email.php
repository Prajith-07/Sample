<?php
/**
 * iTech Seed Grand Challenge 2027
 * API Endpoint: Resend or Update Mentor Approval Link
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
require_once __DIR__ . '/../utils/mailer.php';

$rawBody = file_get_contents('php://input');
$data    = json_decode($rawBody, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

$teamId   = isset($data['team_id']) ? (int)$data['team_id'] : 0;
$mentorId = isset($data['mentor_id']) ? (int)$data['mentor_id'] : 0;
$newEmail = isset($data['email']) ? trim((string)$data['email']) : '';

if ($teamId <= 0 || $mentorId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid team or mentor ID.']);
    exit;
}

$pdo = getDatabaseConnection();
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

try {
    // Fetch mentor
    $stmtMentor = $pdo->prepare("SELECT * FROM `team_mentors` WHERE id = :mid AND team_id = :tid LIMIT 1");
    $stmtMentor->execute([':mid' => $mentorId, ':tid' => $teamId]);
    $mentor = $stmtMentor->fetch(PDO::FETCH_ASSOC);

    if (!$mentor) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Mentor record not found.']);
        exit;
    }

    // Optionally update email if provided
    if (!empty($newEmail) && filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        $stmtUpdateEmail = $pdo->prepare("UPDATE `team_mentors` SET email = :email WHERE id = :mid");
        $stmtUpdateEmail->execute([':email' => $newEmail, ':mid' => $mentorId]);
        $mentor['email'] = $newEmail;
    }

    // Fetch team & lead
    $stmtTeam = $pdo->prepare("SELECT * FROM `teams` WHERE id = :tid LIMIT 1");
    $stmtTeam->execute([':tid' => $teamId]);
    $team = $stmtTeam->fetch(PDO::FETCH_ASSOC);

    $stmtProp = $pdo->prepare("SELECT project_title FROM `proposals` WHERE team_id = :tid LIMIT 1");
    $stmtProp->execute([':tid' => $teamId]);
    $prop = $stmtProp->fetch(PDO::FETCH_ASSOC);

    $stmtLead = $pdo->prepare("SELECT full_name FROM `team_members` WHERE team_id = :tid AND is_lead = 1 LIMIT 1");
    $stmtLead->execute([':tid' => $teamId]);
    $lead = $stmtLead->fetch(PDO::FETCH_ASSOC);

    $token = $mentor['approval_token'];
    if (empty($token) || $mentor['approval_status'] === 'Declined') {
        $token = bin2hex(random_bytes(32));
        $stmtUpdateToken = $pdo->prepare("UPDATE `team_mentors` SET approval_token = :token WHERE id = :mid");
        $stmtUpdateToken->execute([':token' => $token, ':mid' => $mentorId]);
    }

    $mailer = new LaunchpadMailer();
    $mailResult = $mailer->sendMentorApprovalRequest(
        mentor: $mentor,
        team: $team,
        approvalToken: $token,
        leadName: $lead['full_name'] ?? 'Team Lead',
        projectTitle: $prop['project_title'] ?? $team['team_name']
    );

    if (empty($mailResult['success'])) {
        http_response_code(400);
        echo json_encode([
            'success'      => false,
            'message'      => $mailResult['error'] ?? "Failed to send email to {$mentor['email']}.",
            'approval_url' => $mailResult['approval_url'] ?? '',
            'mentor_email' => $mentor['email']
        ]);
        exit;
    }

    $stmtTouch = $pdo->prepare("UPDATE `team_mentors` SET email_sent_at = NOW() WHERE id = :mid");
    $stmtTouch->execute([':mid' => $mentorId]);

    echo json_encode([
        'success'      => true,
        'message'      => $mailResult['message'] ?? "Consent request link sent to {$mentor['email']}.",
        'approval_url' => $mailResult['approval_url'] ?? '',
        'mentor_email' => $mentor['email']
    ]);

} catch (Exception $e) {
    error_log('resend_mentor_email error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    exit;
}
