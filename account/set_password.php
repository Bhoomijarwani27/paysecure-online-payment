<?php
// Dev helper: set or reset password for an existing account (LOCAL DEV ONLY)
// Access via http://localhost/online_payment/account/set_password.php
require_once 'connect.php';
session_start();

// Simple protection: only allow on localhost
if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1', 'localhost'])) {
    echo "<h3>Access denied</h3><p>This helper is only allowed from localhost for development.</p>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $country_code = $_POST['country_code'] ?? '';
    $phone_number = $_POST['phone_number'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!preg_match('/^\+?\d{1,4}$/', $country_code) || !preg_match('/^\d{6,15}$/', $phone_number) || $password === '') {
        $msg = 'Invalid input. Please provide country code, phone number and a password.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('UPDATE create_account SET password = ? WHERE country_code = ? AND phone_number = ?');
        $stmt->bind_param('sss', $hash, $country_code, $phone_number);
        if ($stmt->execute()) {
            $msg = 'Password updated successfully for ' . htmlspecialchars($country_code . ' ' . $phone_number);
        } else {
            $msg = 'Failed to update password: ' . $stmt->error;
        }
        $stmt->close();
    }
}

?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Set Password (dev)</title></head>
<body style="font-family: Arial, Helvetica, sans-serif; max-width:700px; margin:20px auto;">
<h2>Set / Reset Password (development helper)</h2>
<?php if (!empty($msg)) echo '<div style="padding:10px;background:#f0f0f0;margin-bottom:10px;">' . htmlspecialchars($msg) . '</div>'; ?>
<form method="post">
  <label>Country code<br><input name="country_code" value="+91"></label><br><br>
  <label>Phone number<br><input name="phone_number" placeholder="9876543210"></label><br><br>
  <label>New password (will be hashed)<br><input name="password" type="password"></label><br><br>
  <button type="submit">Set password</button>
</form>
<p style="color:#666;font-size:13px;">After setting, try logging in with the new password at <a href="login.html">login.html</a>.</p>
</body>
</html>
