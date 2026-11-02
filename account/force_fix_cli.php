<?php
// Force fix script for CLI execution
require_once 'connect.php';

echo "Starting Force Fix...\n";

// 1. Fix VPA Column
try {
    $result = $conn->query("SHOW COLUMNS FROM create_account LIKE 'vpa'");
    if ($result->num_rows == 0) {
        echo "Adding vpa column...\n";
        $conn->query("ALTER TABLE create_account ADD COLUMN vpa VARCHAR(100) UNIQUE DEFAULT NULL AFTER phone_number");
        echo "VPA column added.\n";
    } else {
        echo "VPA column already exists.\n";
    }
    
    // Update VPAs
    $conn->query("UPDATE create_account SET vpa = CONCAT(phone_number, '@paysecure') WHERE vpa IS NULL AND phone_number IS NOT NULL");
    echo "VPAs updated.\n";
    
} catch (Exception $e) {
    echo "Error fixing VPA: " . $e->getMessage() . "\n";
}

// 2. Fix Banks for user 1234567890
$target_phone = '1234567890';
$target_country = '+91';

try {
    // Check if banks exist for this user
    $stmt = $conn->prepare("SELECT count(*) as count FROM bank_details WHERE phone_number = ?");
    $stmt->bind_param('s', $target_phone);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['count'] == 0) {
        echo "No banks found for $target_phone. Inserting demo banks...\n";
        
        // Insert HDFC
        $stmt = $conn->prepare("INSERT INTO bank_details (phone_number, country_code, bank_name, last_6_digits, is_verified, created_at) VALUES (?, ?, 'HDFC Bank', '123456', 1, NOW())");
        $stmt->bind_param('ss', $target_phone, $target_country);
        $stmt->execute();
        
        // Insert SBI
        $stmt = $conn->prepare("INSERT INTO bank_details (phone_number, country_code, bank_name, last_6_digits, is_verified, created_at) VALUES (?, ?, 'State Bank of India', '654321', 1, NOW())");
        $stmt->bind_param('ss', $target_phone, $target_country);
        $stmt->execute();
        
        echo "Banks inserted for $target_phone.\n";
    } else {
        echo "Banks already exist for $target_phone (Count: " . $row['count'] . ").\n";
    }
    
} catch (Exception $e) {
    echo "Error fixing banks: " . $e->getMessage() . "\n";
}

echo "Force Fix Complete.\n";
?>
