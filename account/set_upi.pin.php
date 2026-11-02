<?php
// filepath: c:\xampp\htdocs\online_payment\account\set_upi_pin.php
session_start();

// Database connection to get data from both tables
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "online_payment";

$phone_number = '';
$country_code = '';
$bank_name = '';
$account_number = '';
$user_found = false;

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Step 1: Get latest user from create account database (users table)
    $stmt = $pdo->query("SELECT phone_number, country_code FROM users ORDER BY id DESC LIMIT 1");
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $phone_number = $user['phone_number'];
        $country_code = $user['country_code'];
        $user_found = true;
        
        // Step 2: Get bank details from bank_detail table using phone_number
        $stmt = $pdo->prepare("SELECT bank_name, account_number FROM bank_detail WHERE phone_number = ?");
        $stmt->execute([$phone_number]);
        $bank = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($bank) {
            $bank_name = $bank['bank_name'];
            $account_number = $bank['account_number'];
        }
    }
} catch(PDOException $e) {
    echo "Database connection failed: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>PaySecure - Set UPI PIN</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="paysecure-universal.css?v=2">
  <style>
    .upi-container {
      max-width: 450px;
      margin: 0 auto;
    }
    
    .back-btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      color: white;
      text-decoration: none;
      font-size: 16px;
      font-weight: 500;
      margin-bottom: 20px;
      transition: all 0.3s ease;
    }
    
    .back-btn:hover {
      color: rgba(255, 255, 255, 0.8);
      transform: translateX(-3px);
    }
    
    /* Auto-populated user info display */
    .user-info {
      background: rgba(25, 118, 210, 0.1);
      border-radius: 8px;
      padding: 15px;
      margin-bottom: 20px;
      font-size: 14px;
      color: #333;
    }
    
    .user-phone {
      color: #1d9bf0;
      font-weight: 600;
    }
    
    .bank-info {
      color: #666;
      margin-top: 8px;
    }
    
    .subtitle {
      color: #666;
      font-size: 16px;
      margin-bottom: 30px;
      text-align: center;
    }
    .pin-row {
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 12px;
      margin-bottom: 25px;
      width: 100%;
    }
    
    .pin-box {
      width: 45px;
      height: 55px;
      font-size: 20px;
      text-align: center;
      border: 2px solid #f0f0f0;
      border-radius: 12px;
      background: rgba(255, 255, 255, 0.9);
      outline: none;
      font-family: inherit;
      color: #333;
      font-weight: 600;
      transition: all 0.3s ease;  
    }
    
    .pin-box:focus {
      border-color: #1d9bf0;
      box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.1);
      background: white;
    }
    .eye-icon {
      margin-left: 15px;
      font-size: 20px;
      color: #666;
      cursor: pointer;
      user-select: none;
      transition: color 0.3s;
      display: flex;
      align-items: center;
      height: 55px;
      padding: 0 5px;
    }
    
    .eye-icon:hover {
      color: #1d9bf0;
    }
    
    .note {
      text-align: center;
      color: #666;
      font-size: 14px;
      margin-bottom: 20px;
      margin-top: 10px;
    }
    @media (max-width: 768px) {
      .pin-row { 
        gap: 8px; 
      }
      
      .pin-box {
        width: 40px;
        height: 50px;
        font-size: 18px;
      }
      
      .eye-icon {
        height: 50px;
        font-size: 18px;
      }
    }
  </style>
