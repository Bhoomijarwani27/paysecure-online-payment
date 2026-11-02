<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Diagnosing Database Connection...\n";
echo "-------------------------------\n";

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "online_payment";

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        throw new Exception($conn->connect_error);
    }
    echo "✅ Connection Successful!\n";
    echo "Server Info: " . $conn->server_info . "\n";
    $conn->close();
} catch (Exception $e) {
    echo "❌ Connection Failed!\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "\nPossible Causes:\n";
    echo "1. MySQL service is NOT running in XAMPP.\n";
    echo "2. MySQL is running on a different port (not 3306).\n";
}
?>
