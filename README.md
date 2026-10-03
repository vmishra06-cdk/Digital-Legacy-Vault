# Digital Legacy Vault

Decide today what happens to your digital life tomorrow.

Digital Legacy Vault is an automated cryptographic fail-safe platform for digital asset inheritance. It enables individuals to store private credentials, financial access keys, crypto seed phrases, and confidential documents protected by authenticated AES-256-GCM encryption, governed by an automated Dead Man's Switch protocol.

---

## Key Features

- **Authenticated Cryptography**: Military-grade AES-256-GCM encryption with per-record Initialization Vectors (IV) and authentication tags.
- **Dead Man's Switch Protocol**: Automated heartbeat monitor with configurable check-in intervals and grace periods.
- **Granular Beneficiary Delegation**: Map specific vault records or confidential files to specific trustees.
- **Document & File Upload Pipeline**: Securely attach legal documents, wills, and certificates (up to 25MB).
- **Independent Beneficiary Claim Portal**: Dedicated claim gateway using 48-character unguessable access tokens with conditional decryption.
- **3D WebGL Holographic GUI**: Hardware-accelerated Three.js 3D vault core that visually responds to protocol health and system states.
- **Immutable Security Audit Trail**: Comprehensive logging of authentications, record updates, check-ins, and external claim access attempts.
- **Air-Gapped Data Backup**: One-click JSON export for offline preservation.

---

## Technology Stack

- **Backend**: PHP 8.1+ (Engineered with strict session controls and anti-CSRF token verification)
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
