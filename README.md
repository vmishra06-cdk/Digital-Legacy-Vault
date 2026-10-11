# Digital Legacy Vault

Decide today what happens to your digital life tomorrow.

Digital Legacy Vault is a secure web application that acts as a digital safety deposit box. It allows you to store passwords, financial accounts, cryptocurrency recovery phrases, legal wills, and private documents. If something happens to you and you stop checking in, an automated Dead Man's Switch protocol safely transfers your selected secrets to your trusted beneficiaries.

---

## System Requirements

Before running the application on your computer, ensure you have the following software installed:

### 1. Required Software
- **PHP**: Version 8.0, 8.1, 8.2, or newer.
- **MySQL / MariaDB**: Version 5.7, 8.0, or newer (MariaDB 10.3+ also supported).
- **Web Browser**: Any modern web browser that supports the Web Crypto API (Google Chrome, Mozilla Firefox, Microsoft Edge, Brave, or Apple Safari).
- **Git** *(Optional)*: To clone the repository from GitHub.

### 2. Required PHP Extensions
Most standard PHP installations (including XAMPP, MAMP, WAMP, and Homebrew PHP) include these by default:
- `mysqli` (for database communication)
- `openssl` (for server-side cryptographic operations)
- `session` (for user login sessions)
- `json` (for backup exports and data encoding)

---

## Step-by-Step Setup Guide (Run on Localhost)

Follow these steps in order to get the project running on your computer.

### Step 1: Open Your Terminal or Command Prompt
Open your terminal application:
- **macOS**: Open `Terminal` (via Spotlight or Applications > Utilities).
- **Windows**: Open `PowerShell` or `Command Prompt`.
- **Linux**: Open your preferred shell terminal.

Navigate to the project folder where you extracted or cloned the repository:
```bash
cd "path/to/Digital-Legacy-Vault"
```

---

### Step 2: Start Your MySQL Server
Make sure your MySQL database server is running.

- **If you use XAMPP / MAMP / WAMP**:
  Open the XAMPP/MAMP control panel and click **Start** next to **MySQL**.
- **If you use macOS Homebrew**:
  ```bash
  brew services start mysql
  ```
- **If you use Linux**:
  ```bash
  sudo systemctl start mysql
  ```

---

### Step 3: Create the Database and Import Tables

You need to create a database named `digital_legacy` and import the provided database file.

#### Option A: Using the Command Line (Fastest)
Run the following commands in your terminal:

