<?php
// Balance Management System for PaySecure
// Last Updated: 2025-11-24 - Fixed user_id and phone_number population
require_once 'connect.php';

class BalanceManager {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }

    public function updateBalance($userId, $amount, $transactionType, $description, $reference = null) {
        try {
            $currentBalance = $this->getCurrentBalance($userId);
            $newBalance = $currentBalance + $amount;
            
            $stmt = $this->conn->prepare("UPDATE create_account SET balance = ? WHERE phone_number = ?");
            $stmt->bind_param("ds", $newBalance, $userId);
            if (!$stmt->execute()) {
                throw new Exception("Failed to update balance: " . $stmt->error);
            }

            if ($stmt->affected_rows === 0) {
                throw new Exception("User not found or balance unchanged (Phone: $userId)");
            }
            
            $transactionId = $this->recordTransaction($userId, $amount, $transactionType, $description, $reference, $newBalance);
            
            return ['success' => true, 'transaction_id' => $transactionId, 'new_balance' => $newBalance];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
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

    public function sendMoney($senderPhone, $recipientPhone, $amount, $note = 'Money Sent') {
        try {
            $this->conn->begin_transaction();
            
            $currentBalance = $this->getCurrentBalance($senderPhone);
            if ($currentBalance < $amount) {
                throw new Exception("Insufficient balance");
            }
            
            $transactionId = 'TXN' . time() . rand(1000, 9999);
            
            $debitResult = $this->updateBalance($senderPhone, -$amount, 'debit', 
                "Payment to $recipientPhone", $transactionId);
                
            if (!$debitResult['success']) {
                throw new Exception($debitResult['error']);
            }
            
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
    
    public function processRecharge($userId, $operatorId, $amount, $transactionId, $operator, $mobileNumber, $countryCode) {
        return $this->updateBalance($userId, -$amount, 'mobile_recharge', "Mobile recharge - $operator", $transactionId);
    }

    public function processBillPayment($userId, $providerId, $amount, $transactionId, $billType, $provider, $accountNumber, $customerName) {
        return $this->updateBalance($userId, -$amount, 'bill_payment', "Bill Pay: $billType", $transactionId);
    }
    
    private function recordTransaction($userId, $amount, $type, $description, $reference, $balanceAfter) {
        $transactionId = 'TXN' . time() . rand(1000, 9999);
        
        $this->createTransactionsTable();
        
        // Get columns
        $columns = [];
        $result = $this->conn->query("SHOW COLUMNS FROM transactions");
        while ($row = $result->fetch_assoc()) {
            $columns[] = strtolower($row['Field']);
        }
        
        $typeColumn = in_array('tx_type', $columns) ? 'tx_type' : (in_array('transaction_type', $columns) ? 'transaction_type' : 'type');
        $descColumn = in_array('note', $columns) ? 'note' : 'description';
        
        // Base columns
        $insertCols = ['amount', $typeColumn, $descColumn];
        $params = [$amount, $type, $description];
        $types = "dss";
        
        // Add user identifiers
        if (in_array('user_id', $columns)) {
            $insertCols[] = 'user_id';
            $params[] = $userId;
            $types .= "s";
        }
        if (in_array('phone_number', $columns)) {
            $insertCols[] = 'phone_number';
            $params[] = $userId;
            $types .= "s";
        }
        
        // Add optional columns if they exist
        if (in_array('transaction_id', $columns)) {
            $insertCols[] = 'transaction_id';
            $params[] = $transactionId;
            $types .= "s";
        }
        
        if (in_array('balance_after', $columns)) {
            $insertCols[] = 'balance_after';
            $params[] = $balanceAfter;
            $types .= "d";
        }
        
        if (in_array('reference_id', $columns)) {
            $insertCols[] = 'reference_id';
            $params[] = $reference;
            $types .= "s";
        }
        
        if (in_array('direction', $columns)) {
            $insertCols[] = 'direction';
            $params[] = ($amount >= 0) ? 'in' : 'out';
            $types .= "s";
        }

        // Log for debugging
        file_put_contents('c:/xampp/htdocs/online_payment/account/debug_log.txt', "Recording Transaction: UserID=$userId, Amount=$amount, Type=$type\n", FILE_APPEND);
        
        $placeholders = str_repeat('?,', count($params) - 1) . '?';
        $sql = "INSERT INTO transactions (" . implode(', ', $insertCols) . ") VALUES ($placeholders)";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            error_log("Transaction insert failed: " . $stmt->error);
            file_put_contents('c:/xampp/htdocs/online_payment/account/debug_log.txt', "Insert Failed: " . $stmt->error . "\n", FILE_APPEND);
        }
        
        return $transactionId;
    }
    
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
        
        if (!in_array('transaction_id', $columns)) $this->conn->query("ALTER TABLE transactions ADD COLUMN transaction_id VARCHAR(50)");
        if (!in_array('balance_after', $columns)) $this->conn->query("ALTER TABLE transactions ADD COLUMN balance_after DECIMAL(10,2)");
        if (!in_array('reference_id', $columns)) $this->conn->query("ALTER TABLE transactions ADD COLUMN reference_id VARCHAR(255)");
    }
}

if (isset($conn)) {
    $balanceManager = new BalanceManager($conn);
}
?>