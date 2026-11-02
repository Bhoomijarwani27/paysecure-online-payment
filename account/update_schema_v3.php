<?php
require_once 'connect.php';

echo "<h2>Database Schema Update V3</h2>";
echo "<pre>";

// 1. Update create_account table
echo "Checking create_account table...\n";
$result = $conn->query("DESCRIBE create_account");
$columns = [];
while ($row = $result->fetch_assoc()) {
    $columns[] = $row['Field'];
}

// Add upi_pin
if (!in_array('upi_pin', $columns)) {
    $sql = "ALTER TABLE create_account ADD COLUMN upi_pin VARCHAR(6) DEFAULT NULL";
    if ($conn->query($sql)) {
        echo "✅ Added 'upi_pin' column.\n";
    } else {
        echo "❌ Error adding 'upi_pin' column: " . $conn->error . "\n";
    }
} else {
    echo "ℹ️ 'upi_pin' column already exists.\n";
}

// Add account_type
if (!in_array('account_type', $columns)) {
    $sql = "ALTER TABLE create_account ADD COLUMN account_type VARCHAR(20) DEFAULT 'user'";
    if ($conn->query($sql)) {
        echo "✅ Added 'account_type' column.\n";
    } else {
        echo "❌ Error adding 'account_type' column: " . $conn->error . "\n";
    }
} else {
    echo "ℹ️ 'account_type' column already exists.\n";
}

// Add name
if (!in_array('name', $columns)) {
    $sql = "ALTER TABLE create_account ADD COLUMN name VARCHAR(100) DEFAULT NULL";
    if ($conn->query($sql)) {
        echo "✅ Added 'name' column.\n";
    } else {
        echo "❌ Error adding 'name' column: " . $conn->error . "\n";
    }
} else {
    echo "ℹ️ 'name' column already exists.\n";
}

// Add email
if (!in_array('email', $columns)) {
    $sql = "ALTER TABLE create_account ADD COLUMN email VARCHAR(100) DEFAULT NULL";
    if ($conn->query($sql)) {
        echo "✅ Added 'email' column.\n";
    } else {
        echo "❌ Error adding 'email' column: " . $conn->error . "\n";
    }
} else {
    echo "ℹ️ 'email' column already exists.\n";
}

// Add created_at if missing (used in getOrCreateOperator)
if (!in_array('created_at', $columns)) {
    $sql = "ALTER TABLE create_account ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
    if ($conn->query($sql)) {
        echo "✅ Added 'created_at' column.\n";
    } else {
        echo "❌ Error adding 'created_at' column: " . $conn->error . "\n";
    }
} else {
    echo "ℹ️ 'created_at' column already exists.\n";
}

echo "\nDone!";
echo "</pre>";
?>
