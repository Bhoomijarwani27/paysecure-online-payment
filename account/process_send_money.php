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
    
    // Validate required fields - now accepts either phone or VPA
    $required_fields = ['amount', 'pin'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    // Accept either counterparty_phone OR counterparty_vpa
    if (empty($data['counterparty_phone']) && empty($data['counterparty_vpa'])) {
        throw new Exception('Either phone number or VPA is required');
    }
    
    $recipient_phone = isset($data['counterparty_phone']) ? trim($data['counterparty_phone']) : null;
    $recipient_vpa = isset($data['counterparty_vpa']) ? trim($data['counterparty_vpa']) : null;
    $amount = floatval($data['amount']);
    $upi_pin = trim($data['pin']);
    $note = isset($data['note']) ? trim($data['note']) : 'Money Sent';
    
    // Validate amount
    if ($amount <= 0) {
        throw new Exception('Invalid amount');
    }
    
    // Validate UPI PIN format
    if (!preg_match('/^\d{4,6}$/', $upi_pin)) {
        throw new Exception('Invalid UPI PIN format');
    }
    
    // Get user phone from session
    $user_phone = isset($_SESSION['phone_number']) ? $_SESSION['phone_number'] : null;
    
    if (!$user_phone) {
        throw new Exception('User not logged in');
    }
    
    // Create BalanceManager instance
    $balanceManager = new BalanceManager($conn);
    
    // Verify UPI PIN from bank_details table
    // Prioritize accounts that have a PIN set
    $stmt = $conn->prepare("SELECT upi_pin, bank_name FROM bank_details WHERE phone_number = ? ORDER BY CASE WHEN upi_pin IS NOT NULL AND upi_pin != '' THEN 1 ELSE 2 END, bank_name LIMIT 1");
    $stmt->bind_param("s", $user_phone);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('No bank account linked. Please link a bank account first.');
    }
    
    $bank_account = $result->fetch_assoc();
    $bank_name = $bank_account['bank_name'];
    
    // Verify UPI PIN - trim both values and convert to string for comparison
    $stored_pin = trim((string)($bank_account['upi_pin'] ?? ''));
    $entered_pin = trim((string)$upi_pin);
    
    // Check if PIN is set
    if (empty($stored_pin)) {
        throw new Exception('UPI PIN not set. Please set your UPI PIN first.');
    }
    
    // Compare PINs
    if ($stored_pin !== $entered_pin) {
        throw new Exception('Invalid UPI PIN');
    }
    
    // Check bank balance from bank_balances table
    $stmt = $conn->prepare("SELECT balance FROM bank_balances WHERE phone_number = ? AND bank_name = ?");
    $stmt->bind_param("ss", $user_phone, $bank_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Bank balance not found. Please contact support.');
    }
    
    $balance_row = $result->fetch_assoc();
    $current_balance = floatval($balance_row['balance']);
    
    if ($current_balance < $amount) {
        throw new Exception('Insufficient balance in your bank account');
    }
    
    // Find recipient phone by phone OR VPA
    if ($recipient_vpa) {
        // Search by VPA first
        $stmt = $conn->prepare("SELECT phone_number, name FROM create_account WHERE vpa = ?");
        $stmt->bind_param("s", $recipient_vpa);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('This VPA is not registered in PaySecure. Please use a valid PaySecure VPA or phone number.');
        }
    } else {
        // Search by phone number
        $stmt = $conn->prepare("SELECT phone_number, name FROM create_account WHERE phone_number = ?");
        $stmt->bind_param("s", $recipient_phone);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('Recipient phone not found: ' . $recipient_phone);
        }
    }
    
    $recipient = $result->fetch_assoc();
    $recipient_phone_number = $recipient['phone_number'];
    
    if ($recipient_phone_number == $user_phone) {
        throw new Exception('Cannot send money to yourself');
    }
    
    // Process money transfer using BalanceManager (updates wallet balance)
    $transfer_result = $balanceManager->sendMoney(
        $user_phone,
        $recipient_phone_number,
        $amount,
        $note
    );
    
    if ($transfer_result['success']) {
        // Also deduct from bank balance
        $stmt = $conn->prepare("UPDATE bank_balances SET balance = balance - ? WHERE phone_number = ? AND bank_name = ?");
        $stmt->bind_param("dss", $amount, $user_phone, $bank_name);
        if (!$stmt->execute()) {
            // Log error but don't fail the transaction since wallet was already updated
            error_log("Failed to update bank balance for $user_phone: " . $stmt->error);
        }
        
        // Generate receipt
        $transaction_id = $transfer_result['transaction_id'];
        $receipt_data = [
            'transaction_id' => $transaction_id,
            'recipient_phone' => $recipient_phone_number,
            'recipient_name' => $recipient['name'],
            'amount' => $amount,
            'note' => $note,
            'date' => date('Y-m-d H:i:s'),
            'new_balance' => $transfer_result['new_balance'],
            'status' => 'completed'
        ];
        
        // Save receipt to file
        $receipt_filename = 'txn_rcpt_' . strtolower(substr($transaction_id, 3)) . '.json';
        $receipt_path = '_receipts/' . $receipt_filename;
        
        // Ensure receipts directory exists
        if (!is_dir('_receipts')) {
            mkdir('_receipts', 0755, true);
        }
        
        file_put_contents($receipt_path, json_encode($receipt_data, JSON_PRETTY_PRINT));
        
        // Return success response
        echo json_encode([
            'ok' => true,
            'success' => true,
            'message' => 'Money sent successfully',
            'transaction_id' => $transaction_id,
            'recipient_name' => $recipient['name'],
            'tx_sender' => [
                'amount' => $amount,
                'counterparty' => $recipient_phone_number,
                'new_balance' => $transfer_result['new_balance'],
                'receipt_url' => $receipt_path
            ],
            'receipt_url' => $receipt_path
        ]);
        
    } else {
        throw new Exception($transfer_result['error'] ?? 'Transfer failed');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'ok' => false,
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
