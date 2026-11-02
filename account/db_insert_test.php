<?php
require_once __DIR__ . '/connect.php';
header('Content-Type: text/plain; charset=utf-8');

echo "DB Insert Test - online_payment\n";
echo "================================\n";

// Test connection
if ($conn->connect_errno) {
    echo "ERROR: Connection failed: " . $conn->connect_error . "\n";
    exit(1);
}
echo "✓ Connected to MySQL: {$conn->host_info}\n";

// Check if transactions table exists
$check = $conn->query("SHOW TABLES LIKE 'transactions'");
if ($check && $check->num_rows > 0) {
    echo "✓ Table 'transactions' exists\n";
} else {
    echo "ERROR: Table 'transactions' does not exist. Run migration first:\n";
    echo "  http://localhost/online_payment/account/migrations/create_tables.php\n";
    exit(1);
}

// Test INSERT into transactions
echo "\nTesting INSERT into transactions...\n";
$test_country = '+91';
$test_phone = '1234567890';
$test_type = 'debit';
$test_counterparty = '9876543210';
$test_amount = 100.50;
$test_currency = 'INR';
$test_direction = 'out';
$test_balance_before = 1000.0;
$test_balance_after = 899.50;
$test_note = 'Test transaction';

$ins = $conn->prepare("INSERT INTO transactions (country_code, phone_number, tx_type, counterparty, amount, currency, direction, balance_before, balance_after, note) VALUES (?,?,?,?,?,?,?,?,?,?)");

if (!$ins) {
    echo "ERROR: Prepare failed: " . $conn->error . "\n";
    exit(1);
}

$bind_ok = $ins->bind_param('ssssdssdds', $test_country, $test_phone, $test_type, $test_counterparty, $test_amount, $test_currency, $test_direction, $test_balance_before, $test_balance_after, $test_note);

if (!$bind_ok) {
    echo "ERROR: Bind failed: " . $ins->error . "\n";
    $ins->close();
    exit(1);
}

$exec_ok = $ins->execute();

if (!$exec_ok) {
    echo "ERROR: Execute failed: " . $ins->error . "\n";
    echo "MySQL errno: " . $ins->errno . "\n";
} else {
    $insert_id = $ins->insert_id;
    echo "✓ INSERT successful! New row ID: {$insert_id}\n";
}

$ins->close();

// Verify the row was inserted
$verify = $conn->query("SELECT COUNT(*) as c FROM transactions WHERE country_code = '{$test_country}' AND phone_number = '{$test_phone}'");
if ($verify) {
    $row = $verify->fetch_assoc();
    echo "✓ Verification: Found {$row['c']} test row(s)\n";
} else {
    echo "ERROR: Verification query failed: " . $conn->error . "\n";
}

// Show total transaction count
$total = $conn->query("SELECT COUNT(*) as total FROM transactions");
if ($total) {
    $total_row = $total->fetch_assoc();
    echo "✓ Total transactions in DB: {$total_row['total']}\n";
} else {
    echo "ERROR: Total count query failed: " . $conn->error . "\n";
}

echo "\nTest complete. If INSERT failed, the error above shows why.\n";
echo "If successful, check phpMyAdmin to confirm the row appears.\n";

$conn->close();
?>
