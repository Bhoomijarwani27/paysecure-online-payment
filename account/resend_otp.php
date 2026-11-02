<?php
require_once 'connect.php';

$phone_number = $_GET['phone_number'] ?? '';
$country_code = $_GET['country_code'] ?? '';

$phone_number = trim($phone_number);
$country_code = trim($country_code);

if (empty($phone_number) || empty($country_code)) {
    echo "Missing phone number or country code.";
    exit;
}

// Debug: Show received values
error_log("Resend OTP for phone_number: '$phone_number', country_code: '$country_code'");

// Generate OTP
$otp = rand(100000, 999999);

// Update OTP in database
$stmt = $conn->prepare("UPDATE create_account SET otp=? WHERE country_code=? AND phone_number=?");
$stmt->bind_param("sss", $otp, $country_code, $phone_number);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo "OTP resent! Your new OTP is: $otp";
    } else {
        echo "No matching user found for country code and phone number.";
    }
} else {
    echo "Failed to resend OTP.";
}
$conn->close();
?>