1. Log in to MySQL and create the database:
   ```bash
   mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS digital_legacy;"
   ```
   *(Press Enter. If you have a password, type it. If you don't have a password, just press Enter).*

2. Import the database tables from `database.sql`:
   ```bash
   mysql -u root -p digital_legacy < digital-legacy-vault/database.sql
   ```

#### Option B: Using phpMyAdmin (Visual Interface)
If you use XAMPP, MAMP, or WAMP:
1. Open your browser and go to `http://localhost/phpmyadmin`.
2. Click on **Databases** in the top menu.
3. Type `digital_legacy` in the database name field and click **Create**.
4. Select the newly created `digital_legacy` database from the left sidebar.
5. Click the **Import** tab at the top.
6. Click **Choose File** and select `digital-legacy-vault/database.sql`.
7. Scroll to the bottom and click **Import** (or **Go**).

---

### Step 4: Check Database Connection Settings

Open the file `digital-legacy-vault/backend/config.php` in any text editor.

Verify that the database settings match your local MySQL configuration:
```php
define("DB_HOST", "127.0.0.1");
define("DB_USER", "root");
define("DB_PASS", "root");      // Change to your MySQL password, or leave empty "" if no password
define("DB_NAME", "digital_legacy");
define("DB_PORT", 3306);
```

- **XAMPP Default**: `DB_USER` is `"root"`, `DB_PASS` is `""` (empty).
- **MAMP Default**: `DB_USER` is `"root"`, `DB_PASS` is `"root"`.
- **Custom MySQL**: Use your own MySQL username and password.

---

### Step 5: Start the Built-in PHP Development Server

From your terminal, navigate directly into the `digital-legacy-vault` folder and start PHP's built-in web server:

```bash
cd digital-legacy-vault
php -S localhost:8000
```

You should see an output similar to:
```text
[PHP Development Server started at http://localhost:8000]
[Listening on http://localhost:8000]
[Document root is /path/to/digital-legacy-vault]
```

Keep this terminal window open while you use the application.

---

### Step 6: Open the Application in Your Browser

Open your web browser and navigate to:
```text
http://localhost:8000
```

You will see the home page. From here:
1. Click **Get Started** or go to `http://localhost:8000/register.php` to create an account.
2. Enter your Name, Email, and a Master Password.
3. Once registered, log in at `http://localhost:8000/login.php`.
4. You are now inside your personal Digital Legacy Vault dashboard!

---

## How to Use the Key Features

### 1. Adding Secret Records
- In the dashboard, click **+ Add Secret Record**.
- Choose a category (Credentials, Financial, Legal, Crypto, or Personal).
- Enter your secret details and optional notes.
- Optionally attach a file (up to 25MB) such as a PDF of a legal document or will.
- Click **Secure and Encrypt**. The secret is encrypted directly inside your browser before saving.

### 2. Adding Beneficiaries (Nominees)
- Click the **Beneficiaries** tab in the sidebar.
- Click **+ Add Beneficiary**.
- Enter their name, email, phone number, and relationship (e.g., Spouse, Child, Lawyer).
- Each beneficiary is generated a unique, private claim key.

### 3. Assigning Secrets to Beneficiaries
- Edit any vault record to select which beneficiaries are allowed to receive that specific secret.
- Unassigned secrets remain private to you only.

### 4. Dead Man's Switch & Pulse Heartbeat
- Click the **Dead Man Switch** tab in the sidebar.
- Set your preferred check-in interval (for example, every 30 days).
- Set a grace period (for example, 7 days).
- When active, simply click **Send Pulse Check-In** whenever you visit the dashboard to confirm your vitality.
- If you miss your check-in deadline and the grace period expires, the protocol activates and your designated beneficiaries receive access instructions.

### 5. Two-Factor Authentication (2FA)
- Go to the **Security & 2FA** tab.
- Scan the displayed QR code with Google Authenticator, Authy, or 1Password.
- Enter the 6-digit code to activate 2FA.
- Download or print the 8 emergency recovery codes in case you ever lose your phone.

### 6. Interactive Vault Security Health Score
- View the real-time security score (0 to 100%) on your dashboard.
- The score evaluates 5 pillars:
  1. Master password strength and entropy.
  2. Two-Factor Authentication status.
  3. Beneficiary assignment coverage (ensures no secret is left without a designated trustee).
  4. Dead Man's Switch frequency and freshness.
  5. Offline cold backup freshness (alerts you if no backup was exported in 30 days).
- Click **Health Audit** in the sidebar to test simulations and review actionable recommendations.

### 7. Claiming Assets as a Beneficiary
- Beneficiaries access the independent claim portal at:
  ```text
  http://localhost:8000/claim.php
  ```
- They enter their registered email and private 48-character access token.
- If the protocol has triggered, their assigned secrets are decrypted directly in their browser.

---

## Project Folder Structure

```text
Digital-Legacy-Vault/
|-- README.md                         # Project documentation and setup guide
|-- digital-legacy-vault/             # Main application directory
    |-- index.php                     # Landing page
    |-- login.php                     # User login page (with floating 3D infinity background)
    |-- register.php                  # Account registration page
    |-- login_2fa.php                 # Two-Factor Authentication challenge page
    |-- dashboard.php                 # Main user dashboard and management portal
    |-- claim.php                     # Beneficiary emergency claim gateway
    |-- capsule_view.php              # Time-locked milestone message reader
    |-- logout.php                    # Session termination script
    |-- database.sql                  # Database table definitions and schema
    |
    |-- backend/                      # Server-side controllers and helpers
    |   |-- config.php                # Database and environment configuration
    |   |-- db.php                    # MySQL connection handler
    |   |-- helpers.php               # Security score audit engine, TOTP & crypto helpers
    |   |-- auth.php                  # Authentication, registration, and 2FA handling
    |   |-- vault.php                 # Secret creation, updates, and deletion
    |   |-- nominee.php               # Beneficiary management controller
    |   |-- pulse.php                 # Dead Man's Switch heartbeat tracker
    |   |-- export.php                # Offline JSON archive backup generator
    |   |-- download.php              # Encrypted file attachment download handler
    |   |-- security.php              # 2FA setup, recovery codes, and duress control
    |   |-- capsule.php               # Future-dated time capsule controller
    |   |-- cron_worker.php           # Background task runner for automated triggers
    |
    |-- assets/                       # Static front-end assets
        |-- css/
        |   |-- style.css             # Cyberpunk glassmorphic design system
        |-- js/
            |-- bg3d.js               # 3D floating infinity loop background animation
            |-- vault3d.js            # 3D holographic vault core & audio synthesizer
            |-- crypto-zk.js          # Web Crypto API Zero-Knowledge client encryption
    |
    |-- uploads/                      # Protected directory for encrypted attachments
```

---

## Troubleshooting Common Issues

### 1. Database Connection Error ("Connection failed")
- **Cause**: MySQL is not running, or credentials in `backend/config.php` do not match.
- **Fix**:
  1. Verify MySQL is active (`mysql -u root -p`).
  2. Open `digital-legacy-vault/backend/config.php` and verify `DB_USER` and `DB_PASS`. If using XAMPP, `DB_PASS` should typically be empty `""`.

### 2. "Port 8000 already in use"
- **Cause**: Another application or process is using port 8000.
- **Fix**: Start the server on a different port, such as 8080:
  ```bash
  php -S localhost:8080
  ```
  Then open `http://localhost:8080` in your browser.

### 3. File Uploads Not Saving
- **Cause**: The `uploads/` directory lacks write permissions.
- **Fix**:
  - On macOS / Linux:
    ```bash
    chmod -R 775 digital-legacy-vault/uploads
    ```
  - On Windows: Ensure the user running PHP has write permissions to the folder.

### 4. Forgotten Master Password
- Because the application uses Zero-Knowledge client-side encryption, your master password is used to derive the decryption key. If you forget your password, encrypted data cannot be decrypted by the server. Always export an offline JSON backup or save your emergency recovery codes in a secure physical location.

---

## Security Architecture Summary

- **Zero-Knowledge Principle**: Secrets are encrypted and decrypted locally in your browser using the native Web Crypto API (`window.crypto.subtle`). The server only stores encrypted ciphertext, initialization vectors (IV), and authentication tags.
- **Authenticated Encryption**: Employs AES-256-GCM, preventing both eavesdropping and unauthorized tampering.
- **Key Derivation**: Uses PBKDF2 with 100,000 SHA-256 iterations and unique per-user salts.
- **Two-Factor Authentication**: Implements RFC 6238 Time-Based One-Time Passwords (TOTP) and single-use emergency recovery keys.
- **Anti-CSRF Protection**: All POST forms enforce cryptographic session tokens.
- **Prepared Statements**: All database operations use parameterized MySQLi queries to prevent SQL injection.

---

## License

This project is open-source and released under the MIT License.
