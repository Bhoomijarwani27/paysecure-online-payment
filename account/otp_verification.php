<?php
session_start(); // Add session start
$phone_number = $_GET['phone_number'] ?? '';
$country_code = $_GET['country_code'] ?? '';
$referral_code = $_GET['ref'] ?? ''; // Capture referral code
$error = $_GET['error'] ?? '';

if (!$phone_number || !$country_code) {
    echo "<script>alert('Phone number or country code missing!');</script>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PaySecure - OTP Verification</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="paysecure-universal.css?v=2">
  <style>
    .otp-container {
      max-width: 450px;
      margin: 0 auto;
    }
    
    .otp-input-row {
      display: flex;
      justify-content: center;
      gap: 12px;
      margin: 25px 0;
    }
    
    .otp-input {
      width: 50px;
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
    
    .otp-input:focus {
      border-color: #1d9bf0;
      box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.1);
      background: white;
    }
    
    .otp-input.error {
      border-color: #d32f2f;
      background: #ffebee;
    }
    
    .resend-link {
      color: #1d9bf0;
      font-weight: 500;
      cursor: pointer;
      display: block;
      margin-top: 20px;
      text-align: center;
      text-decoration: none;
      transition: all 0.3s ease;
    }
    
    .resend-link:hover {
      color: #1d9bf0;
      text-decoration: underline;
    }
    
    .phone-display {
      color: #1d9bf0;
      font-weight: 600;
    }
    
    .error-message {
      background: #ffebee;
      color: #d32f2f;
      padding: 12px;
      border-radius: 8px;
      margin: 15px 0;
      text-align: center;
      font-weight: 600;
      border: 1px solid #ffcdd2;
    }
    
    @media (max-width: 768px) {
      .otp-input {
        width: 45px;
        height: 50px;
        font-size: 18px;
      }
      
      .otp-input-row {
        gap: 8px;
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
    </a>    <!-- OTP Content -->
    <div class="otp-container">
      <div class="page-card">
        <h2 class="page-title">OTP Verification</h2>
        <p class="subtitle">
          OTP has been sent to <span class="phone-display"><?= $country_code ?> <?= $phone_number ?></span>. 
          Please enter it below.
        </p>
        
        <?php if ($error === 'invalid_otp'): ?>
        <div class="error-message">
          <i class="fas fa-exclamation-circle"></i>
          Invalid OTP! Please check and try again.
        </div>
        <?php endif; ?>
        
        <form onsubmit="return verifyOtp(event)">
          <div class="form-group">
            <label class="form-label">Enter 6-Digit OTP</label>
            <div class="otp-input-row">
              <input type="text" class="otp-input<?= $error === 'invalid_otp' ? ' error' : '' ?>" maxlength="1" id="otp1" inputmode="numeric" pattern="\d" required>
              <input type="text" class="otp-input<?= $error === 'invalid_otp' ? ' error' : '' ?>" maxlength="1" id="otp2" inputmode="numeric" pattern="\d" required>
              <input type="text" class="otp-input<?= $error === 'invalid_otp' ? ' error' : '' ?>" maxlength="1" id="otp3" inputmode="numeric" pattern="\d" required>
              <input type="text" class="otp-input<?= $error === 'invalid_otp' ? ' error' : '' ?>" maxlength="1" id="otp4" inputmode="numeric" pattern="\d" required>
              <input type="text" class="otp-input<?= $error === 'invalid_otp' ? ' error' : '' ?>" maxlength="1" id="otp5" inputmode="numeric" pattern="\d" required>
              <input type="text" class="otp-input<?= $error === 'invalid_otp' ? ' error' : '' ?>" maxlength="1" id="otp6" inputmode="numeric" pattern="\d" required>
            </div>
          </div>
          
          <input type="hidden" name="otp" id="otp-hidden">
          
          <button class="btn-primary" type="submit" name="verify_otp" style="width: 100%; margin-top: 10px;">
            Verify OTP
          </button>
          
          <a href="#" class="resend-link" onclick="resendOtp(); return false;">
            Didn't receive OTP? Resend
          </a>
        </form>
      </div>
    </div>
  </div>



<script>
    function prepareOtp() {
      let otp = '';
      for (let i = 1; i <= 6; i++) {
        const val = document.getElementById('otp' + i).value;
        if (val === '') {
          return null;
        }
        otp += val;
      }
      return otp;
    }

    // Handle OTP verification on the same page
    function verifyOtp(event) {
      event.preventDefault();
      
      const otp = prepareOtp();
      if (!otp) {
        showError("Please enter the full 6-digit OTP.");
        return false;
      }
      
      // Check if using current expected OTP or initial phone-based OTP
      const phoneNumber = "<?= $phone_number ?>";
      const initialOtp = phoneNumber.slice(-6).padStart(6, '1');
      const expectedOtp = window.currentExpectedOtp || initialOtp;
      
      if (otp === expectedOtp) {
        showSuccess("OTP verified successfully! Redirecting...");
        
        // Store phone details in session and redirect directly
        window.location.href = 'set_passwod.php?phone=' + encodeURIComponent(phoneNumber) + '&country=' + encodeURIComponent("<?= $country_code ?>") + '&ref=' + encodeURIComponent("<?= $referral_code ?>");
        
      } else {
        // Generate new OTP and show error with new OTP
        const newOtp = Math.floor(100000 + Math.random() * 900000).toString();
        
        // Show alert with new OTP
        alert(`Your OTP is wrong!\n\nYour new OTP is: ${newOtp}`);
        
        // Add red border to all inputs but don't clear them yet
        document.querySelectorAll('.otp-input').forEach(input => {
          input.classList.add('error');
        });
        
        // Focus on first input after a short delay
        setTimeout(() => {
          document.querySelectorAll('.otp-input').forEach(input => input.value = '');
          document.getElementById('otp1').focus();
        }, 500);
        
        // Update expected OTP for next verification
        window.currentExpectedOtp = newOtp;
      }
    }

    function showError(message) {
      let errorDiv = document.querySelector('.dynamic-error-message');
      if (!errorDiv) {
        errorDiv = document.createElement('div');
        errorDiv.className = 'dynamic-error-message';
        errorDiv.style.cssText = 'background:#ffebee;color:#d32f2f;padding:12px;border-radius:8px;margin:15px 0;text-align:center;font-weight:600;border:1px solid #ffcdd2;';
        document.querySelector('.form-group').appendChild(errorDiv);
      }
      errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + message;
      errorDiv.style.display = 'block';
    }

    function showSuccess(message) {
      let successDiv = document.querySelector('.success-message');
      if (!successDiv) {
        successDiv = document.createElement('div');
        successDiv.className = 'success-message';
        successDiv.style.cssText = 'background:#e8f5e8;color:#2e7d32;padding:12px;border-radius:8px;margin:15px 0;text-align:center;font-weight:600;border:1px solid #c8e6c9;';
        document.querySelector('.form-group').appendChild(successDiv);
      }
      successDiv.innerHTML = '<i class="fas fa-check-circle"></i> ' + message;
      successDiv.style.display = 'block';
    }

    // Remove error styling when user starts typing
    document.querySelectorAll('.otp-input').forEach(input => {
      input.addEventListener('input', function() {
        document.querySelectorAll('.otp-input').forEach(inp => inp.classList.remove('error'));
        const errorMsg = document.querySelector('.dynamic-error-message');
        if (errorMsg) errorMsg.style.display = 'none';
        const successMsg = document.querySelector('.success-message');
        if (successMsg) successMsg.style.display = 'none';
      });
    });

    // Enhanced OTP input navigation with Enter key support
    document.querySelectorAll('.otp-input').forEach((input, idx, arr) => {
      // Handle keydown events (Enter, Arrow keys, Backspace)
      input.addEventListener('keydown', function(e) {
        // Enter key - move to next input
        if (e.key === 'Enter') {
          e.preventDefault();
          if (idx < arr.length - 1) {
            arr[idx + 1].focus();
          } else {
            // If on last input and Enter pressed, verify OTP
            verifyOtp(e);
          }
          return;
        }
        
        // Arrow key navigation
        if (e.key === 'ArrowLeft' && idx > 0) {
          arr[idx - 1].focus();
          e.preventDefault();
        }
        if (e.key === 'ArrowRight' && idx < arr.length - 1) {
          arr[idx + 1].focus();
          e.preventDefault();
        }
        
        // Backspace - clear current and move to previous
        if (e.key === 'Backspace') {
          if (this.value === '' && idx > 0) {
            arr[idx - 1].focus();
            e.preventDefault();
          }
        }
        
        // Only allow numbers
        if (!/\d/.test(e.key) && !['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab', 'Enter'].includes(e.key)) {
          e.preventDefault();
        }
      });
      
      // Handle input event for auto-advance
      input.addEventListener('input', function(e) {
        // Remove any non-digit characters
        this.value = this.value.replace(/\D/g, '');
        
        // Auto-advance to next input when digit is entered
        if (this.value.length === 1 && idx < arr.length - 1) {
          arr[idx + 1].focus();
        }
      });
      
      // Handle paste event
      input.addEventListener('paste', function(e) {
        e.preventDefault();
        const paste = (e.clipboardData || window.clipboardData).getData('text');
        const digits = paste.replace(/\D/g, '').slice(0, 6);
        
        // Fill inputs with pasted digits
        for (let i = 0; i < digits.length && (idx + i) < arr.length; i++) {
          arr[idx + i].value = digits[i];
        }
        
        // Focus on next empty input or last filled input
        const nextEmpty = arr.findIndex((inp, i) => i >= idx && inp.value === '');
        if (nextEmpty !== -1) {
          arr[nextEmpty].focus();
        } else {
          arr[Math.min(idx + digits.length, arr.length - 1)].focus();
        }
      });
    });

    // Resend OTP function
    function resendOtp() {
      const newOtp = Math.floor(100000 + Math.random() * 900000).toString();
      
      // Show alert with new OTP
      alert(`New OTP sent!\n\nYour OTP is: ${newOtp}`);
      
      // Update the expected OTP
      window.currentExpectedOtp = newOtp;
      
      // Clear all OTP inputs and remove error styling
      document.querySelectorAll('.otp-input').forEach(input => {
        input.value = '';
        input.classList.remove('error');
      });
      const errorMsg = document.querySelector('.dynamic-error-message');
      if (errorMsg) errorMsg.style.display = 'none';
      const successMsg = document.querySelector('.success-message');
      if (successMsg) successMsg.style.display = 'none';
      document.getElementById('otp1').focus();
    }

    // Initialize with first OTP - use the OTP from URL parameter if available
    const urlParams = new URLSearchParams(window.location.search);
    const otpFromUrl = urlParams.get('otp');
    if (otpFromUrl) {
        window.currentExpectedOtp = otpFromUrl;
    } else {
        window.currentExpectedOtp = "<?= $phone_number ?>".slice(-6);
    }
  </script>


    <script>
    function smartGoBack() {
        // From OTP verification, go back to create account page
        window.location.href = 'create_account.html';
    }
    </script>
</body>
</html>



