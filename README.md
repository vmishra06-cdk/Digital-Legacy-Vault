# Digital Legacy Vault

Decide today what happens to your digital life tomorrow.

---

## Table of Contents
1. About Digital Legacy Vault
2. Why Do You Need This?
3. How Does It Work (In Plain English)
4. What Do You Need Before Getting Started?
5. How to Run on Localhost (Step-by-Step Guide)
   - Method 1: Using XAMPP (Easiest for Beginners)
   - Method 2: Using the Command Line (Fast and Direct)
6. Feature Guide: How to Use the System
7. Zero-Knowledge Security: How Your Secrets Stay Safe
8. Frequently Asked Questions (FAQ) & Troubleshooting
9. Project File Structure
10. License

---

## 1. About Digital Legacy Vault

Digital Legacy Vault is a web application that acts like a private digital safe deposit box for your most important information.

In today's world, everyone has digital assets:
- Online banking and investment accounts
- Cryptocurrency wallet keys and seed phrases
- Passwords and digital account credentials
- Legal wills, property papers, and insurance policies
- Personal letters, photos, and time-capsule messages for family

If an unexpected accident or emergency happens to you, your family members usually cannot access these accounts or even know where to look. Digital Legacy Vault solves this problem by holding your encrypted data safely and only releasing specific secrets to your chosen beneficiaries if you stop checking in.

---

## 2. Why Do You Need This?

Ask yourself these simple questions:
- If you were in an accident tomorrow, would your family know your bank accounts, life insurance policies, or passwords?
- If you own cryptocurrency, would your private recovery keys be lost forever?
- Can you trust cloud companies not to read your private notes or legal documents?

Digital Legacy Vault gives you peace of mind:
- Your secrets are encrypted right inside your own browser before being sent anywhere. The server never knows what your secrets are.
- You remain in total control while you are alive and well.
- Only when an automated timer detects that you are missing does the system allow your designated beneficiaries to claim what belongs to them.

---

## 3. How Does It Work (In Plain English)

The entire system works in four straightforward steps:

### Step 1: Store Your Secrets
You log in to your vault and add your secrets. These can be passwords, bank account numbers, cryptocurrency recovery phrases, notes, or uploaded files (like PDFs, photos, or documents up to 25 MB). Everything is locked using strong military-grade encryption in your browser before saving.

### Step 2: Add Your Trusted Beneficiaries
You list the people you trust, such as your spouse, child, sibling, or family lawyer. For each person, you enter their name, email, phone number, and relationship. The system generates a private, unique access token for each beneficiary.

### Step 3: Choose Who Gets What
You decide which beneficiary gets which secret. For example:
- Your spouse receives the bank account and mortgage information.
- Your child receives personal letters and photos.
- Your lawyer receives your legal will.
Secrets that you do not assign to anyone remain private to you only.

### Step 4: The Dead Man's Switch (The Check-In Timer)
You set a check-in schedule, such as "Check in every 30 days" with a "7-day grace period".
- Every time you log in to your vault, you click the "Pulse Check-In" button to let the system know you are alive and well.
- As long as you check in, the vault stays locked and no one else can see your secrets.
- If you miss your check-in deadline and the grace period passes, the system enters the released state. Your beneficiaries can then visit the Claim Portal, enter their private token, and decrypt the specific secrets you left for them.

---

## 4. What Do You Need Before Getting Started?

To run this application on your personal computer (localhost), you need:

1. **A Computer**: Windows, macOS, or Linux.
2. **PHP**: Version 8.0 or newer. PHP is the programming language that runs the backend logic.
3. **MySQL or MariaDB**: A database system that stores the encrypted records and user accounts.
4. **A Web Browser**: Google Chrome, Mozilla Firefox, Microsoft Edge, Brave, or Apple Safari.
5. **A Beginner-Friendly Local Server Tool (Recommended)**:
   - For Windows / macOS / Linux: **XAMPP** (comes with PHP, MySQL, and phpMyAdmin in one package).
   - Alternatively on macOS: **MAMP** or **Homebrew**.

---

## 5. How to Run on Localhost (Step-by-Step Guide)

Choose the method that feels easiest for you.

---

### Method 1: Using XAMPP (Easiest for Beginners)

XAMPP gives you everything you need in a single graphical application.

#### Step 1: Download and Install XAMPP
1. Download XAMPP for your operating system from the official website: `https://www.apachefriends.org`.
2. Install it with the default options selected.

