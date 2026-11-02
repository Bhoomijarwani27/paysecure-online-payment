<?php
// Direct transaction test - bypasses all caching
require_once 'connect.php';

echo "<h2>Direct Transaction Test</h2>";

$testPhone = '1234567890';

// 1. Check table structure
echo "<h3>1. Table Structure</h3>";
$result = $conn->query("SHOW CREATE TABLE transactions");
if ($result) {
    $row = $result->fetch_assoc();
    echo "<pre>" . htmlspecialchars($row['Create Table']) . "</pre>";
}

// 2. Insert a test transaction directly
echo "<h3>2. Inserting Test Transaction</h3>";
$testRef = 'DIRECT_TEST_' . time();
$sql = "INSERT INTO transactions (phone_number, amount, tx_type, note, reference_id, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())";
$stmt = $conn->prepare($sql);
$amount = -50.00;
$type = 'test_recharge';
$note = 'Direct test transaction';
$stmt->bind_param("sdsss", $testPhone, $amount, $type, $note, $testRef);

if ($stmt->execute()) {
    echo "<p style='color: green'>✅ Insert successful! Reference: $testRef</p>";
} else {
    echo "<p style='color: red'>❌ Insert failed: " . $stmt->error . "</p>";
}

// 3. Search for the transaction we just inserted
echo "<h3>3. Searching for Test Transaction</h3>";
$sql = "SELECT * FROM transactions WHERE reference_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $testRef);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "<p style='color: green'>✅ Found transaction!</p>";
    while ($row = $result->fetch_assoc()) {
        echo "<pre>" . print_r($row, true) . "</pre>";
    }
} else {
    echo "<p style='color: red'>❌ Transaction not found</p>";
}

// 4. Search for transactions with phone 1234567890
echo "<h3>4. All Transactions for Phone $testPhone</h3>";
$sql = "SELECT * FROM transactions WHERE phone_number = ? ORDER BY created_at DESC LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $testPhone);
$stmt->execute();
$result = $stmt->get_result();

echo "<p>Found " . $result->num_rows . " transactions</p>";
while ($row = $result->fetch_assoc()) {
    echo "<pre>" . print_r($row, true) . "</pre>";
}

// 5. Check if there are any TEST transactions
echo "<h3>5. Searching for TEST Transactions</h3>";
$sql = "SELECT * FROM transactions WHERE note LIKE '%TEST%' OR reference_id LIKE '%TEST%'";
$result = $conn->query($sql);
echo "<p>Found " . $result->num_rows . " TEST transactions</p>";
while ($row = $result->fetch_assoc()) {
    echo "<pre>" . print_r($row, true) . "</pre>";
}
?>
