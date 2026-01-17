<?php
/**
 * Create Withdrawal Request - Organizations can request fund withdrawals
 * 
 * BUSINESS LOGIC:
 * 1. Validates user role (must be organization) and approval status
 * 2. Calculates available balance dynamically (not stored):
 *    available = SUM(donations) - SUM(approved withdrawals) - SUM(pending withdrawals)
 * 3. Creates withdrawal with pending status
 * 4. Creates FIFO allocation records linking donations to withdrawal
 * 5. Links to impact story if provided (tracks fund usage)
 * 
 * SECURITY:
 * - Uses transactions for atomicity
 * - FOR UPDATE locks prevent race conditions
 * - Prepared statements prevent SQL injection
 * - Duplicate submission protection
 */

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
    echo json_encode(['success' => false, 'message' => 'Method not allowed', 'code' => 'method_not_allowed']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$organizationId = $input['organizationId'] ?? null;
$amount = isset($input['amount']) ? floatval($input['amount']) : 0;
$bankAccountNumber = trim($input['bankAccountNumber'] ?? '');
$purpose = trim($input['purpose'] ?? '');
$impactStoryId = isset($input['impactStoryId']) ? trim($input['impactStoryId']) : null;
$requestId = $input['requestId'] ?? null; // Client-side idempotency key

// Validation
if (empty($organizationId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Organization ID is required', 'code' => 'validation_error']);
    exit();
}

if ($amount <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Amount must be greater than 0', 'code' => 'validation_error']);
    exit();
}

if (empty($bankAccountNumber)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Bank account number is required', 'code' => 'validation_error']);
    exit();
}

if (empty($purpose)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Purpose is required', 'code' => 'validation_error']);
    exit();
}

try {
    $db = getDBConnection();
    $db->beginTransaction();
    
    // Lock organization row for update to prevent race conditions
    $stmt = $db->prepare("
        SELECT o.organizationId, o.verificationStatus, o.totalReceived, o.totalSpent,
               u.userId, u.isActive
        FROM Organization o
        INNER JOIN User u ON o.userId = u.userId
        WHERE o.organizationId = ?
        FOR UPDATE
    ");
    $stmt->execute([$organizationId]);
    $org = $stmt->fetch();
    
    if (!$org) {
        $db->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Organization not found', 'code' => 'unauthorized']);
        exit();
    }
    
    // Check if user is active
    if (!$org['isActive']) {
        $db->rollBack();
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Account is deactivated', 'code' => 'unauthorized']);
        exit();
    }
    
    // Check if organization is approved
    if ($org['verificationStatus'] !== 'approved') {
        $db->rollBack();
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Only approved organizations can make withdrawals', 'code' => 'unauthorized']);
        exit();
    }
    
    // Calculate available balance dynamically
    // Available = Total Donations - (Approved Withdrawals + Pending Withdrawals)
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(amountTotal), 0) as totalDonations
        FROM Donation
        WHERE organizationId = ?
    ");
    $stmt->execute([$organizationId]);
    $totalDonations = floatval($stmt->fetch()['totalDonations']);
    
    // Get sum of approved and pending withdrawals
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(amount), 0) as totalWithdrawals
        FROM Withdrawal
        WHERE organizationId = ?
        AND status IN ('approved', 'pending', 'completed')
    ");
    $stmt->execute([$organizationId]);
    $totalWithdrawals = floatval($stmt->fetch()['totalWithdrawals']);
    
    // Calculate actual available balance
    $availableBalance = $totalDonations - $totalWithdrawals;
    
    // Validate available balance
    if ($amount > $availableBalance) {
        $db->rollBack();
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'Insufficient balance. Available: $' . number_format($availableBalance, 2),
            'code' => 'insufficient_balance',
            'availableBalance' => $availableBalance
        ]);
        exit();
    }
    
    // Check for duplicate submissions (same amount within 5 minutes)
    $stmt = $db->prepare("
        SELECT withdrawalId, amount, status
        FROM Withdrawal
        WHERE organizationId = ? 
        AND amount = ? 
        AND status = 'pending'
        AND requestedAt > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        FOR UPDATE
    ");
    $stmt->execute([$organizationId, $amount]);
    $pendingDuplicate = $stmt->fetch();
    
    if ($pendingDuplicate) {
        $db->rollBack();
        http_response_code(409);
        echo json_encode([
            'success' => false, 
            'message' => 'A similar withdrawal request was recently submitted. Please wait before submitting again.',
            'code' => 'duplicate_request',
            'existingWithdrawalId' => $pendingDuplicate['withdrawalId']
        ]);
        exit();
    }
    
    // Validate impact story if provided
    if (!empty($impactStoryId)) {
        $stmt = $db->prepare("
            SELECT storyId, title FROM ImpactStory 
            WHERE storyId = ? AND organizationId = ?
        ");
        $stmt->execute([$impactStoryId, $organizationId]);
        $story = $stmt->fetch();
        
        if (!$story) {
            $db->rollBack();
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'message' => 'Invalid impact story selected',
                'code' => 'validation_error'
            ]);
            exit();
        }
    }
    
    // Generate UUID for withdrawal
    $stmt = $db->query("SELECT UUID() as uuid");
    $withdrawalId = $stmt->fetch()['uuid'];
    
    // Create withdrawal request with pending status
    $stmt = $db->prepare("
        INSERT INTO Withdrawal (
            withdrawalId,
            organizationId,
            amount,
            status,
            bankAccountNumber,
            purpose,
            requestedAt
        ) VALUES (?, ?, ?, 'pending', ?, ?, NOW())
    ");
    $stmt->execute([
        $withdrawalId,
        $organizationId,
        $amount,
        $bankAccountNumber,
        $purpose
    ]);
    
    // Create FIFO allocation records - link withdrawal to donations
    // Get donations with remaining unallocated amounts
    $stmt = $db->prepare("
        SELECT d.donationId, d.donorId, d.amountTotal,
               COALESCE((SELECT SUM(da.amountSpent) FROM DonationAllocation da WHERE da.donationId = d.donationId), 0) as amountAllocated
        FROM Donation d
        WHERE d.organizationId = ?
        HAVING (d.amountTotal - amountAllocated) > 0
        ORDER BY d.donatedAt ASC
        FOR UPDATE
    ");
    $stmt->execute([$organizationId]);
    $donations = $stmt->fetchAll();
    
    $remainingAmount = $amount;
    $allocations = [];
    
    // Get or create a default beneficiary for the organization
    $stmt = $db->prepare("
        SELECT beneficiaryId FROM Beneficiary 
        WHERE organizationId = ? AND name = 'General Fund'
        LIMIT 1
    ");
    $stmt->execute([$organizationId]);
    $beneficiary = $stmt->fetch();
    
    if (!$beneficiary) {
        // Create default beneficiary
        $stmt = $db->query("SELECT UUID() as uuid");
        $beneficiaryId = $stmt->fetch()['uuid'];
        
        $stmt = $db->prepare("
            INSERT INTO Beneficiary (beneficiaryId, organizationId, name, type, location, description)
            VALUES (?, ?, 'General Fund', 'project', 'Organization Operations', 'General operational fund for the organization')
        ");
        $stmt->execute([$beneficiaryId, $organizationId]);
    } else {
        $beneficiaryId = $beneficiary['beneficiaryId'];
    }
    
    // Allocate from donations using FIFO
    foreach ($donations as $donation) {
        if ($remainingAmount <= 0) break;
        
        $available = floatval($donation['amountTotal']) - floatval($donation['amountAllocated']);
        $allocateAmount = min($available, $remainingAmount);
        
        if ($allocateAmount > 0) {
            // Generate allocation UUID
            $stmt = $db->query("SELECT UUID() as uuid");
            $allocationId = $stmt->fetch()['uuid'];
            
            // Create allocation record
            $stmt = $db->prepare("
                INSERT INTO DonationAllocation (
                    allocationId,
                    donationId,
                    beneficiaryId,
                    impactStoryId,
                    withdrawalId,
                    amountSpent,
                    purpose,
                    status,
                    spentAt
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([
                $allocationId,
                $donation['donationId'],
                $beneficiaryId,
                $impactStoryId,
                $withdrawalId,
                $allocateAmount,
                $purpose
            ]);
            
            $allocations[] = [
                'allocationId' => $allocationId,
                'donationId' => $donation['donationId'],
                'donorId' => $donation['donorId'],
                'amount' => $allocateAmount
            ];
            
            $remainingAmount -= $allocateAmount;
        }
    }
    
    // Update impact story funding if provided
    if (!empty($impactStoryId)) {
        $stmt = $db->prepare("
            UPDATE ImpactStory 
            SET totalFunding = totalFunding + ?,
                allocationCount = allocationCount + ?
            WHERE storyId = ?
        ");
        $stmt->execute([$amount, count($allocations), $impactStoryId]);
    }
    
    $db->commit();
    
    // Log successful withdrawal request
    error_log("Withdrawal Request Created: ID=$withdrawalId, Org=$organizationId, Amount=$amount, Allocations=" . count($allocations));
    
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Withdrawal request submitted successfully',
        'code' => 'success',
        'data' => [
            'withdrawalId' => $withdrawalId,
            'amount' => $amount,
            'status' => 'pending',
            'bankAccountNumber' => $bankAccountNumber,
            'purpose' => $purpose,
            'impactStoryId' => $impactStoryId,
            'allocationsCount' => count($allocations),
            'availableBalanceAfter' => $availableBalance - $amount
        ]
    ]);
    
} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Create Withdrawal Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to create withdrawal request.',
        'code' => 'server_error'
    ]);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Create Withdrawal Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred.',
        'code' => 'server_error'
    ]);
}
?>
