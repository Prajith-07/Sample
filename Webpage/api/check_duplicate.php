<?php
/**
 * API: Check Field Duplication (Roll Number, Email, Phone, Team Name)
 * Returns JSON: { available: bool, message: string }
 * iTech Seed Grand Challenge 2027
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$pdo = getDatabaseConnection();

if (!$pdo) {
    // If MySQL is offline, return available: true so local dev / form filling is not blocked,
    // but inform the user.
    echo json_encode([
        'available' => true, 
        'db_offline' => true,
        'message' => 'Database offline (MySQL not started in XAMPP).'
    ]);
    exit;
}

$field = trim($_REQUEST['field'] ?? '');
$value = trim($_REQUEST['value'] ?? '');
$excludeTeamId = isset($_REQUEST['exclude_team_id']) ? (int)$_REQUEST['exclude_team_id'] : 0;

if (empty($field) || empty($value)) {
    echo json_encode(['available' => true, 'message' => '']);
    exit;
}

try {
    switch ($field) {
        case 'team_name':
            $sql = "SELECT id, team_name FROM `teams` WHERE LOWER(TRIM(`team_name`)) = LOWER(:val)";
            if ($excludeTeamId > 0) {
                $sql .= " AND id != :excludeId";
            }
            $stmt = $pdo->prepare($sql);
            $params = [':val' => $value];
            if ($excludeTeamId > 0) $params[':excludeId'] = $excludeTeamId;
            $stmt->execute($params);
            
            if ($row = $stmt->fetch()) {
                echo json_encode([
                    'available' => false,
                    'message' => "Team name '{$value}' is already taken. Please choose a unique team name."
                ]);
            } else {
                echo json_encode(['available' => true, 'message' => "Team name is available!"]);
            }
            break;

        case 'roll_number':
            if (!preg_match('/^[A-Za-z0-9-]+$/', $value)) {
                echo json_encode([
                    'available' => false,
                    'message' => "Roll Number must contain letters, numbers, or hyphens (e.g. 23AD01)."
                ]);
                break;
            }

            $sql = "
                SELECT m.roll_number, m.full_name, t.team_name 
                FROM `team_members` m
                JOIN `teams` t ON m.team_id = t.id
                WHERE UPPER(TRIM(m.roll_number)) = UPPER(:val)
            ";
            if ($excludeTeamId > 0) {
                $sql .= " AND t.id != :excludeId";
            }
            $stmt = $pdo->prepare($sql);
            $params = [':val' => $value];
            if ($excludeTeamId > 0) $params[':excludeId'] = $excludeTeamId;
            $stmt->execute($params);
            
            if ($row = $stmt->fetch()) {
                echo json_encode([
                    'available' => false,
                    'message' => "Roll Number '{$value}' is already registered with Team '{$row['team_name']}' ({$row['full_name']}). A student can participate in only one team."
                ]);
            } else {
                echo json_encode(['available' => true, 'message' => "Roll Number is valid and available."]);
            }
            break;

        case 'college_email':
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                echo json_encode([
                    'available' => false,
                    'message' => "Please enter a valid email address."
                ]);
                break;
            }

            // If the email being checked is the current logged-in or staged Team Leader's email, it's their own account
            $isCurrentLeaderEmail = false;
            $checkEmail = strtolower(trim($value));
            if (!empty($_SESSION['staged_lead_email']) && strtolower(trim($_SESSION['staged_lead_email'])) === $checkEmail) {
                $isCurrentLeaderEmail = true;
            } elseif (!empty($_SESSION['user_email']) && strtolower(trim($_SESSION['user_email'])) === $checkEmail && !empty($_SESSION['is_lead'])) {
                $isCurrentLeaderEmail = true;
            }

            if ($isCurrentLeaderEmail) {
                echo json_encode(['available' => true, 'message' => "Team Leader email verified."]);
                exit;
            }

            // Check if registered as team lead in teams table
            $stmtLead = $pdo->prepare("SELECT id, team_name FROM `teams` WHERE LOWER(TRIM(leader_email)) = LOWER(:val)");
            $stmtLead->execute([':val' => $value]);
            if ($lRow = $stmtLead->fetch()) {
                if ($excludeTeamId <= 0 || (int)$lRow['id'] !== $excludeTeamId) {
                    echo json_encode([
                        'available' => false,
                        'message' => "Email '{$value}' is already registered with Team '{$lRow['team_name']}'. Please proceed to the Login page."
                    ]);
                    exit;
                }
            }

            // Check student email in team_members
            $sql = "
                SELECT m.college_email, m.full_name, t.team_name 
                FROM `team_members` m
                JOIN `teams` t ON m.team_id = t.id
                WHERE LOWER(TRIM(m.college_email)) = LOWER(:val)
            ";
            if ($excludeTeamId > 0) {
                $sql .= " AND t.id != :excludeId";
            }
            $stmt = $pdo->prepare($sql);
            $params = [':val' => $value];
            if ($excludeTeamId > 0) $params[':excludeId'] = $excludeTeamId;
            $stmt->execute($params);
            
            if ($row = $stmt->fetch()) {
                echo json_encode([
                    'available' => false,
                    'message' => "Email '{$value}' is already registered with Team '{$row['team_name']}'. Each student may register once."
                ]);
                exit;
            }

            // Check mentor email
            $stmtMentor = $pdo->prepare("SELECT name FROM `team_mentors` WHERE LOWER(TRIM(email)) = LOWER(:val)");
            $stmtMentor->execute([':val' => $value]);
            if ($mRow = $stmtMentor->fetch()) {
                echo json_encode([
                    'available' => false,
                    'message' => "Email '{$value}' is listed as a mentor ({$mRow['name']}). Mentors cannot be team members."
                ]);
                exit;
            }

            $isLeadCheck = isset($_REQUEST['is_lead']) && ($_REQUEST['is_lead'] == '1' || $_REQUEST['is_lead'] === 'true');
            echo json_encode([
                'available' => true, 
                'message' => $isLeadCheck ? "Team Leader email verified." : "Email is available."
            ]);
            break;

        case 'phone_number':
            // Standardize phone by extracting digits
            $cleanPhone = preg_replace('/[^\d]/', '', $value);
            if (strlen($cleanPhone) > 10) {
                $cleanPhone = substr($cleanPhone, -10);
            }
            
            $sql = "
                SELECT m.phone_number, m.full_name, t.team_name 
                FROM `team_members` m
                JOIN `teams` t ON m.team_id = t.id
                WHERE RIGHT(REPLACE(REPLACE(REPLACE(m.phone_number, ' ', ''), '-', ''), '+91', ''), 10) = :val
            ";
            if ($excludeTeamId > 0) {
                $sql .= " AND t.id != :excludeId";
            }
            $stmt = $pdo->prepare($sql);
            $params = [':val' => $cleanPhone];
            if ($excludeTeamId > 0) $params[':excludeId'] = $excludeTeamId;
            $stmt->execute($params);

            if ($row = $stmt->fetch()) {
                echo json_encode([
                    'available' => false,
                    'message' => "Phone number '{$value}' is already associated with Team '{$row['team_name']}'."
                ]);
            } else {
                echo json_encode(['available' => true, 'message' => "Phone number is available."]);
            }
            break;

        default:
            echo json_encode(['available' => true, 'message' => '']);
            break;
    }
} catch (PDOException $e) {
    echo json_encode([
        'available' => true,
        'error' => true,
        'message' => 'Verification query error: ' . $e->getMessage()
    ]);
}
