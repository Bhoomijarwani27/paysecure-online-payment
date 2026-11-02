<?php
// filepath: d:\xampp\htdocs\online_payment\account\set_password_process.php

require_once 'connect.php';
session_start();

$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// Validate: secure password requirements
if (strlen($password) !== 4 || !ctype_digit($password)) {
    echo "<script>alert('Password must be exactly 4 digits.'); window.history.back();</script>";
    exit();
}

// Letters and numbers validation removed - now only accepts 4 digits

// Check for weak 4-digit passwords - show warning but allow
$weakPasswords = ['1234', '0000', '1111', '2222', '3333', '4444', '5555', '6666', '7777', '8888', '9999', '1122', '1212'];
$isWeakPassword = in_array($password, $weakPasswords);

if ($isWeakPassword) {
    // Show warning but continue with account creation
    $warningMessage = "⚠️ Your password is weak and may be easily guessed. Consider using a stronger 4-digit combination for better security.";
} else {
    $warningMessage = "";
}
if ($password !== $confirm_password) {
    echo "<script>alert('Passwords do not match.'); window.history.back();</script>";
    exit;
}

// Get phone number and country code from session (set after OTP verification)
$phone_number = $_SESSION['phone_number'] ?? '';
$country_code = $_SESSION['country_code'] ?? '';

if (!$phone_number || !$country_code) {
    echo "<script>alert('Session expired. Please start again.'); window.location.href='create_account.html';</script>";
    exit;
}

// Hash password for security
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Update password in database
$stmt = $conn->prepare("UPDATE create_account SET password=? WHERE country_code=? AND phone_number=?");
$stmt->bind_param("sss", $hashed_password, $country_code, $phone_number);

if ($stmt->execute()) {
    require_once 'balance_manager.php';
    
    $referral_code = isset($_POST['referral_code']) ? trim($_POST['referral_code']) : '';
    $referral_message = "";
    
    if (!empty($referral_code)) {
        // Validate and Apply Referral Bonus
        $check_ref = $conn->prepare("SELECT user_id, referral_code FROM referral_codes WHERE referral_code = ?");
        $check_ref->bind_param("s", $referral_code);
        $check_ref->execute();
        $ref_result = $check_ref->get_result();
        
        if ($ref_result->num_rows > 0) {
            $referrer_data = $ref_result->fetch_assoc();
            $referrer_id = $referrer_data['user_id'];
            
            // Get Referrer Phone
            $get_phone = $conn->prepare("SELECT phone_number FROM create_account WHERE id = ?");
            $get_phone->bind_param("i", $referrer_id);
            $get_phone->execute();
            $phone_res = $get_phone->get_result();
            
            if ($phone_res->num_rows > 0) {
                $referrer_phone = $phone_res->fetch_assoc()['phone_number'];
                
                // Apply Bonuses
                $bm = new BalanceManager($conn);
                
                // 1. Bonus to New User (Referee)
                $bm->updateBalance($phone_number, 50, 'signup_bonus', "Welcome bonus for using referral code: $referral_code", $referral_code);
                
                // 2. Bonus to Referrer
                $bm->updateBalance($referrer_phone, 50, 'referral_bonus', "Referral bonus for code: $referral_code", $referral_code);
                
                // 3. Update Stats
                $conn->query("UPDATE referral_codes SET earnings = earnings + 50, total_referrals = total_referrals + 1 WHERE referral_code = '$referral_code'");
                
                // 4. Record Transaction History for Referral System
                // Get New User ID
                $get_new_id = $conn->prepare("SELECT id FROM create_account WHERE phone_number = ?");
                $get_new_id->bind_param("s", $phone_number);
                $get_new_id->execute();
                $new_user_id = $get_new_id->get_result()->fetch_assoc()['id'];
                
                $conn->query("INSERT INTO referral_transactions (referrer_user_id, referred_user_id, referral_code, amount, transaction_type, created_at) VALUES ($referrer_id, $new_user_id, '$referral_code', 50, 'referral_bonus', NOW())");
                
                $referral_message = "\\n\\n🎉 Referral Bonus Applied! You and your referrer got ₹50 each.";
            }
        }
    }

    if ($isWeakPassword) {
        echo "<script>alert('Password set successfully!$referral_message\\n\\n⚠️ Security Notice: Your password is weak and may be easily guessed. Consider changing it to a stronger 4-digit combination later for better security.'); window.location.href='http://localhost/online_payment/account/link_with_bank.html';</script>";
    } else {
        echo "<script>alert('Password set successfully!$referral_message'); window.location.href='http://localhost/online_payment/account/link_with_bank.html';</script>";
    }
} else {
    echo "<script>alert('Error setting password.'); window.history.back();</script>";
}

$conn->close();
?>
