<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
include_once 'connect.php';

// Read user values from session (fallbacks if not present)
$name = '';
if (!empty($_SESSION['name'])) {
    $name = $_SESSION['name'];
} elseif (!empty($_SESSION['user_name'])) {
    $name = $_SESSION['user_name'];
} elseif (!empty($_SESSION['full_name'])) {
    $name = $_SESSION['full_name'];
} else {
    $name = 'User';
}



// Fallback: If name is still 'User' or empty, try to fetch from DB using phone number
if (($name === 'User' || empty($name)) && !empty($_SESSION['phone_number'])) {
    if (isset($conn)) {
        try {
            $stmtName = $conn->prepare("SELECT name FROM create_account WHERE phone_number = ? LIMIT 1");
            if ($stmtName) {
                $stmtName->bind_param('s', $_SESSION['phone_number']);
                $stmtName->execute();
                $resName = $stmtName->get_result();
                if ($rowName = $resName->fetch_assoc()) {
                    $name = $rowName['name'];
                    // Update session to avoid future DB calls
                    $_SESSION['name'] = $name;
                }
                $stmtName->close();
            }
        } catch (Exception $e) {
            // Ignore error
        }
    }
}

$country_code = $_SESSION['country_code'] ?? '';
$phone_number = $_SESSION['phone_number'] ?? '';
$email = $_SESSION['email'] ?? '';

