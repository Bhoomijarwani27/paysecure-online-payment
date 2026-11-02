<?php
session_start();
include_once 'connect.php';
require_once 'balance_manager.php';

// Get user info
$phone = $_SESSION['phone_number'] ?? '';
$country_code = $_SESSION['country_code'] ?? '+91';
$user_id = $phone; // Use phone number as user ID

if (!$user_id) {
    // Redirect to login if not logged in
    header('Location: login.html');
    exit();
}

// Get name from session or database
$name = $_SESSION['name'] ?? 'User';
if ($name === 'User' && !empty($user_id)) {
    // Fetch name from database if not in session
    $stmt = $conn->prepare("SELECT name FROM create_account WHERE phone_number = ?");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $name = $row['name'] ?? 'User';
        $_SESSION['name'] = $name; // Store in session for future use
    }
    $stmt->close();
}

// Initialize BalanceManager
$balanceManager = new BalanceManager($conn);

// Get real balance
$balance = $balanceManager->getCurrentBalance($user_id);

// Get recent transactions
$transactions = $balanceManager->getTransactionHistory($user_id, 5);

// Get linked banks from database (real data, not demo)
$banks = [];
if (!empty($user_id) && isset($conn)) {
    try {
        // Debug: Log what we're searching for
        error_log("Fetching banks for user_id: $user_id, country_code: $country_code");
        
        // Try with country_code first, then without
        $stmt = $conn->prepare("SELECT bank_name as name, last_6_digits as account, is_verified as verified FROM bank_details WHERE country_code = ? AND phone_number = ?");
        if ($stmt) {
            $stmt->bind_param('ss', $country_code, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            error_log("Query 1 (with country_code) returned: " . $result->num_rows . " rows");
            
            if ($result->num_rows === 0) {
                // Try without country_code
                $stmt->close();
                $stmt = $conn->prepare("SELECT bank_name as name, last_6_digits as account, is_verified as verified FROM bank_details WHERE phone_number = ?");
                $stmt->bind_param('s', $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                error_log("Query 2 (without country_code) returned: " . $result->num_rows . " rows");
            }
            
            while ($row = $result->fetch_assoc()) {
                $banks[] = [
                    'name' => $row['name'],
                    'account' => '****' . $row['account'],
                    'verified' => (bool)$row['verified']
                ];
                error_log("Added bank: " . $row['name']);
            }
            $stmt->close();
        }
        
        error_log("Total banks loaded: " . count($banks));
    } catch (Exception $e) {
        error_log("Error fetching banks: " . $e->getMessage());
    }
}

// Count unread notifications (transactions from last 24 hours)
$unreadCount = 0;
if (!empty($transactions)) {
    foreach ($transactions as $tx) {
        $txTime = strtotime($tx['created_at']);
        if ($txTime > (time() - 86400)) { // Last 24 hours
            $unreadCount++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PaySecure - Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #42A5F5 0%, #1976D2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container { max-width: 1400px; margin: 0 auto; }
        
        /* Header */
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 20px 30px;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            overflow: visible;
            position: relative;
            z-index: 100;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 24px;
            font-weight: 700;
        }
        
        .logo i { color: #1976D2; font-size: 28px; }
        .logo .pay { color: #000000; }
        .logo .secure { color: #1976D2; }
        
        .search-bar {
            flex: 1;
            max-width: 500px;
            position: relative;
        }
        
        .search-bar input {
            width: 100%;
            padding: 12px 45px 12px 20px;
            border: 1px solid #e0e0e0;
            border-radius: 25px;
            font-size: 14px;
            outline: none;
        }
        
        .search-bar input:focus {
            border-color: #1976D2;
        }
        
        .search-bar i {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }
        
        .notification-btn {
            position: relative;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        
        .notification-btn:hover {
            background: #e0e0e0;
        }
        
        .notification-btn .badge {
            position: absolute;
            top: -2px;
            right: -2px;
            background: #f44336;
            color: white;
            border-radius: 10px;
            padding: 2px 6px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .profile-section {
            position: relative;
            display: flex;
            align-items: center;
            gap: 15px;
            overflow: visible;
        }
        
        .profile-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            border-radius: 12px;
            cursor: pointer;
            background: #f5f5f5;
            border: none;
        }
        
        .profile-btn:hover { background: #e0e0e0; }
        
        .profile-info {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }
        
        .profile-name { 
            font-weight: 600; 
            color: #333;
            font-size: 14px;
        }
        
        .profile-subtitle {
            font-size: 11px;
            color: #666;
        }
        
        .profile-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #1976D2;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        
        .profile-name { font-weight: 600; color: #333; }
        
        /* Dropdown */
        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            width: 220px;
            margin-top: 8px;
            display: none;
            overflow: hidden;
            z-index: 9999;
            border: 1px solid #e0e0e0;
        }
        
        .dropdown-menu.show { 
            display: block;
            animation: slideDown 0.2s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .dropdown-item {
            padding: 14px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            color: #333;
            text-decoration: none;
            transition: all 0.2s;
            border-bottom: 1px solid #f5f5f5;
        }
        
        .dropdown-item:last-child {
            border-bottom: none;
        }
        
        .dropdown-item:hover { 
            background: #f8f9fa;
            padding-left: 24px;
        }
        
        .dropdown-item i { 
            width: 20px;
            font-size: 15px;
        }
        
        /* Main Content */
        .main-content {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 24px;
        }
        
        /* Balance Card */
        .balance-card {
            background: linear-gradient(135deg, #42A5F5 0%, #1976D2 100%);
            backdrop-filter: blur(10px);
            padding: 35px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(25, 118, 210, 0.4);
            margin-bottom: 24px;
            color: white;
        }
        
        .balance-card h3 {
            color: rgba(255, 255, 255, 0.9);
            font-size: 15px;
            margin-bottom: 12px;
            font-weight: 500;
        }
        
        .balance-amount {
            font-size: 42px;
            font-weight: 700;
            color: white;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .add-money-btn {
            margin-top: 15px;
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid white;
            color: white;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .add-money-btn:hover {
            background: white;
            color: #1976D2;
        }
        
        /* Quick Actions */
        .quick-actions {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .quick-actions h2 {
            margin-bottom: 20px;
            color: #333;
        }
        
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }
        
        .action-card {
            background: white;
            padding: 24px 20px;
            border-radius: 16px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
        }
        
        .action-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, #42A5F5 0%, #1976D2 100%);
            opacity: 0;
            transition: opacity 0.3s;
            z-index: 0;
        }
        
        .action-card:hover::before {
            opacity: 0.05;
        }
        
        .action-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 35px rgba(25, 118, 210, 0.3);
            border-color: #1976D2;
        }
        
        .action-card i {
            font-size: 36px;
            margin-bottom: 12px;
            background: linear-gradient(135deg, #42A5F5 0%, #1976D2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            position: relative;
            z-index: 1;
        }
        
        .action-card h3 {
            font-size: 16px;
            color: #333;
            margin-bottom: 5px;
            font-weight: 600;
            position: relative;
            z-index: 1;
        }
        
        .action-card p {
            font-size: 13px;
            color: #666;
            position: relative;
            z-index: 1;
        }
        
        /* Sidebar */
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }
        
        .sidebar-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 20px;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .sidebar-card h3 {
            margin-bottom: 15px;
            color: #333;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .card-header h3 {
            margin: 0;
        }
        
        .transaction-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .transaction-item:last-child {
            border-bottom: none;
        }
        
        .tx-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: white;
        }
        
        .tx-icon.credit { background: #4CAF50; }
        .tx-icon.debit { background: #FF9800; }
        
        .tx-details {
            flex: 1;
        }
        
        .tx-title {
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        .tx-time {
            font-size: 12px;
            color: #999;
        }
        
        .tx-amount {
            font-weight: 700;
            font-size: 15px;
        }
        
        .tx-amount.positive { color: #4CAF50; }
        .tx-amount.negative { color: #FF5722; }
        
        .bank-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .bank-item:last-child {
            border-bottom: none;
        }
        
        .bank-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: #E3F2FD;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1976D2;
        }
        
        .bank-details {
            flex: 1;
        }
        
        .bank-name {
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        .bank-account {
            font-size: 12px;
            color: #666;
        }
        
        /* Profile Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10000;
        }
        
        .modal-overlay.show {
            display: flex;
        }
        
        .profile-modal {
            background: white;
            border-radius: 20px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        
        .modal-header {
            padding: 20px 30px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 {
            margin: 0;
            font-size: 20px;
            color: #333;
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            color: #999;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
        }
        
        .close-btn:hover {
            color: #333;
        }
        
        .modal-body {
            padding: 30px;
            text-align: center;
        }
        
        .profile-avatar-large {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #42A5F5 0%, #1976D2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            font-weight: 700;
            margin: 0 auto 15px;
        }
        
        .profile-user-name {
            font-size: 22px;
            margin: 0 0 5px 0;
            color: #333;
        }
        
        .profile-email {
            color: #666;
            margin: 0 0 25px 0;
        }
        
        .profile-info-grid {
            text-align: left;
            margin-bottom: 25px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f5f5f5;
        }
        
        .info-label {
            color: #666;
            font-size: 14px;
        }
        
        .info-value {
            color: #333;
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .copy-btn {
            background: #E3F2FD;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            color: #1976D2;
        }
        
        .copy-btn:hover {
            background: #BBDEFB;
        }
        
        .qr-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        
        .qr-section h4 {
            margin: 0 0 15px 0;
            color: #1976D2;
        }
        
        .qr-code-container {
            background: white;
            padding: 15px;
            border-radius: 8px;
            display: inline-block;
            margin-bottom: 10px;
        }
        
        .qr-hint {
            font-size: 12px;
            color: #666;
            margin: 10px 0 15px 0;
        }
        
        .qr-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        .btn-primary, .btn-secondary {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: #1976D2;
            color: white;
        }
        
        .btn-primary:hover {
            background: #1565C0;
        }
        
        .btn-secondary {
            background: white;
            color: #1976D2;
            border: 2px solid #1976D2;
        }
        
        .btn-secondary:hover {
            background: #E3F2FD;
        }
        
        .profile-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-edit, .btn-security {
            flex: 1;
            padding: 12px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 600;
        }
        
        .btn-edit {
            background: #1976D2;
            color: white;
        }
        
        .btn-edit:hover {
            background: #1565C0;
        }
        
        .btn-security {
            background: white;
            color: #1976D2;
            border: 2px solid #1976D2;
        }
        
        .btn-security:hover {
            background: #E3F2FD;
        }
        
        /* Notifications Modal */
        .notifications-modal {
            background: white;
            border-radius: 20px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        
        .notif-actions {
            padding: 15px 30px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .mark-read-btn {
            background: #E3F2FD;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            color: #1976D2;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
        }
        
        .mark-read-btn:hover {
            background: #BBDEFB;
        }
        
        .notif-list {
            padding: 10px 0;
        }
        
        .notif-item {
            padding: 15px 30px;
            display: flex;
            align-items: center;
            gap: 15px;
            border-bottom: 1px solid #f5f5f5;
            cursor: pointer;
            transition: background 0.2s;
            position: relative;
        }
        
        .notif-item:hover {
            background: #f8f9fa;
        }
        
        .notif-item.unread {
            background: #F0F7FF;
        }
        
        .notif-icon {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
        }
        
        .notif-icon.success { background: #4CAF50; }
        .notif-icon.warning { background: #FF9800; }
        .notif-icon.error { background: #F44336; }
        .notif-icon.info { background: #2196F3; }
        
        .notif-content {
            flex: 1;
        }
        
        .notif-title {
            font-weight: 600;
            color: #333;
            font-size: 15px;
            margin-bottom: 3px;
        }
        
        .notif-message {
            font-size: 13px;
            color: #666;
            margin-bottom: 3px;
        }
        
        .notif-time {
            font-size: 12px;
            color: #999;
        }
        
        .notif-amount {
            font-weight: 700;
            font-size: 16px;
        }
        
        .notif-amount.positive { color: #4CAF50; }
        .notif-amount.negative { color: #F44336; }
        
        .notif-badge {
            position: absolute;
            right: 30px;
            top: 50%;
            transform: translateY(-50%);
            width: 8px;
            height: 8px;
            background: #1976D2;
            border-radius: 50%;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="logo">
                <i class="fas fa-lock"></i>
                <div><span class="pay">pay</span><span class="secure">secure</span></div>
            </div>
            
            <div class="search-bar">
                <input type="text" id="searchInput" placeholder="Search payments, contacts..." onkeyup="handleSearch(event)">
                <i class="fas fa-search"></i>
            </div>
            
            <div class="profile-section">
                <div class="notification-btn" onclick="toggleNotifications()">
                    <i class="fas fa-bell"></i>
                    <?php if ($unreadCount > 0): ?>
                        <span class="badge"><?php echo $unreadCount; ?></span>
                    <?php endif; ?>
                </div>
                
                <button class="profile-btn" onclick="toggleDropdown()">
                    <div class="profile-avatar"><?php echo strtoupper(substr($name, 0, 1)); ?></div>
                    <div class="profile-info">
                        <span class="profile-name"><?php echo htmlspecialchars($name); ?></span>
                        <span class="profile-subtitle">Welcome back</span>
                    </div>
                    <i class="fas fa-chevron-down"></i>
                </button>
                
                <div class="dropdown-menu" id="profileDropdown">
                    <a href="#" class="dropdown-item" onclick="openProfileModal(); return false;">
                        <i class="fas fa-user"></i>
                        <span>My Profile</span>
                    </a>
                    <a href="transaction_history.php" class="dropdown-item">
                        <i class="fas fa-history"></i>
                        <span>Transaction History</span>
                    </a>
                    <a href="settings.php" class="dropdown-item">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                    <a href="logout.php" class="dropdown-item" style="color: #f44336;">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </div>
        
        <div class="main-content">
            <div class="left-content">
                <!-- Balance Card -->
                <div class="balance-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <h3>Current Balance</h3>
                        <i class="fas fa-eye" id="balanceToggle" onclick="toggleBalance()" style="cursor: pointer;"></i>
                    </div>
                    <div class="balance-amount" id="balanceAmount">₹<?php echo number_format($balance, 2); ?></div>
                    <button class="add-money-btn" onclick="window.location.href='add_money.php'">
                        <i class="fas fa-plus"></i> Add Money
                    </button>
                </div>
                
                <!-- Quick Actions -->
                <div class="quick-actions">
                    <h2>Quick Actions</h2>
                    <div class="actions-grid">
                        <div class="action-card" onclick="navigate('scan_and_pay.html')">
                            <i class="fas fa-qrcode"></i>
                            <h3>Scan QR</h3>
                            <p>Pay securely</p>
                        </div>

                        <div class="action-card" onclick="navigate('bill_pay.html')">
                            <i class="fas fa-file-invoice-dollar"></i>
                            <h3>Pay Bills</h3>
                            <p>Utilities & more</p>
                        </div>

                        <div class="action-card" onclick="openRechargeModal()">
                            <i class="fas fa-mobile-alt"></i>
                            <h3>Recharge</h3>
                            <p>Mobile & DTH</p>
                        </div>
                        
                        <div class="action-card" onclick="navigate('link_with_bank.html')">
                            <i class="fas fa-university"></i>
                            <h3>Add Bank</h3>
                            <p>Link account</p>
                        </div>
                        
                        <div class="action-card" onclick="navigate('phone.html')">
                            <i class="fas fa-arrow-up"></i>
                            <h3>Send Money</h3>
                            <p>Transfer funds</p>
                        </div>
                        
                        <div class="action-card" onclick="navigate('referral.html')">
                            <i class="fas fa-gift"></i>
                            <h3>Refer & Earn</h3>
                            <p>Invite friends</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="sidebar">
                <div class="sidebar-card">
                    <div class="card-header">
                        <h3>Recent Activity</h3>
                        <a href="#" style="color: #1976D2; text-decoration: none; font-size: 13px;">View All</a>
                    </div>
                    <?php if (!empty($transactions)): ?>
                        <?php foreach ($transactions as $tx): ?>
                            <?php 
                                $type = $tx['tx_type'] ?? $tx['type'] ?? $tx['transaction_type'] ?? '';
                                $isCredit = in_array($type, ['credit', 'money_received', 'money_added', 'referral_bonus', 'signup_bonus', 'recharge_collection', 'bill_collection']);
                            ?>
                            <div class="transaction-item">
                                <div class="tx-icon <?php echo $isCredit ? 'credit' : 'debit'; ?>">
                                    <?php 
                                        $desc = $tx['note'] ?? $tx['description'] ?? 'T';
                                        echo strtoupper(substr($desc, 0, 1)); 
                                    ?>
                                </div>
                                <div class="tx-details">
                                    <div class="tx-title"><?php 
                                        $desc = $tx['note'] ?? $tx['description'] ?? 'Transaction';
                                        echo htmlspecialchars($desc); 
                                    ?></div>
                                    <div class="tx-time"><?php echo date('M d, Y', strtotime($tx['created_at'])); ?></div>
                                </div>
                                <div class="tx-amount <?php echo $isCredit ? 'positive' : 'negative'; ?>">
                                    <?php echo $isCredit ? '+' : '-'; ?>₹<?php echo number_format(abs($tx['amount']), 2); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: #666; padding: 15px 0;">No recent transactions</p>
                    <?php endif; ?>
                </div>
                
                <div class="sidebar-card">
                    <div class="card-header">
                        <h3>Linked Banks</h3>
                        <a href="#" onclick="navigate('link_with_bank.html'); return false;" style="color: #1976D2; text-decoration: none; font-size: 13px;">Add Bank</a>
                    </div>
                    
                    <?php foreach ($banks as $bank): ?>
                        <div class="bank-item">
                            <div class="bank-icon">
                                <i class="fas fa-university"></i>
                            </div>
                            <div class="bank-details">
                                <div class="bank-name"><?php echo htmlspecialchars($bank['name']); ?></div>
                                <div class="bank-account"><?php echo htmlspecialchars($bank['account']); ?></div>
                            </div>
                            <?php if ($bank['verified']): ?>
                                <i class="fas fa-check-circle" style="color: #4CAF50;"></i>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Profile Modal -->
    <div class="modal-overlay" id="profileModal" onclick="closeProfileModal()">
        <div class="profile-modal" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h2>My Profile</h2>
                <button class="close-btn" onclick="closeProfileModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body">
                <div class="profile-avatar-large">
                    <?php echo strtoupper(substr($name, 0, 1)); ?>
                </div>
                
                <h3 class="profile-user-name"><?php echo htmlspecialchars($name); ?></h3>
                <p class="profile-email"><?php echo htmlspecialchars($phone); ?>@paysecure.com</p>
                
                <div class="profile-info-grid">
                    <div class="info-row">
                        <span class="info-label">Phone Number:</span>
                        <span class="info-value"><?php echo htmlspecialchars($phone); ?></span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">UPI ID:</span>
                        <span class="info-value">
                            <?php echo htmlspecialchars($phone); ?>@paysecure
                            <button class="copy-btn" onclick="copyUPI('<?php echo htmlspecialchars($phone); ?>@paysecure')">
                                <i class="fas fa-copy"></i>
                            </button>
                        </span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">Account Type:</span>
                        <span class="info-value">Premium</span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">Member Since:</span>
                        <span class="info-value">January 2024</span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">KYC Status:</span>
                        <span class="info-value" style="color: #4CAF50;">
                            Verified <i class="fas fa-check-circle"></i>
                        </span>
                    </div>
                </div>
                
                <div class="qr-section">
                    <h4>Your UPI QR Code</h4>
                    <div class="qr-code-container">
                        <div id="qrcode"></div>
                    </div>
                    <p class="qr-hint">Share this QR code with others to receive payments instantly</p>
                    
                    <div class="qr-actions">
                        <button class="btn-primary" onclick="downloadQR()">
                            <i class="fas fa-download"></i> Download
                        </button>
                        <button class="btn-secondary" onclick="shareQR()">
                            <i class="fas fa-share-alt"></i> Share
                        </button>
                    </div>
                </div>
                
                <div class="profile-actions">
                    <button class="btn-edit" onclick="window.location.href='edit_profile.php'">
                        Edit Profile
                    </button>
                    <button class="btn-security" onclick="window.location.href='settings.php'">
                        Security Settings
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Notifications Modal -->
    <div class="modal-overlay" id="notificationsModal" onclick="closeNotificationsModal()">
        <div class="notifications-modal" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h2>Notifications</h2>
                <button class="close-btn" onclick="closeNotificationsModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="notif-actions">
                <button class="mark-read-btn" onclick="markAllRead()">
                    <i class="fas fa-check-double"></i> Mark all as read
                </button>
            </div>
            
            <div class="notif-list">
                <?php if (!empty($transactions)): ?>
                    <?php foreach ($transactions as $index => $tx): ?>
                        <?php
                            $isCredit = ($tx['direction'] === 'in');
                            $iconClass = $isCredit ? 'success' : 'error';
                            $icon = $isCredit ? 'fa-arrow-down' : 'fa-arrow-up';
                            $amountClass = $isCredit ? 'positive' : 'negative';
                            $amountPrefix = $isCredit ? '+' : '-';
                            $title = $isCredit ? 'Money Received' : 'Payment Sent';
                            $message = $isCredit ? 'From ' . htmlspecialchars($tx['counterparty']) : 'To ' . htmlspecialchars($tx['counterparty']);
                            $timeAgo = date('M d, Y', strtotime($tx['created_at']));
                            $isUnread = ($index < 2); // Mark first 2 as unread for demo
                        ?>
                        <div class="notif-item <?php echo $isUnread ? 'unread' : ''; ?>">
                            <div class="notif-icon <?php echo $iconClass; ?>">
                                <i class="fas <?php echo $icon; ?>"></i>
                            </div>
                            <div class="notif-content">
                                <div class="notif-title"><?php echo $title; ?></div>
                                <div class="notif-message"><?php echo $message; ?></div>
                                <div class="notif-time"><?php echo $timeAgo; ?></div>
                            </div>
                            <div class="notif-amount <?php echo $amountClass; ?>"><?php echo $amountPrefix; ?>₹<?php echo number_format($tx['amount'], 0); ?></div>
                            <?php if ($isUnread): ?>
                                <span class="notif-badge"></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="notif-item">
                        <div class="notif-content" style="text-align: center; padding: 20px; color: #999;">
                            <i class="fas fa-bell-slash" style="font-size: 32px; margin-bottom: 10px; opacity: 0.5;"></i>
                            <div>No notifications yet</div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Recharge Modal -->
    <div class="modal-overlay" id="rechargeModal" onclick="closeRechargeModal()">
        <div class="profile-modal" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h2>Mobile Recharge</h2>
                <button class="close-btn" onclick="closeRechargeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body" style="text-align: left;">
                <form id="rechargeForm" onsubmit="handleRecharge(event)">
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">Mobile Number</label>
                        <div style="position: relative;">
                            <i class="fas fa-mobile-alt" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #999;"></i>
                            <input type="tel" id="rechargeMobile" placeholder="Enter 10-digit number" maxlength="10" pattern="[0-9]{10}" required style="width: 100%; padding: 12px 15px 12px 40px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 15px; outline: none;">
                        </div>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">Operator</label>
                        <div style="position: relative;">
                            <i class="fas fa-broadcast-tower" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #999;"></i>
                            <select id="rechargeOperator" required style="width: 100%; padding: 12px 15px 12px 40px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 15px; outline: none; appearance: none; background: white;">
                                <option value="">Select Operator</option>
                                <option value="Jio">Jio</option>
                                <option value="Airtel">Airtel</option>
                                <option value="Vi">Vi</option>
                                <option value="BSNL">BSNL</option>
                            </select>
                            <i class="fas fa-chevron-down" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: #999; pointer-events: none;"></i>
                        </div>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">Amount</label>
                        <div style="position: relative;">
                            <span style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #333; font-weight: 600;">₹</span>
                            <input type="number" id="rechargeAmount" placeholder="0" required style="width: 100%; padding: 12px 15px 12px 30px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 15px; outline: none;">
                        </div>
                    </div>

                    <div style="margin-bottom: 25px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">UPI PIN</label>
                        <div style="position: relative;">
                            <i class="fas fa-key" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #999;"></i>
                            <input type="password" id="rechargePin" placeholder="Enter UPI PIN" maxlength="6" required style="width: 100%; padding: 12px 15px 12px 40px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 15px; outline: none;">
                        </div>
                    </div>

                    <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; padding: 14px;">
                        Proceed to Recharge
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="receipt_utils.js?v=<?php echo time(); ?>"></script>
    <script>
        // Recharge Modal Functions
        function handleRecharge(event) {
            event.preventDefault();
            
            const mobile = document.getElementById('rechargeMobile').value;
            const operator = document.getElementById('rechargeOperator').value;
            const amount = document.getElementById('rechargeAmount').value;
            const pin = document.getElementById('rechargePin').value;
            
            if (!mobile || !operator || !amount || !pin) {
                alert('Please fill all fields');
                return;
            }
            
            if (pin.length < 4) {
                alert('Please enter a valid UPI PIN');
                return;
            }
            
            // Show processing state
            const btn = event.target.querySelector('button[type="submit"]');
            const originalText = btn.textContent;
            btn.textContent = 'Processing...';
            btn.disabled = true;
            
            fetch('process_recharge.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    mobile_number: mobile,
                    operator: operator,
                    amount: amount,
                    upi_pin: pin
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show receipt instead of alert
                    if (typeof showReceipt === 'function') {
                        showReceipt({
                            success: true,
                            amount: data.amount,
                            transaction_id: data.transaction_id,
                            recipient: data.operator + ' - ' + data.mobile_number,
                            date: data.recharge_date || new Date().toLocaleString()
                        });
                        closeRechargeModal();
                    } else {
                        // Fallback if receipt script failed to load
                        alert('Recharge Successful! Transaction ID: ' + data.transaction_id);
                        closeRechargeModal();
                        window.location.reload();
                    }
                    
                    // Update balance in background without reload
                    if (data.new_balance) {
                         const balanceEl = document.getElementById('balanceAmount');
                         if (balanceEl && !balanceEl.textContent.includes('****')) {
                             balanceEl.textContent = '₹' + parseFloat(data.new_balance).toFixed(2);
                         }
                    }
                } else {
                    alert('Recharge Failed: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            })
            .finally(() => {
                btn.textContent = originalText;
                btn.disabled = false;
            });
        }

        function openRechargeModal() {
            document.getElementById('rechargeModal').classList.add('show');
        }

        function closeRechargeModal() {
            document.getElementById('rechargeModal').classList.remove('show');
        }

        function toggleDropdown() {
            var dropdown = document.getElementById('profileDropdown');
            dropdown.classList.toggle('show');
        }
        
        function navigate(page) {
            window.location.href = page;
        }
        
        // Search functionality
        function handleSearch(event) {
            const query = event.target.value.toLowerCase();
            if (event.key === 'Enter' && query) {
                alert('Searching for: ' + query + '\n\nSearch results will appear here.');
                // In future, this would search transactions, contacts, etc.
            }
        }
        
        // Notifications functionality
        function toggleNotifications() {
            const modal = document.getElementById('notificationsModal');
            modal.classList.add('show');
        }
        
        function closeNotificationsModal() {
            const modal = document.getElementById('notificationsModal');
            modal.classList.remove('show');
        }
        
        function markAllRead() {
            // Hide all notification badges
            const badges = document.querySelectorAll('.notif-badge');
            badges.forEach(badge => badge.style.display = 'none');
            
            // Remove unread styling
            const unreadItems = document.querySelectorAll('.notif-item.unread');
            unreadItems.forEach(item => item.classList.remove('unread'));
            
            // Update header badge to 0
            const headerBadge = document.querySelector('.notification-btn .badge');
            if (headerBadge) {
                headerBadge.textContent = '0';
                headerBadge.style.display = 'none';
            }
        }
        
        // Balance toggle
        let balanceVisible = true;
        const actualBalance = '₹<?php echo number_format($balance, 2); ?>';
        
        function toggleBalance() {
            const balanceEl = document.getElementById('balanceAmount');
            const toggleIcon = document.getElementById('balanceToggle');
            
            if (balanceVisible) {
                balanceEl.textContent = '₹****.**';
                toggleIcon.className = 'fas fa-eye-slash';
            } else {
                balanceEl.textContent = actualBalance;
                toggleIcon.className = 'fas fa-eye';
            }
            balanceVisible = !balanceVisible;
        }
        
        // Close dropdown when clicking outside
        window.onclick = function(event) {
            if (!event.target.matches('.profile-btn') && !event.target.closest('.profile-btn')) {
                var dropdown = document.getElementById('profileDropdown');
                if (dropdown.classList.contains('show')) {
                    dropdown.classList.remove('show');
                }
            }
        }
        
        // Profile Modal Functions
        let qrCodeInstance = null;
        
        function openProfileModal() {
            const modal = document.getElementById('profileModal');
            modal.classList.add('show');
            
            // Generate QR code if not already generated
            if (!qrCodeInstance) {
                const qrContainer = document.getElementById('qrcode');
                qrContainer.innerHTML = ''; // Clear any existing QR code
                qrCodeInstance = new QRCode(qrContainer, {
                    text: '<?php echo htmlspecialchars($phone); ?>@paysecure',
                    width: 200,
                    height: 200,
                    colorDark: '#000000',
                    colorLight: '#ffffff',
                });
            }
            
            // Close dropdown
            const dropdown = document.getElementById('profileDropdown');
            dropdown.classList.remove('show');
        }
        
        function closeProfileModal() {
            const modal = document.getElementById('profileModal');
            modal.classList.remove('show');
        }
        
        function copyUPI(upiId) {
            navigator.clipboard.writeText(upiId).then(function() {
                alert('UPI ID copied to clipboard!');
            });
        }
        
        function downloadQR() {
            const canvas = document.querySelector('#qrcode canvas');
            if (canvas) {
                const url = canvas.toDataURL('image/png');
                const link = document.createElement('a');
                link.download = 'my-upi-qr-code.png';
                link.href = url;
                link.click();
            }
        }
        
        function shareQR() {
            alert('Share functionality coming soon!');
        }
    </script>
</body>
</html>
