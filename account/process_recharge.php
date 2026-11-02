<?php
require_once 'connect.php';
require_once 'balance_manager.php';

// Set JSON content type
header('Content-Type: application/json');

// Start session for user authentication
session_start();

try {
    // Get POST data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Invalid JSON data received');
    }
    
    // Validate required fields
    $required_fields = ['mobile_number', 'operator', 'amount', 'upi_pin'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    $mobile_number = trim($data['mobile_number']);
    $operator = trim($data['operator']);
    $amount = floatval($data['amount']);
    $upi_pin = trim($data['upi_pin']);
    $country_code = isset($data['country_code']) ? trim($data['country_code']) : '+91';
    
    // Validate amount
    if ($amount <= 0) {
        throw new Exception('Invalid recharge amount');
    }
    
    // Validate mobile number format
    if (!preg_match('/^\d{10,15}$/', $mobile_number)) {
        throw new Exception('Invalid mobile number format');
    }
    
    // Validate UPI PIN format
    if (!preg_match('/^\d{4,6}$/', $upi_pin)) {
        throw new Exception('Invalid UPI PIN format');
    }
    
    // Get user_id from session or use default for testing
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;
    
    // Ensure recharges table exists
    createRechargesTable($conn);
    
    // Create BalanceManager instance
    $balanceManager = new BalanceManager($conn);
    
    // Verify UPI PIN (you can enhance this with actual PIN verification)
    $stmt = $conn->prepare("SELECT upi_pin FROM create_account WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('User account not found');
    }
    
    $user = $result->fetch_assoc();
    
    // Verify UPI PIN (simple verification - you can enhance this)
    if ($user['upi_pin'] !== $upi_pin) {
        throw new Exception('Invalid UPI PIN');
    }
    
    // Check if user has sufficient balance
    $current_balance = $balanceManager->getCurrentBalance($user_id);
    if ($current_balance < $amount) {
        throw new Exception('Insufficient balance');
    }
    
    // Generate transaction ID
    $transaction_id = 'RCH' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 12));
    
    // Create operator account if not exists
    $operator_account_id = getOrCreateOperator($conn, $operator);
    
    // Process recharge using BalanceManager
    $recharge_result = $balanceManager->processRecharge(
        $user_id,
        $operator_account_id,
        $amount,
        $transaction_id,
        $operator,
        $mobile_number,
        $country_code
    );
    
    if ($recharge_result['success']) {
        // Record recharge details for history
        $stmt = $conn->prepare("INSERT INTO recharges (transaction_id, user_id, mobile_number, country_code, operator, amount, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'completed', NOW())");
        $stmt->bind_param("sisssd", $transaction_id, $user_id, $mobile_number, $country_code, $operator, $amount);
        $stmt->execute();
        
        // Generate receipt
        $receipt_data = [
            'transaction_id' => $transaction_id,
            'mobile_number' => $mobile_number,
            'country_code' => $country_code,
            'operator' => $operator,
            'amount' => $amount,
            'recharge_date' => date('Y-m-d H:i:s'),
            'new_balance' => $recharge_result['new_balance'],
            'status' => 'completed'
        ];
        
        // Save receipt to file
        $receipt_filename = 'rch_rcpt_' . strtolower(substr($transaction_id, 3)) . '.json';
        $receipt_path = '_receipts/' . $receipt_filename;
        
        // Ensure receipts directory exists
        if (!is_dir('_receipts')) {
            mkdir('_receipts', 0755, true);
        }
        
        file_put_contents($receipt_path, json_encode($receipt_data, JSON_PRETTY_PRINT));
        
        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'Recharge processed successfully',
            'transaction_id' => $transaction_id,
            'mobile_number' => $mobile_number,
            'country_code' => $country_code,
            'operator' => $operator,
            'amount' => $amount,
            'new_balance' => $recharge_result['new_balance'],
            'receipt_url' => $receipt_path,
            'recharge_date' => date('Y-m-d H:i:s')
        ]);
        
    } else {
        throw new Exception($recharge_result['error'] ?? 'Recharge processing failed');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function getOrCreateOperator($conn, $operator_name) {
    // Check if operator already exists
    $stmt = $conn->prepare("SELECT id FROM create_account WHERE phone_number = ? AND account_type = 'operator'");
    $operator_phone = 'operator_' . strtolower(str_replace(' ', '_', $operator_name));
    $stmt->bind_param("s", $operator_phone);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['id'];
    }
    
    // Create new operator account
    $operator_email = strtolower(str_replace(' ', '_', $operator_name)) . '@recharge.com';
    $default_pin = '1234'; // Default PIN for operators
    
    $stmt = $conn->prepare("INSERT INTO create_account (name, email, phone_number, upi_pin, account_type, created_at) VALUES (?, ?, ?, ?, 'operator', NOW())");
    $stmt->bind_param("ssss", $operator_name, $operator_email, $operator_phone, $default_pin);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to create operator account');
    }
    
    $operator_id = $conn->insert_id;
    
    // Initialize operator balance (they start receiving payments)
    $balanceManager = new BalanceManager($conn);
    $balanceManager->updateBalance($operator_id, 0, 'initial', 'Operator account created');
    
    return $operator_id;
}

function createRechargesTable($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS recharges (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transaction_id VARCHAR(50) UNIQUE NOT NULL,
        user_id INT NOT NULL,
        mobile_number VARCHAR(15) NOT NULL,
        country_code VARCHAR(10) NOT NULL,
        operator VARCHAR(50) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        status ENUM('pending', 'completed', 'failed') DEFAULT 'completed',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_created (user_id, created_at),
        INDEX idx_mobile (mobile_number),
        INDEX idx_transaction_id (transaction_id)
    )";
    
    $conn->query($sql);
}
?>