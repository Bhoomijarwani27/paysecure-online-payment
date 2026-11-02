<?php
session_start();
include_once 'connect.php';

$name = $_SESSION['name'] ?? 'User';
$phone = $_SESSION['phone_number'] ?? '';
$user_id = $_SESSION['user_id'] ?? '';
$message = '';
$error = '';

if (!$user_id) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $current_pin = $_POST['current_pin'] ?? '';
    $new_pin = $_POST['new_pin'] ?? '';
    $confirm_pin = $_POST['confirm_pin'] ?? '';
    
    // Validate inputs
    if (strlen($current_pin) != 4 || !is_numeric($current_pin)) {
        $error = 'Current PIN must be 4 digits';
    } elseif (strlen($new_pin) != 4 || !is_numeric($new_pin)) {
        $error = 'New PIN must be 4 digits';
    } elseif ($new_pin !== $confirm_pin) {
        $error = 'New PIN and Confirm PIN do not match';
    } elseif ($new_pin == $current_pin) {
        $error = 'New PIN must be different from current PIN';
    } else {
        // Verify current PIN from database
        $stmt = $conn->prepare("SELECT upi_pin FROM create_account WHERE phone_number = ?");
        $stmt->bind_param('s', $phone);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $stored_pin = $row['upi_pin'] ?? '';
            
            // Check if current PIN matches
            if ($stored_pin != $current_pin) {
                $error = 'Current PIN is incorrect';
            } else {
                // Update PIN in database
                $update_stmt = $conn->prepare("UPDATE create_account SET upi_pin = ? WHERE phone_number = ?");
                $update_stmt->bind_param('ss', $new_pin, $phone);
                
                if ($update_stmt->execute()) {
                    $message = 'UPI PIN changed successfully!';
                } else {
                    $error = 'Failed to update PIN. Please try again.';
                }
                $update_stmt->close();
            }
        } else {
            $error = 'User not found';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change UPI PIN - PaySecure</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #42A5F5 0%, #1976D2 100%);
            min-height: 100vh;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .container {
            max-width: 500px;
            width: 100%;
        }
        
        .card {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .header i {
            font-size: 50px;
            color: #1976D2;
            margin-bottom: 15px;
        }
        
        h1 {
            color: #333;
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .subtitle {
            color: #666;
            font-size: 14px;
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
        
        .pin-input {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 24px;
            text-align: center;
            letter-spacing: 10px;
            font-weight: 700;
        }
        
        .pin-input:focus {
            outline: none;
            border-color: #1976D2;
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
        
        .btn-primary:hover {
            background: #1565C0;
        }
        
        .btn-secondary {
            background: white;
            color: #1976D2;
            border: 2px solid #1976D2;
        }
        
        .btn-secondary:hover {
            background: #E3F2FD;
        }
        
        .security-tips {
            background: #F5F5F5;
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
        }
        
        .security-tips h3 {
            font-size: 14px;
            color: #333;
            margin-bottom: 10px;
        }
        
        .security-tips ul {
            list-style: none;
            padding-left: 0;
        }
        
        .security-tips li {
            font-size: 13px;
            color: #666;
            margin-bottom: 5px;
            padding-left: 20px;
            position: relative;
        }
        
        .security-tips li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #4CAF50;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <i class="fas fa-lock"></i>
                <h1>Change UPI PIN</h1>
                <p class="subtitle">Enter your current PIN and set a new 4-digit PIN</p>
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
                    <label>Current PIN</label>
                    <input type="password" name="current_pin" class="pin-input" maxlength="4" pattern="[0-9]{4}" required placeholder="••••">
                </div>
                
                <div class="form-group">
                    <label>New PIN</label>
                    <input type="password" name="new_pin" class="pin-input" maxlength="4" pattern="[0-9]{4}" required placeholder="••••">
                </div>
                
                <div class="form-group">
                    <label>Confirm New PIN</label>
                    <input type="password" name="confirm_pin" class="pin-input" maxlength="4" pattern="[0-9]{4}" required placeholder="••••">
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-check"></i> Change PIN
                </button>
                
                <button type="button" class="btn btn-secondary" onclick="window.location.href='settings.php'">
                    <i class="fas fa-arrow-left"></i> Back to Settings
                </button>
            </form>
            
            <div class="security-tips">
                <h3><i class="fas fa-shield-alt"></i> Security Tips</h3>
                <ul>
                    <li>Never share your PIN with anyone</li>
                    <li>Don't use easy-to-guess PINs like 1234 or 0000</li>
                    <li>Change your PIN regularly</li>
                    <li>Don't write down your PIN</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>
