<?php
// Quick script to set your balance
require_once 'connect.php';
session_start();

$user_phone = isset($_SESSION['phone_number']) ? $_SESSION['phone_number'] : null;

if (!$user_phone) {
    echo "Not logged in. Please login first.";
    exit;
}

// Set balance to 10000
$new_balance = 10000;

$stmt = $conn->prepare("UPDATE create_account SET balance = ? WHERE phone_number = ?");
$stmt->bind_param("ds", $new_balance, $user_phone);

if ($stmt->execute()) {
    echo "<h2>✅ Balance Updated!</h2>";
    echo "<p>Your balance has been set to: <strong>₹" . number_format($new_balance, 2) . "</strong></p>";
    echo "<p><a href='homepage_clean.php'>Go to Dashboard</a></p>";
} else {
    echo "<h2>❌ Error</h2>";
    echo "<p>Could not update balance: " . $stmt->error . "</p>";
}

$stmt->close();
$conn->close();
?>
