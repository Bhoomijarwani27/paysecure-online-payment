<?php
// Save bank selection and card details into PHP session
session_start();
// connect.php may not be needed for this endpoint; include only if you require DB access
if (file_exists(__DIR__ . '/connect.php')) {
    require_once 'connect.php';
}

header('Content-Type: application/json; charset=utf-8');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$bank_name = trim($_POST['bank_name'] ?? '');
$last_6 = trim($_POST['last_6_digits'] ?? '');
$expiry = trim($_POST['expiry_date'] ?? '');

// Normalise expiry (allow MM/YY or MM-YY)
$expiry = str_replace('-', '/', $expiry);

// Basic server-side validation
$errors = [];
if ($bank_name === '') {
    $errors[] = 'bank_name is required';
}
if (!preg_match('/^\d{6}$/', $last_6)) {
    $errors[] = 'last_6_digits must be exactly 6 digits';
}
if (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $expiry)) {
    $errors[] = 'expiry_date must be MM/YY';
}

if (!empty($errors)) {
    // Log the bad request for debugging
    $logLine = date('Y-m-d H:i:s') . " | INVALID INPUT | " . json_encode(['bank_name'=>$bank_name,'last_6'=>$last_6,'expiry'=>$expiry,'errors'=>$errors]) . PHP_EOL;
    @file_put_contents(__DIR__ . '/save_bank_session.log', $logLine, FILE_APPEND | LOCK_EX);

    echo json_encode(['success' => false, 'message' => 'Invalid input', 'errors' => $errors]);
    exit;
}

// Save to session
$_SESSION['bank_name'] = $bank_name;
$_SESSION['last_6_digits'] = $last_6;
$_SESSION['expiry_date'] = $expiry;

// Log success (timestamp, session id, payload)
$logLine = date('Y-m-d H:i:s') . " | SAVED | session_id=" . session_id() . " | " . json_encode(['bank_name'=>$bank_name,'last_6'=>$last_6,'expiry'=>$expiry]) . PHP_EOL;
@file_put_contents(__DIR__ . '/save_bank_session.log', $logLine, FILE_APPEND | LOCK_EX);

echo json_encode(['success' => true, 'message' => 'Saved to session', 'data' => ['bank_name'=>$bank_name,'last_6_digits'=>$last_6,'expiry_date'=>$expiry]]);
exit;
?>