#### Step 2: Copy the Project Folder to XAMPP
1. Locate your XAMPP web directory:
   - **Windows**: `C:\xampp\htdocs\`
   - **macOS**: `/Applications/XAMPP/xamppfiles/htdocs/`
   - **Linux**: `/opt/lampp/htdocs/`
2. Copy the entire `digital-legacy-vault` folder into that `htdocs` directory.
3. Rename the copied folder to `digital-legacy-vault` so the path is:
   `htdocs/digital-legacy-vault`

#### Step 3: Start Apache and MySQL
1. Open the **XAMPP Control Panel**.
2. Click the **Start** button next to **Apache**.
3. Click the **Start** button next to **MySQL**.
Both services should now turn green, indicating they are active.

#### Step 4: Create the Database in phpMyAdmin
1. Open your web browser and go to:
   ```text
   http://localhost/phpmyadmin
   ```
2. Click on the **Databases** tab in the top navigation bar.
3. Under **Create database**, type the name:
   ```text
   digital_legacy
   ```
4. Click the **Create** button.
5. In the left sidebar, click on your newly created `digital_legacy` database.
6. Click on the **Import** tab in the top navigation bar.
7. Click **Choose File** (or **Browse**) and select the `database.sql` file located inside your `digital-legacy-vault` folder.
8. Scroll to the bottom and click **Import** (or **Go**).
9. You will see a success message confirming that the tables (`users`, `vaults`, `nominees`, etc.) have been imported.

#### Step 5: Check Database Credentials
1. Open the file `digital-legacy-vault/backend/config.php` in any text editor (like Notepad, VS Code, or TextEdit).
2. Look at lines 4 to 8:
   ```php
   define("DB_HOST", "127.0.0.1");
   define("DB_USER", "root");
   define("DB_PASS", "");          // For XAMPP, password is empty by default
   define("DB_NAME", "digital_legacy");
   define("DB_PORT", 3306);
   ```
3. If you use standard XAMPP, leave `DB_PASS` empty (`""`). If you set a root password, put it between the quotes.
4. Save the file.

#### Step 6: Open the App in Your Browser
Open your browser and visit:
```text
http://localhost/digital-legacy-vault/
```
You will see the home page. You can now register an account and start using your vault.

---

### Method 2: Using the Command Line (Fast and Direct)

If you already have PHP and MySQL installed on your machine (via Homebrew, Anaconda, or apt), you can run the project in three minutes.

#### Step 1: Open Your Terminal
Open Terminal (macOS/Linux) or PowerShell (Windows) and navigate to the project directory:
```bash
cd path/to/Digital-Legacy-Vault
```

#### Step 2: Ensure MySQL Is Running
- **macOS (Homebrew)**:
  ```bash
  brew services start mysql
  ```
- **Linux (systemd)**:
  ```bash
  sudo systemctl start mysql
  ```

#### Step 3: Create the Database and Import Tables
Run the following commands:
```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS digital_legacy;"
mysql -u root -p digital_legacy < digital-legacy-vault/database.sql
```
*(When prompted for a password, enter your MySQL root password. If you have no password, press Enter).*

#### Step 4: Verify Database Settings
Check `digital-legacy-vault/backend/config.php`:
```php
define("DB_HOST", "127.0.0.1");
define("DB_USER", "root");
define("DB_PASS", "root"); // Match your local MySQL password
define("DB_NAME", "digital_legacy");
define("DB_PORT", 3306);
```

#### Step 5: Start the PHP Development Server
Navigate into the `digital-legacy-vault` folder and run PHP's built-in server:
```bash
cd digital-legacy-vault
php -S 0.0.0.0:8000
```

#### Step 6: Visit the Application
Open your browser and go to:
```text
http://localhost:8000
```

---

## 6. Feature Guide: How to Use the System

### 1. Creating an Account
- Go to `http://localhost:8000/register.php`.
- Enter your full name, email address, and choose a strong Master Password.
- **Important**: Your master password is the foundation of your encryption key. Do not forget it, because no one (not even the server administrator) can recover your secrets without it.

### 2. Logging In
- Go to `http://localhost:8000/login.php`.
- Enter your email and master password.
- Watch the top status badge turn into **ZERO-KNOWLEDGE ACTIVE**. This means your decryption key has been generated inside your browser's private memory.

