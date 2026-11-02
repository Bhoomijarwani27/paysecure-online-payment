<?php
session_start();
require_once 'connect.php';

// Create linked_banks table if it doesn't exist
$createTableSQL = "CREATE TABLE IF NOT EXISTS linked_banks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_phone VARCHAR(15) NOT NULL,
    bank_name VARCHAR(100) NOT NULL,
    last_6_digits VARCHAR(6) NOT NULL,
    expiry_date VARCHAR(7),
    atm_pin VARCHAR(4),
    is_verified TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_phone (user_phone)
)";

if ($conn->query($createTableSQL)) {
    $response = ['success' => true, 'message' => 'Linked banks table ready'];
} else {
    $response = ['success' => false, 'message' => 'Error creating table: ' . $conn->error];
}

header('Content-Type: application/json');
echo json_encode($response);
?>
