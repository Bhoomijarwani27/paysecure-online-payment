<?php
require_once 'connect.php';

// First, check if id column exists
$result = $conn->query("SHOW COLUMNS FROM create_account LIKE 'id'");
if ($result->num_rows > 0) {
    echo "✓ id column already exists in create_account table\n";
} else {
    // Add id column as AUTO_INCREMENT (not primary key since table already has composite PK)
    $sql = "ALTER TABLE create_account ADD COLUMN id INT AUTO_INCREMENT UNIQUE FIRST";
    if ($conn->query($sql)) {
        echo "✓ Successfully added id column to create_account table\n";
    } else {
        echo "✗ Error adding id column: " . $conn->error . "\n";
    }
}

// Verify the change
$result = $conn->query("DESCRIBE create_account");
echo "\nUpdated create_account structure:\n";
while($row = $result->fetch_assoc()) {
    $key = $row['Key'] ? " [" . $row['Key'] . "]" : "";
    $extra = $row['Extra'] ? " {" . $row['Extra'] . "}" : "";
    echo "  " . $row['Field'] . " (" . $row['Type'] . ")" . $key . $extra . "\n";
}
?>
