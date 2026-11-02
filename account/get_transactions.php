<?php
session_start();
require_once 'connect.php';
require_once 'balance_manager.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];
$type = $_GET['type'] ?? 'all';
$period = $_GET['period'] ?? '30';

// Convert period to days
$periodDays = 30;
if ($period === '90') $periodDays = 90;
else if ($period === '365') $periodDays = 365;
else if ($period === 'all') $periodDays = 'all';

// Convert type filter
$typeFilter = 'all';
if ($type === 'sent') $typeFilter = 'money_sent';
else if ($type === 'received') $typeFilter = 'money_received';
else if ($type === 'bills') $typeFilter = 'bill_payment';
else if ($type === 'recharge') $typeFilter = 'mobile_recharge';
else if ($type === 'referral') $typeFilter = 'referral_bonus';

try {
    $balanceManager = new BalanceManager($conn);
    $transactions = $balanceManager->getTransactionHistory($userId, 100, $typeFilter, $periodDays);
    
    echo json_encode([
        'success' => true,
        'transactions' => $transactions,
        'count' => count($transactions)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>