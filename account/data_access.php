<?php
require_once 'connect.php';
session_start();

// Handle user's choice for existing incomplete registration
if (isset($_GET['continue']) && $_GET['continue'] === 'true') {
    $_SESSION['continue_registration'] = true;
    $_POST['NAME'] = $_GET['name'];
    $_POST['COUNTRYCODE'] = $_GET['country_code'];
    $_POST['PHONENO'] = $_GET['phone_number'];
} elseif (isset($_GET['start_fresh']) && $_GET['start_fresh'] === 'true') {
    // Delete existing incomplete record and start fresh
    $delete_stmt = $conn->prepare("DELETE FROM create_account WHERE country_code = ? AND phone_number = ? AND account_complete = 0");
    $delete_stmt->bind_param("ss", $_GET['country_code'], $_GET['phone_number']);
    $delete_stmt->execute();
    $delete_stmt->close();
    
    $_POST['NAME'] = $_GET['name'];
    $_POST['COUNTRYCODE'] = $_GET['country_code'];
    $_POST['PHONENO'] = $_GET['phone_number'];
}

// Debug: See what is received from the form
// Uncomment the next line to debug form data
// var_dump($_POST);

$name = isset($_POST['NAME']) ? trim($_POST['NAME']) : '';
$country_code = isset($_POST['COUNTRYCODE']) && $_POST['COUNTRYCODE'] !== '' ? trim($_POST['COUNTRYCODE']) : '+91';
$phone_number = isset($_POST["PHONENO"]) ? trim($_POST["PHONENO"]) : '';

$error = "";

// ✅ Validation for Name
if (!preg_match("/^[a-zA-Z\s]+$/", $name)) {
    $error = "Invalid name. Please enter a valid name.";
} 
// ✅ Validation for Country Code
// elseif (!preg_match('/^\+\d{1,4}$/', $country_code)) {
//     $error = "Invalid country code. Use format like +91.";
// } 
// ✅ Validation for Phone Nustillmber
elseif (!preg_match('/^\d{10}$/', $phone_number)) {
    $error = "Invalid phone number. Use 10 digits only.";
}

if (!empty($error)) {
    echo "<script>alert('$error'); window.history.back();</script>";
    exit;
}

// ✅ Check if phone number already exists and account status
$check_stmt = $conn->prepare("SELECT name, phone_verified, account_complete, status FROM create_account WHERE country_code = ? AND phone_number = ?");
$check_stmt->bind_param("ss", $country_code, $phone_number);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows > 0) {
    $existing_user = $result->fetch_assoc();
    
    // If account is complete, block registration
    if ($existing_user['account_complete'] == 1) {
        echo "<script>alert('This phone number is already registered and active. Please login instead.'); window.history.back();</script>";
        exit;
    }
    
    // If account is incomplete, give user choice to start fresh or continue
    else {
        // Check if this is a fresh start request (no existing session)
        if (!isset($_SESSION['continue_registration'])) {
            echo "<script>
                if (confirm('An incomplete registration exists for this phone number. Do you want to continue the previous registration?\\n\\nClick OK to continue previous registration\\nClick Cancel to start fresh')) {
                    // User wants to continue
                    window.location.href = 'data_access.php?continue=true&name=" . urlencode($name) . "&country_code=" . urlencode($country_code) . "&phone_number=" . urlencode($phone_number) . "';
                } else {
                    // User wants to start fresh - delete old record
                    window.location.href = 'data_access.php?start_fresh=true&name=" . urlencode($name) . "&country_code=" . urlencode($country_code) . "&phone_number=" . urlencode($phone_number) . "';
                }
            </script>";
            exit;
        } else {
            // User chose to continue, update and proceed
            $update_stmt = $conn->prepare("UPDATE create_account SET name=? WHERE country_code=? AND phone_number=?");
            $update_stmt->bind_param("sss", $name, $country_code, $phone_number);
            $update_stmt->execute();
            $update_stmt->close();
            
            // Continue with OTP process
            $otp = rand(100000, 999999);
            
            // Save OTP
            $otp_stmt = $conn->prepare("UPDATE create_account SET otp=? WHERE country_code=? AND phone_number=?");
            $otp_stmt->bind_param("sss", $otp, $country_code, $phone_number);
            $otp_stmt->execute();
            $otp_stmt->close();
            
            unset($_SESSION['continue_registration']); // Clear session flag
            
            echo "<script>
                alert('Your OTP is: $otp');
                window.location.href = 'otp_verification.php?country_code=" . urlencode($country_code) . "&phone_number=" . urlencode($phone_number) . "&otp=' + '$otp';
            </script>";
            exit;
        }
    }
  }

// ✅ Insert new record with random initial balance
$random_balance = rand(1000, 10000); // Random balance between ₹1000 and ₹10000
$sql = "INSERT INTO create_account (name, country_code, phone_number, balance) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssd", $name, $country_code, $phone_number, $random_balance);

if ($stmt->execute()) {
    // ✅ Generate OTP
    $otp = rand(100000, 999999);
    $referral_code = isset($_POST['REFERRAL_CODE']) ? trim($_POST['REFERRAL_CODE']) : '';

    // ✅ Save OTP to database
    $update_stmt = $conn->prepare("UPDATE create_account SET otp=? WHERE country_code=? AND phone_number=?");
    $update_stmt->bind_param("sss", $otp, $country_code, $phone_number);
    
    if ($update_stmt->execute()) {
        echo "<script>console.log('✅ OTP saved to database: $otp');</script>";
    } else {
        echo "<script>console.log('❌ Failed to save OTP: " . $update_stmt->error . "');</script>";
    }
    $update_stmt->close();

    // ✅ Pass OTP and Referral Code via URL
    echo "<script>
        const otp = '$otp';
        alert('Your OTP is: ' + otp);
        window.location.href = 'otp_verification.php?country_code=" . urlencode($country_code) . "&phone_number=" . urlencode($phone_number) . "&otp=' + otp + '&ref=" . urlencode($referral_code) . "';
    </script>";
    exit;
} else {
    echo "<script>alert('❌ Insert Error: " . $stmt->error . "'); window.history.back();</script>";
}

$conn->close();
?>



