<?php
session_start();
header('Content-Type: application/json');

while ($conn->next_result()) {
    if ($result = $conn->store_result()) {
        $result->free();
    }
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch($action) {
    case 'validate_code':
        validateReferralCode();
        break;
    case 'apply_code':
        applyReferralCode();
        break;
    case 'generate_code':
        generateReferralCode();
        break;
    case 'get_earnings':
        getReferralEarnings();
        break;
    case 'get_transactions':
        getReferralTransactions();
        break;
    case 'add_test_money':
        addTestMoney();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function validateReferralCode() {
    global $conn;
    
    $code = $_GET['code'] ?? '';
    $currentUserId = $_SESSION['user_id'] ?? null;
    
    if (empty($code)) {
        echo json_encode(['valid' => false, 'message' => 'Please enter a referral code']);
        return;
    }
    
    // Check if code exists and is not from the same user
    $stmt = $conn->prepare("SELECT rc.*, u.name as referrer_name FROM referral_codes rc JOIN users u ON rc.user_id = u.id WHERE rc.referral_code = ?");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $result = $stmt->get_result();
    $referralData = $result->fetch_assoc();
    
    if ($referralData) {
        // Check if user is trying to use their own code
        if ($currentUserId && $referralData['user_id'] == $currentUserId) {
            echo json_encode([
                'valid' => false, 
                'message' => '✗ You cannot use your own referral code'
            ]);
            return;
        }
        
        // Check if user has already used a referral code
        if ($currentUserId) {
            $checkStmt = $conn->prepare("SELECT COUNT(*) FROM referral_transactions WHERE referred_user_id = ?");
            $checkStmt->bind_param("i", $currentUserId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            if ($checkResult->fetch_row()[0] > 0) {
                echo json_encode([
                    'valid' => false, 
                    'message' => '✗ You have already used a referral code'
                ]);
                return;
            }
        }
        
        echo json_encode([
            'valid' => true, 
            'message' => '✓ Valid referral code! You and ' . $referralData['referrer_name'] . ' will each earn ₹50',
            'referrer_name' => $referralData['referrer_name']
        ]);
    } else {
        echo json_encode([
            'valid' => false, 
            'message' => '✗ Invalid referral code. Please check and try again.'
        ]);
    }
}

function applyReferralCode() {
    global $conn, $balanceManager;
    
    $code = $_POST['code'] ?? '';
    $userId = $_SESSION['user_id'] ?? null;
    
    if (!$userId) {
        echo json_encode(['success' => false, 'message' => 'User not logged in']);
        return;
    }
    
    if (empty($code)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a referral code']);
        return;
    }
    
    // Check if user has already used a referral code
    $stmt = $conn->prepare("SELECT COUNT(*) FROM referral_transactions WHERE referred_user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->fetch_row()[0] > 0) {
        echo json_encode(['success' => false, 'message' => 'You have already used a referral code']);
        return;
    }
    
    // Get referral code info
    $stmt = $conn->prepare("SELECT rc.*, u.name as referrer_name FROM referral_codes rc JOIN users u ON rc.user_id = u.id WHERE rc.referral_code = ?");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $result = $stmt->get_result();
    $referralInfo = $result->fetch_assoc();
    
    if (!$referralInfo) {
        echo json_encode(['success' => false, 'message' => 'Invalid referral code']);
        return;
    }
    
    // Check if user is trying to use their own code
    if ($referralInfo['user_id'] == $userId) {
        echo json_encode(['success' => false, 'message' => 'You cannot use your own referral code']);
        return;
    }
    
    $conn->begin_transaction();
    
    try {
        // Add ₹50 to current user (referred user) - signup bonus
        $signupResult = $balanceManager->updateBalance(
            $userId, 
            50, 
            'signup_bonus', 
            "Welcome bonus for using referral code: $code", 
            $code
        );
        
        if (!$signupResult['success']) {
            throw new Exception($signupResult['error']);
        }
        
        // Add ₹50 to referrer - referral bonus
        $referralResult = $balanceManager->updateBalance(
            $referralInfo['user_id'], 
            50, 
            'referral_bonus', 
            "Referral bonus for code: $code", 
            $code
        );
        
        if (!$referralResult['success']) {
            throw new Exception($referralResult['error']);
        }
        
        // Update referral code stats
        $stmt = $conn->prepare("UPDATE referral_codes SET earnings = earnings + 50, total_referrals = total_referrals + 1 WHERE id = ?");
        $stmt->bind_param("i", $referralInfo['id']);
        $stmt->execute();
        
        // Record referral transaction mapping
        $stmt = $conn->prepare("INSERT INTO referral_transactions (referrer_user_id, referred_user_id, referral_code, amount, transaction_type, created_at) VALUES (?, ?, ?, 50, 'signup_bonus', NOW())");
        $stmt->bind_param("iis", $referralInfo['user_id'], $userId, $code);
        $stmt->execute();
        
        $stmt = $conn->prepare("INSERT INTO referral_transactions (referrer_user_id, referred_user_id, referral_code, amount, transaction_type, created_at) VALUES (?, ?, ?, 50, 'referral_bonus', NOW())");
        $stmt->bind_param("iis", $referralInfo['user_id'], $userId, $code);
        $stmt->execute();
        
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => "🎉 Referral code applied! ₹50 added to your account. {$referralInfo['referrer_name']} also earned ₹50!",
            'bonus_amount' => 50,
            'referrer_name' => $referralInfo['referrer_name'],
            'signup_transaction_id' => $signupResult['transaction_id'],
            'referral_transaction_id' => $referralResult['transaction_id']
        ]);
        
    } catch(Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Transaction failed: ' . $e->getMessage()]);
    }
}

function generateReferralCode() {
    global $conn;
    
    $userId = $_SESSION['user_id'] ?? null;
    
    if (!$userId) {
        echo json_encode(['success' => false, 'message' => 'User not logged in']);
        return;
    }
    
    // Check if user already has a referral code
    $stmt = $conn->prepare("SELECT referral_code FROM referral_codes WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $existingCode = $result->fetch_row();
    
    if ($existingCode) {
        echo json_encode(['success' => true, 'code' => $existingCode[0]]);
        return;
    }
    
    // Generate unique referral code
    do {
        $code = 'PAYS' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
        $checkStmt = $conn->prepare("SELECT COUNT(*) FROM referral_codes WHERE referral_code = ?");
        $checkStmt->bind_param("s", $code);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
    } while ($checkResult->fetch_row()[0] > 0);
    
    // Insert new referral code
    $stmt = $conn->prepare("INSERT INTO referral_codes (user_id, referral_code, created_at) VALUES (?, ?, NOW())");
    $stmt->bind_param("is", $userId, $code);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'code' => $code]);
}

