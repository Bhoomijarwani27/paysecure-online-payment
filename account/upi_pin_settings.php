<?php
session_start();

if ($_POST['action'] ?? '' === 'set_pin') {
    $pin = trim($_POST['pin'] ?? '');
    if (strlen($pin) === 4 && ctype_digit($pin)) {
        $_SESSION['upi_pin'] = $pin;
        $message = "UPI PIN set successfully to: $pin";
    } else {
        $error = "Please enter a valid 4-digit PIN";
    }
}

$currentPin = $_SESSION['upi_pin'] ?? '1234';
?>
<!DOCTYPE html>
<html>
<head>
    <title>UPI PIN Settings</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 400px; margin: 50px auto; padding: 20px; }
        .form-group { margin: 15px 0; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
        .success { color: green; background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: red; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; padding: 10px; border-radius: 5px; margin: 10px 0; }
    </style>
</head>
<body>
    <h2>UPI PIN Settings</h2>
    
    <?php if (isset($message)): ?>
        <div class="success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <div class="info">
        <strong>Note:</strong> Your UPI PIN is set when you link your bank account during account setup.
    </div>
    
    <div class="info">
        <strong>To set/change your UPI PIN:</strong>
        <ol>
            <li>Go to <a href="homepage_v1.php">Dashboard</a></li>
            <li>Click "Link Bank Account" or "Settings"</li>
            <li>Follow the bank linking process</li>
            <li>Set your 4 or 6-digit UPI PIN</li>
        </ol>
    </div>
    
    <hr style="margin: 30px 0;">
    
    <h3>Test Payment Flow:</h3>
    <ol>
        <li>Set your UPI PIN above (or use default: 1234)</li>
        <li><a href="phone.html">Go to Send Money page</a></li>
        <li>Enter any phone number and amount</li>
        <li>Use the PIN you set above</li>
        <li>Payment should redirect to receipt page</li>
    </ol>
    
    <p><strong>Note:</strong> Use PIN <code>0000</code> to test failed payments.</p>
</body>
</html>
