<?php
session_start();
$name = $_SESSION['name'] ?? 'User';
$phone = $_SESSION['phone_number'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - PaySecure</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #42A5F5 0%, #1976D2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container { max-width: 900px; margin: 0 auto; }
        
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
        
        .settings-section {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        h2 { color: #333; margin-bottom: 20px; }
        h3 { color: #666; margin-bottom: 15px; font-size: 16px; }
        
        .setting-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .setting-item:last-child { border-bottom: none; }
        
        .setting-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .setting-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #E3F2FD;
            color: #1976D2;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .setting-text h4 {
            font-size: 15px;
            color: #333;
            margin-bottom: 3px;
        }
        
        .setting-text p {
            font-size: 13px;
            color: #666;
        }
        
        .toggle-switch {
            position: relative;
            width: 50px;
            height: 26px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: #1976D2;
        }
        
        input:checked + .slider:before {
            transform: translateX(24px);
        }
        
        .btn-action {
            padding: 8px 16px;
            background: #1976D2;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .btn-action:hover { background: #1565C0; }
        
        .btn-danger {
            background: #F44336;
        }
        
        .btn-danger:hover { background: #D32F2F; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Settings</h2>
            <button class="back-btn" onclick="window.location.href='homepage_clean.php'">
                <i class="fas fa-arrow-left"></i> Back to Home
            </button>
        </div>
        
        <!-- Account Settings -->
        <div class="settings-section">
            <h3>Account Settings</h3>
            
            <div class="setting-item">
                <div class="setting-info">
                    <div class="setting-icon">
                        <i class="fas fa-key"></i>
                    </div>
                    <div class="setting-text">
                        <h4>Change UPI PIN</h4>
                        <p>Update your 4-digit UPI PIN</p>
                    </div>
                </div>
                <button class="btn-action" onclick="window.location.href='change_pin.php'">Change</button>
            </div>
            
            <div class="setting-item">
                <div class="setting-info">
                    <div class="setting-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="setting-text">
                        <h4>Two-Factor Authentication</h4>
                        <p>Add extra security to your account</p>
                    </div>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" checked>
                    <span class="slider"></span>
                </label>
            </div>
        </div>
        
        <!-- Privacy & Security -->
        <div class="settings-section">
            <h3>Privacy & Security</h3>
            
            <div class="setting-item">
                <div class="setting-info">
                    <div class="setting-icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <div class="setting-text">
                        <h4>Change Password</h4>
                        <p>Update your login password</p>
                    </div>
                </div>
                <button class="btn-action" onclick="window.location.href='change_password.php'">Change</button>
            </div>
        </div>
        
        <!-- Danger Zone -->
        <div class="settings-section">
            <h3>Danger Zone</h3>
            
            <div class="setting-item">
                <div class="setting-info">
                    <div class="setting-icon" style="background: #FFEBEE; color: #F44336;">
                        <i class="fas fa-trash"></i>
                    </div>
                    <div class="setting-text">
                        <h4>Delete Account</h4>
                        <p>Permanently delete your account</p>
                    </div>
                </div>
                <button class="btn-action btn-danger" onclick="if(confirm('Are you sure you want to delete your account? This action cannot be undone!')) window.location.href='delete_account.php'">Delete</button>
            </div>
        </div>
    </div>
</body>
</html>
