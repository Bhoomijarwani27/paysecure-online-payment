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
    $operator = isset($_GET['operator']) ? trim($_GET['operator']) : '';
    $period = isset($_GET['period']) ? intval($_GET['period']) : 30;
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    
    // Build query with filters
    $whereClause = "WHERE t.user_id = ? AND t.transaction_type = 'mobile_recharge'";
    $params = [$user_id];
    $types = "i";
    
    // Add operator filter
    if ($operator) {
        $whereClause .= " AND r.operator = ?";
        $params[] = $operator;
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
    
    // First, ensure recharges table exists
    createRechargesTable($conn);
    
    // Query to get recharge transactions with recharge details
    $sql = "SELECT 
                t.*,
                r.mobile_number,
                r.country_code,
                r.operator
            FROM transactions t
            LEFT JOIN recharges r ON t.reference_id = r.transaction_id
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
            'operator' => $operator,
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
        INDEX idx_transaction_id (transaction_id),
        FOREIGN KEY (user_id) REFERENCES create_account(id) ON DELETE CASCADE
    )";
    
    $conn->query($sql);
}
?>