</head>
<body>
  <div class="page-container">
    <!-- Header -->
    <div class="header">
      <div class="logo">
        <i class="fa-solid fa-lock"></i>
        <span class="pay">pay</span><span class="secure">secure</span>
      </div>
      <div class="help-link-header">
        <a href="help.html" class="help-text">Help?</a>
      </div>
    </div>
    
    <!-- Back Button -->
    <a href="#" onclick="smartGoBack(); return false;" class="back-btn">
      <i class="fas fa-arrow-left"></i>
      Back
    </a>
    
    <!-- UPI PIN Content -->
    <div class="upi-container">
      <div class="page-card">
        <h2 class="page-title">Set Your UPI PIN</h2>
        
        <!-- Display auto-fetched data from databases -->
        <?php if ($user_found): ?>
        <div class="user-info">
          <div><i class="fas fa-user-circle"></i> Account: <span class="user-phone"><?= htmlspecialchars($country_code) ?> <?= htmlspecialchars($phone_number) ?></span></div>
          <?php if ($bank_name): ?>
          <div class="bank-info"><i class="fas fa-university"></i> Bank: <?= htmlspecialchars($bank_name) ?> - Account: <?= htmlspecialchars($account_number) ?></div>
          <?php else: ?>
          <div class="bank-info"><i class="fas fa-exclamation-triangle"></i> No bank details found. Please complete bank linking first.</div>
          <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="user-info" style="background: rgba(255, 193, 7, 0.1); color: #856404;">
          <i class="fas fa-exclamation-triangle"></i> No account found. Please create account first.
        </div>
        <?php endif; ?>
        
        <p class="subtitle">Enter a <strong>4 or 6 digit UPI PIN</strong> and confirm it to complete setup.</p>
        <form id="upiPinForm" autocomplete="off" onsubmit="return validateUpiPin()">
          <div class="form-group">
            <label class="form-label">Enter UPI PIN</label>
            <div class="pin-row" id="pinRow">
              <input type="password" maxlength="1" class="pin-box" inputmode="numeric" pattern="\d" required>
              <input type="password" maxlength="1" class="pin-box" inputmode="numeric" pattern="\d" required>
              <input type="password" maxlength="1" class="pin-box" inputmode="numeric" pattern="\d" required>
              <input type="password" maxlength="1" class="pin-box" inputmode="numeric" pattern="\d" required>
              <input type="password" maxlength="1" class="pin-box" inputmode="numeric" pattern="\d">
              <input type="password" maxlength="1" class="pin-box" inputmode="numeric" pattern="\d">
              <span class="eye-icon" id="togglePin"><i class="fa-regular fa-eye"></i></span>
            </div>
          </div>
          
          <div class="form-group">
            <label class="form-label">Confirm UPI PIN</label>
            <div class="pin-row" id="pinRowConfirm">
              <input type="password" maxlength="1" class="pin-box" inputmode="numeric" pattern="\d" required>
              <input type="password" maxlength="1" class="pin-box" inputmode="numeric" pattern="\d" required>
              <input type="password" maxlength="1" class="pin-box" inputmode="numeric" pattern="\d" required>
              <input type="password" maxlength="1" class="pin-box" inputmode="numeric" pattern="\d" required>
              <input type="password" maxlength="1" class="pin-box" inputmode="numeric" pattern="\d">
              <input type="password" maxlength="1" class="pin-box" inputmode="numeric" pattern="\d">
              <span class="eye-icon" id="togglePinConfirm"><i class="fa-regular fa-eye"></i></span>
            </div>
          </div>
          
          <div class="note">UPI PIN must be <strong>4 or 6 digits</strong>.</div>
          
          <input type="hidden" id="upiPin" name="upiPin" />
          <input type="hidden" id="upiPinConfirm" name="upiPinConfirm" />
          
          <button class="btn-primary" type="submit" style="width: 100%; margin-top: 10px;" <?= !$user_found ? 'disabled' : '' ?>>
            Set UPI PIN
          </button>
        </form>
      </div>
    </div>
  </div>
  <script>
    // PIN navigation for both rows (forward/backward on input/backspace)
    document.querySelectorAll('.pin-row').forEach(row => {
      const inputs = row.querySelectorAll('.pin-box');
      inputs.forEach((input, idx, arr) => {
        input.addEventListener('input', function () {
          if (this.value.length === 1 && idx < arr.length - 1) {
            arr[idx + 1].focus();
          }
        });
        input.addEventListener('keydown', function (e) {
          if (e.key === 'Backspace' && this.value === '' && idx > 0) {
            arr[idx - 1].focus();
          }
        });
      });
    });

    // Eye icon toggle for PIN visibility
    function togglePinVisibility(rowId, iconId) {
      const row = document.getElementById(rowId);
      const inputs = row.querySelectorAll('.pin-box');
      const icon = document.getElementById(iconId).querySelector('i');
      let visible = false;
      document.getElementById(iconId).addEventListener('click', function () {
        visible = !visible;
        inputs.forEach(input => input.type = visible ? 'text' : 'password');
        icon.className = visible ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye';
      });
    }
    togglePinVisibility('pinRow', 'togglePin');
    togglePinVisibility('pinRowConfirm', 'togglePinConfirm');

    // Form validation for PINs
    function validateUpiPin() {
      const pinInputs = document.getElementById('pinRow').querySelectorAll('.pin-box');
      const confirmInputs = document.getElementById('pinRowConfirm').querySelectorAll('.pin-box');
      let pin = '', confirmPin = '';
      for (let i = 0; i < pinInputs.length; i++) {
        if (pinInputs[i].value === '') break;
        if (!/^\d$/.test(pinInputs[i].value)) {
          alert('UPI PIN must be digits only.');
          pinInputs[i].focus();
          return false;
        }
        pin += pinInputs[i].value;
      }
      for (let i = 0; i < confirmInputs.length; i++) {
        if (confirmInputs[i].value === '') break;
        if (!/^\d$/.test(confirmInputs[i].value)) {
          alert('UPI PIN must be digits only.');
          confirmInputs[i].focus();
          return false;
        }
        confirmPin += confirmInputs[i].value;
      }
      if (!(pin.length === 4 || pin.length === 6)) {
        alert('UPI PIN must be 4 or 6 digits.');
        pinInputs[0].focus();
        return false;
      }
      if (pin !== confirmPin) {
        alert('UPI PINs do not match.');
        confirmInputs[0].focus();
        return false;
      }
      
      // Save UPI PIN to bank_detail database using phone_number from create account
      const formData = new FormData();
      formData.append('upi_pin', pin);
      formData.append('phone_number', '<?= $phone_number ?>');

      fetch('save_upi_pin.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
          .then(data => {
        if (data.status === 'success') {
          alert('UPI PIN set successfully! Your PaySecure account is now ready.');
          setTimeout(() => {
            window.location.href = 'homepage_v1.php';
          }, 1000);
        } else {
          alert('Error: ' + data.message);
        }
      })
      .catch(error => {
        alert('Failed to save UPI PIN. Please try again.');
      });
      
      return false; // Prevent form submission, handle with JS redirect  
    }
  </script>

    <script>
    function smartGoBack() {
        // From UPI PIN setting, go back to bank selection
        const urlParams = new URLSearchParams(window.location.search);
        const phone = urlParams.get('phone');
        const country = urlParams.get('country');
        
        if (phone && country) {
            window.location.href = `select_bank.html?phone=${phone}&country=${country}`;
        } else {
            window.location.href = 'select_bank.html';
        }
    }
    </script>
</body>
</html>
