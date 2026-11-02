<?php

require_once 'connect.php';

$phone_number = $_GET['phone_number'] ?? '';
$country_code = $_GET['country_code'] ?? '';
$entered_otp = $_POST['otp'] ?? '';

$phone_number = trim($phone_number);
$country_code = trim($country_code);
$entered_otp = trim($entered_otp);

if (empty($phone_number) || empty($country_code) || empty($entered_otp)) {
    echo "<script>alert('Missing phone number, country code, or OTP.'); window.history.back();</script>";
    exit;
}

// Fetch OTP from database
$stmt = $conn->prepare("SELECT otp FROM create_account WHERE country_code=? AND phone_number=?");
$stmt->bind_param("ss", $country_code, $phone_number);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
   
// ...existing code...
$stmt->bind_result($db_otp);
$stmt->fetch();


// Add debug output here
echo "<script>console.log('Entered OTP: $entered_otp, DB OTP: $db_otp');</script>";

// Force both values to string and trim
$entered_otp = trim(strval($entered_otp));
$db_otp = trim(strval($db_otp));


if (strcmp($entered_otp, $db_otp) === 0) {
    session_start();
    $_SESSION['phone_number'] = $phone_number;
    $_SESSION['country_code'] = $country_code;
    echo "<script>alert('OTP verified successfully!'); window.location.href='http://localhost/online_payment/account/set_passwod.php';</script>";
}
} else {
    // Only generate and update new OTP if wrong
    $new_otp = rand(100000, 999999);
    $update_stmt = $conn->prepare("UPDATE create_account SET otp=? WHERE country_code=? AND phone_number=?");
    $update_stmt->bind_param("sss", $new_otp, $country_code, $phone_number);
    $update_stmt->execute();
    $update_stmt->close();
    echo "<script>alert('Invalid OTP. A new OTP has been sent: $new_otp'); window.history.back();</script>";
}
// } else {
//     echo "<script>alert('No user found for this phone number and country code.'); window.history.back();</script>";
// }

$stmt->close();
$conn->close();
?>
