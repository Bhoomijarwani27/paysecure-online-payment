<?php
session_start();

// Get phone data from URL parameters or session
$phone_number = $_GET['phone'] ?? ($_SESSION['phone_number'] ?? '');
$country_code = $_GET['country'] ?? ($_SESSION['country_code'] ?? '');
$referral_code = $_GET['ref'] ?? ''; // Capture referral code

if (!$phone_number || !$country_code) {
    echo "<script>alert('Phone number or country code missing! Please start again.'); window.location.href='create_account.html';</script>";
    exit;
}

// Store in session for future use
$_SESSION['phone_number'] = $phone_number;
$_SESSION['country_code'] = $country_code;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Password - PaySecure</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="paysecure-universal.css?v=2">
    <style>
        .otp-container {
            margin: 0 auto;
        }
        
        .password-requirements {
            background: rgba(25, 118, 210, 0.05);
            border-left: 3px solid #1976D2;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .password-requirements strong {
            color: #1976D2;
            display: block;
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .password-requirements ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .password-requirements li {
            color: #666;
            font-size: 13px;
            margin: 6px 0;
            line-height: 1.5;
        }
        
        .input-eye-group {
            position: relative;
        }
        
        .toggle-eye {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #999;
            transition: color 0.3s ease;
            font-size: 18px;
        }
        
        .toggle-eye:hover {
            color: #1976D2;
        }
        
        .input-eye-group .form-input {
            padding-right: 45px;
        }
    </style>
</head>
<body>
    <div class="page-container">
        <!-- Header -->
        <div class="header">
            <div class="logo">
                <i class="fa-solid fa-lock"></i>
                <span class="pay">pay</span><span class="secure">secure</span>
            </div>
            <div class="help-link-header">
                <a href="help.html" class="help-text">Help?</a>
            </div>
        </div>
        
        <!-- Back Button -->
        <a href="#" onclick="smartGoBack(); return false;" class="back-btn">
            <i class="fas fa-arrow-left"></i>
            Back
        </a>
        
        <!-- Password Content -->
        <div class="otp-container" style="max-width: 500px;">
            <div class="page-card">
                <h2 class="page-title">Set Your Password</h2>
                <p class="subtitle">Create a secure 4-digit password</p>
                
                <form id="passwordForm" action="password_db.php" method="post">
                    <input type="hidden" name="phone_number" value="<?= htmlspecialchars($phone_number) ?>">
                    <input type="hidden" name="country_code" value="<?= htmlspecialchars($country_code) ?>">
                    <input type="hidden" name="referral_code" value="<?= htmlspecialchars($referral_code) ?>">
                    
                    <div class="form-group">
                        <label class="form-label">Enter Password</label>
                        <div class="input-eye-group">
                            <input type="password" 
                                   class="form-input" 
                                   name="password" 
                                   id="password" 
                                   placeholder="Enter 4-digit password" 
                                   required 
                                   minlength="4"
                                   maxlength="4"
                                   pattern="\d{4}"
                                   inputmode="numeric">
                            <span class="toggle-eye" onclick="togglePassword('password', this)">
                                <i class="fa-solid fa-eye"></i>
                            </span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Confirm Password</label>
                        <div class="input-eye-group">
                            <input type="password" 
                                   class="form-input" 
                                   name="confirm_password" 
                                   id="confirm_password" 
                                   placeholder="Confirm 4-digit password" 
                                   required 
                                   minlength="4"
                                   maxlength="4"
                                   pattern="\d{4}"
                                   inputmode="numeric">
                            <span class="toggle-eye" onclick="togglePassword('confirm_password', this)">
                                <i class="fa-solid fa-eye"></i>
                            </span>
                        </div>
                    </div>
                    
                    <div class="password-requirements">
                        <strong>Password Requirements:</strong>
                        <ul>
                            <li>Exactly 4 digits long</li>
                            <li>Must contain only numbers</li>
                            <li>Both passwords must match</li>
                            <li>Avoid easy passwords (like 1234) for better security</li>
                        </ul>
                    </div>
                    
                    <button class="btn-primary" type="submit" style="width: 100%; margin-top: 10px;">
                        Set Password
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Handle form submission with validation
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            // Validation checks
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match.');
                return false;
            }
            
            if (password.length !== 4 || !/^\d{4}$/.test(password)) {
                e.preventDefault();
                alert('Password must be exactly 4 digits.');
                return false;
            }
            
            // Check for common weak passwords
            const weakPasswords = ['1234', '0000', '1111', '2222', '3333', '4444', '5555', '6666', '7777', '8888', '9999'];
            if (weakPasswords.includes(password)) {
                const confirm = window.confirm('This password is too weak. Are you sure you want to use it?');
                if (!confirm) {
                    e.preventDefault();
                    return false;
                }
            }
            
            // If validation passes, allow form submission
            return true;
        });

        function togglePassword(fieldId, iconSpan) {
            const input = document.getElementById(fieldId);
            const icon = iconSpan.querySelector('i');
            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = "password";
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        function smartGoBack() {
            // For password setting page, go back to OTP verification with proper parameters
            const urlParams = new URLSearchParams(window.location.search);
            const phone = urlParams.get('phone');
            const country = urlParams.get('country');
            
            if (phone && country) {
                // Go back to OTP verification with the same phone and country
                window.location.href = `otp_verification.php?phone_number=${phone}&country_code=${country}`;
            } else {
                // Fallback to create account page if parameters are missing
                window.location.href = 'create_account.html';
            }
        }
    </script>
</body>
</html>
