<?php
session_start();

// Simple server-side fallback for environments where JS fails.
// Reads form POST from `phone.html` and creates a mock receipt in session,
// then redirects the user to the shareable `receipt.php?id=...` page.

// Expecting form fields: COUNTRYCODE, PHONENO, AMOUNT, NOTE

$country = isset($_POST['COUNTRYCODE']) ? trim($_POST['COUNTRYCODE']) : '+91';
$phone = isset($_POST['PHONENO']) ? trim($_POST['PHONENO']) : '';
$amount = isset($_POST['AMOUNT']) ? floatval($_POST['AMOUNT']) : 0;
$note = isset($_POST['NOTE']) ? trim($_POST['NOTE']) : '';

if ($phone === '' || $amount <= 0) {
    http_response_code(400);
    echo "<h2>Invalid request</h2><p>Please provide a valid phone number and amount.</p>";
    exit;
}

// Ensure mock stores exist (reuse same session keys used by mock_api.php)
if (!isset($_SESSION['mock_accounts'])) $_SESSION['mock_accounts'] = [];
if (!isset($_SESSION['mock_banks'])) $_SESSION['mock_banks'] = [];
if (!isset($_SESSION['mock_transactions'])) $_SESSION['mock_transactions'] = [];

// If no logged-in mock user, create a demo user (same behavior as mock_api)
$key = $_SESSION['mock_logged_in'] ?? null;
if (!$key) {
    $demo_country = '+91';
    $demo_phone = '9999999999';
    $demo_name = 'Demo User';
    $demo_key = $demo_country . '|' . $demo_phone;
    if (!isset($_SESSION['mock_accounts'][$demo_key])) {
        $_SESSION['mock_accounts'][$demo_key] = ['name'=>$demo_name,'country_code'=>$demo_country,'phone_number'=>$demo_phone,'password'=>'','created_at'=>date('c')];
        $_SESSION['mock_banks'][$demo_key] = $_SESSION['mock_banks'][$demo_key] ?? [];
        $_SESSION['mock_transactions'][$demo_key] = $_SESSION['mock_transactions'][$demo_key] ?? [];
        $_SESSION['mock_transactions'][$demo_key][] = ['tx_type'=>'credit','counterparty'=>'System','amount'=>5000,'currency'=>'INR','direction'=>'in','note'=>'Demo balance','created_at'=>date('Y-m-d H:i:s')];
    }
    $_SESSION['mock_logged_in'] = $demo_key;
    $key = $demo_key;
}

// Create a transaction entry for the sender
$senderAcc = $_SESSION['mock_accounts'][$key] ?? null;
$senderName = $senderAcc['name'] ?? 'You';
$txSender = [
    'tx_type' => 'debit',
    'counterparty' => $phone,
    'amount' => $amount,
    'currency' => 'INR',
    'direction' => 'out',
    'note' => $note ?: 'Payment to ' . $phone,
    'created_at' => date('Y-m-d H:i:s')
];
$_SESSION['mock_transactions'][$key][] = $txSender;

// Persist sender transaction and update DB balances if database is available
try {
    require_once 'connect.php';
    $parts = explode('|', $key);
    $sender_country = $parts[0] ?? '+91';
    $sender_phone = $parts[1] ?? ($senderAcc['phone_number'] ?? '');

    $balance_before = 0.0;
    $stmt = $conn->prepare("SELECT balance FROM account_balances WHERE country_code = ? AND phone_number = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('ss', $sender_country, $sender_phone);
        if ($stmt->execute()) {
            $stmt->store_result();
            $stmt->bind_result($balance_val);
            if ($stmt->fetch()) { $balance_before = floatval($balance_val); }
        }
        $stmt->close();
    }
    $balance_after = $balance_before - $amount;

    $ins = $conn->prepare("INSERT INTO transactions (country_code, phone_number, tx_type, counterparty, amount, currency, direction, balance_before, balance_after, note) VALUES (?,?,?,?,?,?,?,?,?,?)");
    if ($ins) {
        // use variables for bind_param (references required)
        $tx_type = $txSender['tx_type'];
        $tx_counterparty = $txSender['counterparty'];
        $tx_amount = $txSender['amount'];
        $tx_currency = $txSender['currency'];
        $tx_direction = $txSender['direction'];
        $tx_note = $txSender['note'];
        if (!$ins->bind_param('ssssdssdds', $sender_country, $sender_phone, $tx_type, $tx_counterparty, $tx_amount, $tx_currency, $tx_direction, $balance_before, $balance_after, $tx_note)) {
            @file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . '_receipts' . DIRECTORY_SEPARATOR . '_db_errors.log', date('c') . " bind_sender_tx_error: " . ($ins->error ?? $conn->error ?? 'unknown') . "\n", FILE_APPEND);
        } else {
            if (!$ins->execute()) {
                @file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . '_receipts' . DIRECTORY_SEPARATOR . '_db_errors.log', date('c') . " execute_sender_tx_error: " . ($ins->error ?? $conn->error ?? 'unknown') . "\n", FILE_APPEND);
            }
        }
        $ins->close();
    } else {
        @file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . '_receipts' . DIRECTORY_SEPARATOR . '_db_errors.log', date('c') . " prepare_sender_insert_error: " . ($conn->error ?? 'unknown') . "\n", FILE_APPEND);
    }
    $up = $conn->prepare("INSERT INTO account_balances (country_code, phone_number, balance) VALUES (?,?,?) ON DUPLICATE KEY UPDATE balance = VALUES(balance)");
    if ($up) { $up->bind_param('ssd', $sender_country, $sender_phone, $balance_after); $up->execute(); $up->close(); }
} catch (Exception $e) { }

