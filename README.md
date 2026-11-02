# PaySecure Online Payment

PaySecure is a PHP-based online payment and wallet management project for
laptop/desktop browsers. It provides account management, wallet balance
tracking, money transfers, bill payments, mobile recharge, bank linking,
transaction history, UPI PIN management, and referrals.

## Current status

- Designed and tested for laptop/desktop screens.
- Mobile-phone responsive design is not implemented yet.
- This project is intended for local development and learning.
- It is not production-ready for real financial transactions.

## Technology stack

- **Backend:** PHP 8.2.12
- **Database:** MySQL/MariaDB
- **Web server:** Apache
- **Local environment:** XAMPP
- **Frontend:** HTML5, CSS3, and JavaScript
- **Icons:** Font Awesome
- **Database access:** PHP MySQLi
- **Session handling:** PHP sessions

## Main features

- User registration and login
- Password and PIN management
- Wallet balance display
- Add money flow
- Send money to another account
- Mobile and DTH recharge
- Bill payments
- Linked bank accounts
- QR and UPI payment flows
- Transaction and payment history
- Referral system
- Receipt generation

## Local setup

1. Install XAMPP with Apache, PHP, and MySQL/MariaDB.
2. Clone or copy this project into:

   ```text
   C:\xampp\htdocs\online_payment
   ```

3. Start **Apache** and **MySQL** from the XAMPP Control Panel.
4. Create a database named `online_payment`.
5. Import the project database schema into that database.
6. Configure the database values in `account/connect.php`.
7. Open the application at:

   ```text
   http://localhost/online_payment/account/login.html
   ```

## Project entry points

- Login: `account/login.html`
- Login handler: `account/login.php`
- Dashboard: `account/homepage_clean.php`
- Database connection: `account/connect.php`

## Important notes

- Keep Apache and MySQL running while using the application.
- Keep the local folder name as `online_payment` if using the URLs above.
- Do not use real banking credentials, payment details, or personal data.
- Review database credentials and generated receipt files before publishing
  the repository publicly.

## License

This project is provided for educational and local-development purposes.
