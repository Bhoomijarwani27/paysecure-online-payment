<?php
require_once __DIR__ . '/connect.php';
header('Content-Type: text/plain; charset=utf-8');

echo "DB Test - online_payment\n";
if ($conn->connect_errno) {
    echo "Connection error: " . $conn->connect_error . "\n";
    exit(1);
}

echo "Connected to MySQL OK. Host: {$conn->host_info}\n";

// Show transactions count
$res = $conn->query("SELECT COUNT(*) AS c FROM transactions");
if ($res) {
    $row = $res->fetch_assoc();
    echo "transactions.count = " . ($row['c'] ?? '0') . "\n";
} else {
    echo "Error querying transactions: " . $conn->error . "\n";
}

// Show last 5 transactions
$res2 = $conn->query("SELECT id, country_code, phone_number, tx_type, counterparty, amount, direction, created_at FROM transactions ORDER BY id DESC LIMIT 5");
if ($res2) {
    echo "Last transactions (up to 5):\n";
    while ($r = $res2->fetch_assoc()) {
        echo "#" . $r['id'] . " " . ($r['country_code'] ?? '') . " " . ($r['phone_number'] ?? '') . " " . ($r['tx_type'] ?? '') . " " . ($r['amount'] ?? '') . " " . ($r['direction'] ?? '') . " " . ($r['created_at'] ?? '') . "\n";
    }
} else {
    echo "Error fetching last transactions: " . $conn->error . "\n";
}

// Print last SQL error (if any)
if (property_exists($conn, 'warning_count')) {
    $warnings = $conn->warning_count;
    echo "Connection warnings: " . $warnings . "\n";
}

// Provide guidance
echo "\nIf this shows a connection error or 0 transactions after you made a payment, please:\n";
echo " - Open the Send Money page and make a payment (JS path preferred).\n";
echo " - If you used the JS path, check the Network POST to mock_api.php?action=send_payment and copy the JSON response (it may include 'db_errors').\n";
echo " - If you used the non-JS fallback (form submit), check the file 'account/_receipts/_db_errors.log' for errors.\n";

$conn->close();
