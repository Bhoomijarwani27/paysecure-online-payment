<?php
session_start();
include_once 'connect.php';

$name = $_SESSION['name'] ?? 'User';
$phone = $_SESSION['phone_number'] ?? '';
$user_id = $_SESSION['user_id'] ?? '';
$error = '';

if (!$user_id) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $password = $_POST['password'] ?? '';
    $confirmation = $_POST['confirmation'] ?? '';
    
    if (empty($password)) {
        $error = 'Password is required';
    } elseif ($confirmation !== 'DELETE') {
        $error = 'Please type DELETE to confirm';
    } else {
        // Verify password
        $stmt = $conn->prepare("SELECT password FROM create_account WHERE phone_number = ?");
        $stmt->bind_param('s', $phone);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            if ($row['password'] != $password) {
                $error = 'Incorrect password';
            } else {
                // Delete account
                $delete_stmt = $conn->prepare("DELETE FROM create_account WHERE phone_number = ?");
                $delete_stmt->bind_param('s', $phone);
                
                if ($delete_stmt->execute()) {
                    // Destroy session
                    session_destroy();
                    header('Location: login.php?deleted=1');
                    exit();
                } else {
                    $error = 'Failed to delete account. Please try again.';
                }
                $delete_stmt->close();
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
    <title>Delete Account - PaySecure</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #F44336 0%, #D32F2F 100%);
            min-height: 100vh;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .container { max-width: 500px; width: 100%; }
        
        .card {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .header i {
            font-size: 60px;
            color: #F44336;
            margin-bottom: 15px;
        }
        
        h1 {
            color: #F44336;
            font-size: 26px;
            margin-bottom: 10px;
        }
        
        .subtitle {
            color: #666;
            font-size: 14px;
        }
        
        .warning-box {
            background: #FFEBEE;
            border: 2px solid #F44336;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
        }
        
        .warning-box h3 {
            color: #F44336;
            font-size: 16px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .warning-box ul {
            list-style: none;
            padding-left: 0;
        }
        
        .warning-box li {
            color: #666;
            font-size: 14px;
            margin-bottom: 8px;
            padding-left: 25px;
            position: relative;
        }
        
        .warning-box li:before {
            content: "✗";
            position: absolute;
            left: 0;
            color: #F44336;
            font-weight: 700;
            font-size: 16px;
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
        
        input[type="password"],
        input[type="text"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
        }
        
        input:focus {
            outline: none;
            border-color: #F44336;
        }
        
        .confirmation-note {
            background: #FFF3E0;
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            color: #F57C00;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background: #FFEBEE;
            color: #F44336;
            border: 1px solid #F44336;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
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
        
        .btn-danger {
            background: #F44336;
            color: white;
            margin-bottom: 10px;
        }
        
        .btn-danger:hover { background: #D32F2F; }
        
        .btn-secondary {
            background: white;
            color: #333;
            border: 2px solid #e0e0e0;
        }
        
        .btn-secondary:hover { background: #f5f5f5; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <i class="fas fa-exclamation-triangle"></i>
                <h1>Delete Account</h1>
                <p class="subtitle">This action cannot be undone</p>
            </div>
            
            <div class="warning-box">
                <h3><i class="fas fa-exclamation-circle"></i> Warning</h3>
                <ul>
                    <li>All your data will be permanently deleted</li>
                    <li>Your wallet balance will be lost</li>
                    <li>Transaction history will be removed</li>
                    <li>Linked bank accounts will be unlinked</li>
                    <li>You cannot recover your account</li>
                </ul>
            </div>
            
            <?php if ($error): ?>
                <div class="alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" onsubmit="return confirm('Are you absolutely sure you want to delete your account? This action is PERMANENT and CANNOT be undone!')">
                <div class="form-group">
                    <label>Enter Your Password</label>
                    <input type="password" name="password" required placeholder="Enter your password to confirm">
                </div>
                
                <div class="form-group">
                    <label>Type "DELETE" to confirm</label>
                    <input type="text" name="confirmation" required placeholder="Type DELETE in capital letters">
                </div>
                
                <div class="confirmation-note">
                    <i class="fas fa-info-circle"></i> Please make sure you have withdrawn all your money before deleting your account.
                </div>
                
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-trash"></i> Permanently Delete Account
                </button>
                
                <button type="button" class="btn btn-secondary" onclick="window.location.href='settings.php'">
                    <i class="fas fa-arrow-left"></i> Cancel & Go Back
                </button>
            </form>
        </div>
    </div>
</body>
</html>