function getReferralEarnings() {
    global $conn;
    
    $userId = $_SESSION['user_id'] ?? null;
    
    if (!$userId) {
        echo json_encode(['earnings' => 0, 'referrals' => 0]);
        return;
    }
    
    $stmt = $conn->prepare("SELECT earnings, total_referrals FROM referral_codes WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    
    echo json_encode([
        'earnings' => $data['earnings'] ?? 0,
        'referrals' => $data['total_referrals'] ?? 0
    ]);
}

function getReferralTransactions() {
    global $conn, $balanceManager;
    
    $userId = $_SESSION['user_id'] ?? null;
    $type = $_GET['type'] ?? 'all';
    $period = $_GET['period'] ?? '30';
    
    if (!$userId) {
        echo json_encode(['success' => true, 'transactions' => []]);
        return;
    }
    
    // Convert period to days
    $periodDays = 30;
    if ($period === '90') $periodDays = 90;
    else if ($period === '365') $periodDays = 365;
    else if ($period === 'all') $periodDays = 'all';
    
    // Get regular transactions from balance manager
    $transactions = $balanceManager->getTransactionHistory($userId, 100, $type, $periodDays);
    
    // Get referral-specific details for referral transactions
    foreach ($transactions as &$transaction) {
        if (in_array($transaction['transaction_type'], ['referral_bonus', 'signup_bonus'])) {
            $stmt = $conn->prepare("
                SELECT rt.referral_code, u.name as other_user_name
                FROM referral_transactions rt 
                LEFT JOIN users u ON (
                    CASE 
                        WHEN rt.referrer_user_id = ? THEN rt.referred_user_id = u.id
                        ELSE rt.referrer_user_id = u.id
                    END
                )
                WHERE (rt.referrer_user_id = ? OR rt.referred_user_id = ?) 
                AND rt.transaction_type = ?
                AND ABS(TIMESTAMPDIFF(SECOND, rt.created_at, ?)) < 5
                LIMIT 1
            ");
            $stmt->bind_param("iiiss", $userId, $userId, $userId, $transaction['transaction_type'], $transaction['created_at']);
            $stmt->execute();
            $result = $stmt->get_result();
            $referralData = $result->fetch_assoc();
            
            if ($referralData) {
                $transaction['referral_code'] = $referralData['referral_code'];
                $transaction['other_user_name'] = $referralData['other_user_name'];
            }
        }
    }
    
    echo json_encode(['success' => true, 'transactions' => $transactions]);
}

// Add money for testing (remove in production)
function addTestMoney() {
    global $balanceManager;
    
    $userId = $_SESSION['user_id'] ?? null;
    $amount = floatval($_POST['amount'] ?? 500); // Default ₹500 for testing
    
    if (!$userId) {
        echo json_encode(['success' => false, 'message' => 'User not logged in']);
        return;
    }
    
    if ($amount <= 0 || $amount > 10000) {
        echo json_encode(['success' => false, 'message' => 'Invalid amount (₹1 to ₹10,000)']);
        return;
    }
    
    try {
        $result = $balanceManager->addMoney($userId, $amount, 'test_credit');
        
        if ($result['success']) {
            echo json_encode([
                'success' => true,
                'message' => "₹{$amount} added to your wallet for testing",
                'new_balance' => $result['new_balance'],
                'transaction_id' => $result['transaction_id']
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => $result['error']
            ]);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to add money: ' . $e->getMessage()]);
    }
}
?>
