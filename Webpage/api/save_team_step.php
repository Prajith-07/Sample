<?php
/**
 * API: Save Step 1 (Team & Account Details)
 * Handles full server-side validation & atomic MySQL persistence
 * iTech Seed Grand Challenge 2027
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../utils/mailer.php';

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
$teamName = trim($data['team_name'] ?? '');
$members = $data['members'] ?? [];
$faculty = $data['faculty_mentor'] ?? [];
$addFaculty = $data['additional_faculty'] ?? [];
$industry = $data['industry_mentor'] ?? [];

// Restrict member modifications strictly to Team Leader
if (isset($_SESSION['is_lead']) && $_SESSION['is_lead'] === false) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Only the designated Team Leader has authority to create, edit, or modify team members and mentors.'
    ]);
    exit;
}

$errors = [];

// 1. Team Name Validation
if (empty($teamName)) {
    $errors[] = "Team Name is mandatory.";
} elseif (strlen($teamName) < 3) {
    $errors[] = "Team Name must be at least 3 characters long.";
} else {
    // Uniqueness in DB
    $teamCheckSql = "SELECT id FROM `teams` WHERE LOWER(TRIM(`team_name`)) = LOWER(:name)";
    if ($teamId > 0) {
        $teamCheckSql .= " AND id != :tid";
    }
    $stmt = $pdo->prepare($teamCheckSql);
    $params = [':name' => $teamName];
    if ($teamId > 0) $params[':tid'] = $teamId;
    $stmt->execute($params);
    if ($stmt->fetch()) {
        $errors[] = "The team name '{$teamName}' is already taken. Please choose another.";
    }
}

// 2. Team Size Validation (2 to 4 members)
$memberCount = is_array($members) ? count($members) : 0;
if ($memberCount < 2 || $memberCount > 4) {
    $errors[] = "Team size must be between 2 and 4 members. Current count: {$memberCount}.";
}

// 3. Member Slot Validations & Internal Duplicate Checks
$seenRolls = [];
$seenEmails = [];
$seenPhones = [];
$seenDepts = [];
$hasFemale = false;
$validMembers = [];

$allowedDepts = [
    'Artificial Intelligence & Data Science',
    'Computer Science & Engineering',
    'Electronics & Communication',
    'Electrical & Electronics',
    'Mechanical Engineering',
    'Civil Engineering',
    'Information Technology',
    'Computer Science & Business Systems'
];

$allowedYears = ['II', 'III', 'Final'];
$allowedGenders = ['Female', 'Male'];

foreach ($members as $idx => $m) {
    $slotNum = $idx + 1;
    $isLead = ($idx === 0) ? 1 : 0;
    $label = ($idx === 0) ? "Team Lead (Member 1)" : "Member {$slotNum}";

    $fullName = trim($m['full_name'] ?? '');
    $rollNo = trim($m['roll_number'] ?? '');
    $dept = trim($m['department'] ?? '');
    $year = trim($m['year_of_study'] ?? '');
    $email = strtolower(trim($m['college_email'] ?? ''));
    $phone = preg_replace('/[^\d]/', '', trim($m['phone_number'] ?? ''));
    $gender = trim($m['gender'] ?? '');

    // Full name
    if (empty($fullName)) {
        $errors[] = "{$label}: Full Name is required.";
    }

    // Roll number (letters, numbers, hyphens e.g. 23AD01 or numeric)
    if (empty($rollNo)) {
        $errors[] = "{$label}: Roll Number / College ID is required.";
    } elseif (!preg_match('/^[A-Za-z0-9-]+$/', $rollNo)) {
        $errors[] = "{$label}: Roll Number must contain alphanumeric characters or hyphens (e.g. 23AD01).";
    } elseif (strlen($rollNo) > 12) {
        $errors[] = "{$label}: Roll Number cannot exceed 12 digits.";
    } elseif (in_array($rollNo, $seenRolls)) {
        $errors[] = "Duplicate Roll Number detected: '{$rollNo}' is entered more than once within your team.";
    } else {
        $seenRolls[] = $rollNo;
    }

    // Department
    if (empty($dept)) {
        $errors[] = "{$label}: Department selection is required.";
    } else {
        $seenDepts[] = $dept;
    }

    // Year of study
    if (!in_array($year, $allowedYears)) {
        $errors[] = "{$label}: Year of study must be II, III, or Final year.";
    }

    // Email
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "{$label}: A valid email address is required.";
    } elseif (in_array($email, $seenEmails)) {
        $errors[] = "Duplicate Email detected: '{$email}' is entered more than once within your team.";
    } else {
        $seenEmails[] = $email;
    }

    // Phone
    if (strlen($phone) !== 10) {
        $errors[] = "{$label}: Phone number must be exactly 10 digits.";
    } else {
        if (in_array($phone, $seenPhones)) {
            $errors[] = "Duplicate Phone Number detected: '{$phone}' is entered more than once within your team.";
        } else {
            $seenPhones[] = $phone;
        }
    }

    // Gender & Diversity Check
    if (!in_array($gender, $allowedGenders)) {
        $errors[] = "{$label}: Please select a valid gender.";
    } elseif ($gender === 'Female') {
        $hasFemale = true;
    }

    $validMembers[] = [
        'is_lead' => $isLead,
        'member_order' => $slotNum,
        'full_name' => $fullName,
        'roll_number' => $rollNo,
        'department' => $dept,
        'year_of_study' => $year,
        'college_email' => $email,
        'phone_number' => $phone,
        'gender' => $gender
    ];
}

// 4. Woman Member Rule Enforcement (Removed - condition is optional)
// if (!$hasFemale) { ... }

// 4b. Interdisciplinary Team Rule Enforcement (At least 2 distinct departments)
$uniqueDepts = array_unique(array_filter($seenDepts));
if (count($uniqueDepts) < 2) {
    $errors[] = "Mandatory Interdisciplinary Rule: The team must be interdisciplinary, comprising students from at least two different departments. (Currently selected: " . (count($uniqueDepts) === 1 ? reset($uniqueDepts) : 'None') . ").";
}

// 5. Database Duplicate Check for All Members (Cross-team)
if (empty($errors)) {
    foreach ($validMembers as $m) {
        $label = ($m['is_lead'] === 1) ? "Team Lead ({$m['full_name']})" : "Member {$m['member_order']} ({$m['full_name']})";
        
        // Roll number check
        $stmt = $pdo->prepare("
            SELECT t.team_name, m.full_name 
            FROM `team_members` m 
            JOIN `teams` t ON m.team_id = t.id 
            WHERE UPPER(TRIM(m.roll_number)) = :roll " . ($teamId > 0 ? "AND t.id != :tid" : "")
        );
        $p = [':roll' => $m['roll_number']];
        if ($teamId > 0) $p[':tid'] = $teamId;
        $stmt->execute($p);
        if ($row = $stmt->fetch()) {
            $errors[] = "Roll number '{$m['roll_number']}' ({$label}) is already registered with team '{$row['team_name']}'.";
        }

        // Email check
        $stmt = $pdo->prepare("
            SELECT t.team_name, m.full_name 
            FROM `team_members` m 
            JOIN `teams` t ON m.team_id = t.id 
            WHERE LOWER(TRIM(m.college_email)) = :email " . ($teamId > 0 ? "AND t.id != :tid" : "")
        );
        $p = [':email' => $m['college_email']];
        if ($teamId > 0) $p[':tid'] = $teamId;
        $stmt->execute($p);
        if ($row = $stmt->fetch()) {
            $errors[] = "Email '{$m['college_email']}' ({$label}) is already registered with team '{$row['team_name']}'.";
        }

        // Leader email cross-check against teams table
        if (!empty($m['is_lead'])) {
            $stmtLead = $pdo->prepare("
                SELECT id, team_name 
                FROM `teams` 
                WHERE LOWER(TRIM(leader_email)) = :email " . ($teamId > 0 ? "AND id != :tid" : "")
            );
            $pLead = [':email' => $m['college_email']];
            if ($teamId > 0) $pLead[':tid'] = $teamId;
            $stmtLead->execute($pLead);
            if ($rowL = $stmtLead->fetch()) {
                $errors[] = "Leader email '{$m['college_email']}' is already registered with team '{$rowL['team_name']}'.";
            }
        }

        // Phone check
        $phone10 = substr($m['phone_number'], -10);
        $stmt = $pdo->prepare("
            SELECT t.team_name, m.full_name 
            FROM `team_members` m 
            JOIN `teams` t ON m.team_id = t.id 
            WHERE RIGHT(REPLACE(REPLACE(REPLACE(m.phone_number, ' ', ''), '-', ''), '+91', ''), 10) = :phone " . ($teamId > 0 ? "AND t.id != :tid" : "")
        );
        $p = [':phone' => $phone10];
        if ($teamId > 0) $p[':tid'] = $teamId;
        $stmt->execute($p);
        if ($row = $stmt->fetch()) {
            $errors[] = "Phone number '{$m['phone_number']}' ({$label}) is already registered with team '{$row['team_name']}'.";
        }
    }
}

// 6. Mentor Validations
// 6.1 Faculty Mentor (Mandatory)
$facName = trim($faculty['name'] ?? '');
$facDept = trim($faculty['department'] ?? '');
$facEmail = strtolower(trim($faculty['email'] ?? ''));
$facPhone = preg_replace('/[^\d]/', '', trim($faculty['phone'] ?? ''));

if (empty($facName) || empty($facDept) || empty($facEmail) || empty($facPhone)) {
    $errors[] = "Faculty Mentor: All fields (Name, Department, Email, and Phone) are mandatory.";
} elseif (strlen($facPhone) !== 10) {
    $errors[] = "Faculty Mentor: Mobile phone number must be exactly 10 digits.";
} elseif (!filter_var($facEmail, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Faculty Mentor: Please enter a valid email address.";
} elseif (in_array($facEmail, $seenEmails)) {
    $errors[] = "Conflict: Faculty Mentor email cannot be the same as any student team member's email.";
}

// 6.2 Additional Faculty Mentor (Optional)
$hasAddFaculty = false;
$addFacName = trim($addFaculty['name'] ?? '');
$addFacDept = trim($addFaculty['department'] ?? '');
$addFacEmail = strtolower(trim($addFaculty['email'] ?? ''));
$addFacPhone = preg_replace('/[^\d]/', '', trim($addFaculty['phone'] ?? ''));

if (!empty($addFacName) || !empty($addFacEmail)) {
    $hasAddFaculty = true;
    if (empty($addFacName) || empty($addFacDept) || empty($addFacEmail) || empty($addFacPhone)) {
        $errors[] = "Additional Faculty Mentor: If providing a co-mentor, please complete all fields (Name, Department, Email, and Phone).";
    } elseif (strlen($addFacPhone) !== 10) {
        $errors[] = "Additional Faculty Mentor: Mobile phone number must be exactly 10 digits.";
    } elseif (!filter_var($addFacEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Additional Faculty Mentor: Please enter a valid email address.";
    } elseif (in_array($addFacEmail, $seenEmails) || $addFacEmail === $facEmail) {
        $errors[] = "Conflict: Additional Faculty Mentor email must differ from students and primary faculty mentor.";
    }
}

// 6.3 Industry Mentor (Mandatory)
$indName = trim($industry['name'] ?? '');
$indOrg = trim($industry['organisation'] ?? '');
$indDesig = trim($industry['designation'] ?? '');
$indEmail = strtolower(trim($industry['email'] ?? ''));
$indPhone = preg_replace('/[^\d]/', '', trim($industry['phone'] ?? ''));
$indLinkedin = trim($industry['linkedin'] ?? '');

if (empty($indName) || empty($indOrg) || empty($indDesig) || empty($indEmail) || empty($indPhone)) {
    $errors[] = "Industry Expert Mentor: Name, Organisation, Designation, Email, and Phone are mandatory.";
} elseif (strlen($indPhone) !== 10) {
    $errors[] = "Industry Expert Mentor: Mobile phone number must be exactly 10 digits.";
} elseif (!filter_var($indEmail, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Industry Expert Mentor: Please enter a valid email address.";
} elseif (in_array($indEmail, $seenEmails) || $indEmail === $facEmail) {
    $errors[] = "Conflict: Industry Mentor email cannot match any student or faculty mentor email.";
}

// If any validation errors exist, return immediately
if (!empty($errors)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Validation failed. Please correct the following issues:',
        'errors' => $errors
    ]);
    exit;
}

// 7. Atomic Database Persistence & Account Provisioning
try {
    $pdo->beginTransaction();

    $leadMember = $validMembers[0] ?? [];
    $leadEmail  = strtolower($leadMember['college_email'] ?? '');
    $leadName   = $leadMember['full_name'] ?? 'Team Leader';

    // Retrieve staged password hash from signup session if available
    $stagedLeadPasswordHash = $_SESSION['staged_lead_password_hash'] ?? null;

    $existingMemberPasswords = [];
    $existingMentorPasswords = [];
    $existingTeamHash = null;

    if ($teamId > 0) {
        // Cache existing passwords to prevent resetting passwords for users who already customized them
        $stmtOldM = $pdo->prepare("SELECT LOWER(college_email) AS email, password_hash, is_dummy_password FROM `team_members` WHERE team_id = :tid");
        $stmtOldM->execute([':tid' => $teamId]);
        foreach ($stmtOldM->fetchAll(PDO::FETCH_ASSOC) as $oldM) {
            $existingMemberPasswords[$oldM['email']] = $oldM;
        }

        $stmtOldMen = $pdo->prepare("SELECT LOWER(email) AS email, password_hash, is_dummy_password FROM `team_mentors` WHERE team_id = :tid");
        $stmtOldMen->execute([':tid' => $teamId]);
        foreach ($stmtOldMen->fetchAll(PDO::FETCH_ASSOC) as $oldMen) {
            $existingMentorPasswords[$oldMen['email']] = $oldMen;
        }

        $stmtOldT = $pdo->prepare("SELECT password_hash FROM `teams` WHERE id = :tid");
        $stmtOldT->execute([':tid' => $teamId]);
        $existingTeamHash = $stmtOldT->fetchColumn() ?: null;

        $leadPasswordHash = $stagedLeadPasswordHash ?: ($existingTeamHash ?: password_hash('PSGitech2027!', PASSWORD_DEFAULT));

        // Update existing team
        $stmt = $pdo->prepare("
            UPDATE `teams` 
            SET `team_name` = :tname, `leader_email` = :lemail, `password_hash` = :phash, `team_size` = :tsize, `has_woman_member` = :has_woman, `current_step` = GREATEST(`current_step`, 2), `updated_at` = NOW()
            WHERE `id` = :tid
        ");
        $stmt->execute([
            ':tname' => $teamName,
            ':lemail' => $leadEmail,
            ':phash' => $leadPasswordHash,
            ':tsize' => count($validMembers),
            ':has_woman' => $hasFemale ? 1 : 0,
            ':tid' => $teamId
        ]);

        // Clean old members and mentors to re-insert fresh
        $pdo->prepare("DELETE FROM `team_members` WHERE `team_id` = :tid")->execute([':tid' => $teamId]);
        $pdo->prepare("DELETE FROM `team_mentors` WHERE `team_id` = :tid")->execute([':tid' => $teamId]);

        // Retrieve existing application_no
        $stmtApp = $pdo->prepare("SELECT `application_no` FROM `teams` WHERE `id` = :tid");
        $stmtApp->execute([':tid' => $teamId]);
        $applicationNo = $stmtApp->fetchColumn();
    } else {
        // Generate Unique Application Number (e.g. SGC27-XXXXXX)
        $applicationNo = 'SGC27-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $leadPasswordHash = $stagedLeadPasswordHash ?: password_hash('PSGitech2027!', PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            INSERT INTO `teams` (`application_no`, `team_name`, `leader_email`, `password_hash`, `team_size`, `has_woman_member`, `current_step`, `status`)
            VALUES (:app_no, :tname, :lemail, :phash, :tsize, :has_woman, 2, 'Draft')
        ");
        $stmt->execute([
            ':app_no' => $applicationNo,
            ':tname' => $teamName,
            ':lemail' => $leadEmail,
            ':phash' => $leadPasswordHash,
            ':tsize' => count($validMembers),
            ':has_woman' => $hasFemale ? 1 : 0
        ]);
        $teamId = (int)$pdo->lastInsertId();
    }

    // Insert Team Members with Account Credentials
    $stmtMem = $pdo->prepare("
        INSERT INTO `team_members` 
        (`team_id`, `is_lead`, `member_order`, `full_name`, `roll_number`, `department`, `year_of_study`, `college_email`, `phone_number`, `gender`, `password_hash`, `is_dummy_password`)
        VALUES (:tid, :lead, :ord, :name, :roll, :dept, :year, :email, :phone, :gender, :phash, :is_dummy)
    ");

    $createdMembersForEmail = [];

    foreach ($validMembers as $m) {
        $mEmail = strtolower($m['college_email']);
        $cleanPhone = preg_replace('/[^\d]/', '', $m['phone_number']);
        if (strlen($cleanPhone) > 10) {
            $cleanPhone = substr($cleanPhone, -10);
        }

        if (!empty($m['is_lead'])) {
            // Team Leader: already created password during sign-up
            $memberHash = $leadPasswordHash;
            $isDummy = 0;
        } else {
            // Team Member: check if already has customized password
            if (isset($existingMemberPasswords[$mEmail]) && (int)$existingMemberPasswords[$mEmail]['is_dummy_password'] === 0) {
                $memberHash = $existingMemberPasswords[$mEmail]['password_hash'];
                $isDummy = 0;
            } else {
                // Initial Account: Mobile phone number is the temporary dummy password
                $memberHash = password_hash($cleanPhone, PASSWORD_DEFAULT);
                $isDummy = 1;
            }

            $createdMembersForEmail[] = [
                'member_order'  => $m['member_order'],
                'full_name'     => $m['full_name'],
                'college_email' => $m['college_email'],
                'phone_number'  => $cleanPhone,
                'is_lead'       => 0
            ];
        }

        $stmtMem->execute([
            ':tid'      => $teamId,
            ':lead'     => $m['is_lead'],
            ':ord'      => $m['member_order'],
            ':name'     => $m['full_name'],
            ':roll'     => $m['roll_number'],
            ':dept'     => $m['department'],
            ':year'     => $m['year_of_study'],
            ':email'    => $m['college_email'],
            ':phone'    => $m['phone_number'],
            ':gender'   => $m['gender'],
            ':phash'    => $memberHash,
            ':is_dummy' => $isDummy
        ]);
    }

    // Insert Mentors with Account Credentials
    $stmtMentor = $pdo->prepare("
        INSERT INTO `team_mentors` 
        (`team_id`, `mentor_type`, `name`, `department`, `organisation`, `designation`, `email`, `phone`, `linkedin_url`, `password_hash`, `is_dummy_password`)
        VALUES (:tid, :mtype, :name, :dept, :org, :desig, :email, :phone, :linkedin, :phash, :is_dummy)
    ");

    $createdMentorsForEmail = [];

    // Helper to compute mentor password
    $getMentorCreds = function(string $email, string $phone) use ($existingMentorPasswords): array {
        $cleanPhone = preg_replace('/[^\d]/', '', $phone);
        if (strlen($cleanPhone) > 10) {
            $cleanPhone = substr($cleanPhone, -10);
        }
        $mEmail = strtolower($email);
        if (isset($existingMentorPasswords[$mEmail]) && (int)$existingMentorPasswords[$mEmail]['is_dummy_password'] === 0) {
            return [$existingMentorPasswords[$mEmail]['password_hash'], 0, $cleanPhone];
        }
        return [password_hash($cleanPhone, PASSWORD_DEFAULT), 1, $cleanPhone];
    };

    // 1. Primary Faculty Mentor
    [$facHash, $facIsDummy, $facCleanPhone] = $getMentorCreds($facEmail, $facPhone);
    $stmtMentor->execute([
        ':tid'       => $teamId,
        ':mtype'     => 'faculty',
        ':name'      => $facName,
        ':dept'      => $facDept,
        ':org'       => 'PSG Institute of Technology and Applied Research',
        ':desig'     => 'Faculty Mentor',
        ':email'     => $facEmail,
        ':phone'     => $facPhone,
        ':linkedin'  => null,
        ':phash'     => $facHash,
        ':is_dummy'  => $facIsDummy
    ]);
    $createdMentorsForEmail[] = [
        'mentor_type' => 'faculty',
        'name'        => $facName,
        'email'       => $facEmail,
        'phone'       => $facCleanPhone
    ];

    // 2. Optional Additional Faculty Mentor
    if ($hasAddFaculty) {
        [$addFacHash, $addFacIsDummy, $addFacCleanPhone] = $getMentorCreds($addFacEmail, $addFacPhone);
        $stmtMentor->execute([
            ':tid'       => $teamId,
            ':mtype'     => 'additional_faculty',
            ':name'      => $addFacName,
            ':dept'      => $addFacDept,
            ':org'       => 'PSG Institute of Technology and Applied Research',
            ':desig'     => 'Additional Faculty Mentor',
            ':email'     => $addFacEmail,
            ':phone'     => $addFacPhone,
            ':linkedin'  => null,
            ':phash'     => $addFacHash,
            ':is_dummy'  => $addFacIsDummy
        ]);
        $createdMentorsForEmail[] = [
            'mentor_type' => 'additional_faculty',
            'name'        => $addFacName,
            'email'       => $addFacEmail,
            'phone'       => $addFacCleanPhone
        ];
    }

    // 3. Mandatory Industry Mentor
    [$indHash, $indIsDummy, $indCleanPhone] = $getMentorCreds($indEmail, $indPhone);
    $stmtMentor->execute([
        ':tid'       => $teamId,
        ':mtype'     => 'industry',
        ':name'      => $indName,
        ':dept'      => null,
        ':org'       => $indOrg,
        ':desig'     => $indDesig,
        ':email'     => $indEmail,
        ':phone'     => $indPhone,
        ':linkedin'  => !empty($indLinkedin) ? $indLinkedin : null,
        ':phash'     => $indHash,
        ':is_dummy'  => $indIsDummy
    ]);
    $createdMentorsForEmail[] = [
        'mentor_type' => 'industry',
        'name'        => $indName,
        'email'       => $indEmail,
        'phone'       => $indCleanPhone
    ];

    $pdo->commit();

    // Clear staged signup session variables
    unset($_SESSION['staged_lead_email']);
    unset($_SESSION['staged_lead_password_hash']);
    unset($_SESSION['is_new_registration']);

    // Set active team session
    $_SESSION['team_id']   = $teamId;
    $_SESSION['user_email'] = $leadEmail;
    $_SESSION['team_name'] = $teamName;
    $_SESSION['is_lead']   = true;

    // Send email to Team Lead Gmail notifying about member & mentor accounts
    try {
        $mailer = new LaunchpadMailer();
        $mailer->sendTeamAccountsCreatedNotification(
            leadEmail: $leadEmail,
            leadName: $leadName,
            teamName: $teamName,
            appNo: $applicationNo,
            members: $createdMembersForEmail,
            mentors: $createdMentorsForEmail
        );
    } catch (Throwable $mailEx) {
        error_log("Failed to send team account credentials notification email: " . $mailEx->getMessage());
    }

    echo json_encode([
        'success'        => true,
        'message'        => "Step 1 (Team & Account Details) saved successfully. Individual accounts have been provisioned for all members and mentors.",
        'application_no' => $applicationNo,
        'team_id'        => $teamId,
        'team_name'      => $teamName,
        'team_size'      => count($validMembers)
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error while saving team details: ' . $e->getMessage()
    ]);
}
