<?php
/**
 * API: Handle Registration Alert Subscription
 * Stores student interest in the SQL database
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$fullName = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$department = trim($_POST['department'] ?? '');
$year = trim($_POST['year_of_study'] ?? 'II');
$role = trim($_POST['interest_role'] ?? 'Team Lead');

// Basic Validation
if (empty($fullName) || empty($email) || empty($department)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields (Name, Email, and Department).']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please enter a valid college email address.']);
    exit;
}

$pdo = getDatabaseConnection();

if (!$pdo) {
    http_response_code(503);
    echo json_encode([
        'success' => false, 
        'message' => 'Database service currently offline. Please ensure MySQL is started in XAMPP control panel.'
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO `registration_alerts` (`full_name`, `email`, `phone`, `department`, `year_of_study`, `interest_role`)
        VALUES (:name, :email, :phone, :dept, :year, :role)
        ON DUPLICATE KEY UPDATE 
            `full_name` = VALUES(`full_name`),
            `phone` = VALUES(`phone`),
            `department` = VALUES(`department`),
            `year_of_study` = VALUES(`year_of_study`),
            `interest_role` = VALUES(`interest_role`)
    ");

    $stmt->execute([
        ':name' => $fullName,
        ':email' => $email,
        ':phone' => $phone,
        ':dept' => $department,
        ':year' => $year,
        ':role' => $role
    ]);

    echo json_encode([
        'success' => true,
        'message' => "Thank you, {$fullName}. You have successfully registered for early application alerts. We will notify you as soon as the portal opens."
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred while saving your details: ' . $e->getMessage()]);
}
