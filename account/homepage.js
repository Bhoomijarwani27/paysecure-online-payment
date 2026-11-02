// Enhanced homepage.js with Blue & White Theme
document.addEventListener('DOMContentLoaded', function() {
    loadUserData();
    loadBankData();
    initializeHomepage();
});

// Initialize homepage functionality
function initializeHomepage() {
    // Close profile menu when clicking outside
    document.addEventListener('click', function(event) {
        const menu = document.getElementById('profileMenu');
        const profileBtn = document.querySelector('.profile-btn');
        
        if (menu && !menu.contains(event.target) && !profileBtn.contains(event.target)) {
            menu.classList.remove('show');
        }
    });

    // Initialize search functionality
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', performSearch);
    }

    // Update balance periodically
    setInterval(updateBalance, 30000); // Update every 30 seconds
}

// Load user data from session/database
async function loadUserData() {
    try {
        const response = await fetch('get_user_data.php');
        const userData = await response.json();
        
        if (userData.error) {
            // User not logged in, redirect to login
            window.location.href = 'login.html';
            return;
        }
        
        // Update profile info
        const profileName = document.getElementById('profileName');
        const profilePhone = document.getElementById('profilePhone');
        const upiId = document.getElementById('upiId');
        
        if (profileName) profileName.textContent = userData.name;
        if (profilePhone) profilePhone.textContent = `${userData.country_code} ••••••${userData.phone_number.slice(-4)}`;
        
        // Generate UPI ID
        if (upiId) {
            const generatedUpiId = `${userData.phone_number}@payease`;
            upiId.textContent = generatedUpiId;
        }
        
    } catch (error) {
        console.error('Error loading user data:', error);
        // Redirect to login on error
        window.location.href = 'login.html';
    }
}

// Load bank data and update balance
async function loadBankData() {
    try {
        const response = await fetch('get_bank_data.php');
        const banks = await response.json();
        
        if (banks && banks.length > 0) {
            const primaryBank = banks[0];
            
            // Update balance
            const balanceAmount = document.getElementById('balanceAmount');
            if (balanceAmount) {
                balanceAmount.textContent = `₹${(Math.random() * 10000 + 1000).toFixed(2)}`;
            }
            
            // Update bank list if exists
            const bankList = document.getElementById('bankList');
            if (bankList) {
                bankList.innerHTML = '';
                banks.forEach(bank => {
                    const bankCard = createBankCard(bank);
                    bankList.appendChild(bankCard);
                });
            }
            
            // Update primary bank info in balance card
            const bankInfo = document.querySelector('.balance-card .bank-info');
            if (bankInfo) {
                bankInfo.innerHTML = `
                    <span class="bank-name">${primaryBank.bank_name}</span>
                    <span class="account-number">••••••${primaryBank.last_6_digits}</span>
                `;
            }
        } else {
            // No banks linked
            const balanceAmount = document.getElementById('balanceAmount');
            if (balanceAmount) {
                balanceAmount.textContent = '₹0.00';
            }
        }
    } catch (error) {
        console.error('Error loading bank data:', error);
        const balanceAmount = document.getElementById('balanceAmount');
        if (balanceAmount) {
            balanceAmount.textContent = '₹---.--';
        }
    }
}

function createBankCard(bank) {
    const card = document.createElement('div');
    card.className = 'bank-card';
    card.setAttribute('data-bank-id', bank.id || bank.bank_name);
    
    const statusIcon = bank.is_verified ? 
        '<i class="material-icons status-icon verified">check_circle</i>' : 
        '<i class="material-icons status-icon pending">schedule</i>';
    
    card.innerHTML = `
        <div class="bank-info">
            <div class="bank-header">
                <h4 class="bank-name">${bank.bank_name}</h4>
                ${statusIcon}
            </div>
            <p class="account-number">•••• •••• •••• ${bank.last_6_digits}</p>
            <div class="bank-actions">
                <button class="action-btn primary" onclick="selectPrimaryBank('${bank.bank_name}')">
                    <i class="material-icons">account_balance</i>
                    Set Primary
                </button>
                <button class="action-btn secondary" onclick="viewBankDetails('${bank.bank_name}')">
                    <i class="material-icons">visibility</i>
                    Details
                </button>
            </div>
        </div>
    `;
    
    // Add click handler for card selection
    card.addEventListener('click', function() {
        selectBankCard(this);
    });
    
    return card;
}

