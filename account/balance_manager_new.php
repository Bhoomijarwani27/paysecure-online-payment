<?php
// Balance Management System for PaySecure
// This file handles all balance updates and transaction recording

require_once 'connect.php';

class BalanceManager {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Update user balance and record transaction
     */
    public function updateBalance($userId, $amount, $transactionType, $description, $reference = null) {
        try {
            // Get current balance
            $currentBalance = $this->getCurrentBalance($userId);
            $newBalance = $currentBalance + $amount;
            
            // Update account balance in create_account table using phone_number
            $stmt = $this->conn->prepare("UPDATE create_account SET balance = ? WHERE phone_number = ?");
            $stmt->bind_param("ds", $newBalance, $userId);
            if (!$stmt->execute()) {
                throw new Exception("Failed to update balance: " . $stmt->error);
            }
            
            // Record transaction
            $transactionId = $this->recordTransaction($userId, $amount, $transactionType, $description, $reference, $newBalance);
            
            return ['success' => true, 'transaction_id' => $transactionId, 'new_balance' => $newBalance];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get current balance by user phone number
     */
    public function getCurrentBalance($userId) {
        $stmt = $this->conn->prepare("SELECT balance FROM create_account WHERE phone_number = ?");
        $stmt->bind_param("s", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return floatval($row['balance']);
        }
        
        return 0.00;
    }
    
    /**
     * Get transaction history
     */
    public function getTransactionHistory($userId, $limit = 10) {
        $stmt = $this->conn->prepare("SELECT * FROM transactions WHERE user_id = ? OR phone_number = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->bind_param("ssi", $userId, $userId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $transactions = [];
        while ($row = $result->fetch_assoc()) {
            $transactions[] = $row;
        }
        
        return $transactions;
    }

    /**
     * Send money to another user
     */
    public function sendMoney($senderPhone, $recipientPhone, $amount, $note = 'Money Sent') {
        try {
            $this->conn->begin_transaction();
            
            // Check sender balance
            $currentBalance = $this->getCurrentBalance($senderPhone);
            if ($currentBalance < $amount) {
                throw new Exception("Insufficient balance");
            }
            
            $transactionId = 'TXN' . time() . rand(1000, 9999);
            
            // Debit sender
            $debitResult = $this->updateBalance($senderPhone, -$amount, 'debit', 
                "Payment to $recipientPhone", $transactionId);
                
            if (!$debitResult['success']) {
                throw new Exception($debitResult['error']);
            }
            
            // Credit recipient
            $creditResult = $this->updateBalance($recipientPhone, $amount, 'credit', 
                "Payment from $senderPhone", $transactionId);
                
            if (!$creditResult['success']) {
                throw new Exception($creditResult['error']);
            }
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'transaction_id' => $transactionId,
                'new_balance' => $debitResult['new_balance']
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Process mobile recharge
     */
    public function processRecharge($userId, $operatorId, $amount, $transactionId, $operator, $mobileNumber, $countryCode) {
        try {
            $this->conn->begin_transaction();
            
            // Check current balance
            $currentBalance = $this->getCurrentBalance($userId);
            if ($currentBalance < $amount) {
                throw new Exception("Insufficient balance");
            }
            
            // Debit from user
            $debitResult = $this->updateBalance($userId, -$amount, 'mobile_recharge', 
                "Mobile recharge - $operator ($mobileNumber)", $transactionId);
            
            if (!$debitResult['success']) {
                throw new Exception($debitResult['error']);
            }
            
            // Credit to operator (if operator account exists)
            if ($operatorId) {
                $creditResult = $this->updateBalance($operatorId, $amount, 'recharge_collection', 
                    "Recharge received - $mobileNumber ($operator)", $transactionId);
                
                if (!$creditResult['success']) {
                    throw new Exception($creditResult['error']);
                }
            }
            
            $this->conn->commit();
            return [
                'success' => true,
                'new_balance' => $debitResult['new_balance'],
                'transaction_id' => $transactionId
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process bill payment
     */
    public function processBillPayment($userId, $providerId, $amount, $transactionId, $billType, $provider, $accountNumber, $customerName) {
        try {
            $this->conn->begin_transaction();
            
            // Check current balance
            $currentBalance = $this->getCurrentBalance($userId);
            if ($currentBalance < $amount) {
                throw new Exception("Insufficient balance");
            }
            
            // Debit from user
            $debitResult = $this->updateBalance($userId, -$amount, 'bill_payment', 
                "Bill Pay: $billType - $provider ($accountNumber)", $transactionId);
            
            if (!$debitResult['success']) {
                throw new Exception($debitResult['error']);
            }
            
            // Credit to provider (if provider account exists)
            if ($providerId) {
                $creditResult = $this->updateBalance($providerId, $amount, 'bill_collection', 
                    "Bill Payment received - $accountNumber ($provider)", $transactionId);
                
                if (!$creditResult['success']) {
                    throw new Exception($creditResult['error']);
                }
            }
            
            $this->conn->commit();
            return [
                'success' => true,
                'new_balance' => $debitResult['new_balance'],
                'transaction_id' => $transactionId
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Record transaction in transactions table
     */
    private function recordTransaction($userId, $amount, $type, $description, $reference, $balanceAfter) {
        $transactionId = 'TXN' . time() . rand(1000, 9999);
        
        // Ensure transactions table exists
        $this->createTransactionsTable();
        
        // Get all columns
        $columns = [];
        $result = $this->conn->query("SHOW COLUMNS FROM transactions");
        while ($row = $result->fetch_assoc()) {
            $columns[] = strtolower($row['Field']);
        }
        
        // Determine columns to use
        $typeColumn = in_array('tx_type', $columns) ? 'tx_type' : (in_array('transaction_type', $columns) ? 'transaction_type' : 'type');
        $descColumn = in_array('note', $columns) ? 'note' : 'description';
        $userColumn = in_array('phone_number', $columns) ? 'phone_number' : 'user_id';
        
        $hasBalanceAfter = in_array('balance_after', $columns);
        $hasReferenceId = in_array('reference_id', $columns);
        $hasTransactionId = in_array('transaction_id', $columns);
        $hasDirection = in_array('direction', $columns);
        $hasCounterparty = in_array('counterparty', $columns);
        $hasStatus = in_array('status', $columns);
        
        // Determine direction
        $direction = ($amount >= 0) ? 'in' : 'out';
        
        // Construct query dynamically (created_at will use DEFAULT CURRENT_TIMESTAMP)
        $insertCols = [$userColumn, 'amount', $typeColumn, $descColumn];
        $params = [$userId, $amount, $type, $description];
        $types = "sdss";
        
        if ($hasTransactionId) {
            $insertCols[] = 'transaction_id';
            $params[] = $transactionId;
            $types .= "s";
        }
        
        if ($hasBalanceAfter) {
            $insertCols[] = 'balance_after';
            $params[] = $balanceAfter;
            $types .= "d";
        }
        
        if ($hasReferenceId) {
            $insertCols[] = 'reference_id';
            $params[] = $reference;
            $types .= "s";
        }
        
        if ($hasDirection) {
            $insertCols[] = 'direction';
            $params[] = $direction;
            $types .= "s";
        }
        
        if ($hasCounterparty) {
            $insertCols[] = 'counterparty';
            $counterparty = $reference; 
            $params[] = $counterparty;
            $types .= "s";
        }
        
        if ($hasStatus) {
            $insertCols[] = 'status';
            $params[] = 'success';
            $types .= "s";
        }
        
        // Build proper SQL with correct number of placeholders
        $placeholders = str_repeat('?,', count($params) - 1) . '?';
        $sql = "INSERT INTO transactions (" . implode(', ', $insertCols) . ") VALUES ($placeholders)";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            error_log("Transaction insert failed: " . $stmt->error);
        }
        
        return $transactionId;
    }
    
    /**
     * Create transactions table if it doesn't exist
     */
    public function createTransactionsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id VARCHAR(15) NOT NULL,
            type VARCHAR(50) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            description VARCHAR(255),
            status VARCHAR(20) DEFAULT 'success',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $this->conn->query($sql);
        
        // Add extra columns if needed
        $columns = [];
        $result = $this->conn->query("DESCRIBE transactions");
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
        
        if (!in_array('transaction_id', $columns)) {
            $this->conn->query("ALTER TABLE transactions ADD COLUMN transaction_id VARCHAR(50)");
        }
        if (!in_array('balance_after', $columns)) {
            $this->conn->query("ALTER TABLE transactions ADD COLUMN balance_after DECIMAL(10,2)");
        }
        if (!in_array('reference_id', $columns)) {
            $this->conn->query("ALTER TABLE transactions ADD COLUMN reference_id VARCHAR(255)");
        }
    }
}

// Initialize balance manager
if (isset($conn)) {
    $balanceManager = new BalanceManager($conn);
}
?>