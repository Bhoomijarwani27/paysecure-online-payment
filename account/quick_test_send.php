<?php
require_once 'connect.php';

// Give sender some money
$conn->query("UPDATE create_account SET balance = 1000 WHERE phone_number = '1234567890'");
echo "✅ Updated sender (1234567890) balance to ₹1000\n";

// Now run the test again
require_once 'balance_manager.php';
$balanceManager = new BalanceManager($conn);

echo "\nSending ₹100 from 1234567890 to 9876543210...\n";
$result = $balanceManager->sendMoney('1234567890', '9876543210', 100, 'Test payment');

if ($result['success']) {
    echo "✅ SUCCESS! Transaction ID: " . $result['transaction_id'] . "\n";
    echo "Sender new balance: ₹" . $result['new_balance'] . "\n";
    echo "Recipient new balance: ₹" . $balanceManager->getCurrentBalance('9876543210') . "\n";
} else {
    echo "❌ FAILED: " . $result['error'] . "\n";
}
?>
