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
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (strlen($current_password) < 6) {
        $error = 'Current password is required';
    } elseif (strlen($new_password) < 6) {
        $error = 'New password must be at least 6 characters';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New password and confirm password do not match';
    } elseif ($new_password == $current_password) {
        $error = 'New password must be different from current password';
    } else {
        // Verify current password
        $stmt = $conn->prepare("SELECT password FROM create_account WHERE phone_number = ?");
        $stmt->bind_param('s', $phone);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $stored_password = $row['password'];
            
            // Check if current password matches
            if ($stored_password != $current_password) {
                $error = 'Current password is incorrect';
            } else {
                // Update password
                $update_stmt = $conn->prepare("UPDATE create_account SET password = ? WHERE phone_number = ?");
                $update_stmt->bind_param('ss', $new_password, $phone);
                
                if ($update_stmt->execute()) {
                    $message = 'Password changed successfully!';
                } else {
                    $error = 'Failed to update password. Please try again.';
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
    <title>Change Password - PaySecure</title>
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
        
        .container { max-width: 500px; width: 100%; }
        
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
        
        .password-input-wrapper {
            position: relative;
        }
        
        input[type="password"],
        input[type="text"] {
            width: 100%;
            padding: 12px 45px 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
        }
        
        input:focus {
            outline: none;
            border-color: #1976D2;
        }
        
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #999;
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
        
        .password-requirements {
            background: #F5F5F5;
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
            font-size: 13px;
        }
        
        .password-requirements h3 {
            font-size: 14px;
            color: #333;
            margin-bottom: 10px;
        }
        
        .password-requirements ul {
            list-style: none;
            padding-left: 0;
        }
        
        .password-requirements li {
            color: #666;
            margin-bottom: 5px;
            padding-left: 20px;
            position: relative;
        }
        
        .password-requirements li:before {
            content: "•";
            position: absolute;
            left: 0;
            color: #1976D2;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <i class="fas fa-lock"></i>
                <h1>Change Password</h1>
                <p class="subtitle">Update your login password</p>
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
                    <label>Current Password</label>
                    <div class="password-input-wrapper">
                        <input type="password" name="current_password" id="currentPassword" required>
                        <i class="fas fa-eye toggle-password" onclick="togglePassword('currentPassword', this)"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>New Password</label>
                    <div class="password-input-wrapper">
                        <input type="password" name="new_password" id="newPassword" minlength="6" required>
                        <i class="fas fa-eye toggle-password" onclick="togglePassword('newPassword', this)"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <div class="password-input-wrapper">
                        <input type="password" name="confirm_password" id="confirmPassword" minlength="6" required>
                        <i class="fas fa-eye toggle-password" onclick="togglePassword('confirmPassword', this)"></i>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-check"></i> Change Password
                </button>
                
                <button type="button" class="btn btn-secondary" onclick="window.location.href='settings.php'">
                    <i class="fas fa-arrow-left"></i> Back to Settings
                </button>
            </form>
            
            <div class="password-requirements">
                <h3><i class="fas fa-info-circle"></i> Password Requirements</h3>
                <ul>
                    <li>At least 6 characters long</li>
                    <li>Different from current password</li>
                    <li>Use a strong, unique password</li>
                </ul>
            </div>
        </div>
    </div>
    
    <script>
        function togglePassword(inputId, icon) {
            const input = document.getElementById(inputId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>