// Fetch REAL linked banks from database (not hardcoded)
$linkedBanks = [];
if (!empty($country_code) && !empty($phone_number) && isset($conn)) {
    try {
        $stmt = $conn->prepare("SELECT bank_name, last_6_digits, is_verified FROM bank_details WHERE country_code = ? AND phone_number = ?");
        if ($stmt) {
            $stmt->bind_param('ss', $country_code, $phone_number);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $linkedBanks[] = $row;
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        error_log("Error fetching banks: " . $e->getMessage());
    }
}

// Fetch recent transactions for the logged-in user
$recentActivities = [];
if (!empty($country_code) && !empty($phone_number) && isset($conn)) {
    try {
        $stmtTx = $conn->prepare("SELECT tx_type, counterparty, amount, currency, direction, note, created_at FROM transactions WHERE country_code = ? AND phone_number = ? ORDER BY created_at DESC LIMIT 6");
        if ($stmtTx) {
            $stmtTx->bind_param('ss', $country_code, $phone_number);
            $stmtTx->execute();
            $resTx = $stmtTx->get_result();
            while ($r = $resTx->fetch_assoc()) {
                $recentActivities[] = $r;
            }
            $stmtTx->close();
        }
    } catch (Exception $e) {
        // Silently ignore
    }
}

$display_phone = trim(($country_code ? $country_code . ' ' : '') . $phone_number);

$upi_id = strtolower(preg_replace('/\s+/', '', $name)) . '@paysecure';

// Simple escaping helper for embedding into JS
function js_escape($s) {
    return str_replace(array('\\', "\n", "\r", '"', "'"), array('\\\\', '\\n', '\\r', '\\"', "\\'"), $s);
}

// Fetch balance directly from create_account table (single source of truth)
$current_balance = 0;
if (!empty($phone_number) && isset($conn)) {
    try {
        $stmt = $conn->prepare("SELECT balance FROM create_account WHERE phone_number = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $phone_number);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $current_balance = floatval($row['balance']);
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        error_log("Error fetching balance: " . $e->getMessage());
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
    <link rel="stylesheet" href="paysecure-universal.css?v=<?php echo time(); ?>">
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    
    <style>
        /* BEAUTIFUL GRADIENT BACKGROUND - Your Original Design */
        body {
            font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #42A5F5 0%, #1976D2 100%);
            min-height: 100vh;
            padding: 20px;
            margin: 0;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Page container - with proper margins */
        .page-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            box-sizing: border-box;
        }

        /* Header - Clean modern styling */
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 20px 30px;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(25, 118, 210, 0.15);
            margin: 0 40px 24px 40px !important;
            width: calc(100% - 80px) !important;
            box-sizing: border-box !important;
            min-height: 76px !important;
            border: 1px solid rgba(255, 255, 255, 0.2) !important;
            backdrop-filter: blur(10px) !important;
            transition: all 0.3s ease !important;
        }
        
        .header:hover {
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.12), 0 2px 6px rgba(0, 0, 0, 0.08) !important;
            transform: translateY(-1px) !important;
        }
        

        /* Main container - Match reference layout exactly */
        .main-container {
            max-width: 1400px !important;
            margin: 0 auto !important;
            display: grid !important;
            grid-template-columns: 1fr 380px !important;
            gap: 30px !important;
            padding: 0 !important;
            background: transparent !important;
            box-shadow: none !important;
            border: none !important;
        }

        /* Left content area - transparent background */
        .left-content {
            background: transparent !important;
            backdrop-filter: none !important;
            border-radius: 0 !important;
            padding: 0 !important;
            box-shadow: none !important;
            border: 0 !important;
            min-width: 0 !important;
        }

        /* Right sidebar - Natural grid flow */
        .sidebar {
            display: flex !important;
            flex-direction: column !important;
            gap: 25px !important;
        }
        
        /* Logo styling - Proper size for better header */
        .logo {
            display: flex !important;
            align-items: center !important;
            gap: 0 !important;
            text-decoration: none !important;
            color: inherit !important;
        }
        
        .logo i,
        .logo .fas,
        .logo .fa-lock {
            color: #1d9bf0 !important;
            font-size: 24px !important;
            margin-right: 8px !important;
        }
        
        .logo .pay {
            font-weight: 700 !important;
            font-size: 24px !important;
            color: #222 !important;
            margin-right: 0 !important;
        }
        
        .logo .secure {
            font-weight: 700 !important;
            font-size: 24px !important;
            color: #1d9bf0 !important;
            margin-left: 0 !important;
        }

        /* Search Container - Better proportions */
        .search-container {
            flex: 1 !important;
            max-width: 400px !important;
            margin: 0 16px !important;
            position: relative !important;
        }
        
        .search-input {
            width: 100% !important;
            padding: 10px 40px 10px 16px !important;
            border: 2px solid #f0f0f0 !important;
            border-radius: 20px !important;
            font-size: 14px !important;
            outline: none !important;
            transition: all 0.3s ease !important;
            background: rgba(255, 255, 255, 0.8) !important;
        }
        
        .search-input:focus {
            border-color: #1d9bf0 !important;
            box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.1) !important;
            background: white !important;
        }
        
        .search-icon {
            position: absolute !important;
            right: 15px !important;
            top: 50% !important;
            transform: translateY(-50%) !important;
            color: #666 !important;
            cursor: pointer !important;
        }
        
        /* Enhanced Search Results */
        .search-results {
            position: absolute !important;
            top: 100% !important;
            left: 0 !important;
            right: 0 !important;
            background: white !important;
            border-radius: 12px !important;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15) !important;
            z-index: 1000 !important;
            display: none !important;
            max-height: 300px !important;
            overflow-y: auto !important;
            margin-top: 8px !important;
            border: 1px solid #f0f0f0 !important;
        }
        
        .search-result-item {
            display: flex !important;
            align-items: center !important;
            gap: 12px !important;
            padding: 12px 16px !important;
            cursor: pointer !important;
            transition: background 0.2s !important;
            border-bottom: 1px solid #f8f9fa !important;
        }
        
        .search-result-item:last-child {
            border-bottom: none !important;
        }
        
        .search-result-item:hover {
            background: #f8f9fa !important;
        }
        
        .search-result-item i {
            width: 16px !important;
            color: #666 !important;
        }
        
        .result-details {
            flex: 1 !important;
        }
        
        .result-name {
            font-size: 14px !important;
            font-weight: 600 !important;
            color: #333 !important;
        }
        
        .result-subtitle {
            font-size: 12px !important;
            color: #666 !important;
        }
        
        .no-results {
            padding: 20px !important;
            text-align: center !important;
            color: #666 !important;
            font-size: 14px !important;
        }
        
        /* Profile Section */
        .profile-section {
            display: flex !important;
            align-items: center !important;
            gap: 15px !important;
        }
        
        .notifications {
            position: relative !important;
            cursor: pointer !important;
            padding: 8px !important;
            border-radius: 8px !important;
            transition: background 0.2s !important;
        }
        
        .notifications:hover {
            background: rgba(0, 0, 0, 0.05) !important;
        }
        
        .profile-btn {
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            padding: 8px 12px !important;
            border-radius: 12px !important;
            cursor: pointer !important;
            transition: background 0.2s !important;
        }
        
        .profile-btn:hover {
            background: rgba(0, 0, 0, 0.05) !important;
        }
        
        .profile-avatar {
            width: 32px !important;
            height: 32px !important;
            border-radius: 50% !important;
            background: #1d9bf0 !important;
            color: white !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-weight: 600 !important;
            font-size: 14px !important;
        }
        
        .profile-info {
            display: flex !important;
            flex-direction: column !important;
            align-items: flex-start !important;
        }
        
        .profile-name {
            font-weight: 600 !important;
            color: #333 !important;
            font-size: 13px !important;
        }
        
        .profile-subtitle {
            font-size: 11px !important;
            color: #666 !important;
        }
        
        .notifications {
            position: relative !important;
            cursor: pointer !important;
            padding: 6px !important;
            border-radius: 6px !important;
            transition: background 0.2s !important;
        }
        
        .profile-btn {
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
            padding: 4px 8px !important;
            border-radius: 8px !important;
            cursor: pointer !important;
            transition: background 0.2s !important;
        }
        
        /* Notifications Dropdown */
        .notifications {
            position: relative !important;
            cursor: pointer !important;
            padding: 8px !important;
            border-radius: 8px !important;
            transition: background 0.2s !important;
        }
        
        
        .notifications:hover {
            background: rgba(0, 0, 0, 0.05) !important;
        }
        
        /* Profile Menu Dropdown */
        .profile-menu {
            position: absolute !important;
            top: 100% !important;
            right: 0 !important;
            background: white !important;
            border-radius: 12px !important;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15) !important;
            width: 220px !important;
            z-index: 1500 !important;
            opacity: 0 !important;
            visibility: hidden !important;
            transform: translateY(-10px) !important;
            transition: all 0.3s ease !important;
            border: 1px solid #f0f0f0 !important;
            overflow: hidden !important;
            margin-top: 8px !important;
        }
        
        .profile-menu.show {
            opacity: 1 !important;
            visibility: visible !important;
            transform: translateY(0) !important;
        }
        
        .menu-item {
            padding: 12px 20px !important;
            display: flex !important;
            align-items: center !important;
            gap: 12px !important;
            cursor: pointer !important;
            transition: background 0.2s !important;
            color: #333 !important;
            text-decoration: none !important;
        }
        
        .menu-item:hover {
            background: #f5f5f5 !important;
        }
        
        .menu-item i {
            width: 20px !important;
            font-size: 16px !important;
        }
        
        .notification-badge {
            position: absolute !important;
            top: -2px !important;
            right: -2px !important;
            background: #ff4d4f !important;
            color: white !important;
            border-radius: 10px !important;
            padding: 2px 6px !important;
            font-size: 11px !important;
            font-weight: 600 !important;
            min-width: 16px !important;
            text-align: center !important;
        }
        
        .notifications-dropdown {
            position: absolute !important;
            top: 100% !important;
            right: 0 !important;
            background: white !important;
            border-radius: 12px !important;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15) !important;
            width: 320px !important;
            z-index: 1500 !important;
            opacity: 0 !important;
            visibility: hidden !important;
            transform: translateY(-10px) !important;
            transition: all 0.3s ease !important;
            border: 1px solid #f0f0f0 !important;
            overflow: hidden !important;
            margin-top: 8px !important;
        }
        
        .notifications-dropdown.show {
            opacity: 1 !important;
            visibility: visible !important;
            transform: translateY(0) !important;
        }
        
        .notifications-header {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            padding: 16px 20px !important;
            border-bottom: 1px solid #f0f0f0 !important;
        }
        
        .notifications-header h4 {
            margin: 0 !important;
            font-size: 16px !important;
            font-weight: 600 !important;
            color: #333 !important;
        }
        
        .mark-all-read {
            background: none !important;
            border: none !important;
            color: #1d9bf0 !important;
            font-size: 12px !important;
            cursor: pointer !important;
            padding: 4px 8px !important;
            border-radius: 4px !important;
            transition: background 0.2s !important;
        }
        
        .mark-all-read:hover {
            background: #f0f8ff !important;
        }
        
        .notifications-list {
            max-height: 300px !important;
            overflow-y: auto !important;
        }
        
        .notification-item {
            display: flex !important;
            align-items: flex-start !important;
            gap: 12px !important;
            padding: 16px 20px !important;
            border-bottom: 1px solid #f8f9fa !important;
            cursor: pointer !important;
            transition: background 0.2s !important;
        }
        
        .notification-item:hover {
            background: #f8f9fa !important;
        }
        
        .notification-item.unread {
            background: #f0f8ff !important;
        }
        
        .notification-icon {
            width: 32px !important;
            height: 32px !important;
            border-radius: 50% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-size: 14px !important;
            color: white !important;
        }
        
        .notification-icon.success { background: #4caf50 !important; }
        .notification-icon.info { background: #2196f3 !important; }
        .notification-icon.warning { background: #ff9800 !important; }
        .notification-icon.error { background: #f44336 !important; }
        
        .notification-content {
            flex: 1 !important;
        }
        
        .notification-title {
            font-size: 14px !important;
            font-weight: 600 !important;
            color: #333 !important;
            margin-bottom: 4px !important;
        }
        
        .notification-message {
            font-size: 13px !important;
            color: #666 !important;
            margin-bottom: 4px !important;
        }
        
        .notification-time {
            font-size: 11px !important;
            color: #999 !important;
        }
        
        .notifications-footer {
            padding: 12px 20px !important;
            border-top: 1px solid #f0f0f0 !important;
            text-align: center !important;
        }
        
        .notifications-footer a {
            color: #1d9bf0 !important;
            text-decoration: none !important;
            font-size: 13px !important;
            font-weight: 500 !important;
        }
        
        .notification-badge {
            position: absolute !important;
            top: -2px !important;
            right: -2px !important;
            background: #ff4d4f !important;
            color: white !important;
            border-radius: 10px !important;
            padding: 2px 6px !important;
            font-size: 11px !important;
            font-weight: 600 !important;
            min-width: 16px !important;
            text-align: center !important;
        }
        
        .notifications-dropdown {
            position: absolute !important;
            top: 100% !important;
            right: 0 !important;
            background: white !important;
            border-radius: 12px !important;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15) !important;
            width: 320px !important;
            z-index: 1500 !important;
            opacity: 0 !important;
            visibility: hidden !important;
            transform: translateY(-10px) !important;
            transition: all 0.3s ease !important;
            border: 1px solid #f0f0f0 !important;
            overflow: hidden !important;
            margin-top: 8px !important;
        }
        
        .notifications-dropdown.show {
            opacity: 1 !important;
            visibility: visible !important;
            transform: translateY(0) !important;
        }
        
        .notifications-header {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            padding: 16px 20px !important;
            border-bottom: 1px solid #f0f0f0 !important;
        }
        
        .notifications-header h4 {
            margin: 0 !important;
            font-size: 16px !important;
            font-weight: 600 !important;
            color: #333 !important;
        }
        
        .mark-all-read {
            background: none !important;
            border: none !important;
            color: #1d9bf0 !important;
            font-size: 12px !important;
            cursor: pointer !important;
            padding: 4px 8px !important;
            border-radius: 4px !important;
            transition: background 0.2s !important;
        }
        
        .mark-all-read:hover {
            background: #f0f8ff !important;
        }
        
        .notifications-list {
            max-height: 300px !important;
            overflow-y: auto !important;
        }
        
        .notification-item {
            display: flex !important;
            align-items: flex-start !important;
            gap: 12px !important;
            padding: 16px 20px !important;
            border-bottom: 1px solid #f8f9fa !important;
            cursor: pointer !important;
            transition: background 0.2s !important;
        }
        
        .notification-item:hover {
            background: #f8f9fa !important;
        }
        
        .notification-item.unread {
            background: #f0f8ff !important;
        }
        
        .notification-icon {
            width: 32px !important;
            height: 32px !important;
            border-radius: 50% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-size: 14px !important;
            color: white !important;
        }
        
        .notification-icon.success { background: #4caf50 !important; }
        .notification-icon.info { background: #2196f3 !important; }
        .notification-icon.warning { background: #ff9800 !important; }
        .notification-icon.error { background: #f44336 !important; }
        
        .notification-content {
            flex: 1 !important;
        }
        
        .notification-title {
            font-size: 14px !important;
            font-weight: 600 !important;
            color: #333 !important;
            margin-bottom: 4px !important;
        }
        
        .notification-message {
            font-size: 13px !important;
            color: #666 !important;
            margin-bottom: 4px !important;
        }
        
        .notification-time {
            font-size: 11px !important;
            color: #999 !important;
        }
        
        .notifications-footer {
            padding: 12px 20px !important;
            border-top: 1px solid #f0f0f0 !important;
            text-align: center !important;
        }
        
        .notifications-footer a {
            color: #1d9bf0 !important;
            text-decoration: none !important;
            font-size: 13px !important;
            font-weight: 500 !important;
        }
        
        /* Enhanced Search Results */
        .search-results {
            position: absolute !important;
            top: 100% !important;
            left: 0 !important;
            right: 0 !important;
            background: white !important;
            border-radius: 12px !important;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15) !important;
            z-index: 1000 !important;
            display: none !important;
            max-height: 300px !important;
            overflow-y: auto !important;
            margin-top: 8px !important;
            border: 1px solid #f0f0f0 !important;
        }
        
        .search-result-item {
            display: flex !important;
            align-items: center !important;
            gap: 12px !important;
            padding: 12px 16px !important;
            cursor: pointer !important;
            transition: background 0.2s !important;
            border-bottom: 1px solid #f8f9fa !important;
        }
        
        .search-result-item:last-child {
            border-bottom: none !important;
        }
        
        .search-result-item:hover {
            background: #f8f9fa !important;
        }
        
        .search-result-item i {
            width: 16px !important;
            color: #666 !important;
        }
        
        .result-details {
            flex: 1 !important;
        }
        
        .result-name {
            font-size: 14px !important;
            font-weight: 600 !important;
            color: #333 !important;
        }
        
        .result-subtitle {
            font-size: 12px !important;
            color: #666 !important;
        }
        
        .no-results {
            padding: 20px !important;
            text-align: center !important;
            color: #666 !important;
            font-size: 14px !important;
        }
        
        /* Enhanced Profile Menu */
        .profile-menu {
            position: absolute !important;
            top: 100% !important;
            right: 0 !important;
            background: white !important;
            border-radius: 12px !important;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15) !important;
            min-width: 280px !important;
            z-index: 1000 !important;
            opacity: 0 !important;
            visibility: hidden !important;
            transform: translateY(-10px) !important;
            transition: all 0.3s ease !important;
            border: 1px solid #f0f0f0 !important;
            overflow: hidden !important;
            margin-top: 8px !important;
        }
        
        .profile-menu-header {
            display: flex !important;
            align-items: center !important;
            gap: 12px !important;
            padding: 20px !important;
            background: linear-gradient(135deg, #1d9bf0, #1976d2) !important;
            color: white !important;
        }
        
        .profile-menu-avatar {
            width: 40px !important;
            height: 40px !important;
            border-radius: 50% !important;
            background: rgba(255, 255, 255, 0.2) !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-weight: 700 !important;
            font-size: 16px !important;
        }
        
        .profile-menu-info {
            flex: 1 !important;
        }
        
        .profile-menu-name {
            font-size: 16px !important;
            font-weight: 600 !important;
            margin-bottom: 2px !important;
        }
        
        .profile-menu-email {
            font-size: 13px !important;
            opacity: 0.9 !important;
        }
        
        .menu-divider {
            height: 1px !important;
            background: #f0f0f0 !important;
            margin: 0 !important;
        }
        
        /* Modal Styles */
        .profile-modal, .settings-modal, .help-modal {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 100% !important;
            z-index: 2000 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }
        
        .modal-backdrop {
            position: absolute !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 100% !important;
            background: rgba(0, 0, 0, 0.5) !important;
            backdrop-filter: blur(5px) !important;
        }
        
        .profile-container, .settings-container, .help-container {
            position: relative !important;
            background: white !important;
            border-radius: 16px !important;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2) !important;
            width: 90% !important;
            max-width: 500px !important;
            max-height: 80vh !important;
            overflow-y: auto !important;
            animation: modalSlideIn 0.3s ease !important;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0 !important;
                transform: scale(0.9) translateY(-20px) !important;
            }
            to {
                opacity: 1 !important;
                transform: scale(1) translateY(0) !important;
            }
        }
        
        .profile-header, .settings-header, .help-header {
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            padding: 24px !important;
            border-bottom: 1px solid #f0f0f0 !important;
            background: linear-gradient(135deg, #1d9bf0, #1976d2) !important;
            color: white !important;
            border-radius: 16px 16px 0 0 !important;
        }
        
        .profile-header h2, .settings-header h2, .help-header h2 {
            margin: 0 !important;
            font-size: 20px !important;
            font-weight: 600 !important;
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
        }
        
        .close-btn {
            background: none !important;
            border: none !important;
            color: white !important;
            font-size: 24px !important;
            cursor: pointer !important;
            padding: 0 !important;
            width: 32px !important;
            height: 32px !important;
            border-radius: 50% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            transition: background 0.2s !important;
        }
        
        .close-btn:hover {
            background: rgba(255, 255, 255, 0.2) !important;
        }
        
        .profile-content, .settings-content, .help-content {
            padding: 24px !important;
        }
        
        .profile-field {
            margin-bottom: 20px !important;
        }
        
        .profile-field label {
            display: block !important;
            margin-bottom: 8px !important;
            font-weight: 600 !important;
            color: #333 !important;
            font-size: 14px !important;
        }
        
        .profile-field input {
            width: 100% !important;
            padding: 12px !important;
            border: 2px solid #f0f0f0 !important;
            border-radius: 8px !important;
            font-size: 14px !important;
            background: #f8f9fa !important;
            box-sizing: border-box !important;
        }
        
        .profile-actions {
            display: flex !important;
            gap: 12px !important;
            justify-content: flex-end !important;
            padding: 24px !important;
            border-top: 1px solid #f0f0f0 !important;
        }
        
        .btn-primary, .btn-secondary {
            padding: 12px 24px !important;
            border-radius: 8px !important;
            font-size: 14px !important;
            font-weight: 600 !important;
            cursor: pointer !important;
            transition: all 0.2s !important;
            border: none !important;
        }
        
        .btn-primary {
            background: #1d9bf0 !important;
            color: white !important;
        }
        
        .btn-primary:hover {
            background: #1976d2 !important;
        }
        
        .btn-secondary {
            background: #f8f9fa !important;
            color: #666 !important;
        }
        
        .btn-secondary:hover {
            background: #e9ecef !important;
        }
        
        /* Settings Modal Specific Styles */
        .settings-group {
            margin-bottom: 24px !important;
        }
        
        .settings-group h3 {
            margin: 0 0 16px 0 !important;
            font-size: 16px !important;
            font-weight: 600 !important;
            color: #333 !important;
        }
        
        .setting-item {
            display: flex !important;
            align-items: center !important;
            gap: 12px !important;
            padding: 16px !important;
            border-radius: 8px !important;
            cursor: pointer !important;
            transition: background 0.2s !important;
            border: 1px solid #f0f0f0 !important;
            margin-bottom: 8px !important;
        }
        
        .setting-item:hover {
            background: #f8f9fa !important;
        }
        
        .setting-item i {
            width: 20px !important;
            color: #666 !important;
        }
        
        .setting-item span {
            flex: 1 !important;
            font-weight: 500 !important;
            color: #333 !important;
        }
        
        .toggle {
            position: relative !important;
            display: inline-block !important;
            width: 44px !important;
            height: 24px !important;
        }
        
        .toggle input {
            opacity: 0 !important;
            width: 0 !important;
            height: 0 !important;
        }
        
        .slider {
            position: absolute !important;
            cursor: pointer !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            background-color: #ccc !important;
            transition: 0.4s !important;
            border-radius: 24px !important;
        }
        
        .slider:before {
            position: absolute !important;
            content: "" !important;
            height: 18px !important;
            width: 18px !important;
            left: 3px !important;
            bottom: 3px !important;
            background-color: white !important;
            transition: 0.4s !important;
            border-radius: 50% !important;
        }
        
        input:checked + .slider {
            background-color: #1d9bf0 !important;
        }
        
        input:checked + .slider:before {
            transform: translateX(20px) !important;
        }
        
        /* Help Modal Specific Styles */
        .help-section {
            margin-bottom: 24px !important;
        }
        
        .help-section h3 {
            margin: 0 0 16px 0 !important;
            font-size: 16px !important;
            font-weight: 600 !important;
            color: #333 !important;
        }
        
        .faq-item {
            border: 1px solid #f0f0f0 !important;
            border-radius: 8px !important;
            margin-bottom: 8px !important;
            overflow: hidden !important;
        }
        
        .faq-question {
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            padding: 16px !important;
            cursor: pointer !important;
            background: #f8f9fa !important;
            transition: background 0.2s !important;
        }
        
        .faq-question:hover {
            background: #e9ecef !important;
        }
        
        .faq-question span {
            font-weight: 600 !important;
            color: #333 !important;
        }
        
        .faq-question i {
            transition: transform 0.3s !important;
        }
        
        .faq-answer {
            padding: 16px !important;
            background: white !important;
            display: none !important;
            border-top: 1px solid #f0f0f0 !important;
        }
        
        .faq-answer p {
            margin: 0 !important;
            color: #666 !important;
            line-height: 1.6 !important;
        }
        
        .contact-option {
            display: flex !important;
            align-items: center !important;
            gap: 12px !important;
            padding: 16px !important;
            border: 1px solid #f0f0f0 !important;
            border-radius: 8px !important;
            margin-bottom: 8px !important;
            cursor: pointer !important;
            transition: background 0.2s !important;
        }
        
        .contact-option:hover {
            background: #f8f9fa !important;
        }
        
        .contact-option i {
            width: 24px !important;
            height: 24px !important;
            background: #1d9bf0 !important;
            color: white !important;
            border-radius: 50% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-size: 12px !important;
        }
        
        .contact-title {
            font-weight: 600 !important;
            color: #333 !important;
            margin-bottom: 2px !important;
        }
        
        .contact-subtitle {
            font-size: 13px !important;
            color: #666 !important;
        }
        
        .profile-menu.show {
            opacity: 1 !important;
            visibility: visible !important;
            transform: translateY(0) !important;
        }
        
        .menu-item {
            display: flex !important;
            align-items: center !important;
            gap: 12px !important;
            padding: 12px 16px !important;
            cursor: pointer !important;
            transition: background 0.2s !important;
            font-size: 14px !important;
            color: #333 !important;
        }
        
        .menu-item:hover {
            background: #f8f9fa !important;
        }
        
        .menu-item i {
            width: 16px !important;
            font-size: 14px !important;
        }
        
        .profile-section {
            position: relative !important;
        }
        
        /* Balance section - Premium blue gradient with enhanced effects */
        .balance-section {
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 85%, #0d47a1 100%) !important;
            color: white !important;
            padding: 30px !important;
            border-radius: 18px !important;
            margin-bottom: 8px !important;
            box-shadow: 0 8px 32px rgba(33, 150, 243, 0.25), 0 2px 8px rgba(33, 150, 243, 0.15) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            position: relative !important;
            overflow: hidden !important;
            transition: all 0.3s ease !important;
        }
        
        .balance-section::before {
            content: '' !important;
            position: absolute !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 100% !important;
            background: radial-gradient(circle at 20% 20%, rgba(255, 255, 255, 0.1) 0%, transparent 60%) !important;
            pointer-events: none !important;
        }
        
        .balance-section:hover {
            box-shadow: 0 12px 40px rgba(33, 150, 243, 0.3), 0 4px 12px rgba(33, 150, 243, 0.2) !important;
            transform: translateY(-2px) !important;
        }
        
        /* Sidebar Cards - Premium styling with enhanced effects */
        .sidebar-card {
            background: linear-gradient(145deg, #ffffff 0%, #fafafa 100%) !important;
            border-radius: 18px !important;
            padding: 24px !important;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06), 0 1px 4px rgba(0, 0, 0, 0.04) !important;
            margin-bottom: 20px !important;
            border: 1px solid rgba(0, 0, 0, 0.02) !important;
            transition: all 0.3s ease !important;
            position: relative !important;
            overflow: hidden !important;
        }
        
        .sidebar-card::before {
            content: '' !important;
            position: absolute !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 2px !important;
            background: linear-gradient(90deg, #1d9bf0, #2196F3, #1976D2) !important;
            opacity: 0 !important;
            transition: opacity 0.3s ease !important;
        }
        
        .sidebar-card:hover {
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08), 0 2px 8px rgba(0, 0, 0, 0.06) !important;
            transform: translateY(-2px) !important;
        }
        
        .sidebar-card:hover::before {
            opacity: 1 !important;
        }
        
        .sidebar-card .card-header {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            margin-bottom: 16px !important;
        }
        
        .sidebar-card h3 {
            font-size: 18px !important;
            font-weight: 600 !important;
            color: #333 !important;
            margin: 0 !important;
        }
        
        .view-all-link, .add-bank-link {
            color: #1d9bf0 !important;
            font-size: 14px !important;
            text-decoration: none !important;
            font-weight: 500 !important;
        }
        
        .view-all-link:hover, .add-bank-link:hover {
            text-decoration: underline !important;
        }

        .balance-card {
            position: relative;
            z-index: 2;
        }

        .balance-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .balance-header h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 500;
            opacity: 0.9;
        }

        .balance-header i {
            cursor: pointer;
            opacity: 0.8;
            font-size: 16px;
            transition: opacity 0.2s ease;
        }

        .balance-header i:hover {
            opacity: 1;
        }

        .balance-amount {
            font-size: 36px !important;
            font-weight: 700 !important;
            margin: 12px 0 !important;
            position: relative;
            z-index: 2;
        }

        .balance-subtitle {
            font-size: 14px;
            opacity: 0.8;
            position: relative;
            z-index: 2;
        }
        
        /* Quick Actions title styling - With proper spacing */
        .quick-actions {
            margin-top: 30px;
            padding: 0 4px;
            padding-top: 0;
        }
        
        .quick-actions h2 {
            background: linear-gradient(135deg, #ffffff 0%, #f0f8ff 100%) !important;
            -webkit-background-clip: text !important;
            -webkit-text-fill-color: transparent !important;
            background-clip: text !important;
            font-size: 28px !important;
            font-weight: 800 !important;
            margin-bottom: 28px !important;
            text-shadow: 0 3px 6px rgba(0, 0, 0, 0.15) !important;
            letter-spacing: -0.5px !important;
            position: relative !important;
        }
        
        .quick-actions h2::after {
            content: '' !important;
            position: absolute !important;
            bottom: -8px !important;
            left: 0 !important;
            width: 60px !important;
            height: 3px !important;
            background: linear-gradient(90deg, rgba(255,255,255,0.8) 0%, rgba(255,255,255,0.2) 100%) !important;
            border-radius: 2px !important;
        }
        
        /* Activity List Styling */
        .activity-list {
            display: flex !important;
            flex-direction: column !important;
            gap: 12px !important;
        }
        
        .activity-item {
            display: flex !important;
            align-items: center !important;
            gap: 12px !important;
            padding: 8px 0 !important;
        }
        
        .activity-profile .profile-pic {
            width: 40px !important;
            height: 40px !important;
            border-radius: 50% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-weight: 600 !important;
            font-size: 16px !important;
            color: white !important;
        }
        
        .money-received { background: #4CAF50 !important; }
        .payment-sent { background: #FF9800 !important; }
        .bill-payment { background: #2196F3 !important; }
        
        .activity-details {
            flex: 1 !important;
        }
        
        .activity-title {
            font-weight: 600 !important;
            font-size: 14px !important;
            color: #333 !important;
            margin-bottom: 2px !important;
        }
        
        .activity-subtitle {
            font-size: 12px !important;
            color: #666 !important;
            margin-bottom: 2px !important;
        }
        
        .activity-time {
            font-size: 11px !important;
            color: #999 !important;
        }
        
        .activity-amount {
            font-weight: 600 !important;
            font-size: 14px !important;
        }
        
        .activity-amount.positive {
            color: #4CAF50 !important;
        }
        
        .activity-amount.negative {
            color: #F44336 !important;
        }
        
        /* Bank List Styling */
        .bank-list {
            display: flex !important;
            flex-direction: column !important;
            gap: 12px !important;
        }
        
        .bank-item {
            display: flex !important;
            align-items: center !important;
            gap: 12px !important;
            padding: 12px 0 !important;
            border-bottom: 1px solid #f0f0f0 !important;
        }
        
        .bank-item:last-of-type {
            border-bottom: none !important;
        }
        
        .bank-icon {
            width: 40px !important;
            height: 40px !important;
            background: #607D8B !important;
            border-radius: 8px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            color: white !important;
            font-size: 18px !important;
        }
        
        .bank-details {
            flex: 1 !important;
            min-width: 0 !important;
        }
        
        .bank-account {
            font-size: 12px !important;
            color: #666 !important;
            margin-top: 2px !important;
        }
        
        .bank-name {
            font-weight: 600 !important;
            font-size: 14px !important;
            color: #333 !important;
            line-height: 1.2 !important;
            margin-bottom: 2px !important;
            color: #333 !important;
            margin-bottom: 2px !important;
        }
        
        .bank-account {
            font-size: 12px !important;
            color: #666 !important;
        }
        
        .bank-status {
            color: #4CAF50 !important;
            font-size: 16px !important;
        }
        
        /* Add Bank Box Styling */
        .add-bank-box {
            display: flex !important;
            align-items: center !important;
            gap: 12px !important;
            padding: 16px !important;
            border: 2px dashed #1d9bf0 !important;
            border-radius: 12px !important;
            cursor: pointer !important;
            transition: all 0.3s ease !important;
            margin-top: 8px !important;
        }
        
        .add-bank-box:hover {
            background: rgba(29, 155, 240, 0.05) !important;
            border-color: #1976d2 !important;
        }
        
        .add-bank-icon {
            width: 40px !important;
            height: 40px !important;
            background: #1d9bf0 !important;
            border-radius: 50% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            color: white !important;
            font-size: 18px !important;
        }
        
        .add-bank-text {
            flex: 1 !important;
        }
        
        .add-bank-title {
            font-weight: 600 !important;
            font-size: 14px !important;
            color: #333 !important;
            margin-bottom: 2px !important;
        }
        
        .add-bank-subtitle {
            font-size: 12px !important;
            color: #666 !important;
        }

        /* Action card text styling */
        /* Colored icons for action cards */
        .action-card i {
            font-size: 32px !important;
            margin-bottom: 8px !important;
        }

        /* Card Icon Backgrounds - Square/rounded shape like reference images */
        .card-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px auto;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15), 0 2px 6px rgba(0, 0, 0, 0.08);
            z-index: 10;
            border: none;
            transition: all 0.3s cubic-bezier(0.2, 0.8, 0.2, 1);
        }
        
        .card-icon i {
            font-size: 20px !important;
            color: white !important;
            transition: all 0.3s ease !important;
        }
        
        .action-card:hover .card-icon i {
            font-size: 22px !important;
            filter: brightness(1.1) !important;
        }
        
        /* Action cards spacing and animations */
        .action-row {
            position: relative;
            z-index: 1;
        }
        
        .action-card {
            animation: fadeInUp 0.6s ease-out both;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 30px 20px;
            min-height: 160px;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .first-row .action-card:nth-child(1) { animation-delay: 0.1s; }
        .first-row .action-card:nth-child(2) { animation-delay: 0.2s; }
        .first-row .action-card:nth-child(3) { animation-delay: 0.3s; }
        .second-row .action-card:nth-child(1) { animation-delay: 0.4s; }
        .second-row .action-card:nth-child(2) { animation-delay: 0.5s; }
        .second-row .action-card:nth-child(3) { animation-delay: 0.6s; }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .first-row .action-card:nth-child(1) { animation-delay: 0.1s; }
        .first-row .action-card:nth-child(2) { animation-delay: 0.2s; }
        .first-row .action-card:nth-child(3) { animation-delay: 0.3s; }
        .second-row .action-card:nth-child(1) { animation-delay: 0.4s; }
        .second-row .action-card:nth-child(2) { animation-delay: 0.5s; }
        .second-row .action-card:nth-child(3) { animation-delay: 0.6s; }
        
        /* BRIGHT SOLID COLORS - Ultra visible backgrounds */
        .action-card .card-icon.scan-qr-bg { 
            background: linear-gradient(135deg, #2196F3, #1976D2) !important; 
        }
        .action-card .card-icon.recharge-bg { 
            background: linear-gradient(135deg, #9C27B0, #7B1FA2) !important; 
        }
        .action-card .card-icon.bills-bg { 
            background: linear-gradient(135deg, #F44336, #D32F2F) !important; 
        }
        .action-card .card-icon.add-bank-bg { 
            background: linear-gradient(135deg, #607D8B, #455A64) !important; 
        }
        .action-card .card-icon.send-money-bg { 
            background: linear-gradient(135deg, #4CAF50, #388E3C) !important; 
        }
        .action-card .card-icon.request-money-bg { 
            background: linear-gradient(135deg, #FF9800, #F57C00) !important; 
        }

        /* Force white icons and size inside card-icon */
        .action-card .card-icon i {
            color: #fff;
            font-size: 24px;
            line-height: 1;
            transition: all 0.3s ease;
        }
        
        .action-card:hover .card-icon {
            transform: scale(1.1) !important;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.2), 0 6px 15px rgba(0, 0, 0, 0.1) !important;
        }

        /* Ensure action cards layout allows visible icon circles and keeps text inside */
        .action-card {
            position: relative;
            overflow: visible;
            align-items: center;
            padding-top: 40px; /* more space for larger icons */
            padding-bottom: 20px;
            padding-left: 18px;
            padding-right: 18px;
            min-height: 130px;
        }

        .action-card h3, .action-card .card-title {
            font-size: 17px !important;
            font-weight: 700 !important;
            margin: 14px 0 6px 0 !important;
            color: #2c3e50 !important;
            line-height: 1.2 !important;
            letter-spacing: -0.3px !important;
            word-break: break-word !important;
            letter-spacing: -0.1px !important;
        }

        .action-card p, .action-card .card-subtitle {
            font-size: 12px !important;
            color: #7f8c8d !important;
            margin: 0 !important;
            line-height: 1.3 !important;
            font-weight: 400 !important;
        }

        /* Quick Actions Grid - Balanced spacing */
        .first-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-bottom: 30px;
            margin-top: 30px;
        }

        .second-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-top: 30px;
        }

        /* Action cards styling - Premium design */
        .action-card {
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.98), rgba(255, 255, 255, 0.95)) !important;
            backdrop-filter: blur(20px) !important;
            padding: 24px !important;
            border-radius: 24px !important;
            text-align: center !important;
            cursor: pointer !important;
            transition: all 0.3s cubic-bezier(0.2, 0.8, 0.2, 1) !important;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.08), 0 6px 20px rgba(0, 0, 0, 0.04), inset 0 1px 0 rgba(255, 255, 255, 0.7) !important;
            border: 1px solid rgba(255, 255, 255, 0.5) !important;
            display: flex !important;
            flex-direction: column !important;
            justify-content: center !important;
            align-items: center !important;
            height: 160px !important;
            position: relative !important;
            overflow: hidden !important;
            transform: translateY(0) !important;
        }
        
        .action-card::before {
            content: '' !important;
            position: absolute !important;
            top: 0 !important;
            left: -100% !important;
            width: 100% !important;
            height: 100% !important;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent) !important;
            transition: left 0.6s ease !important;
        }

        .action-card:hover {
            transform: translateY(-12px) scale(1.03) !important;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.15), 0 10px 20px rgba(0, 0, 0, 0.1), inset 0 2px 0 rgba(255, 255, 255, 0.9) !important;
            border-color: rgba(255, 255, 255, 0.7) !important;
            background: linear-gradient(145deg, rgba(255, 255, 255, 1), rgba(255, 255, 255, 0.98)) !important;
        }
        
        .action-card:hover::before {
            left: 100% !important;
        }
        
        .action-card:active {
            transform: translateY(-4px) scale(1.015) !important;
            transition: all 0.15s ease !important;
        }

        /* Colored icons for action cards */
        .action-card i {
            font-size: 32px !important;
            margin-bottom: 8px !important;
        }

        /* Scan QR - Blue */
        .action-card:nth-child(1) i { color: #2196F3 !important; }
        /* Recharge - Purple */
        .action-card:nth-child(2) i { color: #9C27B0 !important; }
        /* Bills - Red */
        .action-card:nth-child(3) i { color: #F44336 !important; }
        /* Add Bank - Gray */
        .action-card:nth-child(4) i { color: #607D8B !important; }
        /* Send Money - Green */
        .action-card:nth-child(5) i { color: #4CAF50 !important; }
        /* Request - Orange */
        .action-card:nth-child(6) i { color: #FF9800 !important; }
        /* Sidebar cards - Compact design */
        .sidebar-card {
            background: rgba(255, 255, 255, 0.98) !important;
            backdrop-filter: blur(8px) !important;
            border-radius: 12px !important;
            padding: 16px !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08) !important;
            border: 0 !important;
        }   border: 0 !important;
        }
        
        /* Mobile Recharge Modal Styles */
        .recharge-modal, .success-modal, .upi-pin-modal, .receipt-modal, .processing-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease;
        }

        .modal-backdrop {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }

        .recharge-container {
            background: white;
            border-radius: 16px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            z-index: 10000;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            margin: 20px auto;
            transform: translateY(0);
            -webkit-overflow-scrolling: touch; /* iOS smooth scrolling */
        }

        .recharge-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            border-bottom: 1px solid #eee;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 16px 16px 0 0;
        }

        .recharge-header h2 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
        }

        .recharge-header i {
            margin-right: 8px;
        }

        .close-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 18px;
            transition: background 0.2s;
        }

        .close-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .recharge-content {
            padding: 24px;
        }

        .input-group {
            margin-bottom: 24px;
        }

        .input-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .mobile-input-wrapper {
            display: flex;
            align-items: center;
            border: 2px solid #ddd;
            border-radius: 8px;
            transition: border-color 0.3s;
        }

        .mobile-input-wrapper:focus-within {
            border-color: #1d9bf0;
        }

        .country-code-select {
            padding: 12px 8px;
            background: #f8f9fa;
            border: none;
            border-right: 1px solid #ddd;
            color: #666;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            outline: none;
            min-width: 85px;
        }

        .country-code-select:hover {
            background: #e9ecef;
        }

        .country-code-select:focus {
            background: #e3f2fd;
        }

        #mobileNumber {
            flex: 1;
            border: none;
            padding: 12px 16px;
            font-size: 16px;
            outline: none;
 b        }

        .contacts-btn {
            background: none;
            border: none;
            padding: 12px 16px;
            color: #1d9bf0;
            cursor: pointer;
            transition: color 0.2s;
        }

        .contacts-btn:hover {
            color: #5a67d8;
        }

        .mobile-error {
            color: #f44336;
            font-size: 14px;
            margin-top: 4px;
        }

        .operator-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-top: 8px;
        }

        .operator-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 16px;
            border: 2px solid #eee;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }

        .operator-card:hover {
            border-color: #1d9bf0;
            transform: translateY(-2px);
        }

        .operator-card.selected {
            border-color: #1d9bf0;
            background: linear-gradient(135deg, #1d9bf020, #0b254020);
        }

        .operator-logo {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .airtel-bg { background: linear-gradient(135deg, #e60026, #ff4444); }
        .jio-bg { background: linear-gradient(135deg, #0066cc, #4d94ff); }
        .vi-bg { background: linear-gradient(135deg, #5f27cd, #a55eea); }
        .bsnl-bg { background: linear-gradient(135deg, #ff6b35, #f7931e); }
        .idea-bg { background: linear-gradient(135deg, #ffd700, #ffb347); }
        .aircel-bg { background: linear-gradient(135deg, #00a651, #32cd32); }
        .docomo-bg { background: linear-gradient(135deg, #dc143c, #ff6347); }
        .uninor-bg { background: linear-gradient(135deg, #4169e1, #6495ed); }
        .reliance-bg { background: linear-gradient(135deg, #800080, #ba55d3); }
        .mts-bg { background: linear-gradient(135deg, #008b8b, #20b2aa); }
        .telenor-bg { background: linear-gradient(135deg, #1e90ff, #87ceeb); }
        .tata-bg { background: linear-gradient(135deg, #ff4500, #ffa500); }

        .plans-container {
            margin-top: 8px;
        }

        .quick-amounts {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 16px;
        }

        .plan-btn {
            background: white;
            border: 2px solid #eee;
            border-radius: 12px;
            padding: 16px 12px;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }

        .plan-btn:hover {
            border-color: #667eea;
            transform: translateY(-2px);
        }

        .plan-btn.selected {
            border-color: #667eea;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .plan-btn small {
            display: block;
            font-size: 12px;
            opacity: 0.8;
            margin-top: 4px;
            font-weight: normal;
        }

        .custom-amount label {
            font-size: 14px;
            margin-bottom: 8px;
        }

        #customAmount {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        #customAmount:focus {
            outline: none;
            border-color: #667eea;
        }

        .selected-plan {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 12px;
            padding: 16px;
            margin-top: 16px;
        }

        .plan-details {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .plan-amount {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
        }

        .plan-info {
            flex: 1;
            margin-left: 16px;
        }

        .plan-description {
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }

        .plan-validity {
            font-size: 14px;
            color: #666;
        }

        .recent-section {
            margin-top: 24px;
            padding-top: 16px;
            border-top: 1px solid #eee;
        }

        .recent-section h4 {
            margin: 0 0 12px 0;
            font-size: 16px;
            color: #333;
        }

        .recent-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 8px;
        }

        .recent-info span {
            display: block;
        }

        .recent-number {
            font-weight: 600;
            color: #333;
        }

        .recent-operator {
            font-size: 14px;
            color: #666;
        }

        .repeat-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.2s;
        }

        .repeat-btn:hover {
            background: #5a67d8;
        }

        .recharge-footer {
            padding: 20px 24px;
            border-top: 1px solid #eee;
        }

        .recharge-btn {
            width: 100%;
            background: linear-gradient(135deg, #4caf50, #45a049);
            color: white;
            border: none;
            padding: 16px;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            outline: none;
            box-sizing: border-box;
            user-select: none;
            -webkit-user-select: none;
            -moz-user-select: none;
            -webkit-tap-highlight-color: transparent;
        }

        .recharge-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(76, 175, 80, 0.3);
        }

        .recharge-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .recharge-btn i {
            margin-right: 8px;
        }

        /* Success Modal */
        .success-container {
            background: white;
            border-radius: 16px;
            padding: 40px 32px;
            text-align: center;
            width: 90%;
            max-width: 400px;
            position: relative;
            z-index: 10000;
        }

        .success-icon {
            font-size: 64px;
            color: #4caf50;
            margin-bottom: 20px;
        }

        .success-container h2 {
            color: #333;
            margin: 0 0 20px 0;
        }

        .success-details {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
        }

        .success-details p {
            margin: 8px 0;
            color: #333;
        }

        .success-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }

        .success-btn {
            flex: 1;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .success-btn.primary {
            background: #667eea;
            color: white;
        }

        .success-btn.secondary {
            background: #f8f9fa;
            color: #333;
            border: 2px solid #ddd;
        }

        .success-btn:hover {
            transform: translateY(-1px);
        }

        /* UPI PIN Modal Styles */
        .upi-pin-container {
            background: white;
            border-radius: 16px;
            width: 90%;
            max-width: 400px;
            position: relative;
            z-index: 10000;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            padding: 30px;
            text-align: center;
        }

        .pin-header h3 {
            margin: 0 0 5px 0;
            color: #333;
            font-size: 24px;
        }

        .pin-header p {
            margin: 0 0 30px 0;
            color: #666;
            font-size: 14px;
        }

        .pin-input-section {
            margin: 20px 0 30px 0;
        }

        .pin-input-field {
            width: 100%;
            padding: 15px 20px;
            font-size: 18px;
            text-align: center;
            border: 2px solid #ddd;
            border-radius: 10px;
            letter-spacing: 8px;
            font-weight: 600;
            transition: border-color 0.3s ease;
            outline: none;
        }

        .pin-input-field:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .pin-input-field::placeholder {
            letter-spacing: normal;
            color: #999;
            font-weight: normal;
        }

        .pin-actions {
            display: flex;
            gap: 12px;
            margin-top: 30px;
        }

        .pin-cancel-btn, .pin-submit-btn {
            flex: 1;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .pin-cancel-btn {
            background: #f8f9fa;
            color: #666;
            border: 2px solid #e0e0e0;
        }

        .pin-cancel-btn:hover {
            background: #e9ecef;
            border-color: #ccc;
        }

        .pin-submit-btn {
            background: #667eea;
            color: white;
        }

        .pin-submit-btn:hover:not(:disabled) {
            background: #5a67d8;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .pin-submit-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .pin-error {
            margin-top: 15px;
            padding: 10px;
            background: #ffebee;
            color: #c62828;
            border-radius: 8px;
            font-size: 14px;
        }

        /* Processing Modal */
        .processing-container {
            background: white;
            border-radius: 16px;
            padding: 40px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }

        .processing-spinner i {
            font-size: 48px;
            color: #667eea;
            margin-bottom: 20px;
        }

        .processing-container h3 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 24px;
        }

        .processing-container p {
            margin: 0;
            color: #666;
            font-size: 16px;
        }

        .processing-details {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .processing-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .processing-item:last-child {
            margin-bottom: 0;
            font-weight: 600;
        }

        .success-message {
            text-align: center;
            padding: 20px;
            background: linear-gradient(135deg, #e8f5e8, #f0f8f0);
            border-radius: 8px;
            border: 1px solid #4caf50;
        }

        .success-message i {
            font-size: 24px;
            color: #4caf50;
            margin-bottom: 10px;
        }

        .success-message p {
            margin: 0;
            color: #2e7d32;
            font-size: 14px;
            line-height: 1.4;
        }

        /* Receipt Modal Styles */
        .receipt-container {
            background: white;
            border-radius: 16px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            z-index: 10000;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }

        .receipt-header {
            text-align: center;
            padding: 30px 30px 20px 30px;
            border-bottom: 1px solid #f0f0f0;
        }

        .receipt-header .success-icon i {
            font-size: 64px;
            color: #4caf50;
            margin-bottom: 15px;
        }

        .receipt-header h2 {
            margin: 0 0 5px 0;
            color: #333;
            font-size: 28px;
        }

        .receipt-subtitle {
            color: #666;
            font-size: 16px;
            margin: 0;
        }

        .receipt-content {
            padding: 20px 30px;
        }

        .receipt-section {
            margin-bottom: 25px;
        }

        .receipt-section h4 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 18px;
            font-weight: 600;
        }

        .receipt-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f5f5f5;
        }

        .receipt-row:last-child {
            border-bottom: none;
        }

        .receipt-row span:first-child {
            color: #666;
            font-size: 14px;
        }

        .receipt-row span:last-child {
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }

        .total-row {
            border-top: 2px solid #e0e0e0;
            margin-top: 10px;
            font-weight: 600;
        }

        .total-row span {
            font-size: 16px;
            font-weight: 700;
        }

        .status-success {
            color: #4caf50 !important;
            background: #e8f5e8;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px !important;
        }

        .receipt-actions {
            padding: 20px 30px;
            border-top: 1px solid #f0f0f0;
            display: flex;
            gap: 10px;
        }

        .receipt-btn {
            flex: 1;
            padding: 12px 16px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .receipt-btn.primary {
            background: #667eea;
            color: white;
        }

        .receipt-btn.secondary {
            background: #f8f9fa;
            color: #667eea;
            border: 1px solid #e0e0e0;
        }

        .receipt-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .receipt-footer {
            padding: 20px 30px;
            border-top: 1px solid #f0f0f0;
            display: flex;
            gap: 10px;
        }

        .receipt-btn.done {
            background: #4caf50;
            color: white;
        }

        .receipt-btn.repeat {
            background: #f8f9fa;
            color: #333;
            border: 1px solid #e0e0e0;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }

        /* Tablet/Laptop responsive design */
        @media (max-width: 1200px) {
            .main-container {
                grid-template-columns: 1fr !important;
                gap: 15px !important;
                max-width: 100% !important;
            }
            
            .sidebar {
                display: flex !important;
                flex-direction: column !important;
            }
            
            /* Force sidebar to right on larger screens */
            @media (min-width: 900px) {
                .main-container {
                    grid-template-columns: 1fr 350px !important;
                }
            }
            
            .recharge-container {
                max-width: 600px;
            }
            
            .operator-grid {
                grid-template-columns: repeat(4, 1fr);
                gap: 12px;
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 15px;
            }
            
            .header {
                padding: 15px 20px;
                flex-wrap: wrap;
                gap: 15px;
            }
            
            .search-container {
                order: 3;
                width: 100%;
                margin-top: 10px;
            }
            
            .operator-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 10px;
            }
            
            .operator-card {
                padding: 15px 10px;
            }
            
            .quick-amounts {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }
            
            .amount-card {
                padding: 12px 8px;
                font-size: 14px;
            }
            
            .recharge-container {
                width: 95%;
                max-width: 500px;
                margin: 10px;
            }
            
            .recharge-header {
                padding: 20px;
            }
            
            .recharge-content {
                padding: 15px 20px;
            }
            
            .mobile-input-wrapper {
                flex-wrap: wrap;
            }
            
            .country-code-select {
                min-width: 70px;
                font-size: 13px;
            }
            
            .upi-pin-container {
                width: 95%;
                padding: 25px 20px;
            }
            
            .receipt-container {
                width: 95%;
                margin: 10px;
            }
        }

        @media (max-width: 480px) {
            .operator-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
            }
            
            .operator-card {
                padding: 12px 8px;
                font-size: 12px;
            }
            
            .operator-card i {
                font-size: 20px;
            }
            
            .quick-amounts {
                grid-template-columns: 1fr;
                gap: 8px;
            }
            
            .amount-card {
                padding: 15px 12px;
            }
            
            .success-actions, .receipt-actions, .receipt-footer {
                flex-direction: column;
                gap: 10px;
            }

            .upi-pin-container {
                padding: 20px 15px;
            }

            .keypad-row button {
                width: 50px;
                height: 50px;
                font-size: 18px;
            }
            
            .pin-dots {
                gap: 10px;
                margin-bottom: 25px;
            }
            
            .pin-dot {
                width: 14px;
                height: 14px;
            }
            
            .mobile-input-wrapper {
                border-radius: 6px;
            }
            
            .country-code-select {
                padding: 10px 6px;
                font-size: 12px;
                min-width: 65px;
            }
            
            #mobileNumber {
                padding: 10px 12px;
                font-size: 14px;
            }
        }

        /* Large screen optimizations */
        @media (min-width: 1400px) {
            .recharge-container {
                max-width: 650px;
            }
            
            .operator-grid {
                grid-template-columns: repeat(6, 1fr);
                gap: 15px;
            }
            
            .quick-amounts {
                grid-template-columns: repeat(4, 1fr);
            }
            
            .upi-pin-container {
                max-width: 450px;
                padding: 40px;
            }
            
            .keypad-row button {
                width: 70px;
                height: 70px;
                font-size: 22px;
            }
        }
        
        /* Original Balance Card Styling */
        .balance-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            color: white;
            background: transparent;
        }
        
        .balance-header h3 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        
        .balance-total-mini {
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 16px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            color: white;
        }
        
        .single-balance-display {
            margin-bottom: 20px;
        }
        
        .balance-main-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 16px;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .balance-main-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .balance-icon {
            width: 48px;
            height: 48px;
            background: #f8f9fa;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-size: 20px;
        }
        
        .balance-info {
            flex: 1;
        }
        
        .account-number {
            font-size: 16px;
            color: #6c757d;
            font-family: 'Monaco', 'Menlo', monospace;
            margin-bottom: 4px;
        }
        
        .bank-status i {
            color: #28a745;
            font-size: 16px;
        }
        
        .balance-amount-large {
            font-size: 32px;
            font-weight: 700;
            color: #1976d2;
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="page-container">
    


    <!-- Header with PaySecure logo, search, and profile -->
    <header class="header">
        <div class="logo">
            <i class="fas fa-lock" style="color: #1d9bf0 !important;"></i>
            <span class="pay">pay</span><span class="secure">secure</span>
        </div>
        
        <div class="search-container">
            <input type="text" class="search-input" id="searchInput" placeholder="Search payments, contacts..." oninput="performSearch()" onkeydown="handleSearchKeydown(event)">
            <i class="fas fa-search search-icon" onclick="focusSearch()"></i>
            <div class="search-results" id="searchResults"></div>
        </div>
        
        <div class="profile-section">
            <div class="notifications" onclick="toggleNotifications()">
                <i class="fas fa-bell"></i>
                <span class="notification-badge" id="notificationCount">3</span>
            </div>
            
            <!-- Notifications Dropdown -->
            <div class="notifications-dropdown" id="notificationsDropdown">
                <div class="notifications-header">
                    <h4>Notifications</h4>
                    <button class="mark-all-read" onclick="markAllAsRead()">Mark all as read</button>
                </div>
                <div class="notifications-list" id="notificationsList">
                    <!-- Will be populated by JavaScript -->
                </div>
                <div class="notifications-footer">
                    <a href="notifications.html">View All Notifications</a>
                </div>
            </div>
            
            <div class="profile-btn" onclick="toggleProfileMenu()">
                <div class="profile-avatar" id="profileAvatar">U</div>
                <div class="profile-info">
                    <span class="profile-name" id="profileName"><?php echo htmlspecialchars($name); ?></span>
                    <span class="profile-subtitle">Welcome back</span>
                </div>
                <i class="fas fa-chevron-down profile-arrow"></i>
            </div>
            
            <!-- Profile Dropdown Menu -->
            <div class="profile-menu" id="profileMenu">
                <div class="menu-item" onclick="viewProfile()">
                    <i class="fas fa-user"></i>
                    <span>My Profile</span>
                </div>
                <div class="menu-item" onclick="viewTransactions()">
                    <i class="fas fa-history"></i>
                    <span>Transaction History</span>
                </div>
                <div class="menu-item" onclick="viewSettings()">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </div>
                <div class="menu-item" onclick="enterReferralCode()">
                    <i class="fas fa-gift"></i>
                    <span>Enter Referral Code</span>
                </div>
                <div class="menu-item" onclick="logout()" style="color: #f44336;">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Container: Left content + Right sidebar -->
    <div class="main-container">
        <!-- Left Side: Main Content -->
        <div class="left-content">
            <!-- Balance Card -->
            <div class="balance-section">
                <div class="balance-card">
                    <div class="balance-header">
                        <h3>Current Balance</h3>
                        <i class="fas fa-eye" id="balanceToggle" onclick="toggleBalance()"></i>
                    </div>
                    <div class="balance-amount" id="balanceAmount"><?php echo '₹' . number_format(($current_balance !== null ? $current_balance : 1230), 2); ?></div>
                    <div class="balance-subtitle">Available balance</div>
                </div>
            </div>

            <!-- Quick Actions Grid (Box Cards) -->
            <div class="quick-actions">
                <h2>Quick Actions</h2>
                
                <!-- First Row: 4 boxes -->
                <div class="action-row first-row">
                    <div class="action-card" onclick="scanQR()">
                        <div class="card-icon scan-qr-bg">
                            <i class="fas fa-qrcode"></i>
                        </div>
                        <h3>Scan QR</h3>
                        <p>Quick payment</p>
                    </div>
                    
                    <div class="action-card" onclick="mobileRecharge()">
                        <div class="card-icon recharge-bg">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <h3>Recharge</h3>
                        <p>Mobile & DTH</p>
                    </div>
                    
                    <div class="action-card" onclick="payBills()">
                        <div class="card-icon bills-bg">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <h3>Bills</h3>
                        <p>Utilities</p>
                    </div>
                    
                    <!-- Add Bank moved to second row to keep 3 items per row -->
                </div>
                
                <!-- Second Row: 2 boxes -->
                <div class="action-row second-row">
                    <div class="action-card" onclick="addBankAccount()">
                        <div class="card-icon add-bank-bg">
                            <i class="fas fa-university"></i>
                        </div>
                        <h3>Add Bank</h3>
                        <p>Link account</p>
                    </div>

                    <div class="action-card" onclick="sendMoney()">
                        <div class="card-icon send-money-bg">
                            <i class="fas fa-arrow-up"></i>
                        </div>
                        <h3>Send Money</h3>
                        <p>Transfer funds</p>
                    </div>
                    
                    <div class="action-card" onclick="referAndEarn()">
                        <div class="card-icon request-money-bg">
                            <i class="fas fa-gift"></i>
                        </div>
                        <h3>Refer & Earn</h3>
                        <p>Invite friends</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side: Sidebar -->
        <div class="sidebar">
            <!-- Recent Activity Card -->
            <div class="sidebar-card activity-card">
                <div class="card-header">
                    <h3>Recent Activity</h3>
                    <a href="#" onclick="showAllTransactions()" class="view-all-link">View All</a>
                </div>
                
                    <div class="activity-list">
                        <?php foreach ($recentActivities as $act): 
                            $isCredit = (isset($act['direction']) && $act['direction'] === 'in') || (isset($act['tx_type']) && $act['tx_type'] === 'credit');
                            $initial = !empty($act['counterparty']) ? strtoupper($act['counterparty'][0]) : 'U';
                            $title = $isCredit ? 'Money Received' : (isset($act['tx_type']) && $act['tx_type']==='debit' ? 'Payment Sent' : 'Transaction');
                            $subtitle = $act['note'] ?? $act['counterparty'] ?? '';
                            $amountSign = $isCredit ? '+' : '-';
                            $amountDisplay = (isset($act['amount']) ? number_format((float)$act['amount']) : '0');
                            $timeAgo = '';
                            if (!empty($act['created_at'])) {
                                $time = strtotime($act['created_at']);
                                $diff = time() - $time;
                                if ($diff < 3600) $timeAgo = intval($diff/60) . ' minutes ago';
                                else if ($diff < 86400) $timeAgo = intval($diff/3600) . ' hours ago';
                                else $timeAgo = intval($diff/86400) . ' day' . (intval($diff/86400)>1?'s':'') . ' ago';
                            }
                        ?>
                        <div class="activity-item">
                            <div class="activity-profile">
                                <div class="profile-pic <?php echo $isCredit ? 'money-received' : 'payment-sent'; ?>"><?php echo $initial; ?></div>
                            </div>
                            <div class="activity-details">
                                <div class="activity-title"><?php echo htmlspecialchars($title); ?></div>
                                <div class="activity-subtitle"><?php echo htmlspecialchars($subtitle); ?></div>
                                <div class="activity-time"><?php echo $timeAgo; ?></div>
                            </div>
                            <div class="activity-amount <?php echo $isCredit ? 'positive' : 'negative'; ?>"><?php echo $amountSign . '₹' . $amountDisplay; ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
            </div>

            <!-- Linked Banks Card -->
            <div class="sidebar-card banks-card">
                <div class="card-header">
                    <h3>Linked Banks</h3>
                    <a href="#" class="add-bank-link">Add Bank</a>
                </div>
                
                <div class="bank-list">
                    <?php if (!empty($linkedBanks)): ?>
                        <?php foreach ($linkedBanks as $b): ?>
                            <div class="bank-item">
                                <div class="bank-icon">
                                    <i class="fas fa-university"></i>
                                </div>
                                <div class="bank-details">
                                    <div class="bank-name" style="color:#111;font-weight:700;font-size:16px;"><?php echo htmlspecialchars($b['bank_name'] ?? 'My Bank Account'); ?></div>
                                    <div class="bank-account" style="color:#666;font-size:14px;">**** **** **** <?php echo htmlspecialchars($b['last_6_digits'] ?? '123456'); ?></div>
                                </div>
                                <div class="bank-status <?php echo (!empty($b['is_verified']) ? 'active' : ''); ?>">
                                    <?php if (!empty($b['is_verified'])): ?>
                                        <i class="fas fa-check-circle"></i>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="color:#666; padding:12px 0;">No linked banks yet.</div>
                    <?php endif; ?>

                    <div class="add-bank-box" onclick="addBankAccount()">
                        <div class="add-bank-icon">
                            <i class="fas fa-plus"></i>
                        </div>
                        <div class="add-bank-text">
                            <div class="add-bank-title">Add New Bank Account</div>
                            <div class="add-bank-subtitle">Ask for payment</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Injected user data from PHP session
        // Injected user data from PHP session
        const userData = <?php echo json_encode([
            'name' => $name,
            'email' => $email,
            'phone' => $display_phone,
            'upiId' => $upi_id
        ]); ?>;

        // Search functionality (same as before)...
// Search functionality replaced by enhanced version below
        
        // Notifications functionality
        function toggleNotifications() {
            const dropdown = document.getElementById('notificationsDropdown');
            const isVisible = dropdown.classList.contains('show');
            
            // Close all dropdowns first
            closeAllDropdowns();
            
            if (!isVisible) {
                dropdown.classList.add('show');
                loadNotifications();
            }
        }
        
        function loadNotifications() {
            const notificationsList = document.getElementById('notificationsList');
            if (!notificationsList) {
                console.error('notificationsList element not found');
                return;
            }
            
            // Sample notifications data - replace with actual API call
            const notifications = [
                {
                    id: 1,
                    type: 'success',
                    title: 'Payment Successful',
                    message: 'Your mobile recharge of ₹299 was completed successfully.',
                    time: '2 minutes ago',
                    unread: true
                },
                {
                    id: 2,
                    type: 'info',
                    title: 'New Feature Available',
                    message: 'Bill payment feature is now available. Pay your utility bills easily.',
                    time: '1 hour ago',
                    unread: true
                },
                {
                    id: 3,
                    type: 'warning',
                    title: 'Low Balance Alert',
                    message: 'Your account balance is below ₹100. Add money to continue transactions.',
                    time: '3 hours ago',
                    unread: false
                },
                {
                    id: 4,
                    type: 'success',
                    title: 'Money Received',
                    message: 'You received ₹500 from John Doe.',
                    time: 'Yesterday',
                    unread: false
                }
            ];
            
            notificationsList.innerHTML = notifications.map(notification => `
                <div class="notification-item ${notification.unread ? 'unread' : ''}" onclick="markAsRead(${notification.id})">
                    <div class="notification-icon ${notification.type}">
                        <i class="fas fa-${getNotificationIcon(notification.type)}"></i>
                    </div>
                    <div class="notification-content">
                        <div class="notification-title">${notification.title}</div>
                        <div class="notification-message">${notification.message}</div>
                        <div class="notification-time">${notification.time}</div>
                    </div>
                </div>
            `).join('');
        }
        
        function getNotificationIcon(type) {
            const icons = {
                success: 'check',
                info: 'info-circle',
                warning: 'exclamation-triangle',
                error: 'times-circle'
            };
            return icons[type] || 'bell';
        }
        
        function markAsRead(notificationId) {
            console.log('Marking notification as read:', notificationId);
            // Update notification count
            updateNotificationCount();
        }
        
        function markAllAsRead() {
            console.log('Marking all notifications as read');
            document.querySelectorAll('.notification-item.unread').forEach(item => {
                item.classList.remove('unread');
            });
            updateNotificationCount();
        }
        
        function updateNotificationCount() {
            const unreadCount = document.querySelectorAll('.notification-item.unread').length;
            const badge = document.getElementById('notificationCount');
            if (badge) {
                badge.textContent = unreadCount;
                badge.style.display = unreadCount > 0 ? 'block' : 'none';
            }
        }
        
        // Enhanced search functionality
        function performSearch() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const searchResults = document.getElementById('searchResults');
            
            if (searchTerm.length === 0) {
                searchResults.style.display = 'none';
                return;
            }
            
            // Enhanced search data with more categories
            const searchData = [
                { type: 'contact', name: 'John Doe', subtitle: 'Contact • +91 9876543210', icon: 'fas fa-user', action: 'sendMoney' },
                { type: 'contact', name: 'Sarah Smith', subtitle: 'Contact • +91 8765432109', icon: 'fas fa-user', action: 'sendMoney' },
                { type: 'service', name: 'Mobile Recharge', subtitle: 'Recharge your mobile', icon: 'fas fa-mobile-alt', action: 'mobileRecharge' },
                { type: 'service', name: 'Bill Payment', subtitle: 'Pay utility bills', icon: 'fas fa-file-invoice-dollar', action: 'payBills' },
                { type: 'service', name: 'Scan & Pay', subtitle: 'Pay using QR code', icon: 'fas fa-qrcode', action: 'scanQR' },
                { type: 'transaction', name: 'Payment to Amazon', subtitle: 'Transaction • Nov 22', icon: 'fas fa-history', action: 'viewTransactions' },
                { type: 'transaction', name: 'Money from John', subtitle: 'Transaction • Nov 21', icon: 'fas fa-history', action: 'viewTransactions' },
                { type: 'help', name: 'Help & Support', subtitle: 'Get help with your account', icon: 'fas fa-question-circle', action: 'showHelp' },
                { type: 'settings', name: 'Account Settings', subtitle: 'Manage your account', icon: 'fas fa-cog', action: 'viewSettings' }
            ];
            
            const filteredResults = searchData.filter(item => 
                item.name.toLowerCase().includes(searchTerm) || 
                item.subtitle.toLowerCase().includes(searchTerm)
            );
            
            if (filteredResults.length > 0) {
                searchResults.innerHTML = filteredResults.map(item => `
                    <div class="search-result-item" onclick="selectSearchResult('${item.action}', '${item.name}')">
                        <i class="${item.icon}"></i>
                        <div class="result-details">
                            <div class="result-name">${item.name}</div>
                            <div class="result-subtitle">${item.subtitle}</div>
                        </div>
                    </div>
                `).join('');
                searchResults.style.display = 'block';
            } else {
                searchResults.innerHTML = '<div class="no-results">No results found for "' + searchTerm + '"</div>';
                searchResults.style.display = 'block';
            }
        }
        
        function handleSearchKeydown(event) {
            if (event.key === 'Enter') {
                const firstResult = document.querySelector('.search-result-item');
                if (firstResult) {
                    firstResult.click();
                }
            } else if (event.key === 'Escape') {
                document.getElementById('searchResults').style.display = 'none';
                document.getElementById('searchInput').blur();
            }
        }
        
        function focusSearch() {
            document.getElementById('searchInput').focus();
        }
        
        function selectSearchResult(action, name) {
            document.getElementById('searchInput').value = '';
            document.getElementById('searchResults').style.display = 'none';
            
            // Execute the action
            if (typeof window[action] === 'function') {
                window[action]();
            } else {
                console.log('Action not found:', action);
            }
        }
        
        // Profile menu toggle
        function toggleProfileMenu() {
            const menu = document.getElementById('profileMenu');
            const isVisible = menu.classList.contains('show');
            
            // Close all dropdowns first
            closeAllDropdowns();
            
            if (!isVisible) {
                menu.classList.add('show');
            }
        }
        
        // Close all dropdowns
        function closeAllDropdowns() {
            document.getElementById('profileMenu')?.classList.remove('show');
            document.getElementById('notificationsDropdown')?.classList.remove('show');
            document.getElementById('searchResults').style.display = 'none';
        }
        
        // Profile and navigation functions
        // Profile and navigation functions
        // viewProfile, viewTransactions, viewSettings, logout are defined later
        
        function viewRechargeHistory() {
            closeAllDropdowns();
            window.location.href = 'recharge_history.html';
        }
        
        function viewBillHistory() {
            closeAllDropdowns();
            window.location.href = 'bill_history.html';
        }
        
        function showHelp() {
            closeAllDropdowns();
            showHelpModal();
        }
        
        // Profile and navigation functions
        function viewProfile() {
            closeAllDropdowns();
            showProfileModal();
        }
        
        function viewTransactions() {
            closeAllDropdowns();
            window.location.href = 'transaction_history.php';
        }
        
        function viewRechargeHistory() {
            closeAllDropdowns();
            window.location.href = 'recharge_history.html';
        }
        
        function viewBillHistory() {
            closeAllDropdowns();
            window.location.href = 'bill_history.html';
        }
        
        function viewSettings() {
            closeAllDropdowns();
            showSettingsModal();
        }
        
        function showHelp() {
            closeAllDropdowns();
            showHelpModal();
        }
        
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'logout.php';
            }
        }
        
        // Modal functions
        // Modal functions
        // showProfileModal, showSettingsModal etc are defined later
        
        function showHelpModal() {
            const modal = document.createElement('div');
            modal.className = 'help-modal';
            modal.innerHTML = `
                <div class="modal-backdrop" onclick="closeHelpModal()"></div>
                <div class="help-container">
                    <div class="help-header">
                        <h2><i class="fas fa-question-circle"></i> Help & Support</h2>
                        <button class="close-btn" onclick="closeHelpModal()">&times;</button>
                    </div>
                    <div class="help-content">
                        <div class="help-section">
                            <h3>Frequently Asked Questions</h3>
                            <div class="faq-item" onclick="toggleFaq(this)">
                                <div class="faq-question">
                                    <span>How do I add money to my wallet?</span>
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                                <div class="faq-answer">
                                    <p>You can add money by clicking the "Add Money" button on your dashboard and selecting your preferred payment method.</p>
                                </div>
                            </div>
                            <div class="faq-item" onclick="toggleFaq(this)">
                                <div class="faq-question">
                                    <span>How do I pay bills?</span>
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                                <div class="faq-answer">
                                    <p>Go to the Bills section, select your service provider, enter your account details, and make the payment.</p>
                                </div>
                            </div>
                            <div class="faq-item" onclick="toggleFaq(this)">
                                <div class="faq-question">
                                    <span>Is my money safe with PaySecure?</span>
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                                <div class="faq-answer">
                                    <p>Yes, we use bank-grade security and encryption to protect your money and personal information.</p>
                                </div>
                            </div>
                        </div>
                        <div class="help-section">
                            <h3>Contact Support</h3>
                            <div class="contact-option" onclick="contactSupport('email')">
                                <i class="fas fa-envelope"></i>
                                <div>
                                    <div class="contact-title">Email Support</div>
                                    <div class="contact-subtitle">support@paysecure.com</div>
                                </div>
                            </div>
                            <div class="contact-option" onclick="contactSupport('phone')">
                                <i class="fas fa-phone"></i>
                                <div>
                                    <div class="contact-title">Phone Support</div>
                                    <div class="contact-subtitle">1800-123-4567</div>
                                </div>
                            </div>
                            <div class="contact-option" onclick="contactSupport('chat')">
                                <i class="fas fa-comments"></i>
                                <div>
                                    <div class="contact-title">Live Chat</div>
                                    <div class="contact-subtitle">Available 24/7</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        
        function closeHelpModal() {
            const modal = document.querySelector('.help-modal');
            if (modal) modal.remove();
        }
        
        function toggleFaq(element) {
            const answer = element.querySelector('.faq-answer');
            const icon = element.querySelector('.faq-question i');
            
            if (answer.style.display === 'block') {
                answer.style.display = 'none';
                icon.style.transform = 'rotate(0deg)';
            } else {
                answer.style.display = 'block';
                icon.style.transform = 'rotate(180deg)';
            }
        }
        
        function contactSupport(method) {
            switch(method) {
                case 'email':
                    window.location.href = 'mailto:support@paysecure.com';
                    break;
                case 'phone':
                    window.location.href = 'tel:18001234567';
                    break;
                case 'chat':
                    alert('Live chat functionality - Coming soon!');
                    break;
            }
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            const profileSection = document.querySelector('.profile-section');
            const notificationsSection = document.querySelector('.notifications');
            const searchContainer = document.querySelector('.search-container');
            
            if (profileSection && !profileSection.contains(event.target)) {
                const profileMenu = document.getElementById('profileMenu');
                if (profileMenu) profileMenu.classList.remove('show');
            }
            
            if (notificationsSection && !notificationsSection.contains(event.target)) {
                const notifDropdown = document.getElementById('notificationsDropdown');
                if (notifDropdown) notifDropdown.classList.remove('show');
            }
            
            if (searchContainer && !searchContainer.contains(event.target)) {
                const searchResults = document.getElementById('searchResults');
                if (searchResults) searchResults.style.display = 'none';
            }
        });
        
        // Balance toggle
        let balanceVisible = true;
        let actualBalance = '<?php echo "₹" . number_format(($current_balance !== null ? $current_balance : 1230), 2); ?>';
        
        function toggleBalance() {
            const balanceAmount = document.getElementById('balanceAmount');
            const toggleIcon = document.getElementById('balanceToggle');
            
            if (balanceVisible) {
                balanceAmount.textContent = '₹****.**';
                toggleIcon.className = 'fas fa-eye-slash';
                balanceVisible = false;
            } else {
                balanceAmount.textContent = actualBalance;
                toggleIcon.className = 'fas fa-eye';
                balanceVisible = true;
            }
        }
        
        // Action functions
        function scanQR() { 
            window.location.href = 'scan_and_pay.html'; 
        }
        
        function payBills() {
            window.location.href = 'bill_pay.html';
        }

        function addBankAccount() {
            window.location.href = 'link_with_bank.html';
        }

        function sendMoney() {
            window.location.href = 'phone.html';
        }

        function referAndEarn() {
            window.location.href = 'test_referral.html';
        }

        function mobileRecharge() {
            // Remove existing modal if present
            const existingModal = document.querySelector('.recharge-modal');
            if (existingModal) existingModal.remove();

            // Create recharge modal
            const modal = document.createElement('div');
            modal.className = 'recharge-modal';
            modal.innerHTML = `
                <div class="modal-backdrop" onclick="closeRechargeModal()"></div>
                <div class="recharge-container">
                    <div class="recharge-header">
                        <h2><i class="fas fa-mobile-alt"></i> Mobile Recharge</h2>
                        <button class="close-btn" onclick="closeRechargeModal()">×</button>
                    </div>
                    
                    <div class="recharge-content">
                        <!-- Mobile Number Input -->
                        <div class="input-group">
                            <label for="mobileNumber">Mobile Number</label>
                            <div class="mobile-input-wrapper">
                                <select id="countryCode" class="country-code-select" onchange="updatePhonePlaceholder()">
                                    <option value="+91" data-digits="10" data-flag="🇮🇳">🇮🇳 +91</option>
                                    <option value="+1" data-digits="10" data-flag="🇺🇸">🇺🇸 +1</option>
                                    <option value="+44" data-digits="10" data-flag="🇬🇧">🇬🇧 +44</option>
                                </select>
                                <input type="tel" id="mobileNumber" placeholder="9876543210" 
                                       maxlength="15" oninput="validateMobileNumber(this)">
                                <button class="contacts-btn" onclick="showRecentContacts()" title="Choose from contacts">
                                    <i class="fas fa-address-book"></i>
                                </button>
                            </div>
                            <div class="mobile-error" id="mobileError" style="display:none;"></div>
                        </div>

                        <!-- Operator Selection -->
                        <div class="input-group">
                            <label>Select Operator</label>
                            <div class="operator-grid">
                                <div class="operator-card" onclick="selectOperator('airtel', this)">
                                    <div class="operator-logo airtel-bg">A</div>
                                    <span>Airtel</span>
                                </div>
                                <div class="operator-card" onclick="selectOperator('jio', this)">
                                    <div class="operator-logo jio-bg">J</div>
                                    <span>Jio</span>
                                </div>
                                <div class="operator-card" onclick="selectOperator('vi', this)">
                                    <div class="operator-logo vi-bg">Vi</div>
                                    <span>Vi</span>
                                </div>
                                <div class="operator-card" onclick="selectOperator('bsnl', this)">
                                    <div class="operator-logo bsnl-bg">B</div>
                                    <span>BSNL</span>
                                </div>
                                <div class="operator-card" onclick="selectOperator('idea', this)">
                                    <div class="operator-logo idea-bg">I</div>
                                    <span>Idea</span>
                                </div>
                                <div class="operator-card" onclick="selectOperator('aircel', this)">
                                    <div class="operator-logo aircel-bg">AC</div>
                                    <span>Aircel</span>
                                </div>
                                <div class="operator-card" onclick="selectOperator('docomo', this)">
                                    <div class="operator-logo docomo-bg">D</div>
                                    <span>Docomo</span>
                                </div>
                                <div class="operator-card" onclick="selectOperator('uninor', this)">
                                    <div class="operator-logo uninor-bg">U</div>
                                    <span>Uninor</span>
                                </div>
                                <div class="operator-card" onclick="selectOperator('reliance', this)">
                                    <div class="operator-logo reliance-bg">R</div>
                                    <span>Reliance</span>
                                </div>
                                <div class="operator-card" onclick="selectOperator('mts', this)">
                                    <div class="operator-logo mts-bg">M</div>
                                    <span>MTS</span>
                                </div>
                                <div class="operator-card" onclick="selectOperator('telenor', this)">
                                    <div class="operator-logo telenor-bg">T</div>
                                    <span>Telenor</span>
                                </div>
                                <div class="operator-card" onclick="selectOperator('tata', this)">
                                    <div class="operator-logo tata-bg">TT</div>
                                    <span>Tata Tele</span>
                                </div>
                            </div>
                            <input type="hidden" id="selectedOperator" value="">
                        </div>

                        <!-- Recharge Plans -->
                        <div class="input-group">
                            <label>Popular Plans</label>
                            <div class="plans-container">
                                <div class="quick-amounts">
                                    <button class="plan-btn" onclick="selectPlan(49, '1GB Data, Unlimited Calls', '28 days')">
                                        ₹49
                                        <small>1GB + Unlimited</small>
                                    </button>
                                    <button class="plan-btn" onclick="selectPlan(149, '2GB/day, Unlimited Calls', '28 days')">
                                        ₹149
                                        <small>2GB/day + Unlimited</small>
                                    </button>
                                    <button class="plan-btn" onclick="selectPlan(299, '4GB/day, Unlimited Calls', '28 days')">
                                        ₹299
                                        <small>4GB/day + Unlimited</small>
                                    </button>
                                    <button class="plan-btn" onclick="selectPlan(599, '6GB/day, Unlimited Calls', '84 days')">
                                        ₹599
                                        <small>6GB/day + Long Validity</small>
                                    </button>
                                </div>
                                
                                <!-- Custom Amount -->
                                <div class="custom-amount">
                                    <label>Or enter custom amount</label>
                                    <input type="number" id="customAmount" placeholder="₹ Enter amount" 
                                           min="10" max="1000" oninput="updateRechargeAmount(this.value)">
                                </div>
                            </div>
                        </div>

                        <!-- Selected Plan Details -->
                        <div class="selected-plan" id="selectedPlan" style="display:none;">
                            <div class="plan-details">
                                <div class="plan-amount">₹<span id="planAmount">0</span></div>
                                <div class="plan-info">
                                    <div class="plan-description" id="planDescription">No plan selected</div>
                                    <div class="plan-validity" id="planValidity"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Recharges -->
                        <div class="recent-section" id="recentRecharges" style="display:none;">
                            <h4>Recent Recharges</h4>
                            <div class="recent-list">
                                <div class="recent-item">
                                    <div class="recent-info">
                                        <span class="recent-number">Enter number to recharge</span>
                                        <span class="recent-operator">Select operator • Choose plan</span>
                                    </div>
                                    <button class="repeat-btn" onclick="document.getElementById('mobileNumber').focus()">
                                        Start
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recharge Button -->
                    <div class="recharge-footer">
                        <button class="recharge-btn" id="rechargeBtn" onclick="processRecharge()">
                            <i class="fas fa-mobile-alt"></i>
                            Recharge Now
                        </button>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);
            
            // Show recent recharges after a delay
            setTimeout(() => {
                const recentSection = document.getElementById('recentRecharges');
                if (recentSection) recentSection.style.display = 'block';
            }, 1000);

            // Focus on mobile input and initialize validation
            setTimeout(() => {
                document.getElementById('mobileNumber').focus();
                updatePhonePlaceholder();
                checkRechargeFormValid();
            }, 300);
        }
        function payBills() { alert('Pay Bills functionality - Coming soon!'); }
        function addBankAccount() { window.location.href = 'link_with_bank.html'; }
        
        // Function to update balance after transaction
        function updateBalance(newBalance, bankName = null) {
            const balanceElement = document.querySelector('.balance-amount');
            if (balanceElement) {
                balanceElement.textContent = '₹' + new Intl.NumberFormat('en-IN').format(newBalance);
            }
            
            // Update bank specific balance if provided
            if (bankName) {
                const bankBalanceElements = document.querySelectorAll('.bank-balance-item span');
                bankBalanceElements.forEach(elem => {
                    if (elem.textContent.includes(bankName)) {
                        const nextSpan = elem.nextElementSibling;
                        if (nextSpan) {
                            nextSpan.textContent = '₹' + new Intl.NumberFormat('en-IN').format(newBalance);
                        }
                    }
                });
            }
        }
        
        // Function to add transaction to recent activity
        function addTransactionToActivity(type, counterparty, amount, note = '') {
            const activityList = document.querySelector('.activity-list');
            if (!activityList) return;
            
            const isCredit = type === 'credit';
            const initial = counterparty ? counterparty.charAt(0).toUpperCase() : 'U';
            const title = isCredit ? 'Money Received' : 'Payment Sent';
            const amountSign = isCredit ? '+' : '-';
            const amountDisplay = new Intl.NumberFormat('en-IN').format(Math.abs(amount));
            
            const newActivity = document.createElement('div');
            newActivity.className = 'activity-item';
            newActivity.innerHTML = `
                <div class=\"activity-profile\">
                    <div class=\"profile-pic ${isCredit ? 'money-received' : 'payment-sent'}\">${initial}</div>
                </div>
                <div class=\"activity-details\">
                    <div class=\"activity-title\">${title}</div>
                    <div class=\"activity-subtitle\">${note || counterparty}</div>
                    <div class=\"activity-time\">Just now</div>
                </div>
                <div class=\"activity-amount ${isCredit ? 'credit' : 'debit'}\">
                    ${amountSign}₹${amountDisplay}
                </div>
            `;
            
            // Insert at the beginning
            activityList.insertBefore(newActivity, activityList.firstChild);
        }
        
        // Function to show all transactions
        function showAllTransactions() {
            window.location.href = 'records.php';
        }
    function sendMoney() { window.location.href = 'phone.html'; }
        
        function referAndEarn() {
            showReferEarnModal();
        }
        
        function showReferEarnModal() {
            // Get user's referral code and earnings
            fetch('referral_system.php?action=generate_code')
                .then(response => response.json())
                .then(codeData => {
                    return fetch('referral_system.php?action=get_earnings')
                        .then(response => response.json())
                        .then(earningsData => {
                            const modal = document.createElement('div');
                            modal.className = 'profile-modal';
                            modal.innerHTML = `
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h2>Refer & Earn</h2>
                                        <span class="close-modal" onclick="closeModal()">&times;</span>
                                    </div>
                                    <div class="modal-body">
                                        <div class="refer-section">
                                            <div class="refer-icon">
                                                <i class="fas fa-gift" style="font-size: 48px; color: #1d9bf0;"></i>
                                            </div>
                                            <h3>Invite Friends & Earn Rewards</h3>
                                            <p>Share your referral code and earn ₹50 for each friend who joins!</p>
                                            
                                            <div class="referral-code">
                                                <label>Your Referral Code:</label>
                                                <div class="code-display">
                                                    <span class="code">${codeData.code || 'Loading...'}</span>
                                                    <button class="copy-btn" onclick="copyReferralCode(this)">
                                                        <i class="fas fa-copy"></i>
                                                    </button>
                                                </div>
                                            </div>
                            
                            <div class="share-options">
                                <h4>Share via:</h4>
                                <div class="share-buttons">
                                    <button class="share-btn whatsapp" onclick="shareViaWhatsApp()">
                                        <i class="fab fa-whatsapp"></i> WhatsApp
                                    </button>
                                    <button class="share-btn sms" onclick="shareViaSMS()">
                                        <i class="fas fa-sms"></i> SMS
                                    </button>
                                    <button class="share-btn link" onclick="copyReferralLink()">
                                        <i class="fas fa-link"></i> Copy Link
                                    </button>
                                </div>
                            </div>
                            
                            <div class="earnings-summary">
                                <h4>Your Earnings</h4>
                                <div class="earning-stats">
                                    <div class="stat">
                                        <span class="number">${earningsData.referrals || 0}</span>
                                        <span class="label">Friends Referred</span>
                                    </div>
                                    <div class="stat">
                                        <span class="number">₹${earningsData.earnings || 0}</span>
                                        <span class="label">Total Earned</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                            `;
                            document.body.appendChild(modal);
                        });
                })
                .catch(error => {
                    console.error('Error loading referral data:', error);
                    alert('Unable to load referral information. Please try again.');
                });
        }
        
        function showTransactionHistoryModal() {
            const modal = document.createElement('div');
            modal.className = 'profile-modal';
            modal.innerHTML = `
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Transaction History</h2>
                        <span class="close-modal" onclick="closeModal()">&times;</span>
                    </div>
                    <div class="modal-body">
                        <div class="transaction-filters">
                            <select class="filter-select">
                                <option value="all">All Transactions</option>
                                <option value="sent">Money Sent</option>
                                <option value="received">Money Received</option>
                                <option value="bills">Bill Payments</option>
                                <option value="recharge">Recharges</option>
                            </select>
                            <select class="filter-select">
                                <option value="30">Last 30 Days</option>
                                <option value="90">Last 3 Months</option>
                                <option value="365">Last Year</option>
                            </select>
                        </div>
                        
                        <div class="transaction-list">
                            <div class="transaction-item">
                                <div class="transaction-icon success">
                                    <i class="fas fa-arrow-down"></i>
                                </div>
                                <div class="transaction-details">
                                    <div class="transaction-title">Money Received</div>
                                    <div class="transaction-subtitle">Initial demo balance after bank link</div>
                                    <div class="transaction-time">4 hours ago</div>
                                </div>
                                <div class="transaction-amount success">+₹4,569</div>
                            </div>
                            
                            <div class="transaction-item">
                                <div class="transaction-icon sent">
                                    <i class="fas fa-arrow-up"></i>
                                </div>
                                <div class="transaction-details">
                                    <div class="transaction-title">Mobile Recharge</div>
                                    <div class="transaction-subtitle">Airtel Prepaid - 9876543210</div>
                                    <div class="transaction-time">Yesterday</div>
                                </div>
                                <div class="transaction-amount sent">-₹399</div>
                            </div>
                            
                            <div class="transaction-item">
                                <div class="transaction-icon sent">
                                    <i class="fas fa-zap"></i>
                                </div>
                                <div class="transaction-details">
                                    <div class="transaction-title">Electricity Bill</div>
                                    <div class="transaction-subtitle">MSEB Bill Payment</div>
                                    <div class="transaction-time">2 days ago</div>
                                </div>
                                <div class="transaction-amount sent">-₹1,250</div>
                            </div>
                            
                            <div class="transaction-item">
                                <div class="transaction-icon success">
                                    <i class="fas fa-arrow-down"></i>
                                </div>
                                <div class="transaction-details">
                                    <div class="transaction-title">Money Received</div>
                                    <div class="transaction-subtitle">From John Doe</div>
                                    <div class="transaction-time">3 days ago</div>
                                </div>
                                <div class="transaction-amount success">+₹2,500</div>
                            </div>
                        </div>
                        
                        <div class="view-all-transactions">
                            <button class="btn-view-all" onclick="window.location.href='records.php'">
                                View All Transactions
                            </button>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        function referAndEarn() { 
            showReferEarnModal();
        }
        function viewProfile() {
            // Close dropdown first
            const menu = document.getElementById('profileMenu');
            const profileBtn = document.querySelector('.profile-btn');
            if (menu) menu.classList.remove('show');
            if (profileBtn) profileBtn.classList.remove('active');

            // Open profile modal (server-backed) and generate QR
            showProfileModal();
            setTimeout(() => {
                if (typeof QRCode !== 'undefined') generateQRCode(); else generateQRCodeFallback();
            }, 200);

            // Fallback if QR still not present
            setTimeout(() => {
                const qrContainer = document.getElementById('userQRCode');
                if (qrContainer && (!qrContainer.innerHTML || qrContainer.innerHTML.trim() === '')) {
                    generateQRCodeFallback();
                }
            }, 1000);
        }
        function viewTransactions() { 
            window.location.href = 'transaction_history.php';
        }
        
        // Referral functions
        function copyReferralCode(button) {
            const code = button.parentElement.querySelector('.code').textContent;
            navigator.clipboard.writeText(code).then(() => {
                button.innerHTML = '<i class="fas fa-check"></i>';
                button.style.background = '#4CAF50';
                setTimeout(() => {
                    button.innerHTML = '<i class="fas fa-copy"></i>';
                    button.style.background = '#1d9bf0';
                }, 2000);
            });
        }
        
        function shareViaWhatsApp() {
            const code = document.querySelector('.code').textContent;
            const message = `Join PaySecure using my referral code ${code} and get ₹50 bonus! Download now: https://paysecure.app`;
            window.open(`https://wa.me/?text=${encodeURIComponent(message)}`, '_blank');
        }
        
        function shareViaSMS() {
            const code = document.querySelector('.code').textContent;
            const message = `Join PaySecure using my referral code ${code} and get ₹50 bonus!`;
            window.open(`sms:?body=${encodeURIComponent(message)}`, '_blank');
        }
        
        function copyReferralLink() {
            const code = document.querySelector('.code').textContent;
            const link = `https://paysecure.app/ref/${code}`;
            navigator.clipboard.writeText(link).then(() => {
                alert('Referral link copied to clipboard!');
            });
        }
        
        function enterReferralCode() {
            // Close profile menu first
            document.getElementById('profileMenu').classList.remove('show');
            document.querySelector('.profile-btn').classList.remove('active');
            
            const modal = document.createElement('div');
            modal.className = 'profile-modal';
            modal.innerHTML = `
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Enter Referral Code</h2>
                        <span class="close-modal" onclick="closeModal()">&times;</span>
                    </div>
                    <div class="modal-body">
                        <div class="referral-entry-section">
                            <div class="referral-entry-icon">
                                <i class="fas fa-gift" style="font-size: 48px; color: #1d9bf0;"></i>
                            </div>
                            <h3>Got a Referral Code?</h3>
                            <p>Enter your friend's referral code to earn ₹50 bonus instantly!</p>
                            
                            <div class="referral-input-group">
                                <label>Referral Code:</label>
                                <div class="input-with-button">
                                    <input type="text" 
                                           id="enterReferralInput" 
                                           placeholder="Enter code (e.g., PAYS123456)"
                                           style="text-transform: uppercase;"
                                           maxlength="10">
                                    <button class="apply-btn" onclick="applyReferralCode()">
                                        Apply
                                    </button>
                                </div>
                                <div id="referralMessage" class="referral-message"></div>
                            </div>
                            
                            <div class="referral-benefits">
                                <h4>Benefits:</h4>
                                <ul>
                                    <li><i class="fas fa-check" style="color: #4CAF50;"></i> Instant ₹50 bonus</li>
                                    <li><i class="fas fa-check" style="color: #4CAF50;"></i> Your friend also gets ₹50</li>
                                    <li><i class="fas fa-check" style="color: #4CAF50;"></i> No minimum balance required</li>
                                </ul>
                            </div>
                            
                            <div class="note">
                                <p><strong>Note:</strong> Referral codes can only be used once per account.</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        
        function applyReferralCode() {
            const input = document.getElementById('enterReferralInput');
            const message = document.getElementById('referralMessage');
            const code = input.value.toUpperCase().trim();
            
            if (!code) {
                message.innerHTML = '<span style="color: #FF5722;">Please enter a referral code</span>';
                return;
            }
            
            if (code.length < 4) {
                message.innerHTML = '<span style="color: #FF5722;">Referral code too short</span>';
                return;
            }
            
            // Show loading
            message.innerHTML = '<span style="color: #1d9bf0;"><i class="fas fa-spinner fa-spin"></i> Applying referral code...</span>';
            
            // Apply referral code via backend
            const formData = new FormData();
            formData.append('action', 'apply_code');
            formData.append('code', code);
            
            fetch('referral_system.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    message.innerHTML = `<span style="color: #4CAF50;"><i class="fas fa-check"></i> ${data.message}</span>`;
                    
                    setTimeout(() => {
                        alert(`🎉 Congratulations! ₹${data.bonus_amount} has been added to your account!`);
                        closeModal();
                        
                        // Refresh the page to show updated balance
                        location.reload();
                    }, 1500);
                } else {
                    message.innerHTML = `<span style="color: #FF5722;"><i class="fas fa-times"></i> ${data.message}</span>`;
                }
            })
            .catch(error => {
                message.innerHTML = '<span style="color: #FF5722;"><i class="fas fa-times"></i> Error applying code. Please try again.</span>';
            });
        }
        function viewSettings() { showSettingsModal(); }
        function changePassword() { document.getElementById('profileMenu').classList.remove('show'); document.querySelector('.profile-btn').classList.remove('active'); showChangePasswordModal(); }
        function changeUpiPin() { document.getElementById('profileMenu').classList.remove('show'); document.querySelector('.profile-btn').classList.remove('active'); showChangeUpiPinModal(); }
        function logout() { if(confirm('Are you sure you want to logout?')) { window.location.href = 'login.html'; } }

        // Refresh header/profile from server session and update UI
        function refreshHeaderFromServer() {
            fetch('mock_api.php?action=server_whoami', { credentials: 'same-origin' })
                .then(r => r.json())
                .then(res => {
                    if (res && res.ok && res.account) {
                        const acc = res.account;
                        // header
                        const profileNameEl = document.getElementById('profileName');
                        if (profileNameEl && acc.name) profileNameEl.textContent = acc.name;
                        const avatar = document.getElementById('profileAvatar');
                        if (avatar && acc.name) avatar.textContent = acc.name.charAt(0).toUpperCase();
                        // update local userData for modal/generation
                        userData.name = acc.name || userData.name;
                        userData.email = acc.email || userData.email;
                        userData.phone = (acc.country_code ? acc.country_code + ' ' : '') + (acc.phone_number || userData.phone);
                        userData.upiId = (acc.name ? acc.name.toLowerCase().replace(/\s+/g,'') : (userData.name||'user')) + '@paysecure';
                    }
                }).catch(e => { console.debug('refreshHeaderFromServer failed', e); });
        }

        // Profile Modal Function (uses userData) — fetch latest server session before rendering
        function showProfileModal() {
            fetch('mock_api.php?action=server_whoami', { credentials: 'same-origin' })
                .then(r => r.json())
                .then(res => {
                    if (res && res.ok && res.account) {
                        const acc = res.account;
                        // ensure userData updated
                        userData.name = acc.name || userData.name;
                        userData.email = acc.email || userData.email;
                        userData.phone = (acc.country_code ? acc.country_code + ' ' : '') + (acc.phone_number || userData.phone);
                        userData.upiId = (acc.name ? acc.name.toLowerCase().replace(/\s+/g,'') : (userData.name||'user')) + '@paysecure';
                    }
                }).catch(() => {})
                .finally(() => {
                    const modal = document.createElement('div');
                    modal.className = 'profile-modal';
                    modal.innerHTML = `
                <div class="modal-content">
                    <div class="modal-header">
                                <h2>My Profile</h2>
                        <span class="close-modal" onclick="closeModal()">&times;</span>
                    </div>
                    <div class="modal-body">
                        <div class="profile-section-modal">
                            <div class="profile-avatar-large">${(userData.name || 'User').charAt(0).toUpperCase()}</div>
                            <h3>${userData.name || 'xyz'}</h3>
                        </div>
                        <div class="profile-details-clean">
                            <div class="detail-row">
                                <span class="detail-label">Phone Number:</span>
                                <div class="detail-value">${userData.phone || '+91 4567890000'}</div>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">UPI ID:</span>
                                <div class="detail-value-with-copy">
                                    <span class="upi-id">${userData.upiId || 'xyz@paysecure'}</span>
                                    <button class="copy-icon-btn" onclick="copyUpiId(event)" title="Copy UPI ID">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Account Type:</span>
                                <div class="detail-value">Premium</div>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Member Since:</span>
                                <div class="detail-value">January 2024</div>
                            </div>
                            <div class="detail-item">
                                <label>KYC Status:</label>
                                <span class="verified">Verified ✓</span>
                            </div>
                        </div>
                        
                        <div class="qr-section">
                            <h4>Your UPI QR Code</h4>
                            <div class="qr-container">
                                <div class="qr-code" id="userQRCode"></div>
                                <div class="qr-actions">
                                    <button class="qr-btn" onclick="downloadQR()">
                                        <i class="fas fa-download"></i>
                                        Download
                                    </button>
                                    <button class="qr-btn" onclick="shareQR()">
                                        <i class="fas fa-share"></i>
                                        Share
                                    </button>
                                </div>
                            </div>
                            <p class="qr-note">Share this QR code with others to receive payments instantly</p>
                        </div>
                        <div class="profile-actions">
                            <button class="btn-edit" onclick="editProfile()">Edit Profile</button>
                            <button class="btn-security" onclick="showSecurity()">Security Settings</button>
                        </div>
                    </div>
                </div>
            `;
                    document.body.appendChild(modal);
                    // After modal is appended, generate the QR inside it
                    // Use a tiny timeout to ensure layout is ready
                    setTimeout(() => {
                        if (typeof QRCode !== 'undefined') generateQRCode(); else generateQRCodeFallback();
                    }, 50);
                });
        }

        // Other modal and helper functions (kept minimal) ...
        function showSettingsModal() {
            const modal = document.createElement('div');
            modal.className = 'profile-modal';
            modal.innerHTML = `
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Settings</h2>
                        <span class="close-modal" onclick="closeModal()">&times;</span>
                    </div>
                    <div class="modal-body">
                        <div class="settings-section">
                            <h3>Account Settings</h3>
                            <div class="setting-item">
                                <label>
                                    <input type="checkbox" checked> Email Notifications
                                </label>
                            </div>
                            <div class="setting-item">
                                <label>
                                    <input type="checkbox" checked> SMS Alerts
                                </label>
                            </div>
                            <div class="setting-item">
                                <label>
                                    <input type="checkbox"> Push Notifications
                                </label>
                            </div>
                        </div>
                        <div class="settings-section">
                            <h3>Security</h3>
                            <div class="setting-item">
                                <label>
                                    <input type="checkbox" checked> Two-Factor Authentication
                                </label>
                            </div>
                            <div class="setting-item">
                                <label>
                                    <input type="checkbox" checked> Biometric Login
                                </label>
                            </div>
                        </div>
                        
                        <div class="settings-section">
                            <h3>Password & Security</h3>
                            <div class="setting-action-item" onclick="changePasswordFromSettings()">
                                <div class="setting-action-content">
                                    <i class="fas fa-lock"></i>
                                    <div class="setting-action-text">
                                        <span class="action-title">Change Password</span>
                                        <span class="action-subtitle">Update your login password</span>
                                    </div>
                                </div>
                                <i class="fas fa-chevron-right"></i>
                            </div>
                            <div class="setting-action-item" onclick="changeUpiPinFromSettings()">
                                <div class="setting-action-content">
                                    <i class="fas fa-key"></i>
                                    <div class="setting-action-text">
                                        <span class="action-title">Change UPI PIN</span>
                                        <span class="action-subtitle">Update your 4-digit UPI PIN</span>
                                    </div>
                                </div>
                                <i class="fas fa-chevron-right"></i>
                            </div>
                        </div>
                        <div class="settings-section">
                            <h3>Privacy</h3>
                            <div class="setting-item">
                                <label>
                                    <input type="checkbox"> Share Usage Data
                                </label>
                            </div>
                        </div>
                        <button class="btn-save" onclick="saveSettings()">Save Settings</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        function showChangePasswordModal() { alert('Change password - coming soon'); }
        function showChangeUpiPinModal() { alert('Change UPI PIN - coming soon'); }
        function closeModal() { const modal = document.querySelector('.profile-modal'); if (modal) modal.remove(); }
        function editProfile() { alert('Edit Profile - coming soon'); closeModal(); }
        function showSecurity() { alert('Security - coming soon'); closeModal(); }

        // UPI ID and QR code helpers
        function generateUpiId() {
            return userData.upiId || (userData.name.toLowerCase().replace(/\s+/g, '') + '@paysecure');
        }

        function copyUpiId(e) {
            const upiId = document.querySelector('.upi-id').textContent;
            navigator.clipboard.writeText(upiId).then(function() {
                const button = e ? e.target.closest('.copy-btn') : null;
                if (button) {
                    const original = button.innerHTML;
                    button.innerHTML = '<i class="fas fa-check"></i>';
                    button.style.background = '#4CAF50';
                    setTimeout(() => { button.innerHTML = original; button.style.background = ''; }, 1500);
                }
            }).catch(function(err) { console.error('Could not copy UPI ID: ', err); alert('Failed to copy UPI ID'); });
        }

        function generateQRCode() {
            try {
                const upiId = generateUpiId();
                const upiString = `upi://pay?pa=${upiId}&pn=${encodeURIComponent(userData.name || 'PaySecure User')}&cu=INR`;
                const qrContainer = document.getElementById('userQRCode');
                if (!qrContainer) return;

                // Prepare a canvas element and pass it to the library (safer than passing a div)
                qrContainer.innerHTML = '';
                const canvas = document.createElement('canvas');
                canvas.width = 180; canvas.height = 180;
                qrContainer.appendChild(canvas);

                if (typeof QRCode === 'undefined' || !QRCode.toCanvas) {
                    generateQRCodeFallback();
                    return;
                }

                QRCode.toCanvas(canvas, upiString, { width: 180, height: 180, margin: 1, errorCorrectionLevel: 'M' }, function (error) {
                    if (error) {
                        console.error('QR Code generation error:', error);
                        generateQRCodeFallback();
                    }
                });
            } catch (e) {
                console.error('generateQRCode exception:', e);
                generateQRCodeFallback();
            }
        }

        function generateQRCodeFallback() {
            const upiId = generateUpiId();
            const upiString = `upi://pay?pa=${upiId}&pn=${encodeURIComponent(userData.name || 'PaySecure User')}&cu=INR`;
            const encodedData = encodeURIComponent(upiString);
            const qrContainer = document.getElementById('userQRCode');
            if (qrContainer) {
                qrContainer.innerHTML = `<img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${encodedData}" alt="UPI QR Code">`;
            }
        }

        function downloadQR() {
            const upiId = generateUpiId();
            const upiString = `upi://pay?pa=${upiId}&pn=${userData.name || 'PaySecure User'}&cu=INR`;
            
            // Create a larger canvas for download with branding
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            
            canvas.width = 400;
            canvas.height = 450;
            
            // Set background
            ctx.fillStyle = 'white';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            
            try {
                // If a QR is already shown in the profile modal, reuse it (canvas or img)
                const existingQRContainer = document.getElementById('userQRCode');
                if (existingQRContainer) {
                    const existingCanvas = existingQRContainer.querySelector('canvas');
                    const existingImg = existingQRContainer.querySelector('img');
                    if (existingCanvas) {
                        // Draw existing small canvas onto larger branded canvas (scale up)
                        ctx.fillStyle = 'white';
                        ctx.fillRect(0, 0, canvas.width, canvas.height);
                        // center scaled QR
                        const qrSize = 300;
                        const sx = existingCanvas.width;
                        const sy = existingCanvas.height;
                        ctx.drawImage(existingCanvas, 0, 0, sx, sy, (canvas.width - qrSize)/2, 60, qrSize, qrSize);

                        // Add branding text
                        ctx.fillStyle = '#333';
                        ctx.font = 'bold 18px Arial';
                        ctx.textAlign = 'center';
                        ctx.fillText('PaySecure QR Code', canvas.width / 2, 30);
                        ctx.font = '14px Arial';
                        ctx.fillText(`Pay to: ${userData.name || 'PaySecure User'}`, canvas.width / 2, 370);
                        ctx.fillText(`UPI ID: ${upiId}`, canvas.width / 2, 390);
                        ctx.fillText('Scan to pay instantly', canvas.width / 2, 420);

                        const link = document.createElement('a');
                        link.href = canvas.toDataURL();
                        link.download = 'PaySecure-QR-Code.png';
                        link.click();
                        return;
                    } else if (existingImg) {
                        // Use existing image element
                        const img = new Image();
                        img.crossOrigin = 'anonymous';
                        img.onload = function() {
                            ctx.fillStyle = 'white';
                            ctx.fillRect(0, 0, canvas.width, canvas.height);
                            const qrSize = 300;
                            ctx.drawImage(img, (canvas.width - qrSize)/2, 60, qrSize, qrSize);
                            ctx.fillStyle = '#333';
                            ctx.font = 'bold 18px Arial';
                            ctx.textAlign = 'center';
                            ctx.fillText('PaySecure QR Code', canvas.width / 2, 30);
                            ctx.font = '14px Arial';
                            ctx.fillText(`Pay to: ${userData.name || 'PaySecure User'}`, canvas.width / 2, 370);
                            ctx.fillText(`UPI ID: ${upiId}`, canvas.width / 2, 390);
                            ctx.fillText('Scan to pay instantly', canvas.width / 2, 420);
                            const link = document.createElement('a');
                            link.href = canvas.toDataURL();
                            link.download = 'PaySecure-QR-Code.png';
                            link.click();
                        };
                        img.onerror = function(err) {
                            console.error('Failed to load existing QR image for download:', err);
                            // fallback to generate via library
                            tryGenerateAndDownload();
                        };
                        img.src = existingImg.src;
                        return;
                    }
                }

                // If no existing QR DOM, try to generate using library (fallback)
                tryGenerateAndDownload();

                function tryGenerateAndDownload() {
                    if (typeof QRCode === 'undefined' || !QRCode.toCanvas) {
                        console.warn('QRCode.toCanvas not available, using fallback image');
                        // Fallback: use remote QR API and download
                        const encodedData = encodeURIComponent(upiString);
                        const link = document.createElement('a');
                        link.href = `https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=${encodedData}`;
                        link.download = 'PaySecure-QR-Code.png';
                        link.click();
                        return;
                    }

                    QRCode.toCanvas(canvas, upiString, {
                        width: 300,
                        margin: 2,
                        color: { dark: '#000000', light: '#ffffff' }
                    }, function (error) {
                        if (error) {
                            console.error('Download QR Code generation error:', error);
                            alert('Failed to generate QR code for download');
                            return;
                        }
                        // Add branding
                        ctx.fillStyle = '#333';
                        ctx.font = 'bold 18px Arial';
                        ctx.textAlign = 'center';
                        ctx.fillText('PaySecure QR Code', canvas.width / 2, 30);
                        ctx.font = '14px Arial';
                        ctx.fillText(`Pay to: ${userData.name || 'PaySecure User'}`, canvas.width / 2, 370);
                        ctx.fillText(`UPI ID: ${upiId}`, canvas.width / 2, 390);
                        ctx.fillText('Scan to pay instantly', canvas.width / 2, 420);
                        const link = document.createElement('a');
                        link.href = canvas.toDataURL();
                        link.download = 'PaySecure-QR-Code.png';
                        link.click();
                    });
                }
            } catch (error) {
                console.error('QR Code generation error:', error);
                alert('Failed to generate QR code');
            }
        }

        function shareQR() {
            const upiId = generateUpiId();
            const upiString = `upi://pay?pa=${upiId}&pn=${userData.name || 'PaySecure User'}&cu=INR`;
            
            // Create canvas for sharing
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            
            canvas.width = 400;
            canvas.height = 450;
            
            // Set background
            ctx.fillStyle = 'white';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            
            try {
                // If a QR is already shown in the profile modal, reuse it (canvas or img)
                const existingQRContainer = document.getElementById('userQRCode');
                if (existingQRContainer) {
                    const existingCanvas = existingQRContainer.querySelector('canvas');
                    const existingImg = existingQRContainer.querySelector('img');
                    if (existingCanvas) {
                        // Draw existing small canvas onto larger branded canvas (scale up)
                        ctx.fillStyle = 'white';
                        ctx.fillRect(0, 0, canvas.width, canvas.height);
                        // center scaled QR
                        const qrSize = 300;
                        const sx = existingCanvas.width;
                        const sy = existingCanvas.height;
                        ctx.drawImage(existingCanvas, 0, 0, sx, sy, (canvas.width - qrSize)/2, 60, qrSize, qrSize);

                        // Add branding text
                        ctx.fillStyle = '#333';
                        ctx.font = 'bold 18px Arial';
                        ctx.textAlign = 'center';
                        ctx.fillText('PaySecure QR Code', canvas.width / 2, 30);
                        ctx.font = '14px Arial';
                        ctx.fillText(`Pay to: ${userData.name || 'PaySecure User'}`, canvas.width / 2, 370);
                        ctx.fillText(`UPI ID: ${upiId}`, canvas.width / 2, 390);
                        ctx.fillText('Scan to pay instantly', canvas.width / 2, 420);

                        // Try Web Share API with the composed canvas
                        shareComposedCanvas(canvas, upiId);
                        return;
                    } else if (existingImg) {
                        // Use existing image element
                        const img = new Image();
                        img.crossOrigin = 'anonymous';
                        img.onload = function() {
                            ctx.fillStyle = 'white';
                            ctx.fillRect(0, 0, canvas.width, canvas.height);
                            const qrSize = 300;
                            ctx.drawImage(img, (canvas.width - qrSize)/2, 60, qrSize, qrSize);
                            ctx.fillStyle = '#333';
                            ctx.font = 'bold 18px Arial';
                            ctx.textAlign = 'center';
                            ctx.fillText('PaySecure QR Code', canvas.width / 2, 30);
                            ctx.font = '14px Arial';
                            ctx.fillText(`Pay to: ${userData.name || 'PaySecure User'}`, canvas.width / 2, 370);
                            ctx.fillText(`UPI ID: ${upiId}`, canvas.width / 2, 390);
                            ctx.fillText('Scan to pay instantly', canvas.width / 2, 420);
                            shareComposedCanvas(canvas, upiId);
                        };
                        img.onerror = function(err) {
                            console.error('Failed to load existing QR image for sharing:', err);
                            // fallback to generate via library
                            tryGenerateAndShare();
                        };
                        img.src = existingImg.src;
                        return;
                    }
                }

                // If no existing QR DOM, try to generate using library (fallback)
                tryGenerateAndShare();

                function tryGenerateAndShare() {
                    if (typeof QRCode === 'undefined' || !QRCode.toCanvas) {
                        console.warn('QRCode.toCanvas not available for sharing, using fallback download');
                        // Fallback: use remote QR API and download instead of share
                        const encodedData = encodeURIComponent(upiString);
                        const link = document.createElement('a');
                        link.href = `https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=${encodedData}`;
                        link.download = 'PaySecure-QR-Code.png';
                        link.click();
                        alert('QR code downloaded! You can share the downloaded image manually.');
                        return;
                    }

                    QRCode.toCanvas(canvas, upiString, {
                        width: 300,
                        margin: 2,
                        color: { dark: '#000000', light: '#ffffff' }
                    }, function (error) {
                        if (error) {
                            console.error('Share QR Code generation error:', error);
                            alert('Failed to generate QR code for sharing');
                            return;
                        }
                        // Add branding
                        ctx.fillStyle = '#333';
                        ctx.font = 'bold 18px Arial';
                        ctx.textAlign = 'center';
                        ctx.fillText('PaySecure QR Code', canvas.width / 2, 30);
                        ctx.font = '14px Arial';
                        ctx.fillText(`Pay to: ${userData.name || 'PaySecure User'}`, canvas.width / 2, 370);
                        ctx.fillText(`UPI ID: ${upiId}`, canvas.width / 2, 390);
                        ctx.fillText('Scan to pay instantly', canvas.width / 2, 420);
                        shareComposedCanvas(canvas, upiId);
                    });
                }

                function shareComposedCanvas(canvas, upiId) {
                    canvas.toBlob(function(blob) {
                        const file = new File([blob], 'PaySecure-QR-Code.png', { type: 'image/png' });
                        
                        if (navigator.share && navigator.canShare && navigator.canShare({ files: [file] })) {
                            navigator.share({
                                title: 'My PaySecure QR Code',
                                text: `Pay me using this QR code! UPI ID: ${upiId}`,
                                files: [file]
                            }).catch(err => {
                                console.log('Web Share API error:', err);
                                downloadGeneratedQR(canvas);
                            });
                        } else {
                            // Fallback: download the QR code
                            downloadGeneratedQR(canvas);
                            alert('QR code downloaded! You can share the downloaded image manually.');
                        }
                    });
                }
            } catch (error) {
                console.error('QR Code generation error:', error);
                alert('Failed to generate QR code for sharing');
            }
        }

        function downloadGeneratedQR(canvas) {
            const link = document.createElement('a');
            link.href = canvas.toDataURL();
            link.download = 'PaySecure-QR-Code.png';
            link.click();
        }

        // State management for UPI PIN
        let upiPinSubmitting = false;
        let currentRechargeAmount = 0;
        let currentTransactionType = 'recharge';
        
        function updateBalanceDisplay(newBalance) {
            const balanceElements = document.querySelectorAll('.balance-amount, #balanceAmount');
            balanceElements.forEach(element => {
                if (element) {
                    element.textContent = `₹${Number(newBalance).toLocaleString('en-IN', {minimumFractionDigits: 2})}`;
                }
            });
        }
        
        // Debug function - can be called from console
        window.testUpiModal = function() {
            console.log('Testing UPI modal directly');
            showUpiPinModal();
        };
        
        window.testReceiptModal = function() {
            console.log('Testing receipt modal directly');
            showDirectReceipt('+91 9876543210', 'airtel', 149);
        };

        // Mobile Recharge Functions
        let selectedRechargeAmount = 0;
        let selectedPlanDetails = null;

        function closeRechargeModal() {
            const modal = document.querySelector('.recharge-modal');
            if (modal) modal.remove();
        }

        function validateMobileNumber(input) {
            // Only allow digits
            input.value = input.value.replace(/[^\d]/g, '');
            
            const countrySelect = document.getElementById('countryCode');
            const selectedOption = countrySelect.options[countrySelect.selectedIndex];
            const expectedDigits = parseInt(selectedOption.getAttribute('data-digits')) || 10;
            const countryCode = selectedOption.value;
            
            const errorDiv = document.getElementById('mobileError');
            const rechargeBtn = document.getElementById('rechargeBtn');
            
            console.log('Validating:', input.value, 'Expected digits:', expectedDigits, 'Country:', countryCode);
            
            if (input.value.length === expectedDigits) {
                errorDiv.style.display = 'none';
                input.style.borderColor = '#4caf50';
                checkRechargeFormValid();
            } else if (input.value.length > 0) {
                if (input.value.length < expectedDigits) {
                    errorDiv.textContent = `Enter ${expectedDigits - input.value.length} more digits (${expectedDigits} digits required)`;
                } else {
                    errorDiv.textContent = `Too many digits (${expectedDigits} digits required)`;
                    input.value = input.value.slice(0, expectedDigits); // Limit to expected length
                }
                errorDiv.style.display = 'block';
                input.style.borderColor = '#f44336';
                checkRechargeFormValid();  // Just update validation status, don't disable
            } else {
                errorDiv.style.display = 'none';
                input.style.borderColor = '#ddd';
                checkRechargeFormValid();  // Just update validation status, don't disable
            }
        }

        function selectOperator(operator, element) {
            // Remove previous selection
            document.querySelectorAll('.operator-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Select current operator
            element.classList.add('selected');
            document.getElementById('selectedOperator').value = operator;
            
            console.log('Selected operator:', operator);
            checkRechargeFormValid();
        }

        function selectPlan(amount, description, validity) {
            selectedRechargeAmount = amount;
            selectedPlanDetails = { amount, description, validity };
            currentRechargeAmount = parseInt(amount.replace('₹', ''));
            currentTransactionType = 'recharge';
            
            // Update plan display
            document.getElementById('planAmount').textContent = amount;
            document.getElementById('planDescription').textContent = description;
            document.getElementById('planValidity').textContent = `Valid for ${validity}`;
            document.getElementById('selectedPlan').style.display = 'block';
            
            // Clear custom amount
            document.getElementById('customAmount').value = '';
            
            // Highlight selected plan button
            document.querySelectorAll('.plan-btn').forEach(btn => btn.classList.remove('selected'));
            event.target.classList.add('selected');
            
            checkRechargeFormValid();
        }

        function updateRechargeAmount(amount) {
            if (amount && amount >= 10) {
                selectedRechargeAmount = parseInt(amount);
                selectedPlanDetails = { 
                    amount: selectedRechargeAmount, 
                    description: 'Custom recharge amount', 
                    validity: 'As per operator' 
                };
                
                // Update display
                document.getElementById('planAmount').textContent = amount;
                document.getElementById('planDescription').textContent = 'Custom recharge amount';
                document.getElementById('planValidity').textContent = 'As per operator terms';
                document.getElementById('selectedPlan').style.display = 'block';
                
                // Remove plan button selections
                document.querySelectorAll('.plan-btn').forEach(btn => btn.classList.remove('selected'));
            } else {
                selectedRechargeAmount = 0;
                selectedPlanDetails = null;
                document.getElementById('selectedPlan').style.display = 'none';
            }
            checkRechargeFormValid();
        }

        function checkRechargeFormValid() {
            const mobile = document.getElementById('mobileNumber').value;
            const operator = document.getElementById('selectedOperator').value;
            const rechargeBtn = document.getElementById('rechargeBtn');
            
            const countrySelect = document.getElementById('countryCode');
            const selectedOption = countrySelect ? countrySelect.options[countrySelect.selectedIndex] : null;
            const expectedDigits = selectedOption ? parseInt(selectedOption.getAttribute('data-digits')) || 10 : 10;
            
            const mobileValid = mobile.length === expectedDigits;
            const operatorValid = operator && operator.length > 0;
            const amountValid = selectedRechargeAmount > 0;
            
            console.log('Validation check:', {
                mobile: mobile,
                mobileLength: mobile.length,
                expectedDigits: expectedDigits,
                mobileValid: mobileValid,
                operator: operator,
                operatorValid: operatorValid,
                selectedRechargeAmount: selectedRechargeAmount,
                amountValid: amountValid
            });
            
            const isValid = mobileValid && operatorValid && amountValid;
            
            // Keep button always enabled, show validation status in title
            if (rechargeBtn) {
                rechargeBtn.disabled = false;  // Always keep enabled
                rechargeBtn.style.opacity = '1';  // Always full opacity
                
                if (!isValid) {
                    let message = 'Missing: ';
                    if (!mobileValid) message += 'Phone, ';
                    if (!operatorValid) message += 'Operator, ';
                    if (!amountValid) message += 'Amount, ';
                    rechargeBtn.title = message.slice(0, -2);
                } else {
                    rechargeBtn.title = 'Click to recharge';
                }
            }
        }

        function updatePhonePlaceholder() {
            const countrySelect = document.getElementById('countryCode');
            const selectedOption = countrySelect.options[countrySelect.selectedIndex];
            const digits = selectedOption.getAttribute('data-digits') || '10';
            const countryCode = selectedOption.value;
            
            let placeholder = '';
            switch(countryCode) {
                case '+91': placeholder = '9876543210'; break;
                case '+1': placeholder = '2025551234'; break;
                case '+44': placeholder = '7700123456'; break;
                case '+33': placeholder = '123456789'; break;
                case '+49': placeholder = '1512345678'; break;
                case '+81': placeholder = '9012345678'; break;
                case '+86': placeholder = '13812345678'; break;
                case '+7': placeholder = '9012345678'; break;
                case '+61': placeholder = '412345678'; break;
                case '+971': placeholder = '501234567'; break;
                case '+966': placeholder = '501234567'; break;
                case '+65': placeholder = '91234567'; break;
                default: placeholder = '1234567890';
            }
            
            document.getElementById('mobileNumber').placeholder = placeholder;
            document.getElementById('mobileNumber').setAttribute('maxlength', digits);
            
            // Revalidate current number with new country code
            validateMobileNumber(document.getElementById('mobileNumber'));
        }

        function getFullPhoneNumber() {
            const countryCode = document.getElementById('countryCode').value;
            const mobile = document.getElementById('mobileNumber').value.replace(/[^\d]/g, '');
            return countryCode + ' ' + mobile;
        }

        function showRecentContacts() {
            // Focus on mobile input for user to enter their number
            document.getElementById('mobileNumber').focus();
            updatePhonePlaceholder();
        }

        function repeatRecharge(mobile, operator, amount) {
            document.getElementById('mobileNumber').value = mobile;
            validateMobileNumber(document.getElementById('mobileNumber'));
            
            // Select operator
            const operatorCard = document.querySelector(`[onclick="selectOperator('${operator}', this)"]`);
            if (operatorCard) selectOperator(operator, operatorCard);
            
            // Select amount
            selectPlan(amount, 'Repeated recharge', '28 days');
        }

        function processRecharge() {
            // Show UPI PIN modal for recharge confirmation
            showRechargeUpiPinModal();
        }

        function showRechargeUpiPinModal() {
            const mobile = document.getElementById('mobileNumber').value;
            const operator = document.getElementById('selectedOperator').value;
            const countryCode = document.getElementById('countryCode')?.value || '+91';
            
            // Validate mobile number
            if (!mobile || mobile.length === 0) {
                alert('Please enter a mobile number');
                document.getElementById('mobileNumber').focus();
                return;
            }
            
            const countrySelect = document.getElementById('countryCode');
            const selectedOption = countrySelect ? countrySelect.options[countrySelect.selectedIndex] : null;
            const expectedDigits = selectedOption ? parseInt(selectedOption.getAttribute('data-digits')) || 10 : 10;
            
            if (mobile.length !== expectedDigits) {
                alert(`Please enter a valid ${expectedDigits}-digit mobile number`);
                document.getElementById('mobileNumber').focus();
                return;
            }
            
            if (!operator || operator.length === 0) {
                alert('Please select an operator');
                return;
            }
            
            if (!selectedRechargeAmount || selectedRechargeAmount <= 0) {
                alert('Please select a recharge amount');
                return;
            }

            // Store recharge data globally
            window.currentRechargeData = {
                mobile_number: mobile,
                operator: operator,
                amount: selectedRechargeAmount,
                country_code: countryCode
            };

            // Close recharge modal first
            closeRechargeModal();

            // Show UPI PIN modal
            const upiModal = document.createElement('div');
            upiModal.className = 'upi-pin-modal';
            upiModal.innerHTML = `
                <div class="modal-backdrop" onclick="closeRechargeUpiPinModal()"></div>
                <div class="upi-pin-container">
                    <div class="pin-header">
                        <h3>Enter UPI PIN</h3>
                        <p>Confirm recharge of ₹${selectedRechargeAmount} for ${countryCode} ${mobile}</p>
                    </div>
                    
                    <div class="pin-input-section">
                        <input type="password" class="pin-input-field" id="rechargeUpiPinInput" 
                               placeholder="Enter UPI PIN" maxlength="6" 
                               oninput="handleRechargePinInput(this)" 
                               onkeydown="if(event.key==='Enter') processRechargeWithPin()">
                        <div class="pin-error" id="rechargePinError" style="display:none;"></div>
                    </div>
                    
                    <div class="pin-actions">
                        <button class="pin-cancel-btn" onclick="closeRechargeUpiPinModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button class="pin-submit-btn" onclick="processRechargeWithPin()" id="rechargePinSubmitBtn">
                            <i class="fas fa-check"></i> Confirm Recharge
                        </button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(upiModal);
            
            setTimeout(() => {
                document.getElementById('rechargeUpiPinInput').focus();
            }, 100);
        }

        function closeRechargeUpiPinModal() {
            const modal = document.querySelector('.upi-pin-modal');
            if (modal) modal.remove();
            window.currentRechargeData = null;
        }

        function handleRechargePinInput(input) {
            input.value = input.value.replace(/[^0-9]/g, '').slice(0, 6);
            
            const submitBtn = document.getElementById('rechargePinSubmitBtn');
            const errorDiv = document.getElementById('rechargePinError');
            
            if (input.value.length >= 4) {
                input.style.borderColor = '#4caf50';
                if (submitBtn) submitBtn.disabled = false;
                if (errorDiv) errorDiv.style.display = 'none';
            } else {
                input.style.borderColor = '#ddd';
                if (submitBtn) submitBtn.disabled = true;
            }
        }

        function processRechargeWithPin() {
            const pinInput = document.getElementById('rechargeUpiPinInput');
            const pin = pinInput ? pinInput.value.trim() : '';
            const rechargeData = window.currentRechargeData;
            
            if (!pin || (pin.length !== 4 && pin.length !== 6)) {
                const errorDiv = document.getElementById('rechargePinError');
                if (errorDiv) {
                    errorDiv.textContent = 'Please enter your UPI PIN (4 or 6 digits)';
                    errorDiv.style.display = 'block';
                }
                return;
            }
            
            if (!rechargeData) {
                alert('Recharge data not found. Please try again.');
                return;
            }
            
            // Add UPI PIN to recharge data
            rechargeData.upi_pin = pin;
            
            // Close UPI PIN modal
            closeRechargeUpiPinModal();
            
            // Show processing
            showDirectProcessing();
        }

        function showDirectProcessing() {
            const rechargeData = window.currentRechargeData;
            if (!rechargeData) {
                alert('Recharge data not found. Please try again.');
                return;
            }
            
            const fullMobile = rechargeData.country_code + ' ' + rechargeData.mobile_number;
            const operatorName = rechargeData.operator ? rechargeData.operator.toUpperCase() : 'UNKNOWN';
            const amount = rechargeData.amount;
            
            // Show processing modal
            const processingModal = document.createElement('div');
            processingModal.className = 'processing-modal';
            
            let html = '<div class="processing-container">';
            html += '<div class="processing-spinner"><i class="fas fa-spinner fa-spin"></i></div>';
            html += '<h3>Processing Recharge...</h3>';
            html += '<p>Please wait while we process your ' + operatorName + ' recharge</p>';
            html += '<div class="processing-details">';
            html += '<div class="processing-item"><span>Mobile Number:</span><span>' + fullMobile + '</span></div>';
            html += '<div class="processing-item"><span>Operator:</span><span>' + operatorName + '</span></div>';
            html += '<div class="processing-item"><span>Amount:</span><span>₹' + amount + '</span></div>';
            html += '</div></div>';
            
            processingModal.innerHTML = html;
            document.body.appendChild(processingModal);

            // Process recharge with API call
            fetch('process_recharge.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(rechargeData)
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                // Remove processing modal
                const modal = document.querySelector('.processing-modal');
                if (modal) modal.remove();
                
                if (data.success) {
                    // Update balance display
                    if (data.new_balance !== undefined) {
                        updateBalanceDisplay(data.new_balance);
                    }
                    
                    showRechargeSuccessModal({
                        mobile: fullMobile,
                        operator: rechargeData.operator,
                        amount: rechargeData.amount,
                        transactionId: data.transaction_id,
                        newBalance: data.new_balance
                    });
                } else {
                    alert('Recharge failed: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(function(error) {
                // Remove processing modal
                const modal = document.querySelector('.processing-modal');
                if (modal) modal.remove();
                alert('Network error: ' + error.message);
            });
        }

        function showRechargeSuccessModal(data) {
            const modal = document.createElement('div');
            modal.className = 'success-modal';
            modal.innerHTML = `
                <div class="modal-backdrop" onclick="closeRechargeSuccessModal()"></div>
                <div class="success-container">
                    <div class="success-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h2>Recharge Successful!</h2>
                    <div class="success-details">
                        <p><strong>Mobile:</strong> ${data.mobile}</p>
                        <p><strong>Operator:</strong> ${data.operator}</p>
                        <p><strong>Amount:</strong> ₹${data.amount}</p>
                        <p><strong>Transaction ID:</strong> ${data.transactionId}</p>
                        <p><strong>New Balance:</strong> ₹${parseFloat(data.newBalance || 0).toFixed(2)}</p>
                    </div>
                    <div class="success-actions">
                        <button class="success-btn secondary" onclick="closeRechargeSuccessModal()">
                            Done
                        </button>
                        <button class="success-btn primary" onclick="downloadRechargeReceipt()">
                            Download Receipt
                        </button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            
            // Store receipt data
            window.currentRechargeReceipt = data;
        }

        function closeRechargeSuccessModal() {
            const modal = document.querySelector('.success-modal');
            if (modal) modal.remove();
            window.currentRechargeReceipt = null;
        }

        function downloadRechargeReceipt() {
            const data = window.currentRechargeReceipt;
            if (!data) return;
            
            const receiptContent = `
MOBILE RECHARGE RECEIPT
=====================
Date: ${new Date().toLocaleString()}
Transaction ID: ${data.transactionId}

RECHARGE DETAILS:
Mobile Number: ${data.mobile}
Operator: ${data.operator}
Amount: ₹${data.amount}
New Balance: ₹${parseFloat(data.newBalance || 0).toFixed(2)}

Thank you for using PaySecure!
            `;
            
            const blob = new Blob([receiptContent], { type: 'text/plain' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `recharge-receipt-${data.transactionId || Date.now()}.txt`;
            a.click();
            window.URL.revokeObjectURL(url);
        }

        function updateBalanceDisplay(newBalance) {
            if (newBalance !== undefined && newBalance !== null) {
                const balanceStr = '₹' + parseFloat(newBalance).toFixed(2);
                const headerBalance = document.getElementById('currentBalance');
                const mainBalance = document.getElementById('balanceAmount');
                
                if (headerBalance) {
                    headerBalance.textContent = balanceStr;
                    headerBalance.style.animation = 'none';
                    headerBalance.offsetHeight; // trigger reflow
                    headerBalance.style.animation = 'pulse 0.6s ease-in-out';
                }
                
                if (mainBalance) {
                    mainBalance.textContent = balanceStr;
                    mainBalance.style.animation = 'none';
                    mainBalance.offsetHeight; // trigger reflow
                    mainBalance.style.animation = 'pulse 0.6s ease-in-out';
                }
                
                // Store in localStorage
                try {
                    localStorage.setItem('currentBalance', newBalance.toString());
                } catch(e) { console.debug('localStorage save failed', e); }
            }
        }

            // Simulate processing time
            setTimeout(() => {
                processingModal.remove();
                console.log('About to show UPI PIN modal');
                // Give a small delay before showing UPI PIN modal
                setTimeout(() => {
                    showUpiPinModal();
                }, 200);
            }, 3000);
        }

        function showUpiPinModal() {
            console.log('showUpiPinModal called');
            
            // Check if UPI PIN modal already exists
            const existingModal = document.querySelector('.upi-pin-modal');
            if (existingModal) {
                console.log('UPI PIN modal already exists, not creating another');
                return;
            }
            
            const modal = document.createElement('div');
            modal.className = 'upi-pin-modal';
            modal.innerHTML = `
                <div class="modal-backdrop" onclick="closeUpiPinModal()"></div>
                <div class="upi-pin-container">
                    <div class="pin-header">
                        <h3>Enter UPI PIN</h3>
                        <p>Enter your 4-digit UPI PIN to confirm payment</p>
                    </div>
                    <div class="pin-input-section">
                        <input type="password" id="upiPinInput" maxlength="4" 
                               placeholder="Enter 4-digit PIN"
                               class="pin-input-field"
                               oninput="validatePinInput(this)" 
                               onkeyup="handlePinKeyup(event)">
                    </div>
                    <div class="pin-actions">
                        <button onclick="closeUpiPinModal()" class="pin-cancel-btn">Cancel</button>
                        <button onclick="submitUpiPin()" class="pin-submit-btn" id="pinSubmitBtn" disabled>
                            <i class="fas fa-lock"></i> Verify PIN
                        </button>
                    </div>
                    <div class="pin-error" id="pinError" style="display:none;"></div>
                </div>
            `;
            console.log('Adding UPI PIN modal to body');
            document.body.appendChild(modal);
            console.log('UPI PIN modal added, checking if visible');
            
            // Focus on PIN input for keyboard entry
            setTimeout(() => {
                const input = document.getElementById('upiPinInput');
                if (input) {
                    console.log('Focusing on UPI PIN input');
                    input.focus();
                } else {
                    console.log('UPI PIN input not found!');
                }
            }, 100);
        }

        function validatePinInput(input) {
            // Only allow digits
            input.value = input.value.replace(/[^0-9]/g, '');
            
            const submitBtn = document.getElementById('pinSubmitBtn');
            const errorDiv = document.getElementById('pinError');
            
            if (input.value.length === 4) {
                submitBtn.disabled = false;
                submitBtn.style.opacity = '1';
                errorDiv.style.display = 'none';
            } else {
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.6';
            }
        }
        
        function handlePinKeyup(event) {
            if (event.key === 'Enter') {
                const input = event.target;
                if (input.value.length === 4) {
                    submitUpiPin();
                }
            } else if (event.key === 'Escape') {
                closeUpiPinModal();
            }
        }
        
        function submitUpiPin() {
            // Prevent multiple submissions
            if (upiPinSubmitting) {
                console.log('UPI PIN submission already in progress');
                return;
            }
            
            const input = document.getElementById('upiPinInput');
            const pin = input ? input.value : '';
            
            console.log('submitUpiPin called, pin length:', pin.length);
            
            if (pin.length !== 4) {
                showPinError('Please enter a 4-digit PIN');
                return;
            }
            
            // Set submitting flag and disable button
            upiPinSubmitting = true;
            const submitBtn = document.getElementById('pinSubmitBtn');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';
            }
            
            // Validate PIN with backend
            const formData = new FormData();
            formData.append('action', 'validate_pin');
            formData.append('pin', pin);
            formData.append('amount', currentRechargeAmount || 0);
            formData.append('type', currentTransactionType || 'recharge');
            
            fetch('referral_system.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('PIN validated, processing payment');
                    closeUpiPinModal();
                    
                    // Update balance on page
                    updateBalanceDisplay(data.new_balance);
                    
                    processPayment(data.transaction_id);
                } else {
                    showPinError(data.message);
                    
                    // Reset form
                    input.value = '';
                    validatePinInput(input);
                    
                    // Re-enable button
                    upiPinSubmitting = false;
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fas fa-lock"></i> Verify PIN';
                    }
                }
            })
            .catch(error => {
                console.error('PIN validation error:', error);
                showPinError('Network error. Please try again.');
                
                // Re-enable button
                upiPinSubmitting = false;
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-lock"></i> Verify PIN';
                }
            });
        }
        
        function showPinError(message) {
            const errorDiv = document.getElementById('pinError');
            if (errorDiv) {
                errorDiv.textContent = message;
                errorDiv.style.display = 'block';
                setTimeout(() => {
                    errorDiv.style.display = 'none';
                }, 3000);
            }
        }
        
        function closeUpiPinModal() {
            const modal = document.querySelector('.upi-pin-modal');
            if (modal) {
                modal.remove();
                console.log('UPI PIN modal closed');
            }
            // Reset submitting state
            upiPinSubmitting = false;
        }
        
        function processPayment() {
            console.log('processPayment called');
            
            // Check if already processing
            const existingProcessingModal = document.querySelector('.processing-modal');
            if (existingProcessingModal) {
                console.log('Payment already processing');
                return;
            }
            
            const mobile = window.rechargeData ? window.rechargeData.mobile : '';
            const operator = window.rechargeData ? window.rechargeData.operator : '';
            const fullMobile = window.rechargeData ? window.rechargeData.fullMobile : '';
            const amount = window.rechargeData ? window.rechargeData.amount : 0;
            
            // Show processing state
            const processingModal = document.createElement('div');
            processingModal.className = 'processing-modal';
            processingModal.innerHTML = `
                <div class="processing-container">
                    <div class="processing-spinner">
                        <i class="fas fa-spinner fa-spin"></i>
                    </div>
                    <h3>Processing Payment...</h3>
                    <p>Please wait while we process your recharge</p>
                </div>
            `;
            document.body.appendChild(processingModal);

            // Process actual recharge payment
            fetch('process_recharge.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    mobile: fullMobile,
                    operator: operator,
                    amount: amount
                })
            })
            .then(response => response.json())
            .then(data => {
                processingModal.remove();
                
                if (data.success) {
                    console.log('Payment successful, showing receipt');
                    showDirectReceipt(fullMobile, operator, amount, data.transaction_id, data.new_balance);
                } else {
                    console.log('Payment failed:', data.error);
                    upiPinSubmitting = false;
                    alert('Payment failed: ' + (data.error || 'Please try again.'));
                }
            })
            .catch(error => {
                processingModal.remove();
                console.error('Payment error:', error);
                upiPinSubmitting = false;
                alert('Payment failed. Please try again.');
            });
        }

        function showDirectReceipt(mobile, operator, amount, transactionId = null, newBalance = null) {
            console.log('showDirectReceipt called with:', mobile, operator, amount, transactionId);
            
            // Check if receipt modal already exists
            const existingModal = document.querySelector('.receipt-modal');
            if (existingModal) {
                console.log('Receipt modal already exists, not creating another');
                return;
            }
            
            // Reset UPI PIN state when showing receipt
            upiPinSubmitting = false;
            
            const finalTransactionId = transactionId || ('TXN' + Date.now().toString().slice(-8));
            const currentDate = new Date().toLocaleString();
            
            const modal = document.createElement('div');
            modal.className = 'receipt-modal';
            modal.innerHTML = `
                <div class="modal-backdrop" onclick="closeReceiptModal()"></div>
                <div class="receipt-container">
                    <div class="receipt-header">
                        <div class="success-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h2>Recharge Successful!</h2>
                        <p class="receipt-subtitle">Your ${operator.toUpperCase()} recharge has been completed</p>
                    </div>
                    
                    <div class="receipt-content">
                        <div class="receipt-section">
                            <h4>Recharge Details</h4>
                            <div class="receipt-row">
                                <span>Mobile Number</span>
                                <span>${mobile}</span>
                            </div>
                            <div class="receipt-row">
                                <span>Operator</span>
                                <span>${operator.toUpperCase()}</span>
                            </div>
                            <div class="receipt-row">
                                <span>Recharge Amount</span>
                                <span>₹${amount}</span>
                            </div>
                            <div class="receipt-row">
                                <span>Service Fee</span>
                                <span>₹0</span>
                            </div>
                            <div class="receipt-row total-row">
                                <span>Total Paid</span>
                                <span>₹${amount}</span>
                            </div>
                        </div>
                        
                        <div class="receipt-section">
                            <h4>Transaction Information</h4>
                            <div class="receipt-row">
                                <span>Transaction ID</span>
                                <span>${finalTransactionId}</span>
                            </div>
                            <div class="receipt-row">
                                <span>Date & Time</span>
                                <span>${currentDate}</span>
                            </div>
                            <div class="receipt-row">
                                <span>Payment Method</span>
                                <span>Quick Pay</span>
                            </div>
                            <div class="receipt-row">
                                <span>Status</span>
                                <span class="status-success">Completed</span>
                            </div>
                        </div>

                        <div class="receipt-section">
                            <div class="success-message">
                                <i class="fas fa-gift"></i>
                                <p>Recharge completed successfully! Your ${operator.toUpperCase()} account (${mobile}) has been recharged with ₹${amount}.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="receipt-actions">
                        <button class="receipt-btn primary" onclick="downloadDirectReceipt('${finalTransactionId}', '${mobile}', '${operator}', ${amount}, '${currentDate}')">
                            <i class="fas fa-download"></i> Download Receipt
                        </button>
                        <button class="receipt-btn secondary" onclick="shareDirectReceipt('${finalTransactionId}', '${mobile}', '${operator}', ${amount})">
                            <i class="fas fa-share"></i> Share
                        </button>
                    </div>
                    
                    <div class="receipt-footer">
                        <button class="receipt-btn done" onclick="closeReceiptModalAndRefresh(${newBalance})">Done</button>
                        <button class="receipt-btn repeat" onclick="rechargeAnother()">Recharge Another</button>
                    </div>
                </div>
            `;
            console.log('Adding receipt modal to body');
            document.body.appendChild(modal);
            console.log('Receipt modal added successfully');
        }
        
        function downloadReceipt(txnId, mobile, operator, amount, date) {
            const receiptContent = `
RECHARGE RECEIPT
================

Transaction ID: ${txnId}
Date: ${date}

Mobile Number: ${mobile}
Operator: ${operator.toUpperCase()}
Amount: ₹${amount}

Payment Method: UPI
Status: Completed

================
Thank you for using our service!
            `;
            
            const blob = new Blob([receiptContent], { type: 'text/plain' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `Receipt_${txnId}.txt`;
            a.click();
            window.URL.revokeObjectURL(url);
        }
        
        function shareReceipt(txnId, mobile, operator, amount) {
            const shareText = `Recharge Successful!\n\nMobile: ${mobile}\nOperator: ${operator.toUpperCase()}\nAmount: ₹${amount}\nTransaction ID: ${txnId}`;
            
            if (navigator.share) {
                navigator.share({
                    title: 'Recharge Receipt',
                    text: shareText
                });
            } else {
                // Fallback: copy to clipboard
                navigator.clipboard.writeText(shareText).then(() => {
                    alert('Receipt details copied to clipboard!');
                });
            }
        }
        
        function downloadDirectReceipt(txnId, mobile, operator, amount, date) {
            const receiptContent = `
MOBILE RECHARGE RECEIPT
=======================

Transaction ID: ${txnId}
Date: ${date}

Mobile Number: ${mobile}
Operator: ${operator.toUpperCase()}
Recharge Amount: ₹${amount}
Service Fee: ₹0
Total Paid: ₹${amount}

Payment Method: Quick Pay
Status: Completed

=======================
Thank you for using PaySecure!
Your recharge has been processed successfully.
            `;
            
            const blob = new Blob([receiptContent], { type: 'text/plain' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `Recharge_Receipt_${txnId}.txt`;
            a.click();
            window.URL.revokeObjectURL(url);
        }
        
        function shareDirectReceipt(txnId, mobile, operator, amount) {
            const shareText = `Mobile Recharge Successful!\\n\\nMobile: ${mobile}\\nOperator: ${operator.toUpperCase()}\\nAmount: ₹${amount}\\nTransaction ID: ${txnId}\\n\\nRecharged via PaySecure`;
            
            if (navigator.share) {
                navigator.share({
                    title: 'Mobile Recharge Receipt',
                    text: shareText
                });
            } else {
                // Fallback: copy to clipboard
                navigator.clipboard.writeText(shareText).then(() => {
                    alert('Receipt details copied to clipboard!');
                });
            }
        }

        function closeReceiptModal() {
            const modal = document.querySelector('.receipt-modal');
            if (modal) modal.remove();
        }
        
        function closeReceiptModalAndRefresh(newBalance = null) {
            closeReceiptModal();
            
            // Refresh balance display if new balance is provided
            if (newBalance !== null && newBalance !== undefined) {
                const balanceElement = document.querySelector('.balance-amount');
                if (balanceElement) {
                    balanceElement.textContent = '₹' + Number(newBalance).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
                }
            }
            
            // Optionally reload the page to refresh all data
            setTimeout(() => {
                location.reload();
            }, 1000);
        }

        function rechargeAnother() {
            closeReceiptModal();
            mobileRecharge();
        }

        // Initialize UI from injected userData
        document.addEventListener('DOMContentLoaded', function() {
            // Update profile name and avatar
            const name = userData.name || 'User';
            document.getElementById('profileName').textContent = name;
            const avatar = document.getElementById('profileAvatar');
            if (avatar) avatar.textContent = name.charAt(0).toUpperCase();

            // Pre-generate QR (so it's quicker when opening profile)
            setTimeout(() => {
                if (typeof QRCode !== 'undefined') generateQRCode(); else generateQRCodeFallback();
            }, 150);

            // Initialize country code placeholder
            if (document.getElementById('countryCode')) {
                updatePhonePlaceholder();
            }

            // Device and responsive handling
            handleResponsiveLayout();
            
            // Listen for resize events
            window.addEventListener('resize', handleResponsiveLayout);
            
            // Listen for orientation changes on mobile
            window.addEventListener('orientationchange', function() {
                setTimeout(handleResponsiveLayout, 100);
            });

            // Refresh header from server so name updates from Settings are shown
            try { refreshHeaderFromServer(); } catch(e) { console.debug('refreshHeaderFromServer error', e); }
            
            // Check if we need to refresh balance after a transaction
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('refresh_balance')) {
                // Remove the parameter from URL without reload
                window.history.replaceState({}, document.title, window.location.pathname);
                // Refresh the page to show updated balance and transactions
                setTimeout(() => window.location.reload(), 1000);
            }
        });
        
        function handleResponsiveLayout() {
            const screenWidth = window.innerWidth;
            const isMobile = screenWidth <= 480;
            const isTablet = screenWidth <= 768 && screenWidth > 480;
            const isLaptop = screenWidth <= 1200 && screenWidth > 768;
            
            // Add device classes to body for CSS targeting
            document.body.className = document.body.className.replace(/device-\w+/g, '');
            if (isMobile) document.body.classList.add('device-mobile');
            else if (isTablet) document.body.classList.add('device-tablet');
            else if (isLaptop) document.body.classList.add('device-laptop');
            else document.body.classList.add('device-desktop');
            
            console.log('Device detected:', {
                width: screenWidth,
                type: isMobile ? 'mobile' : isTablet ? 'tablet' : isLaptop ? 'laptop' : 'desktop'
            });
        }

        // Client-side copy of bank balances (injected from PHP)
        const _bankBalances = <?php echo json_encode($bankBalances, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?> || [];

        function toggleBankList(e) {
            e.stopPropagation();
            const dd = document.getElementById('bankListDropdown');
            if (!dd) return;
            dd.style.display = (dd.style.display === 'block') ? 'none' : 'block';
        }

        // Close bank dropdown when clicking outside (but ignore clicks on the toggle or inside the dropdown)
        document.addEventListener('click', function(ev) {
            const dd = document.getElementById('bankListDropdown');
            const btn = document.getElementById('bankToggle');
            if (!dd) return;
            if (btn && btn.contains(ev.target)) return;
            if (dd.contains(ev.target)) return;
            dd.style.display = 'none';
        });

        function selectBank(idx) {
            const b = _bankBalances[idx];
            if (!b) return;
            const label = document.getElementById('primaryBankLabel');
            if (label) label.textContent = 'Primary: ' + b.bank_name + ' • ****' + b.last_6_digits;
            const dd = document.getElementById('bankListDropdown'); if (dd) dd.style.display = 'none';
            const bb = document.getElementById('bankSpecificBalance');
            if (bb) {
                bb.style.display = 'block';
                bb.textContent = 'Bank Balance: ₹' + Number(b.balance).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
            }
        }
    </script>
    
    <!-- FORCE CENTER ICONS CSS OVERRIDE -->
    <style>
    .action-card {
        display: flex !important;
        flex-direction: column !important;
        justify-content: center !important;
        align-items: center !important;
        text-align: center !important;
        padding: 30px 20px !important;
        min-height: 160px !important;
        position: relative !important;
    }
    
    .card-icon {
        width: 48px !important;
        height: 48px !important;
        border-radius: 12px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        position: absolute !important;
        top: 50% !important;
        left: 50% !important;
        transform: translate(-50%, -50%) !important;
        margin: 0 !important;
    }
    
    /* Fix text positioning for centered icons */
    .action-card h3 {
        position: absolute !important;
        bottom: 40px !important;
        left: 50% !important;
        transform: translateX(-50%) !important;
        margin: 0 !important;
        font-size: 16px !important;
        font-weight: 600 !important;
        color: #333 !important;
        width: 100% !important;
        text-align: center !important;
    }
    
    .action-card p {
        position: absolute !important;
        bottom: 20px !important;
        left: 50% !important;
        transform: translateX(-50%) !important;
        margin: 0 !important;
        font-size: 13px !important;
        color: #666 !important;
        width: 100% !important;
        text-align: center !important;
    }
    
    /* Profile Menu Fix */
    .profile-menu {
        position: absolute !important;
        top: 100% !important;
        right: 0 !important;
        background: white !important;
        border-radius: 12px !important;
        box-shadow: 0 8px 32px rgba(0,0,0,0.15) !important;
        padding: 8px 0 !important;
        min-width: 200px !important;
        z-index: 10000 !important;
        opacity: 0 !important;
        visibility: hidden !important;
        transform: translateY(-10px) !important;
        transition: all 0.3s ease !important;
    }
    
    .profile-menu.show {
        opacity: 1 !important;
        visibility: visible !important;
        transform: translateY(0) !important;
    }
    
    .menu-item {
        display: flex !important;
        align-items: center !important;
        gap: 12px !important;
        padding: 12px 16px !important;
        cursor: pointer !important;
        transition: background 0.3s ease !important;
    }
    
    .menu-item:hover {
        background: rgba(29,155,240,0.1) !important;
    }
    
    /* Profile Modal Fix */
    .profile-modal {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        background: rgba(0,0,0,0.5) !important;
        display: flex !important;
        justify-content: center !important;
        align-items: center !important;
        z-index: 9999 !important;
    }
    
    .modal-content {
        background: white !important;
        border-radius: 16px !important;
        width: 90% !important;
        max-width: 500px !important;
        max-height: 80vh !important;
        overflow-y: auto !important;
        position: relative !important;
    }
    
    .modal-header {
        display: flex !important;
        justify-content: space-between !important;
        align-items: center !important;
        padding: 20px 25px !important;
        border-bottom: 1px solid #f0f0f0 !important;
    }
    
    .close-modal {
        font-size: 24px !important;
        cursor: pointer !important;
        color: #666 !important;
        line-height: 1 !important;
    }
    
    .close-modal:hover {
        color: #333 !important;
    }
    
    /* Modal Body Styling */
    .modal-body {
        padding: 25px !important;
    }
    
    .profile-section-modal {
        text-align: center !important;
        margin-bottom: 25px !important;
    }
    
    .profile-avatar-large {
        width: 80px !important;
        height: 80px !important;
        border-radius: 50% !important;
        background: linear-gradient(135deg, #1d9bf0 0%, #1d9bf0 100%) !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        font-size: 32px !important;
        color: white !important;
        font-weight: 600 !important;
        margin: 0 auto 15px !important;
    }
    
    .profile-section-modal h3 {
        color: #333 !important;
        margin-bottom: 5px !important;
        font-size: 20px !important;
    }
    
    .profile-section-modal p {
        color: #666 !important;
        font-size: 14px !important;
    }
    
    /* Clean Profile Modal Design */
    .profile-details-clean {
        margin: 25px 0 !important;
    }
    
    .detail-row {
        display: flex !important;
        justify-content: space-between !important;
        align-items: center !important;
        padding: 15px 0 !important;
        border-bottom: 1px solid #f0f0f0 !important;
    }
    
    .detail-row:last-child {
        border-bottom: none !important;
    }
    
    .detail-label {
        font-size: 14px !important;
        color: #666 !important;
        font-weight: 500 !important;
    }
    
    .detail-value {
        font-size: 14px !important;
        color: #333 !important;
        font-weight: 600 !important;
        text-align: right !important;
    }
    
    .detail-value.verified {
        color: #4CAF50 !important;
    }
    
    .detail-value-with-copy {
        display: flex !important;
        align-items: center !important;
        gap: 10px !important;
    }
    
    .copy-icon-btn {
        background: #1d9bf0 !important;
        color: white !important;
        border: none !important;
        padding: 6px 8px !important;
        border-radius: 6px !important;
        cursor: pointer !important;
        font-size: 12px !important;
        transition: all 0.3s ease !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
    }
    
    .copy-icon-btn:hover {
        background: #1565c0 !important;
        transform: scale(1.05) !important;
    }
    
    .profile-avatar-large {
        width: 80px !important;
        height: 80px !important;
        border-radius: 50% !important;
        background: linear-gradient(135deg, #1d9bf0 0%, #1d9bf0 100%) !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        font-size: 32px !important;
        color: white !important;
        font-weight: 600 !important;
        margin: 0 auto 15px !important;
    }
    
    /* QR Code Section */
    .qr-section {
        background: #f8f9fa !important;
        border-radius: 12px !important;
        padding: 20px !important;
        text-align: center !important;
        margin-top: 20px !important;
    }
    
    .qr-section h4 {
        color: #1d9bf0 !important;
        margin-bottom: 15px !important;
        font-size: 16px !important;
    }
    
    .qr-code {
        margin: 0 auto 15px !important;
        border: 2px solid #1d9bf0 !important;
        border-radius: 8px !important;
        padding: 10px !important;
        background: white !important;
    }
    
    /* Refer & Earn Modal Styles */
    .refer-section { text-align: center !important; }
    .refer-icon { margin-bottom: 20px !important; }
    .referral-code { background: #f8f9fa !important; padding: 20px !important; border-radius: 12px !important; margin: 20px 0 !important; }
    .code-display { display: flex !important; align-items: center !important; justify-content: center !important; gap: 10px !important; margin-top: 10px !important; }
    .code { background: white !important; padding: 12px 20px !important; border-radius: 8px !important; font-weight: bold !important; font-size: 18px !important; color: #1d9bf0 !important; border: 2px solid #1d9bf0 !important; }
    .share-buttons { display: flex !important; gap: 10px !important; justify-content: center !important; flex-wrap: wrap !important; margin-top: 15px !important; }
    .share-btn { padding: 12px 20px !important; border: none !important; border-radius: 25px !important; cursor: pointer !important; font-size: 14px !important; font-weight: 500 !important; transition: all 0.3s ease !important; display: flex !important; align-items: center !important; gap: 8px !important; }
    .share-btn.whatsapp { background: #25D366 !important; color: white !important; }
    .share-btn.sms { background: #007AFF !important; color: white !important; }
    .share-btn.link { background: #1d9bf0 !important; color: white !important; }
    .earning-stats { display: flex !important; gap: 30px !important; justify-content: center !important; margin-top: 15px !important; }
    .stat { text-align: center !important; }
    .stat .number { display: block !important; font-size: 24px !important; font-weight: bold !important; color: #1d9bf0 !important; }
    .stat .label { font-size: 12px !important; color: #666 !important; }
    
    /* Transaction History Modal Styles */
    .transaction-filters { display: flex !important; gap: 15px !important; margin-bottom: 20px !important; }
    .filter-select { flex: 1 !important; padding: 12px !important; border: 2px solid #f0f0f0 !important; border-radius: 8px !important; font-size: 14px !important; outline: none !important; }
    .transaction-list { max-height: 400px !important; overflow-y: auto !important; }
    .transaction-item { display: flex !important; align-items: center !important; gap: 15px !important; padding: 15px !important; border-bottom: 1px solid #f0f0f0 !important; transition: background 0.3s ease !important; }
    .transaction-item:hover { background: #f8f9fa !important; }
    .transaction-icon { width: 40px !important; height: 40px !important; border-radius: 50% !important; display: flex !important; align-items: center !important; justify-content: center !important; color: white !important; }
    .transaction-icon.success { background: #4CAF50 !important; }
    .transaction-icon.sent { background: #FF9800 !important; }
    .transaction-details { flex: 1 !important; }
    .transaction-title { font-weight: 600 !important; margin-bottom: 4px !important; }
    .transaction-subtitle { font-size: 12px !important; color: #666 !important; margin-bottom: 2px !important; }
    .transaction-time { font-size: 11px !important; color: #999 !important; }
    .transaction-amount { font-weight: bold !important; font-size: 16px !important; }
    .transaction-amount.success { color: #4CAF50 !important; }
    .transaction-amount.sent { color: #FF5722 !important; }
    .btn-view-all { width: 100% !important; padding: 15px !important; background: #1d9bf0 !important; color: white !important; border: none !important; border-radius: 12px !important; font-size: 16px !important; font-weight: 600 !important; cursor: pointer !important; margin-top: 20px !important; transition: all 0.3s ease !important; }
    .btn-view-all:hover { background: #1565c0 !important; transform: translateY(-2px) !important; }
    
    /* Referral Entry Modal Styles */
    .referral-entry-section { text-align: center !important; }
    .referral-entry-icon { margin-bottom: 20px !important; }
    .referral-input-group { margin: 25px 0 !important; text-align: left !important; }
    .input-with-button { display: flex !important; gap: 10px !important; margin-top: 10px !important; }
    .input-with-button input { flex: 1 !important; padding: 12px !important; border: 2px solid #f0f0f0 !important; border-radius: 8px !important; font-size: 16px !important; }
    .input-with-button input:focus { border-color: #1d9bf0 !important; outline: none !important; }
    .apply-btn { padding: 12px 24px !important; background: #1d9bf0 !important; color: white !important; border: none !important; border-radius: 8px !important; cursor: pointer !important; font-weight: 600 !important; }
    .apply-btn:hover { background: #1565c0 !important; }
    .referral-message { margin-top: 10px !important; font-size: 14px !important; }
    .referral-benefits { text-align: left !important; background: #f8f9fa !important; padding: 20px !important; border-radius: 12px !important; margin: 20px 0 !important; }
    .referral-benefits ul { list-style: none !important; padding: 0 !important; }
    .referral-benefits li { margin: 10px 0 !important; display: flex !important; align-items: center !important; gap: 10px !important; }
    .note { background: rgba(255, 193, 7, 0.1) !important; border: 1px solid #FFC107 !important; border-radius: 8px !important; padding: 15px !important; margin-top: 20px !important; }
    .note p { margin: 0 !important; color: #856404 !important; font-size: 14px !important; }
    
    /* Mobile Recharge Modal Fix */
    .recharge-modal {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        z-index: 10000 !important;
        display: flex !important;
        justify-content: center !important;
        align-items: center !important;
        background: rgba(0,0,0,0.5) !important;
    }
    
    .recharge-container {
        background: white !important;
        border-radius: 16px !important;
        width: 90% !important;
        max-width: 500px !important;
        max-height: 80vh !important;
        overflow-y: auto !important;
        position: relative !important;
        z-index: 10001 !important;
    }
    
    .recharge-header {
        display: flex !important;
        justify-content: space-between !important;
        align-items: center !important;
        padding: 20px 25px !important;
        border-bottom: 1px solid #f0f0f0 !important;
        background: #1d9bf0 !important;
        color: white !important;
        border-radius: 16px 16px 0 0 !important;
    }
    
    .recharge-header h2 {
        margin: 0 !important;
        font-size: 18px !important;
        color: white !important;
    }
    
    .close-btn {
        background: none !important;
        border: none !important;
        color: white !important;
        font-size: 24px !important;
        cursor: pointer !important;
        padding: 0 !important;
        width: 30px !important;
        height: 30px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        border-radius: 50% !important;
    }
    
    .close-btn:hover {
        background: rgba(255,255,255,0.2) !important;
    }
    
    .recharge-content {
        padding: 25px !important;
    }
    
    /* Form Elements Styling */
    .input-group {
        margin-bottom: 20px !important;
    }
    
    .input-group label {
        display: block !important;
        font-weight: 600 !important;
        margin-bottom: 8px !important;
        color: #333 !important;
    }
    
    .number-input-container {
        display: flex !important;
        gap: 10px !important;
        align-items: center !important;
    }
    
    .country-select {
        padding: 12px !important;
        border: 2px solid #f0f0f0 !important;
        border-radius: 8px !important;
        font-size: 14px !important;
        background: white !important;
        cursor: pointer !important;
        min-width: 80px !important;
    }
    
    .mobile-input {
        flex: 1 !important;
        padding: 12px 15px !important;
        border: 2px solid #f0f0f0 !important;
        border-radius: 8px !important;
        font-size: 16px !important;
        outline: none !important;
    }
    
    .mobile-input:focus {
        border-color: #1d9bf0 !important;
        box-shadow: 0 0 0 3px rgba(29,155,240,0.1) !important;
    }
    
    .operator-grid {
        display: grid !important;
        grid-template-columns: repeat(5, 1fr) !important;
        gap: 12px !important;
        margin-top: 15px !important;
    }
    
    .operator-btn {
        padding: 15px 8px !important;
        border: 2px solid #f0f0f0 !important;
        border-radius: 12px !important;
        background: white !important;
        cursor: pointer !important;
        text-align: center !important;
        transition: all 0.3s ease !important;
        display: flex !important;
        flex-direction: column !important;
        align-items: center !important;
        gap: 8px !important;
        min-height: 80px !important;
    }
    
    .operator-btn:hover {
        border-color: #1d9bf0 !important;
        background: rgba(29,155,240,0.05) !important;
    }
    
    .operator-btn.selected {
        border-color: #1d9bf0 !important;
        background: rgba(29,155,240,0.1) !important;
        color: #1d9bf0 !important;
    }
    
    .operator-icon {
        width: 32px !important;
        height: 32px !important;
        border-radius: 50% !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        font-size: 16px !important;
        color: white !important;
        margin-bottom: 4px !important;
    }
    
    .operator-name {
        font-size: 12px !important;
        font-weight: 500 !important;
        text-align: center !important;
    }
    
    .plans-section {
        margin-top: 25px !important;
    }
    
    .plans-grid {
        display: grid !important;
        grid-template-columns: repeat(3, 1fr) !important;
        gap: 12px !important;
        margin-top: 15px !important;
    }
    
    .plan-card {
        border: 2px solid #f0f0f0 !important;
        border-radius: 12px !important;
        padding: 15px !important;
        text-align: center !important;
        cursor: pointer !important;
        transition: all 0.3s ease !important;
        background: white !important;
    }
    
    .plan-card:hover {
        border-color: #1d9bf0 !important;
        background: rgba(29,155,240,0.05) !important;
    }
    
    .plan-card.selected {
        border-color: #1d9bf0 !important;
        background: rgba(29,155,240,0.1) !important;
    }
    
    .plan-amount {
        font-size: 18px !important;
        font-weight: bold !important;
        color: #1d9bf0 !important;
        margin-bottom: 5px !important;
    }
    
    .plan-details {
        font-size: 12px !important;
        color: #666 !important;
        line-height: 1.4 !important;
    }
    
    .recharge-btn {
        width: 100% !important;
        padding: 15px !important;
        background: #1d9bf0 !important;
        color: white !important;
        border: none !important;
        border-radius: 12px !important;
        font-size: 16px !important;
        font-weight: 600 !important;
        cursor: pointer !important;
        margin-top: 25px !important;
        transition: all 0.3s ease !important;
    }
    
    .recharge-btn:hover {
        background: #1565c0 !important;
        transform: translateY(-2px) !important;
    }
    
    .recharge-btn:disabled {
        background: #ccc !important;
        cursor: not-allowed !important;
        transform: none !important;
    }
    
    /* UPI PIN Modal Fix */
    .upi-pin-modal {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        z-index: 10000 !important;
        display: flex !important;
        justify-content: center !important;
        align-items: center !important;
        background: rgba(0,0,0,0.5) !important;
    }
    
    .upi-container {
        background: white !important;
        border-radius: 16px !important;
        width: 90% !important;
        max-width: 400px !important;
        position: relative !important;
        z-index: 10001 !important;
        box-shadow: 0 8px 32px rgba(0,0,0,0.2) !important;
    }
    
    .upi-header {
        background: #1d9bf0 !important;
        color: white !important;
        padding: 20px 25px !important;
        border-radius: 16px 16px 0 0 !important;
        text-align: center !important;
    }
    
    .upi-header h3 {
        margin: 0 !important;
        font-size: 20px !important;
        color: white !important;
    }
    
    .upi-body {
        padding: 30px 25px !important;
        text-align: center !important;
    }
    
    .pin-display {
        display: flex !important;
        justify-content: center !important;
        gap: 15px !important;
        margin: 25px 0 !important;
    }
    
    .pin-digit {
        width: 50px !important;
        height: 50px !important;
        border: 2px solid #f0f0f0 !important;
        border-radius: 12px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        font-size: 24px !important;
        font-weight: bold !important;
        background: white !important;
    }
    
    .pin-digit.filled {
        border-color: #1d9bf0 !important;
        background: rgba(29,155,240,0.1) !important;
        color: #1d9bf0 !important;
    }
    
    .keypad {
        display: grid !important;
        grid-template-columns: repeat(3, 1fr) !important;
        gap: 15px !important;
        margin-top: 30px !important;
    }
    
    .key-btn {
        width: 60px !important;
        height: 60px !important;
        border: none !important;
        border-radius: 50% !important;
        font-size: 20px !important;
        font-weight: 600 !important;
        cursor: pointer !important;
        transition: all 0.3s ease !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        margin: 0 auto !important;
    }
    
    .key-btn.number {
        background: #f8f9fa !important;
        color: #333 !important;
    }
    
    .key-btn.number:hover {
        background: #1d9bf0 !important;
        color: white !important;
        transform: scale(1.05) !important;
    }
    
    .key-btn.action {
        background: #FF5722 !important;
        color: white !important;
        grid-column: span 2 !important;
        width: auto !important;
        border-radius: 30px !important;
    }
    
    .key-btn.action:hover {
        background: #D32F2F !important;
    }
    </style>
    
    </div> <!-- End page-container -->
</body>
</html>
