<?php
session_start();
include_once 'connect.php';

$name = $_SESSION['name'] ?? 'User';
$phone = $_SESSION['phone_number'] ?? '';

// Get all transactions (demo data for now)
$transactions = [
    ['id' => 1, 'type' => 'credit', 'description' => 'Money Received from John Doe', 'amount' => 1230, 'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours')), 'status' => 'Success'],
    ['id' => 2, 'type' => 'debit', 'description' => 'Mobile Recharge', 'amount' => 199, 'created_at' => date('Y-m-d H:i:s', strtotime('-5 hours')), 'status' => 'Success'],
    ['id' => 3, 'type' => 'debit', 'description' => 'Payment to Amazon', 'amount' => 2300, 'created_at' => date('Y-m-d H:i:s', strtotime('-1 day')), 'status' => 'Success'],
    ['id' => 4, 'type' => 'debit', 'description' => 'Bill Payment - Electricity', 'amount' => 850, 'created_at' => date('Y-m-d H:i:s', strtotime('-2 days')), 'status' => 'Success'],
    ['id' => 5, 'type' => 'credit', 'description' => 'Refund from Flipkart', 'amount' => 450, 'created_at' => date('Y-m-d H:i:s', strtotime('-3 days')), 'status' => 'Success'],
    ['id' => 6, 'type' => 'debit', 'description' => 'Send Money to Jane Smith', 'amount' => 500, 'created_at' => date('Y-m-d H:i:s', strtotime('-4 days')), 'status' => 'Success'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction History - PaySecure</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #42A5F5 0%, #1976D2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container { max-width: 1200px; margin: 0 auto; }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px 30px;
            border-radius: 16px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .back-btn {
            background: #1976D2;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
        }
        
        .back-btn:hover { background: #1565C0; }
        
        .content-card {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        h1 { color: #333; margin-bottom: 20px; }
        
        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 8px 16px;
            border: 2px solid #1976D2;
            background: white;
            color: #1976D2;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .filter-btn.active {
            background: #1976D2;
            color: white;
        }
        
        .transaction-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .transaction-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 20px;
            background: white;
            border-radius: 12px;
            border: 1px solid #f0f0f0;
            transition: all 0.3s;
        }
        
        .transaction-item:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        
        .tx-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }
        
        .tx-icon.credit { background: #4CAF50; }
        .tx-icon.debit { background: #FF9800; }
        
        .tx-details { flex: 1; }
        
        .tx-title {
            font-weight: 600;
            color: #333;
            font-size: 16px;
            margin-bottom: 5px;
        }
        
        .tx-date {
            font-size: 13px;
            color: #999;
        }
        
        .tx-amount {
            font-weight: 700;
            font-size: 20px;
        }
        
        .tx-amount.positive { color: #4CAF50; }
        .tx-amount.negative { color: #FF5722; }
        
        .tx-status {
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            background: #E8F5E9;
            color: #4CAF50;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Transaction History</h2>
            <button class="back-btn" onclick="window.location.href='homepage_clean.php'">
                <i class="fas fa-arrow-left"></i> Back to Home
            </button>
        </div>
        
        <div class="content-card">
            <h1>All Transactions</h1>
            
            <div class="filters">
                <button class="filter-btn active">All</button>
                <button class="filter-btn">Credit</button>
                <button class="filter-btn">Debit</button>
                <button class="filter-btn">This Month</button>
            </div>
            
            <div class="transaction-list">
                <?php foreach ($transactions as $tx): ?>
                    <div class="transaction-item">
                        <div class="tx-icon <?php echo $tx['type']; ?>">
                            <i class="fas fa-<?php echo $tx['type'] == 'credit' ? 'arrow-down' : 'arrow-up'; ?>"></i>
                        </div>
                        <div class="tx-details">
                            <div class="tx-title"><?php echo htmlspecialchars($tx['description']); ?></div>
                            <div class="tx-date"><?php echo date('M d, Y h:i A', strtotime($tx['created_at'])); ?></div>
                        </div>
                        <div class="tx-amount <?php echo $tx['type'] == 'credit' ? 'positive' : 'negative'; ?>">
                            <?php echo $tx['type'] == 'credit' ? '+' : '-'; ?>₹<?php echo number_format($tx['amount'], 2); ?>
                        </div>
                        <div class="tx-status"><?php echo $tx['status']; ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>