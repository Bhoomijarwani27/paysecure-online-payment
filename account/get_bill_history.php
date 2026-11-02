<?php
require_once 'connect.php';

// Set JSON content type
header('Content-Type: application/json');

// Start session for user authentication
session_start();

try {
    // Get user_id from session or use default for testing
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;
    
    // Get filter parameters
    $bill_type = isset($_GET['bill_type']) ? trim($_GET['bill_type']) : '';
    $period = isset($_GET['period']) ? intval($_GET['period']) : 30;
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    
    // Build query with filters
    $whereClause = "WHERE t.user_id = ? AND t.transaction_type = 'bill_payment'";
    $params = [$user_id];
    $types = "i";
    
    // Add bill type filter
    if ($bill_type) {
        $whereClause .= " AND bp.bill_type = ?";
        $params[] = $bill_type;
        $types .= "s";
    }
    
    // Add period filter
    if ($period > 0) {
        $whereClause .= " AND t.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        $params[] = $period;
        $types .= "i";
    }
    
    // Add status filter
    if ($status) {
        $whereClause .= " AND t.status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    // First, ensure bill_payments table exists
    createBillPaymentsTable($conn);
    
    // Query to get bill payment transactions with bill details
    $sql = "SELECT 
                t.*,
                bp.bill_type,
                bp.provider,
                bp.account_number,
                bp.customer_name
            FROM transactions t
            LEFT JOIN bill_payments bp ON t.reference_id = bp.transaction_id
            $whereClause
            ORDER BY t.created_at DESC
            LIMIT ?";
    
    $params[] = $limit;
    $types .= "i";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'transactions' => $transactions,
        'total_count' => count($transactions),
        'filters_applied' => [
            'bill_type' => $bill_type,
            'period' => $period,
            'status' => $status
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'transactions' => []
    ]);
}

function createBillPaymentsTable($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS bill_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transaction_id VARCHAR(50) UNIQUE NOT NULL,
        user_id INT NOT NULL,
        bill_type VARCHAR(50) NOT NULL,
        provider VARCHAR(255) NOT NULL,
        account_number VARCHAR(100) NOT NULL,
        customer_name VARCHAR(255) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        status ENUM('pending', 'completed', 'failed') DEFAULT 'completed',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_created (user_id, created_at),
        INDEX idx_bill_type (bill_type),
        INDEX idx_transaction_id (transaction_id),
        FOREIGN KEY (user_id) REFERENCES create_account(id) ON DELETE CASCADE
    )";
    
    $conn->query($sql);
}
?>