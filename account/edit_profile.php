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

// Get current user data
$stmt = $conn->prepare("SELECT name, email, phone_number, country_code FROM create_account WHERE phone_number = ?");
$stmt->bind_param('s', $phone);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_name = trim($_POST['name'] ?? '');
    $new_email = trim($_POST['email'] ?? '');
    
    if (empty($new_name)) {
        $error = 'Name cannot be empty';
    } elseif (!empty($new_email) && !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format';
    } else {
        // Update user profile
        $update_stmt = $conn->prepare("UPDATE create_account SET name = ?, email = ? WHERE phone_number = ?");
        $update_stmt->bind_param('sss', $new_name, $new_email, $phone);
        
        if ($update_stmt->execute()) {
            $_SESSION['name'] = $new_name;
            $message = 'Profile updated successfully!';
            $user_data['name'] = $new_name;
            $user_data['email'] = $new_email;
        } else {
            $error = 'Failed to update profile. Please try again.';
        }
        $update_stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - PaySecure</title>
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
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            margin-bottom: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #42A5F5 0%, #1976D2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            font-weight: 700;
            margin: 0 auto 15px;
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
        
        input[type="text"],
        input[type="email"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
        }
        
        input:focus {
            outline: none;
            border-color: #1976D2;
        }
        
        input:disabled {
            background: #f5f5f5;
            cursor: not-allowed;
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
        
        .info-note {
            background: #FFF3E0;
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            color: #F57C00;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <div class="avatar"><?php echo strtoupper(substr($user_data['name'] ?? 'U', 0, 1)); ?></div>
                <h1>Edit Profile</h1>
                <p class="subtitle">Update your personal information</p>
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
                    <label><i class="fas fa-user"></i> Full Name</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($user_data['name'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email Address</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" placeholder="your@email.com">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-phone"></i> Phone Number</label>
                    <input type="text" value="<?php echo htmlspecialchars($user_data['phone_number'] ?? ''); ?>" disabled>
                    <div class="info-note">
                        <i class="fas fa-info-circle"></i> Phone number cannot be changed for security reasons
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
                
                <button type="button" class="btn btn-secondary" onclick="window.location.href='homepage_clean.php'">
                    <i class="fas fa-arrow-left"></i> Back to Home
                </button>
            </form>
        </div>
    </div>
</body>
</html>
