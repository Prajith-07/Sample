<?php
/**
 * API: Save Step 5 (Budget & Itemized Financial Plan)
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
$items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];

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

// 2. Validate Items Array
if (empty($items)) {
    $errors[] = "At least one budget line item is required.";
}

$allowedCategories = [
    'Components, sensors, boards',
    'Raw material',
    'Fabrication and testing',
    'Software licences, cloud credits, datasets',
    'Field trials, user surveys, limited local travel',
    'Patent search and filing (with IPR cell approval)'
];

$sanitizedItems = [];
$calculatedTotal = 0.0;
$order = 1;

foreach ($items as $idx => $item) {
    if (!is_array($item)) {
        continue;
    }

    $name = trim($item['item_name'] ?? '');
    $category = trim($item['category'] ?? '');
    $qty = isset($item['quantity']) ? (int)$item['quantity'] : 0;
    $unitCost = isset($item['unit_cost']) ? (float)$item['unit_cost'] : -1.0;
    $purpose = trim($item['purpose'] ?? '');

    $rowLabel = "Item #" . ($idx + 1);

    if (empty($name)) {
        $errors[] = "$rowLabel: Item name or description is required.";
    }

    if (empty($category) || !in_array($category, $allowedCategories, true)) {
        $errors[] = "$rowLabel: Please select a valid category from the allowed list.";
    }

    if ($qty < 1) {
        $errors[] = "$rowLabel: Quantity must be at least 1.";
    }

    if ($unitCost < 0) {
        $errors[] = "$rowLabel: Unit cost cannot be negative.";
    }

    if (empty($purpose)) {
        $errors[] = "$rowLabel: Purpose of the item is required.";
    }

    $rowAmount = round($qty * $unitCost, 2);
    $calculatedTotal += $rowAmount;

    $sanitizedItems[] = [
        'item_order' => $order++,
        'item_name' => $name,
        'category' => $category,
        'quantity' => $qty,
        'unit_cost' => round($unitCost, 2),
        'amount' => $rowAmount,
        'purpose' => $purpose
    ];
}

// 3. Grant Ceiling Validation (Maximum ₹1,00,000)
$maxGrantCeiling = 100000.00;
if ($calculatedTotal > $maxGrantCeiling) {
    $exceededBy = number_format($calculatedTotal - $maxGrantCeiling, 2);
    $errors[] = "Budget Exceeded: Total requested amount (₹" . number_format($calculatedTotal, 2) . ") exceeds the ₹1,00,000.00 grant ceiling by ₹$exceededBy. Please adjust your item quantities or unit costs.";
}

if ($calculatedTotal <= 0 && empty($errors)) {
    $errors[] = "Total requested budget must be greater than ₹0.00.";
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Validation failed. Please correct the highlighted budget entries.',
        'errors' => $errors
    ]);
    exit;
}

// 4. Database Transaction
try {
    $pdo->beginTransaction();

    // Check if proposal row exists
    $stmtCheck = $pdo->prepare("SELECT id FROM `proposals` WHERE team_id = :tid");
    $stmtCheck->execute([':tid' => $teamId]);
    $propRow = $stmtCheck->fetch();

    if ($propRow) {
        $stmtUp = $pdo->prepare("
            UPDATE `proposals`
            SET `budget_requested` = :bgt,
                `updated_at` = NOW()
            WHERE `team_id` = :tid
        ");
        $stmtUp->execute([
            ':bgt' => $calculatedTotal,
            ':tid' => $teamId
        ]);
    } else {
        $stmtIn = $pdo->prepare("
            INSERT INTO `proposals` (
                `team_id`, `budget_requested`, `created_at`, `updated_at`
            ) VALUES (
                :tid, :bgt, NOW(), NOW()
            )
        ");
        $stmtIn->execute([
            ':tid' => $teamId,
            ':bgt' => $calculatedTotal
        ]);
    }

    // Replace Budget Items for this team
    $stmtDel = $pdo->prepare("DELETE FROM `proposal_budget_items` WHERE team_id = :tid");
    $stmtDel->execute([':tid' => $teamId]);

    $stmtInsertItem = $pdo->prepare("
        INSERT INTO `proposal_budget_items` (
            `team_id`, `item_order`, `item_name`, `category`, `quantity`, `unit_cost`, `amount`, `purpose`, `created_at`, `updated_at`
        ) VALUES (
            :tid, :iorder, :iname, :icat, :iqty, :iucost, :iamount, :ipurpose, NOW(), NOW()
        )
    ");

    foreach ($sanitizedItems as $itemRow) {
        $stmtInsertItem->execute([
            ':tid' => $teamId,
            ':iorder' => $itemRow['item_order'],
            ':iname' => $itemRow['item_name'],
            ':icat' => $itemRow['category'],
            ':iqty' => $itemRow['quantity'],
            ':iucost' => $itemRow['unit_cost'],
            ':iamount' => $itemRow['amount'],
            ':ipurpose' => $itemRow['purpose']
        ]);
    }

    // Advance team step if needed (Step 5 completed)
    $stmtStep = $pdo->prepare("
        UPDATE `teams`
        SET `current_step` = GREATEST(`current_step`, 6),
            `updated_at` = NOW()
        WHERE `id` = :tid
    ");
    $stmtStep->execute([':tid' => $teamId]);

    $pdo->commit();

    // Compute tranche disbursements
    $tranche1 = round($calculatedTotal * 0.50, 2);
    $tranche2 = round($calculatedTotal * 0.30, 2);
    $tranche3 = round($calculatedTotal * 0.20, 2);

    echo json_encode([
        'success' => true,
        'message' => 'Step 5 (Budget) saved successfully.',
        'team_id' => $teamId,
        'next_step' => 6,
        'total_budget' => $calculatedTotal,
        'tranches' => [
            'tranche_1' => $tranche1,
            'tranche_2' => $tranche2,
            'tranche_3' => $tranche3
        ]
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Step 5 Save Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'A database error occurred while saving the budget: ' . $e->getMessage()
    ]);
}
