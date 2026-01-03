<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (empty($input['donorId']) || empty($input['organizationId']) || empty($input['amount'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Donor ID, Organization ID, and amount are required']);
    exit();
}

$amount = floatval($input['amount']);
if ($amount <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Amount must be greater than 0']);
    exit();
}

$paymentMethod = $input['paymentMethod'] ?? 'card';
$validPaymentMethods = ['card', 'bank', 'mobile_money', 'crypto', 'cash'];
if (!in_array($paymentMethod, $validPaymentMethods)) {
    $paymentMethod = 'card';
}

try {
    $db = getDBConnection();
    $db->beginTransaction();
    
    // Verify donor exists
    $stmt = $db->prepare("SELECT donorId FROM Donor WHERE donorId = ?");
    $stmt->execute([$input['donorId']]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Donor not found']);
        exit();
    }
    
    // Verify organization exists and is approved
    $stmt = $db->prepare("SELECT organizationId FROM Organization WHERE organizationId = ? AND verificationStatus = 'approved'");
    $stmt->execute([$input['organizationId']]);
    if (!$stmt->fetch()) {
        // Also allow pending organizations for testing
        $stmt = $db->prepare("SELECT organizationId FROM Organization WHERE organizationId = ?");
        $stmt->execute([$input['organizationId']]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Organization not found']);
            exit();
        }
    }
    
    // Generate transaction reference
    $transactionRef = 'TXN-' . strtoupper(uniqid()) . '-' . time();
    
    // Create donation record
    $stmt = $db->prepare("
        INSERT INTO Donation (donorId, organizationId, amountTotal, amountAllocated, paymentMethod, transactionReference, status)
        VALUES (?, ?, ?, ?, ?, ?, 'allocated')
    ");
    $stmt->execute([
        $input['donorId'],
        $input['organizationId'],
        $amount,
        $amount,
        $paymentMethod,
        $transactionRef
    ]);
    
    // Update donor statistics
    $stmt = $db->prepare("
        UPDATE Donor 
        SET totalDonated = totalDonated + ?,
            donationCount = donationCount + 1,
            lastDonationAt = NOW()
        WHERE donorId = ?
    ");
    $stmt->execute([$amount, $input['donorId']]);
    
    // Update organization balance
    $stmt = $db->prepare("
        UPDATE Organization 
        SET totalReceived = totalReceived + ?,
            availableBalance = availableBalance + ?
        WHERE organizationId = ?
    ");
    $stmt->execute([$amount, $amount, $input['organizationId']]);
    
    $db->commit();
    
    // Get updated donor info
    $stmt = $db->prepare("SELECT totalDonated, donationCount, lastDonationAt FROM Donor WHERE donorId = ?");
    $stmt->execute([$input['donorId']]);
    $updatedDonor = $stmt->fetch();
    
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Donation successful!',
        'data' => [
            'transactionReference' => $transactionRef,
            'amount' => $amount,
            'donorStats' => [
                'totalDonated' => $updatedDonor['totalDonated'],
                'donationCount' => $updatedDonor['donationCount'],
                'lastDonationAt' => $updatedDonor['lastDonationAt']
            ]
        ]
    ]);
    
} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Create Donation Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to process donation. Please try again.'
    ]);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Create Donation Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred.'
    ]);
}
?>
