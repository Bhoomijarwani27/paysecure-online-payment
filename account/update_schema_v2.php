<?php
require_once 'connect.php';

echo "<h2>Database Schema Update</h2>";
echo "<pre>";

// 1. Update create_account table
echo "Checking create_account table...\n";
$result = $conn->query("DESCRIBE create_account");
$columns = [];
while ($row = $result->fetch_assoc()) {
    $columns[] = $row['Field'];
}

if (!in_array('balance', $columns)) {
    $sql = "ALTER TABLE create_account ADD COLUMN balance DECIMAL(10,2) DEFAULT 0.00";
    if ($conn->query($sql)) {
        echo "✅ Added 'balance' column to create_account table.\n";
    } else {
        echo "❌ Error adding 'balance' column: " . $conn->error . "\n";
    }
} else {
    echo "ℹ️ 'balance' column already exists in create_account table.\n";
}

// 2. Create transactions table if not exists
echo "\nChecking transactions table...\n";
$sql = "CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    description VARCHAR(255),
    status VARCHAR(20) DEFAULT 'success',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql)) {
    echo "✅ transactions table checked/created.\n";
} else {
    echo "❌ Error creating transactions table: " . $conn->error . "\n";
}

// 3. Check user_id in transactions table
$result = $conn->query("DESCRIBE transactions");
$columns = [];
while ($row = $result->fetch_assoc()) {
    $columns[] = $row['Field'];
}

if (!in_array('user_id', $columns)) {
    $sql = "ALTER TABLE transactions ADD COLUMN user_id INT NOT NULL AFTER id";
    if ($conn->query($sql)) {
        echo "✅ Added 'user_id' column to transactions table.\n";
    } else {
        echo "❌ Error adding 'user_id' column: " . $conn->error . "\n";
    }
} else {
    echo "ℹ️ 'user_id' column already exists in transactions table.\n";
}

echo "\nDone!";
echo "</pre>";
?>
