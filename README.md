# Digital Legacy Vault

Decide today what happens to your digital life tomorrow.

Digital Legacy Vault is an automated cryptographic fail-safe platform for digital asset inheritance. It enables individuals to store private credentials, financial access keys, crypto seed phrases, and confidential documents protected by authenticated AES-256-GCM encryption, governed by an automated Dead Man's Switch protocol.

---

## Key Features

- **Client-Side Zero-Knowledge Encryption**: Native Web Crypto API (`window.crypto.subtle`) derives AES-256-GCM encryption keys directly from the master password using PBKDF2 (100,000 iterations). Plaintext secrets and attached documents are encrypted locally in the browser before transmission; the server and database never hold or view plaintext in memory.
- **Two-Factor Authentication (2FA / TOTP)**: Full RFC 6238 TOTP implementation compatible with Google Authenticator, Authy, and 1Password. Features dynamic setup QR codes, manual secret entry, and 8 single-use emergency recovery backup codes.
- **Dead Man's Switch Protocol**: Automated heartbeat monitor with configurable check-in intervals, grace periods, and emergency simulation testing.
- **Time-Locked Future Message Capsules**: Encrypted milestone messages locked until specific future release dates for loved ones and descendants.
- **Granular Beneficiary Delegation**: Map specific vault records or confidential files to specific trustees.
- **Document & File Upload Pipeline**: Securely attach legal documents, wills, and certificates (up to 25MB) with local client-side encryption.
- **Independent Beneficiary Claim Portal**: Dedicated claim gateway using 48-character unguessable access tokens with in-browser client decryption.
- **3D WebGL Holographic GUI**: Hardware-accelerated Three.js 3D vault core that visually responds to protocol health and system states.
- **Immutable Security Audit Trail**: Comprehensive logging of authentications, record updates, check-ins, and external claim access attempts.
- **Air-Gapped Data Backup**: One-click Zero-Knowledge JSON archive export decrypted locally in the browser.

---

## Technology Stack

- **Cryptographic Engine**: Browser Web Crypto API (`window.crypto.subtle`), AES-256-GCM, PBKDF2-HMAC-SHA256, RFC 6238 TOTP
- **Backend**: PHP 8.1+ (Strict session isolation, anti-CSRF token verification, and prepared statements)
- **Database**: MySQL 5.7+ / MariaDB
- **Frontend**: HTML5, Modern CSS (Glassmorphism), Vanilla JavaScript
- **3D Visualization**: Three.js WebGL Engine
- **Audio Feedback**: Procedural Web Audio API Sound Synthesizer

---

## Getting Started

### 1. Prerequisites
- PHP 8.0 or newer
- MySQL or MariaDB

### 2. Database Setup
Import the database schema:
```bash
mysql -u root -p < digital-legacy-vault/database.sql
```

Configure database credentials in `digital-legacy-vault/backend/config.php` if needed:
```php
define("DB_HOST", "localhost");
define("DB_USER", "root");
define("DB_PASS", "root");
define("DB_NAME", "digital_legacy");
```

### 3. Start Local Server
Run the built-in development server from the `digital-legacy-vault` folder:
```bash
cd digital-legacy-vault
php -S localhost:8000
```

### 4. Access Application
- Dashboard & Vault: http://localhost:8000
- Beneficiary Claim Gateway: http://localhost:8000/claim.php
