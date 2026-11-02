<?php
// Login handler: verifies phone + password against create_account
require_once 'connect.php';
session_start();

$country_code = isset($_POST['country_code']) ? trim($_POST['country_code']) : '+91';
$phone_number = isset($_POST['phone_number']) ? trim($_POST['phone_number']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

// Basic validation
if (!preg_match('/^\+?\d{1,4}$/', $country_code)) {
    echo "<script>alert('Invalid country code'); window.history.back();</script>";
    exit;
}
if (!preg_match('/^\d{10}$/', $phone_number)) {
    echo "<script>alert('Phone number must be 10 digits'); window.history.back();</script>";
    exit;
}

// Fetch user row
$stmt = $conn->prepare("SELECT id, name, password, country_code, phone_number FROM create_account WHERE country_code = ? AND phone_number = ? LIMIT 1");
$stmt->bind_param('ss', $country_code, $phone_number);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<script>alert('Account not found. Please sign up.'); window.location.href='create_account.html';</script>";
    exit;
}

$user = $result->fetch_assoc();

// Verify password - require stored password for security
$stored = $user['password'] ?? '';
$password_ok = false;

if ($stored === '' || $stored === null) {
    // No password stored - account setup incomplete, redirect to set password
    echo "<script>alert('Account setup incomplete. Please complete your registration.'); window.location.href='create_account.html';</script>";
    exit;
} else {
    // Verify password using proper password_verify function
    if (password_verify($password, $stored)) {
        $password_ok = true;
        
        // Rehash if necessary (security upgrade)
        if (password_needs_rehash($stored, PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $u = $conn->prepare('UPDATE create_account SET password = ? WHERE country_code = ? AND phone_number = ?');
            $u->bind_param('sss', $newHash, $user['country_code'], $user['phone_number']);
            $u->execute();
            $u->close();
        }
    } else {
        $password_ok = false;
    }
}

if (!$password_ok) {
    // Debug logging: append attempt info to a local log (dev only)
    $logPath = __DIR__ . DIRECTORY_SEPARATOR . 'login_debug.log';
    $storedPreview = substr($stored, 0, 30);
    $isHashed = (strpos($stored, '$2y$') === 0 || strpos($stored, '$2a$') === 0 || strpos($stored, '$argon2') === 0) ? 1 : 0;
    $entry = sprintf(
        "%s\tLOGIN_FAIL\tphone=%s%s\tcountry=%s\tstored_preview=%s\tis_hashed=%d\tverify=%d\tplaintext_match=%d\tIP=%s\n",
        date('Y-m-d H:i:s'),
        $country_code, $phone_number,
        $country_code,
        addslashes($storedPreview),
        $isHashed,
        (int)(isset($stored) && password_verify($password, $stored)),
        (int)($stored === $password),
        $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    );
    @file_put_contents($logPath, $entry, FILE_APPEND | LOCK_EX);

    // Redirect back to login page with generic error and prefilled phone fields
    $params = http_build_query([
        'error' => 'Incorrect password',
        'country_code' => $country_code,
        'phone_number' => $phone_number
    ]);
    header('Location: login.html?' . $params);
    exit;
}

// Successful login: set session variables
$_SESSION['user_id'] = $user['id']; // Use the actual integer ID
$_SESSION['name'] = $user['name'];
$_SESSION['country_code'] = $user['country_code'];
$_SESSION['phone_number'] = $user['phone_number'];
if (!empty($user['email'])) $_SESSION['email'] = $user['email'];

// Mark account as complete if needed (use country_code + phone_number to locate row)
$update = $conn->prepare('UPDATE create_account SET account_complete = 1 WHERE country_code = ? AND phone_number = ?');
$update->bind_param('ss', $user['country_code'], $user['phone_number']);
$update->execute();

// Redirect to PHP homepage (session will be available there)
echo "<script>window.location.href='homepage_clean.php';</script>";

$stmt->close();
$conn->close();
?>
