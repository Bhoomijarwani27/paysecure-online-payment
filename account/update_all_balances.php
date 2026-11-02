<?php
// Script to update all accounts with random balances
require_once 'connect.php';

// Update all accounts that have 0 or NULL balance
$stmt = $conn->prepare("SELECT phone_number, balance FROM create_account WHERE balance IS NULL OR balance = 0");
$stmt->execute();
$result = $stmt->get_result();

$updated_count = 0;
$accounts = [];

while ($row = $result->fetch_assoc()) {
    $phone = $row['phone_number'];
    $random_balance = rand(1000, 10000);
    
    $update_stmt = $conn->prepare("UPDATE create_account SET balance = ? WHERE phone_number = ?");
    $update_stmt->bind_param("ds", $random_balance, $phone);
    
    if ($update_stmt->execute()) {
        $updated_count++;
        $accounts[] = [
            'phone' => $phone,
            'balance' => $random_balance
        ];
    }
    
    $update_stmt->close();
}

echo "<h2>✅ Balance Update Complete!</h2>";
echo "<p><strong>$updated_count accounts updated</strong></p>";

if (count($accounts) > 0) {
    echo "<h3>Updated Accounts:</h3>";
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr><th>Phone Number</th><th>New Balance</th></tr>";
    
    foreach ($accounts as $acc) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($acc['phone']) . "</td>";
        echo "<td>₹" . number_format($acc['balance'], 2) . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p>No accounts needed updating.</p>";
}

echo "<br><p><a href='homepage_clean.php'>Go to Dashboard</a></p>";

$stmt->close();
$conn->close();
?>