// Try to credit recipient if they exist
$recipientKey = $country . '|' . $phone;
$txRecipient = null;
if (isset($_SESSION['mock_accounts'][$recipientKey])) {
    $recipientAcc = $_SESSION['mock_accounts'][$recipientKey];
    $txRecipient = [
        'tx_type' => 'credit',
        'counterparty' => $senderName,
        'amount' => $amount,
        'currency' => 'INR',
        'direction' => 'in',
        'note' => $note ?: 'Payment from ' . $senderName,
        'created_at' => date('Y-m-d H:i:s')
    ];
    $_SESSION['mock_transactions'][$recipientKey] = $_SESSION['mock_transactions'][$recipientKey] ?? [];
    $_SESSION['mock_transactions'][$recipientKey][] = $txRecipient;

    // Persist recipient credit to DB if available
    try {
        require_once 'connect.php';
        $rparts = explode('|', $recipientKey);
        $rcountry = $rparts[0] ?? '';
        $rphone = $rparts[1] ?? ($recipientAcc['phone_number'] ?? '');
        $rbefore = 0.0;
        $stmt2 = $conn->prepare("SELECT balance FROM account_balances WHERE country_code = ? AND phone_number = ? LIMIT 1");
        if ($stmt2) {
            $stmt2->bind_param('ss', $rcountry, $rphone);
            if ($stmt2->execute()) {
                $stmt2->store_result();
                $stmt2->bind_result($rbalance_val);
                if ($stmt2->fetch()) { $rbefore = floatval($rbalance_val); }
            }
            $stmt2->close();
        }
        $rafter = $rbefore + $amount;
        $ins2 = $conn->prepare("INSERT INTO transactions (country_code, phone_number, tx_type, counterparty, amount, currency, direction, balance_before, balance_after, note) VALUES (?,?,?,?,?,?,?,?,?,?)");
        if ($ins2) {
            $r_tx_type = $txRecipient['tx_type'];
            $r_tx_counterparty = $txRecipient['counterparty'];
            $r_tx_amount = $txRecipient['amount'];
            $r_tx_currency = $txRecipient['currency'];
            $r_tx_direction = $txRecipient['direction'];
            $r_tx_note = $txRecipient['note'];
            if (!$ins2->bind_param('ssssdssdds', $rcountry, $rphone, $r_tx_type, $r_tx_counterparty, $r_tx_amount, $r_tx_currency, $r_tx_direction, $rbefore, $rafter, $r_tx_note)) {
                @file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . '_receipts' . DIRECTORY_SEPARATOR . '_db_errors.log', date('c') . " bind_recipient_tx_error: " . ($ins2->error ?? $conn->error ?? 'unknown') . "\n", FILE_APPEND);
            } else {
                if (!$ins2->execute()) {
                    @file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . '_receipts' . DIRECTORY_SEPARATOR . '_db_errors.log', date('c') . " execute_recipient_tx_error: " . ($ins2->error ?? $conn->error ?? 'unknown') . "\n", FILE_APPEND);
                }
            }
            $ins2->close();
        } else {
            @file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . '_receipts' . DIRECTORY_SEPARATOR . '_db_errors.log', date('c') . " prepare_recipient_insert_error: " . ($conn->error ?? 'unknown') . "\n", FILE_APPEND);
        }
        $up2 = $conn->prepare("INSERT INTO account_balances (country_code, phone_number, balance) VALUES (?,?,?) ON DUPLICATE KEY UPDATE balance = VALUES(balance)");
        if ($up2) { $up2->bind_param('ssd', $rcountry, $rphone, $rafter); $up2->execute(); $up2->close(); }
    } catch (Exception $e) { }
}

// Create a shareable receipt and redirect to it
if (!isset($_SESSION['mock_receipts'])) $_SESSION['mock_receipts'] = [];
$receipt_id = 'rcpt_' . bin2hex(random_bytes(6));
$_SESSION['mock_receipts'][$receipt_id] = [
    'id' => $receipt_id,
    'from' => $senderName,
    'to' => $phone,
    'amount' => $amount,
    'currency' => 'INR',
    'note' => $note,
    'created_at' => date('Y-m-d H:i:s'),
    'tx_sender' => $txSender,
    'tx_recipient' => $txRecipient
];
// Persist receipt to disk as well so the link works cross-session
try {
    $receiptsDir = __DIR__ . DIRECTORY_SEPARATOR . '_receipts';
    if (!is_dir($receiptsDir)) @mkdir($receiptsDir, 0755, true);
    $filePath = $receiptsDir . DIRECTORY_SEPARATOR . $receipt_id . '.json';
    file_put_contents($filePath, json_encode($_SESSION['mock_receipts'][$receipt_id], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
} catch (Exception $e) {
    // log disk write errors to debug file
    @file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . '_receipts' . DIRECTORY_SEPARATOR . '_db_errors.log', date('c') . " write_receipt_error: " . $e->getMessage() . "\n", FILE_APPEND);
}

// Redirect user to the receipt page (shows receipt + download options)
header('Location: receipt_new.php?id=' . urlencode($receipt_id));
exit;

?>
