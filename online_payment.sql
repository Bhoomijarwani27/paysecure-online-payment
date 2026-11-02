-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 02, 2026 at 02:27 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `online_payment`
--

-- --------------------------------------------------------

--
-- Table structure for table `account_balances`
--

CREATE TABLE `account_balances` (
  `country_code` varchar(8) NOT NULL,
  `phone_number` varchar(32) NOT NULL,
  `balance` decimal(14,2) NOT NULL DEFAULT 0.00,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bank_balances`
--

CREATE TABLE `bank_balances` (
  `country_code` varchar(8) NOT NULL,
  `bank_name` varchar(100) NOT NULL,
  `phone_number` varchar(32) NOT NULL,
  `bank_last6` varchar(12) NOT NULL,
  `balance` decimal(14,2) NOT NULL DEFAULT 0.00,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bank_balances`
--

INSERT INTO `bank_balances` (`country_code`, `bank_name`, `phone_number`, `bank_last6`, `balance`, `updated_at`) VALUES
('', 'State Bank of India', '1234567890', '', 10000.00, '2025-11-24 10:50:49'),
('', 'Punjab National Bank', '1234567899', '', 9870.00, '2025-11-24 13:08:30'),
('', 'Axis Bank', '7227071270', '', 10000.00, '2025-11-24 10:50:49'),
('', 'Canara Bank', '7227071273', '', 10000.00, '2025-11-24 10:50:49'),
('+1', 'Canara Bank', '4567890123', '', 10000.00, '2025-11-24 09:42:21'),
('+91', 'IndusInd Bank', '1234567000', '', 10000.00, '2025-11-24 09:28:58'),
('+91', 'Kotak Mahindra Bank', '1234567800', '', 10000.00, '2025-11-24 09:28:58'),
('+91', 'Axis Bank', '2345678900', '', 10000.00, '2025-11-24 09:28:58'),
('+91', 'ICICI Bank', '7227071273', '', 9890.00, '2025-11-24 10:12:24'),
('+91', 'HDFC Bank', '7227071279', '', 10000.00, '2025-11-24 09:28:58');

-- --------------------------------------------------------

--
-- Table structure for table `bank_details`
--

CREATE TABLE `bank_details` (
  `country_code` varchar(5) NOT NULL,
  `phone_number` varchar(15) NOT NULL,
  `bank_name` varchar(100) NOT NULL,
  `last_6_digits` varchar(6) NOT NULL,
  `expiry_date` varchar(5) NOT NULL,
  `card_pin` varchar(255) NOT NULL,
  `upi_pin` varchar(255) NOT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bank_details`
--

INSERT INTO `bank_details` (`country_code`, `phone_number`, `bank_name`, `last_6_digits`, `expiry_date`, `card_pin`, `upi_pin`, `is_verified`, `created_at`) VALUES
('+1', '4567890123', 'Canara Bank', '999999', '09/27', '1000', '1234', 1, '2025-11-24 04:09:48'),
('+91', '1234567000', 'IndusInd Bank', '123456', '08/27', '1234', '1234', 1, '2025-11-24 02:11:18'),
('+91', '1234567800', 'Kotak Mahindra Bank', '123456', '09/28', '1234', '1234', 1, '2025-11-24 01:57:26'),
('+91', '1234567890', 'State Bank of India', '123456', '09/28', '1234', '1234', 1, '2025-11-24 04:57:42'),
('+91', '1234567899', 'Punjab National Bank', '123456', '09/28', '1234', '1234', 1, '2025-11-24 07:21:46'),
('+91', '2345678900', 'Axis Bank', '123456', '12/39', '1234', '1234', 1, '2025-11-24 03:34:48'),
('+91', '7227071270', 'Axis Bank', '123456', '09/27', '1234', '1234', 1, '2025-11-24 05:12:07'),
('+91', '7227071273', 'Canara Bank', '123456', '09/30', '1234', '1234', 1, '2025-11-24 04:58:43'),
('+91', '7227071273', 'ICICI Bank', '123456', '09/29', '1234', '1234', 1, '2025-11-23 22:00:06'),
('+91', '7227071279', 'HDFC Bank', '123456', '09/27', '1234', '1234', 1, '2025-11-23 22:04:20');

-- --------------------------------------------------------

--
-- Table structure for table `bill_payments`
--

CREATE TABLE `bill_payments` (
  `id` int(11) NOT NULL,
  `transaction_id` varchar(50) NOT NULL,
  `user_phone` varchar(15) NOT NULL,
  `bill_type` varchar(50) NOT NULL,
  `provider` varchar(255) NOT NULL,
  `account_number` varchar(100) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('pending','completed','failed') DEFAULT 'completed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bill_payments`
--

INSERT INTO `bill_payments` (`id`, `transaction_id`, `user_phone`, `bill_type`, `provider`, `account_number`, `customer_name`, `amount`, `status`, `created_at`) VALUES
(1, 'BIL4DF2E343F2B3', '7227071279', 'electricity', 'MSEB', '1234567890', 'nbvbkkzx', 1509.00, 'completed', '2025-11-23 23:44:40'),
(2, 'BIL94B537AB8887', '7227071279', 'electricity', 'MSEB', '123434566', 'jjkjcjsl', 352.00, 'completed', '2025-11-24 01:06:41'),
(3, 'BILF03F195909D4', '8888888888', 'electricity', 'MSEB', '12344565', 'bhjkjkj', 1036.00, 'completed', '2025-11-24 07:37:33');

-- --------------------------------------------------------

--
-- Table structure for table `create_account`
--

CREATE TABLE `create_account` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `country_code` varchar(10) NOT NULL,
  `phone_number` varchar(15) NOT NULL,
  `vpa` varchar(100) DEFAULT NULL,
  `otp` int(4) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `phone_verified` tinyint(1) DEFAULT 0,
  `account_complete` tinyint(1) DEFAULT 0,
  `status` enum('pending','phone_verified','complete') DEFAULT 'pending',
  `balance` decimal(10,2) DEFAULT 0.00,
  `upi_pin` varchar(6) DEFAULT NULL,
  `account_type` varchar(20) DEFAULT 'user',
  `email` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `create_account`
--

INSERT INTO `create_account` (`id`, `name`, `country_code`, `phone_number`, `vpa`, `otp`, `password`, `phone_verified`, `account_complete`, `status`, `balance`, `upi_pin`, `account_type`, `email`, `created_at`) VALUES
(1, 'Test Recipient', '', '8888888888', NULL, 0, NULL, 0, 0, 'pending', 604.00, '1234', 'user', NULL, '2025-11-23 22:58:43'),
(2, 'Test Sender', '', '9999999999', NULL, 0, NULL, 0, 0, 'pending', 4450.00, '1234', 'user', NULL, '2025-11-23 22:58:43'),
(19, 'bhoomi', '+1', '4567890123', NULL, 210095, '$2y$10$pr/4HUhJjnCQ21qjItBWzef9dif.q28zPlm5ztLfdHGkY6GhPJxL6', 0, 1, 'complete', 9740.00, '1000', 'user', NULL, '2025-11-24 04:08:22'),
(8, 'abc', '+91', '1234567000', NULL, 162762, '$2y$10$cUnMmT69T0zSYrCZRan/7.vGPisPBsmUZ.nJzOz.wnKSw6rrinBNm', 0, 1, 'complete', 4880.00, '1234', 'user', NULL, '2025-11-24 02:10:17'),
(7, 'nkkk', '+91', '1234567800', NULL, 560069, '$2y$10$SvMCKVqcnt7k.otoO0VU6uPLElbp1ZgDlGnwgI4GlXBiWlRsGkhyS', 0, 1, 'complete', 4805.00, '1234', 'user', NULL, '2025-11-24 01:39:27'),
(20, 'gfyglk', '+91', '1234567890', NULL, 571989, '$2y$10$NvEmeSRJ4j4qtdeuSSX91.vJTytJpWQtqR9KQuKOIMppWO/r7cLSu', 0, 1, 'complete', 1780.00, '1234', 'user', NULL, '2025-11-24 04:45:55'),
(22, 'mjopj', '+91', '1234567899', NULL, 295596, '$2y$10$MYAxqGdDS0wOaDjEVxYW3.PS44xKm9/rDUSe6yC9UTroFvc4XFj/a', 0, 1, 'complete', 8730.00, '1234', 'user', NULL, '2025-11-24 07:20:56'),
(18, 'help', '+91', '2345678900', NULL, 703355, '$2y$10$sxTnXhSCWJ5cShwEcqO.E.UBz8hGNdKbdK4CCdqEJaS82rX8JiN0.', 0, 1, 'complete', 8884.00, '1234', 'user', NULL, '2025-11-24 03:26:54'),
(9, 'oop', '+91', '2345678901', NULL, 901525, NULL, 0, 0, 'pending', 9139.00, NULL, 'user', NULL, '2025-11-24 02:28:27'),
(23, 'mnp', '+91', '6789012345', NULL, 129196, '$2y$10$Ome6mYCOs05nstc3VNtyO.sOK1CdVut0ShErVXIODi6u7FQAhUbju', 0, 0, 'pending', 7191.00, NULL, 'user', NULL, '2026-08-03 19:22:03'),
(21, 'Bhoomi Jarwani', '+91', '7227071270', NULL, 298381, '$2y$10$l5C9wqBsFUijxjyd.tBehec0oWZa41I0JAqbfi2cg2M/NLJPUIjvS', 0, 1, 'complete', 9285.00, '1234', 'user', NULL, '2025-11-24 05:11:15'),
(3, 'god', '+91', '7227071273', NULL, 759636, '$2y$10$HGI9VwywkymOQ26ikMc2LuBFp8F6m3vGr4tYbS0zfYWIYTgTh/mgO', 0, 1, 'complete', 1067.00, '1234', 'user', NULL, '2025-11-23 21:43:14'),
(4, 'Bhoomi Jarwani', '+91', '7227071279', NULL, 456489, '$2y$10$xBlitU6zNLenR8uzIr8zveEz/jGxKzniA35P2vTZPfZKwPqcXyl.C', 0, 1, 'complete', 976.00, '1234', 'user', NULL, '2025-11-23 22:03:19'),
(5, 'Test Recipient', '+91', '9876543210', NULL, 0, '$2y$10$XA2rgCfcBb4xm08YtfiGvu6cSqhR/SVudupvXgH2zhv7B9Z6GpjP6', 0, 1, 'complete', 500.00, '1234', 'user', NULL, '2025-11-23 22:15:08'),
(6, 'MSEB', '+91', 'provider_mseb', NULL, 0, NULL, 0, 0, 'pending', 1861.00, '1234', 'service_provider', 'mseb@billpay.com', '2025-11-23 23:44:40');

-- --------------------------------------------------------

--
-- Table structure for table `linked_banks`
--

CREATE TABLE `linked_banks` (
  `id` int(11) NOT NULL,
  `user_phone` varchar(15) NOT NULL,
  `bank_name` varchar(100) NOT NULL,
  `last_6_digits` varchar(6) NOT NULL,
  `expiry_date` varchar(7) DEFAULT NULL,
  `atm_pin` varchar(4) DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `recharges`
--

CREATE TABLE `recharges` (
  `id` int(11) NOT NULL,
  `transaction_id` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `mobile_number` varchar(15) NOT NULL,
  `country_code` varchar(10) NOT NULL,
  `operator` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('pending','completed','failed') DEFAULT 'completed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `referral_codes`
--

CREATE TABLE `referral_codes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `referral_code` varchar(20) NOT NULL,
  `earnings` decimal(10,2) DEFAULT 0.00,
  `total_referrals` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `referral_codes`
--

INSERT INTO `referral_codes` (`id`, `user_id`, `referral_code`, `earnings`, `total_referrals`, `created_at`) VALUES
(9, 2147483647, 'PAYS507348', 0.00, 0, '2025-11-24 01:50:56'),
(10, 4, 'PAYS489734', 0.00, 0, '2025-11-24 01:56:04'),
(11, 1, 'TEST7190', 50.00, 1, '2025-11-24 01:59:31'),
(12, 7, 'PAYS564510', 0.00, 0, '2025-11-24 02:09:39'),
(13, 3, 'PAYS100357', 50.00, 1, '2025-11-24 03:26:04');

-- --------------------------------------------------------

--
-- Table structure for table `referral_transactions`
--

CREATE TABLE `referral_transactions` (
  `id` int(11) NOT NULL,
  `referrer_user_id` int(11) NOT NULL,
  `referred_user_id` int(11) NOT NULL,
  `referral_code` varchar(20) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `transaction_type` enum('referral_bonus','signup_bonus') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `referral_transactions`
--

INSERT INTO `referral_transactions` (`id`, `referrer_user_id`, `referred_user_id`, `referral_code`, `amount`, `transaction_type`, `created_at`) VALUES
(1, 1, 2, 'TEST7190', 50.00, 'signup_bonus', '2025-11-24 01:59:31'),
(2, 1, 2, 'TEST7190', 50.00, 'referral_bonus', '2025-11-24 01:59:31'),
(3, 3, 18, 'PAYS100357', 50.00, 'referral_bonus', '2025-11-24 03:33:58');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(10) UNSIGNED NOT NULL,
  `transaction_id` varchar(50) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `country_code` varchar(8) DEFAULT NULL,
  `phone_number` varchar(32) DEFAULT NULL,
  `tx_type` varchar(32) DEFAULT NULL,
  `counterparty` varchar(255) DEFAULT NULL,
  `amount` decimal(14,2) DEFAULT 0.00,
  `currency` varchar(8) DEFAULT 'INR',
  `direction` enum('in','out','none') DEFAULT 'none',
  `balance_before` decimal(14,2) DEFAULT NULL,
  `balance_after` decimal(14,2) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `reference_id` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `transaction_id`, `user_id`, `country_code`, `phone_number`, `tx_type`, `counterparty`, `amount`, `currency`, `direction`, `balance_before`, `balance_after`, `note`, `created_at`, `reference_id`) VALUES
(46, 'TXN17639358089045', 0, NULL, '7227071273', 'debit', 'TXN17639358082159', -100.00, 'INR', 'out', NULL, 1016.00, 'Payment to ', '2025-11-24 03:40:08', 'TXN17639358082159'),
(47, 'TXN17639358081143', 0, NULL, NULL, 'credit', 'TXN17639358082159', 100.00, 'INR', 'in', NULL, 100.00, 'Payment from 7227071273', '2025-11-24 03:40:08', 'TXN17639358082159'),
(48, 'TXN17639358382922', 0, NULL, '7227071273', 'debit', 'TXN17639358385739', -100.00, 'INR', 'out', NULL, 916.00, 'Payment to ', '2025-11-24 03:40:38', 'TXN17639358385739'),
(49, 'TXN17639358387510', 0, NULL, NULL, 'credit', 'TXN17639358385739', 100.00, 'INR', 'in', NULL, 100.00, 'Payment from 7227071273', '2025-11-24 03:40:38', 'TXN17639358385739'),
(50, 'TXN17639387234771', 0, NULL, '9999999999', 'debit', 'TXN17639387238299', -500.00, 'INR', 'out', NULL, 4500.00, 'Payment to 8888888888', '2025-11-24 04:28:43', 'TXN17639387238299'),
(51, 'TXN17639387234102', 0, NULL, '8888888888', 'credit', 'TXN17639387238299', 500.00, 'INR', 'in', NULL, 1500.00, 'Payment from 9999999999', '2025-11-24 04:28:43', 'TXN17639387238299'),
(52, 'TXN17639392142144', 0, NULL, '9999999999', 'debit', 'TXN17639392145236', -500.00, 'INR', 'out', NULL, 4500.00, 'Payment to 8888888888', '2025-11-24 04:36:54', 'TXN17639392145236'),
(53, 'TXN17639392145208', 0, NULL, '8888888888', 'credit', 'TXN17639392145236', 500.00, 'INR', 'in', NULL, 1500.00, 'Payment from 9999999999', '2025-11-24 04:36:54', 'TXN17639392145236'),
(54, 'TXN17639397163420', 0, NULL, '9999999999', 'debit', 'TXN17639397163896', -500.00, 'INR', 'out', NULL, 4500.00, 'Payment to 8888888888', '2025-11-24 04:45:16', 'TXN17639397163896'),
(55, 'TXN17639397165012', 0, NULL, '8888888888', 'credit', 'TXN17639397163896', 500.00, 'INR', 'in', NULL, 1500.00, 'Payment from 9999999999', '2025-11-24 04:45:16', 'TXN17639397163896'),
(56, 'TXN17639398482525', 0, NULL, '7227071273', 'debit', 'TXN17639398482029', -100.00, 'INR', 'out', NULL, 816.00, 'Payment to 7227071279', '2025-11-24 04:47:28', 'TXN17639398482029'),
(57, 'TXN17639398486084', 0, NULL, '7227071279', 'credit', 'TXN17639398482029', 100.00, 'INR', 'in', NULL, 2808.00, 'Payment from 7227071273', '2025-11-24 04:47:28', 'TXN17639398482029'),
(58, 'TXN17639405397953', 0, NULL, '1', 'money_added', NULL, 100.00, 'INR', 'in', NULL, 100.00, 'Adding test funds', '2025-11-24 04:58:59', NULL),
(59, 'TXN17639405686295', 0, NULL, '1', 'money_added', NULL, 100.00, 'INR', 'in', NULL, 100.00, 'Adding test funds', '2025-11-24 04:59:28', NULL),
(60, 'TXN17639406684928', 0, NULL, '8888888888', 'money_added', NULL, 100.00, 'INR', 'in', NULL, 1600.00, 'Adding test funds', '2025-11-24 05:01:08', NULL),
(61, 'TXN17639406681879', 0, NULL, '8888888888', 'mobile_recharge', 'TEST1763940668', -10.00, 'INR', 'out', NULL, 1590.00, 'Mobile recharge - Jio (9876543210)', '2025-11-24 05:01:08', 'TEST1763940668'),
(62, 'TXN17639414804367', 0, NULL, 'provider_mseb', 'initial', NULL, 0.00, 'INR', 'in', NULL, 0.00, 'Service provider account created', '2025-11-24 05:14:40', NULL),
(63, 'TXN17639414804279', 0, NULL, '7227071279', 'bill_payment', 'BIL4DF2E343F2B3', -1509.00, 'INR', 'out', NULL, 1299.00, 'Bill Pay: electricity - MSEB (1234567890)', '2025-11-24 05:14:40', 'BIL4DF2E343F2B3'),
(64, 'TXN17639414804403', 0, NULL, 'provider_mseb', 'bill_collection', 'BIL4DF2E343F2B3', 1509.00, 'INR', 'in', NULL, 1509.00, 'Bill Payment received - 1234567890 (MSEB)', '2025-11-24 05:14:40', 'BIL4DF2E343F2B3'),
(65, 'TXN17639416805761', 0, NULL, '7227071279', 'debit', 'TXN17639416803778', -100.00, 'INR', 'out', NULL, 1199.00, 'Payment to 7227071273', '2025-11-24 05:18:00', 'TXN17639416803778'),
(66, 'TXN17639416803283', 0, NULL, '7227071273', 'credit', 'TXN17639416803778', 100.00, 'INR', 'in', NULL, 916.00, 'Payment from 7227071279', '2025-11-24 05:18:00', 'TXN17639416803778'),
(67, 'TXN17639417452863', 0, NULL, '7227071279', 'debit', 'TXN17639417456141', -100.00, 'INR', 'out', NULL, 1099.00, 'Payment to 7227071273', '2025-11-24 05:19:05', 'TXN17639417456141'),
(68, 'TXN17639417459152', 0, NULL, '7227071273', 'credit', 'TXN17639417456141', 100.00, 'INR', 'in', NULL, 1016.00, 'Payment from 7227071279', '2025-11-24 05:19:05', 'TXN17639417456141'),
(69, 'TXN17639464014570', 0, NULL, '7227071279', 'bill_payment', 'BIL94B537AB8887', -352.00, 'INR', 'out', NULL, 747.00, 'Bill Pay: electricity - MSEB (123434566)', '2025-11-24 06:36:41', 'BIL94B537AB8887'),
(70, 'TXN17639464015582', 0, NULL, 'provider_mseb', 'bill_collection', 'BIL94B537AB8887', 352.00, 'INR', 'in', NULL, 1861.00, 'Bill Payment received - 123434566 (MSEB)', '2025-11-24 06:36:41', 'BIL94B537AB8887'),
(71, 'TXN17639464336293', 0, NULL, '7227071279', 'debit', 'TXN17639464331719', -1.00, 'INR', 'out', NULL, 746.00, 'Payment to 7227071273', '2025-11-24 06:37:13', 'TXN17639464331719'),
(72, 'TXN17639464337123', 0, NULL, '7227071273', 'credit', 'TXN17639464331719', 1.00, 'INR', 'in', NULL, 1017.00, 'Payment from 7227071279', '2025-11-24 06:37:13', 'TXN17639464331719'),
(73, 'TXN17639495717060', 0, NULL, '2', 'signup_bonus', 'TEST7190', 50.00, 'INR', 'in', NULL, 50.00, 'Welcome bonus for using referral code: TEST7190', '2025-11-24 07:29:31', 'TEST7190'),
(74, 'TXN17639495719307', 0, NULL, '1', 'referral_bonus', 'TEST7190', 50.00, 'INR', 'in', NULL, 50.00, 'Referral bonus for code: TEST7190', '2025-11-24 07:29:31', 'TEST7190'),
(75, 'TXN17639498891832', 0, NULL, '9999999999', 'sent', '8888888888', -10.00, 'INR', 'out', NULL, 4490.00, 'Test Transfer', '2025-11-24 07:34:49', '8888888888'),
(76, 'TXN17639498895480', 0, NULL, '8888888888', 'received', '9999999999', 10.00, 'INR', 'in', NULL, 1600.00, 'Test Transfer', '2025-11-24 07:34:49', '9999999999'),
(77, 'TXN17639499343215', 0, NULL, '0000000000', 'received', NULL, 100.00, 'INR', 'in', NULL, 100.00, 'Test', '2025-11-24 07:35:34', NULL),
(78, 'TXN17639510725158', 0, NULL, '1234567800', 'debit', 'TXN17639510722347', -100.00, 'INR', 'out', NULL, 4805.00, 'Payment to 7227071279', '2025-11-24 07:54:32', 'TXN17639510722347'),
(79, 'TXN17639510726908', 0, NULL, '7227071279', 'credit', 'TXN17639510722347', 100.00, 'INR', 'in', NULL, 846.00, 'Payment from 1234567800', '2025-11-24 07:54:32', 'TXN17639510722347'),
(80, 'TXN17639517817482', 0, NULL, '9999999999', 'debit', 'TXN17639517814107', -10.00, 'INR', 'out', NULL, 4480.00, 'Payment to 8888888888', '2025-11-24 08:06:21', 'TXN17639517814107'),
(81, 'TXN17639517812246', 0, NULL, '8888888888', 'credit', 'TXN17639517814107', 10.00, 'INR', 'in', NULL, 1610.00, 'Payment from 9999999999', '2025-11-24 08:06:21', 'TXN17639517814107'),
(82, 'TXN17639524662701', 0, NULL, '9999999999', 'debit', NULL, -10.00, 'INR', 'out', NULL, 4470.00, 'Payment to 8888888888', '2025-11-24 08:17:46', 'TXN17639524669269'),
(83, 'TXN17639524662092', 0, NULL, '8888888888', 'credit', NULL, 10.00, 'INR', 'in', NULL, 1620.00, 'Payment from 9999999999', '2025-11-24 08:17:46', 'TXN17639524669269'),
(84, 'TXN17639535624967', 0, NULL, '9999999999', 'debit', NULL, -10.00, 'INR', 'out', NULL, 4460.00, 'Payment to 8888888888', '2025-11-24 08:36:02', 'TXN17639535624485'),
(85, 'TXN17639535626074', 0, NULL, '8888888888', 'credit', NULL, 10.00, 'INR', 'in', NULL, 1630.00, 'Payment from 9999999999', '2025-11-24 08:36:02', 'TXN17639535624485'),
(86, 'TXN17639536852077', 0, NULL, '9999999999', 'debit', NULL, -10.00, 'INR', 'out', NULL, 4450.00, 'Payment to 8888888888', '2025-11-24 08:38:05', 'TXN17639536854051'),
(87, 'TXN17639536852742', 0, NULL, '8888888888', 'credit', NULL, 10.00, 'INR', 'in', NULL, 1640.00, 'Payment from 9999999999', '2025-11-24 08:38:05', 'TXN17639536854051'),
(88, 'TXN17639552388886', 2147483647, NULL, '2345678900', 'signup_bonus', NULL, 50.00, 'INR', 'in', NULL, 8884.00, 'Welcome bonus for using referral code: PAYS100357', '2025-11-24 09:03:58', 'PAYS100357'),
(89, 'TXN17639552388013', 2147483647, NULL, '7227071273', 'referral_bonus', NULL, 50.00, 'INR', 'in', NULL, 1067.00, 'Referral bonus for code: PAYS100357', '2025-11-24 09:03:58', 'PAYS100357'),
(90, 'TXN17639578361680', 2147483647, NULL, '7227071273', 'debit', NULL, -100.00, 'INR', 'out', NULL, 967.00, 'Payment to 7227071279', '2025-11-24 09:47:16', 'TXN17639578365829'),
(91, 'TXN17639578364003', 2147483647, NULL, '7227071279', 'credit', NULL, 100.00, 'INR', 'in', NULL, 946.00, 'Payment from 7227071273', '2025-11-24 09:47:16', 'TXN17639578365829'),
(92, 'TXN17639593441045', 2147483647, NULL, '7227071273', 'debit', NULL, -10.00, 'INR', 'out', NULL, 957.00, 'Payment to 7227071279', '2025-11-24 10:12:24', 'TXN17639593443782'),
(93, 'TXN17639593443067', 2147483647, NULL, '7227071279', 'credit', NULL, 10.00, 'INR', 'in', NULL, 956.00, 'Payment from 7227071273', '2025-11-24 10:12:24', 'TXN17639593443782'),
(94, 'TXN17639692597193', 1234567899, NULL, '1234567899', 'debit', NULL, -10.00, 'INR', 'out', NULL, 8850.00, 'Payment to 7227071279', '2025-11-24 12:57:39', 'TXN17639692596246'),
(95, 'TXN17639692597567', 2147483647, NULL, '7227071279', 'credit', NULL, 10.00, 'INR', 'in', NULL, 966.00, 'Payment from 1234567899', '2025-11-24 12:57:39', 'TXN17639692596246'),
(96, 'TXN17639692839877', 1234567899, NULL, '1234567899', 'debit', NULL, -100.00, 'INR', 'out', NULL, 8750.00, 'Payment to 7227071273', '2025-11-24 12:58:03', 'TXN17639692835295'),
(97, 'TXN17639692837714', 2147483647, NULL, '7227071273', 'credit', NULL, 100.00, 'INR', 'in', NULL, 1057.00, 'Payment from 1234567899', '2025-11-24 12:58:03', 'TXN17639692835295'),
(98, 'TXN17639694824487', 1234567899, NULL, '1234567899', 'debit', NULL, -10.00, 'INR', 'out', NULL, 8740.00, 'Payment to 7227071279', '2025-11-24 13:01:22', 'TXN17639694821935'),
(99, 'TXN17639694823167', 2147483647, NULL, '7227071279', 'credit', NULL, 10.00, 'INR', 'in', NULL, 976.00, 'Payment from 1234567899', '2025-11-24 13:01:22', 'TXN17639694821935'),
(100, 'TXN17639698538219', 2147483647, NULL, '8888888888', 'bill_payment', NULL, -1036.00, 'INR', 'out', NULL, 604.00, 'Bill Pay: electricity', '2025-11-24 13:07:33', 'BILF03F195909D4'),
(101, 'TXN17639699106842', 1234567899, NULL, '1234567899', 'debit', NULL, -10.00, 'INR', 'out', NULL, 8730.00, 'Payment to 7227071273', '2025-11-24 13:08:30', 'TXN17639699108586'),
(102, 'TXN17639699103592', 2147483647, NULL, '7227071273', 'credit', NULL, 10.00, 'INR', 'in', NULL, 1067.00, 'Payment from 1234567899', '2025-11-24 13:08:30', 'TXN17639699108586');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `account_balances`
--
ALTER TABLE `account_balances`
  ADD PRIMARY KEY (`country_code`,`phone_number`);

--
-- Indexes for table `bank_balances`
--
ALTER TABLE `bank_balances`
  ADD PRIMARY KEY (`country_code`,`phone_number`,`bank_last6`),
  ADD KEY `country_code` (`country_code`,`phone_number`);

--
-- Indexes for table `bank_details`
--
ALTER TABLE `bank_details`
  ADD PRIMARY KEY (`country_code`,`phone_number`,`bank_name`);

--
-- Indexes for table `bill_payments`
--
ALTER TABLE `bill_payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transaction_id` (`transaction_id`),
  ADD KEY `idx_user_created` (`user_phone`,`created_at`),
  ADD KEY `idx_bill_type` (`bill_type`),
  ADD KEY `idx_transaction_id` (`transaction_id`);

--
-- Indexes for table `create_account`
--
ALTER TABLE `create_account`
  ADD PRIMARY KEY (`country_code`,`phone_number`),
  ADD UNIQUE KEY `id` (`id`),
  ADD UNIQUE KEY `vpa` (`vpa`);

--
-- Indexes for table `linked_banks`
--
ALTER TABLE `linked_banks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_phone` (`user_phone`);

--
-- Indexes for table `recharges`
--
ALTER TABLE `recharges`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transaction_id` (`transaction_id`),
  ADD KEY `idx_user_created` (`user_id`,`created_at`),
  ADD KEY `idx_mobile` (`mobile_number`),
  ADD KEY `idx_transaction_id` (`transaction_id`);

--
-- Indexes for table `referral_codes`
--
ALTER TABLE `referral_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `referral_code` (`referral_code`);

--
-- Indexes for table `referral_transactions`
--
ALTER TABLE `referral_transactions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `country_code` (`country_code`,`phone_number`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bill_payments`
--
ALTER TABLE `bill_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `create_account`
--
ALTER TABLE `create_account`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `linked_banks`
--
ALTER TABLE `linked_banks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `recharges`
--
ALTER TABLE `recharges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `referral_codes`
--
ALTER TABLE `referral_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `referral_transactions`
--
ALTER TABLE `referral_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=103;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
