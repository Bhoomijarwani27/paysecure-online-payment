<?php
session_start();
require_once 'connect.php';

// Get user details from session
$country_code = $_SESSION['country_code'] ?? '';
$phone_number = $_SESSION['phone_number'] ?? '';

// Verify user is logged in
if (empty($country_code) || empty($phone_number)) {
    echo "<script>alert('Please login first.'); window.location.href = 'login.html';</script>";
    exit;
}

// Get form data - UPI PIN is always 4 digits
$upi_pin = $_POST['upi_pin'] ?? '';
$confirm_upi_pin = $_POST['confirm_upi_pin'] ?? '';

// Validation
if (empty($upi_pin) || empty($confirm_upi_pin)) {
    echo "<script>alert('Please enter and confirm your UPI PIN.'); window.history.back();</script>";
    exit;
}

// Check if PINs match
if ($upi_pin !== $confirm_upi_pin) {
    echo "<script>alert('UPI PINs do not match. Please try again.'); window.history.back();</script>";
    exit;
}

// UPI PIN must be exactly 4 digits
if (!preg_match('/^\d{4}$/', $upi_pin)) {
    echo "<script>alert('UPI PIN must be exactly 4 digits.'); window.history.back();</script>";
    exit;
}

// Store UPI PIN in create_account table (plain text for now, as per your current setup)
// Note: In production, you should encrypt this!
try {
    // Update UPI PIN in create_account table
    $stmt = $conn->prepare("UPDATE create_account SET upi_pin = ? WHERE country_code = ? AND phone_number = ?");
    $stmt->bind_param("sss", $upi_pin, $country_code, $phone_number);
    
    if ($stmt->execute()) {
        // If bank details exist in session AND card_pin is provided, save them
        if (!empty($_SESSION['bank_name']) && !empty($_SESSION['last_6_digits']) && !empty($_SESSION['card_pin'])) {
            $bank_name = $_SESSION['bank_name'];
            $last_6_digits = $_SESSION['last_6_digits'];
            $expiry_date = $_SESSION['expiry_date'] ?? null;
            $encrypted_card_pin = $_SESSION['card_pin'];
            
            // Save bank details
            $bank_stmt = $conn->prepare("INSERT INTO bank_details (country_code, phone_number, bank_name, last_6_digits, expiry_date, card_pin, is_verified, created_at) VALUES (?, ?, ?, ?, ?, ?, TRUE, NOW())");
            $bank_stmt->bind_param("ssssss", $country_code, $phone_number, $bank_name, $last_6_digits, $expiry_date, $encrypted_card_pin);
            
            if (!$bank_stmt->execute()) {
                error_log("Error saving bank details: " . $bank_stmt->error);
                echo "<script>alert('Error saving bank details: " . addslashes($bank_stmt->error) . "'); window.history.back();</script>";
                exit;
            }
            $bank_stmt->close();
            
            // Clear bank session data
            unset($_SESSION['bank_name'], $_SESSION['last_6_digits'], $_SESSION['expiry_date'], $_SESSION['card_pin']);
        }
        
        // Update account status to complete
        $update_stmt = $conn->prepare("UPDATE create_account SET account_complete = 1, status = 'complete' WHERE country_code = ? AND phone_number = ?");
        $update_stmt->bind_param("ss", $country_code, $phone_number);
        $update_stmt->execute();
        $update_stmt->close();
        
        // Success - redirect to homepage_clean
        echo "<script>
            alert('🎉 UPI PIN set successfully! Welcome to your dashboard.');
            window.location.href = 'homepage_clean.php';
        </script>";
    } else {
        throw new Exception($stmt->error);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo "<script>alert('❌ Error: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
}

$conn->close();
?>
