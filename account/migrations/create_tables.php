<?php
require_once __DIR__ . '/../connect.php';

// Simple migration script to create required tables for demo transactions and balances.
// Run this by visiting via browser (http://localhost/online_payment/account/migrations/create_tables.php)
// or by running `php migrations/create_tables.php` from the account folder in CLI.

$errors = [];

// transactions table
$sqlTx = "CREATE TABLE IF NOT EXISTS `transactions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `country_code` VARCHAR(8) DEFAULT NULL,
  `phone_number` VARCHAR(32) DEFAULT NULL,
  `tx_type` VARCHAR(32) DEFAULT NULL,
  `counterparty` VARCHAR(255) DEFAULT NULL,
  `amount` DECIMAL(14,2) DEFAULT 0,
  `currency` VARCHAR(8) DEFAULT 'INR',
  `direction` ENUM('in','out','none') DEFAULT 'none',
  `balance_before` DECIMAL(14,2) DEFAULT NULL,
  `balance_after` DECIMAL(14,2) DEFAULT NULL,
  `note` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX (`country_code`,`phone_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (!$conn->query($sqlTx)) {
    $errors[] = "transactions: " . $conn->error;
}

// account_balances table
$sqlBal = "CREATE TABLE IF NOT EXISTS `account_balances` (
  `country_code` VARCHAR(8) NOT NULL,
  `phone_number` VARCHAR(32) NOT NULL,
  `balance` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`country_code`,`phone_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (!$conn->query($sqlBal)) {
    $errors[] = "account_balances: " . $conn->error;
}

// bank_balances table (per-linked-bank balances)
$sqlBankBal = "CREATE TABLE IF NOT EXISTS `bank_balances` (
  `country_code` VARCHAR(8) NOT NULL,
  `phone_number` VARCHAR(32) NOT NULL,
  `bank_last6` VARCHAR(12) NOT NULL,
  `balance` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`country_code`,`phone_number`,`bank_last6`),
  INDEX (`country_code`,`phone_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (!$conn->query($sqlBankBal)) {
    $errors[] = "bank_balances: " . $conn->error;
}

// Provide simple feedback
if (empty($errors)) {
    echo "Migration completed successfully. Tables 'transactions' and 'account_balances' are present.<br>";
    echo "You can now link a bank to seed a demo balance and transactions.<br>";
} else {
    echo "Migration finished with errors:<br>";
    foreach ($errors as $e) echo htmlspecialchars($e) . '<br>';
}

$conn->close();
?>