// Action functions
function sendMoney() {
    window.location.href = 'send_money.php';
}

function requestMoney() {
    window.location.href = 'request_money.php';
}

function scanQR() {
    window.location.href = 'scan_and_pay.html';
}

function recharge() {
    window.location.href = 'recharge.php';
}

function billPayment() {
    window.location.href = 'bill_payment.php';
}

function addBank() {
    window.location.href = 'select_bank.html';
}

function logout() {
    if (confirm('Are you sure you want to logout?')) {
        window.location.href = 'logout.php';
    }
}

// Update balance (for demo purposes)
async function updateBalance() {
    const balanceAmount = document.getElementById('balanceAmount');
    if (balanceAmount && !balanceAmount.textContent.includes('--')) {
        const currentBalance = parseFloat(balanceAmount.textContent.replace('₹', '').replace(',', ''));
        const newBalance = currentBalance + (Math.random() * 10 - 5); // Small random changes
        balanceAmount.textContent = `₹${Math.max(0, newBalance).toFixed(2)}`;
    }
}

// Search functionality
function performSearch() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const searchResults = document.getElementById('searchResults');
    
    if (searchTerm.length < 2) {
        searchResults.style.display = 'none';
        return;
    }
    
    const suggestions = [
        { type: 'contact', text: 'Send to John Doe', action: 'sendToContact("john")' },
        { type: 'service', text: 'Mobile Recharge', action: 'recharge()' },
        { type: 'service', text: 'Bill Payment', action: 'billPayment()' },
        { type: 'bank', text: 'Add Bank Account', action: 'addBank()' },
        { type: 'transaction', text: 'Transaction History', action: 'showTransactions()' }
    ].filter(item => item.text.toLowerCase().includes(searchTerm));
    
    if (suggestions.length > 0) {
        searchResults.innerHTML = suggestions.map(item => 
            `<div class="search-result-item" onclick="${item.action}">
                <i class="material-icons">${getSearchIcon(item.type)}</i>
                <span>${item.text}</span>
            </div>`
        ).join('');
        searchResults.style.display = 'block';
    } else {
        searchResults.style.display = 'none';
    }
}

function getSearchIcon(type) {
    const icons = {
        contact: 'person',
        service: 'apps',
        bank: 'account_balance',
        transaction: 'history'
    };
    return icons[type] || 'search';
}

// Profile menu functions
function toggleProfileMenu() {
    const menu = document.getElementById('profileMenu');
    menu.classList.toggle('show');
}

// Bank selection functions
function selectBankCard(card) {
    // Remove active class from all cards
    document.querySelectorAll('.bank-card').forEach(c => c.classList.remove('active'));
    // Add active class to selected card
    card.classList.add('active');
}

function selectPrimaryBank(bankName) {
    // Here you would typically make an API call to set the primary bank
    console.log('Setting primary bank:', bankName);
    // For now, just show a success message
    showNotification(`${bankName} set as primary bank`, 'success');
}

function viewBankDetails(bankName) {
    // Redirect to bank details page
    window.location.href = `bank_details.php?bank=${encodeURIComponent(bankName)}`;
}

// Notification system
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <i class="material-icons">${type === 'success' ? 'check_circle' : 'info'}</i>
        <span>${message}</span>
    `;
    
    document.body.appendChild(notification);
    
    // Show notification
    setTimeout(() => notification.classList.add('show'), 100);
    
    // Hide notification after 3 seconds
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => document.body.removeChild(notification), 300);
    }, 3000);
}

// Navigation functions
function showTransactions() {
    window.location.href = 'transaction_history.php';
}

function showCards() {
    window.location.href = 'manage_cards.php';
}

function showProfile() {
    window.location.href = 'profile.php';
}

function changePassword() {
    window.location.href = 'change_password.php';
}

function manageSettings() {
    window.location.href = 'settings.php';
}