### 3. Adding Secrets
- On your dashboard, click **+ Add Secret Record**.
- Choose a category:
  - **Credentials**: Passwords, server logins, email credentials.
  - **Financial**: Bank account numbers, PINs, routing codes, investment portfolios.
  - **Crypto**: Hardware wallet PINs, 12 or 24-word seed recovery phrases.
  - **Legal**: Will instructions, real estate deeds, attorney contacts.
  - **Personal**: Private letters, family messages, journal notes.
- Type in your secret text.
- Optionally attach a document (up to 25 MB).
- Select which beneficiaries should receive access to this secret.
- Click **Secure and Encrypt**. The data is encrypted immediately in your browser and sent to the database as unreadable ciphertext.

### 4. Viewing and Copying Secrets
- On your vault card grid, secrets appear as masked dots (`••••••••••••••••`).
- Click **Reveal**: Your browser uses your in-memory key to decrypt and show the text on your screen. Click **Hide** to mask it again.
- Click **Copy**: Your browser decrypts the secret and copies it straight to your clipboard.
- Click **Download**: If an encrypted file was attached, your browser fetches the encrypted data, decrypts it locally, and saves the plaintext file to your Downloads folder.

### 5. Managing Beneficiaries (Nominees)
- Click the **Beneficiaries** tab in the dashboard navigation.
- Click **+ Add Beneficiary**.
- Enter their name, email, phone number, and relationship.
- The system generates a private 48-character **Claim Token**.
- You can give this token to your beneficiary, write it in your legal will, or keep it in a safe place for them.

### 6. Setting Up the Dead Man's Switch
- Click the **Dead Man Switch** tab.
- Set your **Check-In Frequency** (e.g., 30 days).
- Set your **Grace Period** (e.g., 7 days).
- The dashboard shows a real-time countdown timer.
- Whenever you visit the dashboard, click **Send Pulse Check-In** to reset the timer to full.

### 7. How Beneficiaries Claim Secrets
- Beneficiaries visit the Claim Portal:
  ```text
  http://localhost:8000/claim.php
  ```
- They enter their registered email address and their private claim token.
- If the Dead Man's Switch is still active (meaning you are checking in normally), the system informs them that the vault is secure and locked.
- If the switch has triggered (meaning your check-in deadline and grace period have expired), the portal unlocks only the secrets you assigned to that specific beneficiary.

### 8. Two-Factor Authentication (2FA)
- Click the **Security & 2FA** tab.
- Scan the QR code using Google Authenticator, Authy, Microsoft Authenticator, or 1Password.
- Type in the 6-digit verification code to enable 2FA.
- Save your 8 single-use emergency backup recovery codes in case you ever lose access to your phone.

### 9. Duress PIN (Decoy Vault)
- In the **Security & 2FA** tab, you can set an alternative "Duress Password".
- If someone ever forces you to log in against your will, enter your Duress Password instead of your real password.
- The system logs you into a clean, convincing decoy vault with zero sensitive records, while secretly writing an emergency security incident to the audit log.

### 10. Security Health Score (Audit System)
- The dashboard displays a security score from 0% to 100%.
- It grades your vault across five critical security areas:
  1. Master password strength and entropy calculation.
  2. Two-Factor Authentication activation.
  3. Beneficiary assignment coverage (ensuring no secret is left without a designated heir).
  4. Dead Man's Switch freshness and active status.
  5. Offline cold backup freshness.
- Click **Health Audit** in the sidebar to view actionable recommendations to achieve a 100% security rating.

### 11. Offline Cold Backup
- Click the **Export Backup** tab.
- Click **Download Encrypted JSON Archive**.
- This saves an offline, timestamped copy of all your encrypted records to your computer or USB thumb drive.
- Even if your server is deleted, your offline backup can be imported and decrypted with your master password.

