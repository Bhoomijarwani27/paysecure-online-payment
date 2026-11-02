<?php
session_start();
include_once 'connect.php';

$name = $_SESSION['name'] ?? 'User';
$phone = $_SESSION['phone_number'] ?? '';
$user_id = $_SESSION['user_id'] ?? '';

if (!$user_id) {
    header('Location: login.php');
    exit();
}

// Fetch current balance
$stmt = $conn->prepare("SELECT balance FROM create_account WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $balance = $row['balance'];
} else {
    $balance = 0.00;
}
$stmt->close();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $amount = $_POST['amount'] ?? 0;
    $method = $_POST['method'] ?? '';
    
    if ($amount < 100) {
        $error = 'Minimum amount is ₹100';
    } elseif ($amount > 100000) {
        $error = 'Maximum amount is ₹1,00,000';
    } elseif (empty($method)) {
        $error = 'Please select a payment method';
    } else {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Update balance
            $new_balance = $balance + $amount;
            $update_stmt = $conn->prepare("UPDATE create_account SET balance = ? WHERE id = ?");
            $update_stmt->bind_param("di", $new_balance, $user_id);
            
            if (!$update_stmt->execute()) {
                throw new Exception("Failed to update balance");
            }
            $update_stmt->close();
            
            // Record transaction
            $trans_stmt = $conn->prepare("INSERT INTO transactions (user_id, type, amount, description, status) VALUES (?, 'credit', ?, ?, 'success')");
            $description = "Added money via " . $method;
            $trans_stmt->bind_param("ids", $user_id, $amount, $description);
            
            if (!$trans_stmt->execute()) {
                throw new Exception("Failed to record transaction");
            }
            $trans_stmt->close();
            
            // Commit transaction
            $conn->commit();
            
            $balance = $new_balance; // Update local variable for display
            $message = "Successfully added ₹" . number_format($amount, 2) . " to your wallet!";
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Transaction failed: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Money - PaySecure</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #42A5F5 0%, #1976D2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container { max-width: 600px; margin: 0 auto; }
        
        .card {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            margin-bottom: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 25px;
        }
        
        .header i {
            font-size: 50px;
            color: #1976D2;
            margin-bottom: 15px;
        }
        
        h1 {
            color: #333;
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .subtitle {
            color: #666;
            font-size: 14px;
        }
        
        .balance-display {
            background: linear-gradient(135deg, #42A5F5 0%, #1976D2 100%);
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 25px;
        }
        
        .balance-display h3 {
            color: rgba(255,255,255,0.9);
            font-size: 14px;
            margin-bottom: 8px;
        }
        
        .balance-display .amount {
            color: white;
            font-size: 32px;
            font-weight: 700;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            color: #333;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        input[type="number"] {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 18px;
            font-weight: 600;
        }
        
        input:focus {
            outline: none;
            border-color: #1976D2;
        }
        
        .quick-amounts {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .quick-amount-btn {
            padding: 12px;
            border: 2px solid #1976D2;
            background: white;
            color: #1976D2;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .quick-amount-btn:hover {
            background: #1976D2;
            color: white;
        }
        
        .payment-methods {
            display: grid;
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .payment-method {
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s;
        }
        
        .payment-method:hover {
            border-color: #1976D2;
            background: #F5F9FF;
        }
        
        .payment-method input[type="radio"] {
            width: 20px;
            height: 20px;
        }
        
        .payment-method i {
            font-size: 24px;
            color: #1976D2;
        }
        
        .payment-method-info h4 {
            color: #333;
            font-size: 15px;
            margin-bottom: 3px;
        }
        
        .payment-method-info p {
            color: #666;
            font-size: 13px;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-success {
            background: #E8F5E9;
            color: #4CAF50;
            border: 1px solid #4CAF50;
        }
        
        .alert-error {
            background: #FFEBEE;
            color: #F44336;
            border: 1px solid #F44336;
        }
        
        .btn {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #1976D2;
            color: white;
            margin-bottom: 10px;
        }
        
        .btn-primary:hover { background: #1565C0; }
        
        .btn-secondary {
            background: white;
            color: #1976D2;
            border: 2px solid #1976D2;
        }
        
        .btn-secondary:hover { background: #E3F2FD; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <i class="fas fa-wallet"></i>
                <h1>Add Money</h1>
                <p class="subtitle">Add money to your PaySecure wallet</p>
            </div>
            
            <div class="balance-display">
                <h3>Current Balance</h3>
                <div class="amount">₹<?php echo number_format($balance, 2); ?></div>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>Enter Amount</label>
                    <input type="number" name="amount" id="amountInput" min="100" max="100000" placeholder="₹ 0" required>
                </div>
                
                <div class="quick-amounts">
                    <button type="button" class="quick-amount-btn" onclick="setAmount(500)">₹500</button>
                    <button type="button" class="quick-amount-btn" onclick="setAmount(1000)">₹1,000</button>
                    <button type="button" class="quick-amount-btn" onclick="setAmount(2000)">₹2,000</button>
                    <button type="button" class="quick-amount-btn" onclick="setAmount(5000)">₹5,000</button>
                    <button type="button" class="quick-amount-btn" onclick="setAmount(10000)">₹10,000</button>
                    <button type="button" class="quick-amount-btn" onclick="setAmount(20000)">₹20,000</button>
                </div>
                
                <div class="form-group">
                    <label>Select Payment Method</label>
                    <div class="payment-methods">
                        <label class="payment-method">
                            <input type="radio" name="method" value="UPI" required>
                            <i class="fas fa-mobile-alt"></i>
                            <div class="payment-method-info">
                                <h4>UPI</h4>
                                <p>Google Pay, PhonePe, Paytm</p>
                            </div>
                        </label>
                        
                        <label class="payment-method">
                            <input type="radio" name="method" value="Debit Card">
                            <i class="fas fa-credit-card"></i>
                            <div class="payment-method-info">
                                <h4>Debit Card</h4>
                                <p>All major debit cards accepted</p>
                            </div>
                        </label>
                        
                        <label class="payment-method">
                            <input type="radio" name="method" value="Net Banking">
                            <i class="fas fa-university"></i>
                            <div class="payment-method-info">
                                <h4>Net Banking</h4>
                                <p>All major banks supported</p>
                            </div>
                        </label>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> Add Money
                </button>
                
                <button type="button" class="btn btn-secondary" onclick="window.location.href='homepage_clean.php'">
                    <i class="fas fa-arrow-left"></i> Back to Home
                </button>
            </form>
        </div>
    </div>
    
    <script>
        function setAmount(amount) {
            document.getElementById('amountInput').value = amount;
        }
    </script>
</body>
</html>