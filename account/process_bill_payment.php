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
    $required_fields = ['bill_type', 'provider', 'account_number', 'customer_name', 'amount', 'upi_pin'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    $bill_type = trim($data['bill_type']);
    $provider = trim($data['provider']);
    $account_number = trim($data['account_number']);
    $customer_name = trim($data['customer_name']);
    $amount = floatval($data['amount']);
    $upi_pin = trim($data['upi_pin']);
    
    // Validate amount
    if ($amount <= 0) {
        throw new Exception('Invalid payment amount');
    }
    
    // Validate UPI PIN format
    if (!preg_match('/^\d{4,6}$/', $upi_pin)) {
        throw new Exception('Invalid UPI PIN format');
    }
    
    // Get user_id (phone_number) from session
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : '8888888888';
    
    // Ensure bill_payments table exists
    createBillPaymentsTable($conn);
    
    // Create BalanceManager instance
    $balanceManager = new BalanceManager($conn);
    
    // Verify UPI PIN
    $stmt = $conn->prepare("SELECT upi_pin FROM create_account WHERE phone_number = ?");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('User account not found');
    }
    
    $user = $result->fetch_assoc();
    
    // Verify UPI PIN
    if ($user['upi_pin'] !== $upi_pin) {
        throw new Exception('Invalid UPI PIN');
    }
    
    // Check if user has sufficient balance
    $current_balance = $balanceManager->getCurrentBalance($user_id);
    if ($current_balance < $amount) {
        throw new Exception('Insufficient balance');
    }
    
    // Generate transaction ID
    $transaction_id = 'BIL' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 12));
    
    // Create service provider account if not exists
    $provider_account_id = getOrCreateServiceProvider($conn, $provider, $bill_type);
    
    // Process bill payment using BalanceManager
    $payment_result = $balanceManager->processBillPayment(
        $user_id,
        $provider_account_id,
        $amount,
        $transaction_id,
        $bill_type,
        $provider,
        $account_number,
        $customer_name
    );
    
    if ($payment_result['success']) {
        // Record bill payment details for history
        $stmt = $conn->prepare("INSERT INTO bill_payments (transaction_id, user_phone, bill_type, provider, account_number, customer_name, amount, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', NOW())");
        $stmt->bind_param("ssssssd", $transaction_id, $user_id, $bill_type, $provider, $account_number, $customer_name, $amount);
        $stmt->execute();
        
        // Generate receipt
        $receipt_data = [
            'transaction_id' => $transaction_id,
            'bill_type' => $bill_type,
            'provider' => $provider,
            'account_number' => $account_number,
            'customer_name' => $customer_name,
            'amount' => $amount,
            'payment_date' => date('Y-m-d H:i:s'),
            'new_balance' => $payment_result['new_balance'],
            'status' => 'completed'
        ];
        
        // Save receipt to file
        $receipt_filename = 'bill_rcpt_' . strtolower(substr($transaction_id, 3)) . '.json';
        $receipt_path = '_receipts/' . $receipt_filename;
        
        // Ensure receipts directory exists
        if (!is_dir('_receipts')) {
            mkdir('_receipts', 0755, true);
        }
        
        file_put_contents($receipt_path, json_encode($receipt_data, JSON_PRETTY_PRINT));
        
        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'Bill payment processed successfully',
            'transaction_id' => $transaction_id,
            'bill_type' => $bill_type,
            'provider' => $provider,
            'account_number' => $account_number,
            'customer_name' => $customer_name,
            'amount' => $amount,
            'new_balance' => $payment_result['new_balance'],
            'receipt_url' => $receipt_path,
            'payment_date' => date('Y-m-d H:i:s')
        ]);
        
    } else {
        throw new Exception($payment_result['error'] ?? 'Payment processing failed');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function getOrCreateServiceProvider($conn, $provider_name, $bill_type) {
    // Check if provider already exists
    $stmt = $conn->prepare("SELECT phone_number FROM create_account WHERE phone_number = ? AND account_type = 'service_provider'");
    $provider_phone = 'provider_' . strtolower(str_replace(' ', '_', $provider_name));
    $stmt->bind_param("s", $provider_phone);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['phone_number'];
    }
    
    // Create new service provider account
    $provider_email = strtolower(str_replace(' ', '_', $provider_name)) . '@billpay.com';
    $default_pin = '1234'; // Default PIN for service providers
    
    $stmt = $conn->prepare("INSERT INTO create_account (name, email, phone_number, country_code, upi_pin, account_type, created_at) VALUES (?, ?, ?, '+91', ?, 'service_provider', NOW())");
    $stmt->bind_param("ssss", $provider_name, $provider_email, $provider_phone, $default_pin);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to create service provider account');
    }
    
    $provider_id = $provider_phone;
    
    // Initialize provider balance (they start receiving payments)
    $balanceManager = new BalanceManager($conn);
    $balanceManager->updateBalance($provider_id, 0, 'initial', 'Service provider account created');
    
    return $provider_id;
}

function createBillPaymentsTable($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS bill_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transaction_id VARCHAR(50) UNIQUE NOT NULL,
        user_phone VARCHAR(15) NOT NULL,
        bill_type VARCHAR(50) NOT NULL,
        provider VARCHAR(255) NOT NULL,
        account_number VARCHAR(100) NOT NULL,
        customer_name VARCHAR(255) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        status ENUM('pending', 'completed', 'failed') DEFAULT 'completed',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_created (user_phone, created_at),
        INDEX idx_bill_type (bill_type),
        INDEX idx_transaction_id (transaction_id)
    )";
    
    $conn->query($sql);
}
?>