### 12. Time Capsules
- Send a message that unlocks on a specific date in the future (for example, on your child's 18th birthday or a wedding anniversary).
- Click the **Time Capsule** tab, write your note, set the target unlock date, and pick the recipient.

---

## 7. Zero-Knowledge Security: How Your Secrets Stay Safe

### What Does "Zero-Knowledge" Mean?
Zero-Knowledge means the web server hosting Digital Legacy Vault knows **zero** information about your actual secrets:
- The server never sees your plaintext passwords.
- The server never receives your unencrypted files.
- The server only stores jumbled, unreadable ciphertext.

### Technical Security Specifications
- **Encryption Algorithm**: AES-256-GCM (Advanced Encryption Standard in Galois/Counter Mode with 256-bit keys). This is the same standard used by government institutions and top security agencies.
- **Key Derivation**: PBKDF2 (Password-Based Key Derivation Function 2) running 100,000 iterations of SHA-256. This protects against brute-force password cracking.
- **Initialization Vectors**: Each piece of data uses a cryptographically random 96-bit IV, ensuring that encrypting the same text twice produces completely different ciphertexts.
- **Data Integrity**: 128-bit authentication tags verify that encrypted data has not been modified or tampered with.
- **Database Safety**: All server database queries use prepared statements with parameterized inputs to eliminate SQL injection attacks.
- **Session Security**: Protected by cryptographic anti-CSRF tokens and regenerated session IDs on every login.

---

## 8. Frequently Asked Questions (FAQ) & Troubleshooting

### Question 1: "Database connection failed" or "Access denied for user root"
- **Solution**: Make sure your MySQL server is running. Then open `digital-legacy-vault/backend/config.php` and verify `DB_USER` and `DB_PASS`. If you use XAMPP, `DB_PASS` should be empty `""`. If you use MAMP, `DB_PASS` is usually `"root"`.

### Question 2: "Port 8000 is already in use"
- **Solution**: Another program is using port 8000. Start the PHP server on port 8080 instead:
  ```bash
  cd digital-legacy-vault
  php -S 0.0.0.0:8080
  ```
  Then open `http://localhost:8080` in your browser.

### Question 3: What happens if I forget my master password?
- **Answer**: Because of the Zero-Knowledge architecture, your master password is never stored on the server. If you lose your password, there is no "password reset" that can decrypt your secrets. We strongly recommend exporting an offline JSON backup and keeping your emergency recovery codes in a safe physical location.

### Question 4: Can I run this online on a real domain?
- **Answer**: Yes. You can upload the `digital-legacy-vault` folder to any web host supporting PHP 8.0+ and MySQL (such as Apache, Nginx, or cPanel hosting). Always ensure HTTPS/SSL is enabled so browser Web Crypto APIs function properly.

### Question 5: Why do I see 3D animations on the login screen?
- **Answer**: The login and landing pages feature an ambient, hardware-accelerated 3D background built using Three.js. It renders dual floating infinity loops that gently react to your mouse movement, symbolizing everlasting digital legacy protection.

---

## 9. Project File Structure

```text
Digital-Legacy-Vault/
|-- README.md                         # This user and setup guide
|-- Dockerfile                        # Optional Docker container definition
|-- start.sh                          # Automated startup helper script
|
|-- digital-legacy-vault/             # Main application directory
    |-- index.php                     # Landing page with feature introduction
    |-- login.php                     # Login page with 3D floating infinity loops
    |-- register.php                  # User account creation page
    |-- login_2fa.php                 # Two-Factor Authentication verification
    |-- dashboard.php                 # Full user dashboard and vault management
    |-- claim.php                     # Beneficiary claim portal
    |-- capsule_view.php              # Time-locked message viewer
    |-- logout.php                    # Secure session termination and key clearing
    |-- database.sql                  # Complete SQL schema and table structures
    |
    |-- backend/                      # Backend controllers and logic
    |   |-- config.php                # Database credentials and global settings
    |   |-- db.php                    # MySQL database connection initialization
    |   |-- helpers.php               # Security score engine, audit logging, TOTP helpers
    |   |-- auth.php                  # User registration, login, and 2FA processing
    |   |-- vault.php                 # Secret creation, updates, and deletion handler
    |   |-- nominee.php               # Beneficiary adding, editing, and token management
    |   |-- pulse.php                 # Dead Man's Switch check-in and timer configuration
    |   |-- export.php                # Offline JSON archive backup generator
    |   |-- download.php              # Encrypted attachment download server
    |   |-- security.php              # 2FA setup, recovery codes, and duress PIN settings
    |   |-- capsule.php               # Future-dated time capsule message handler
    |   |-- cron_worker.php           # Background task runner for automated status checks
    |
    |-- assets/                       # Client-side styling, scripts, and audio
    |   |-- css/
    |   |   |-- style.css             # Cyberpunk glassmorphic styling and layout
    |   |-- js/
    |       |-- bg3d.js               # Dual 3D floating infinity loop animation (Three.js)
    |       |-- vault3d.js            # Holographic core animation and Web Audio SFX
    |       |-- crypto-zk.js          # Web Crypto API Zero-Knowledge AES-256-GCM engine
    |
    |-- uploads/                      # Protected storage folder for encrypted file attachments
```

---

## 10. License

This project is open-source and released under the **MIT License**. You are free to use, study, modify, and distribute this software for personal or educational purposes.
