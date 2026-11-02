<?php
echo "<h1>XAMPP Test - " . date('Y-m-d H:i:s') . "</h1>";

// Test session
session_start();
echo "<p>✅ PHP Session: Working</p>";

// Test database
try {
    include_once 'connect.php';
    if (isset($conn)) {
        echo "<p>✅ Database Connection: Working</p>";
        
        // Test a simple query
        $result = $conn->query("SELECT 1");
        if ($result) {
            echo "<p>✅ Database Query: Working</p>";
        } else {
            echo "<p>❌ Database Query: Failed - " . $conn->error . "</p>";
        }
        
        // Check if our tables exist
        $tables = ['create_account', 'bank_balances', 'transactions'];
        foreach ($tables as $table) {
            $result = $conn->query("SHOW TABLES LIKE '$table'");
            if ($result->num_rows > 0) {
                echo "<p>✅ Table '$table': Exists</p>";
            } else {
                echo "<p>❌ Table '$table': Missing</p>";
            }
        }
        
    } else {
        echo "<p>❌ Database Connection: Failed</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Database Error: " . $e->getMessage() . "</p>";
}

// Test homepage access
echo "<hr>";
echo "<h2>Quick Access</h2>";
echo "<p><a href='homepage_v1.php' style='color: blue; font-size: 18px; text-decoration: none; padding: 10px; background: #f0f0f0; border-radius: 5px; display: inline-block; margin: 10px;'>🏠 Go to Homepage</a></p>";

// Create session for testing
$_SESSION['name'] = 'Test User';
$_SESSION['country_code'] = '+91';
$_SESSION['phone_number'] = '9876543210';
$_SESSION['email'] = 'test@example.com';

echo "<p>✅ Test session created for homepage</p>";
echo "<p><strong>Now click 'Go to Homepage' above to test your dashboard!</strong></p>";
?>