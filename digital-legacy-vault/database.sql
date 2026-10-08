CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100),
  email VARCHAR(100) UNIQUE,
  password VARCHAR(255),
  password_entropy_score INT DEFAULT 65,
  last_check_in TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  check_in_frequency_days INT DEFAULT 30,
  grace_period_days INT DEFAULT 7,
  status VARCHAR(30) DEFAULT 'active',
  two_factor_secret VARCHAR(64) DEFAULT '',
  two_factor_enabled TINYINT(1) DEFAULT 0,
  duress_password VARCHAR(255) DEFAULT '',
  consensus_threshold INT DEFAULT 1,
  last_backup_export TIMESTAMP NULL DEFAULT NULL,
  two_factor_recovery_codes TEXT,
  client_salt VARCHAR(64) DEFAULT '',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS vaults (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  title VARCHAR(150),
  category VARCHAR(50) DEFAULT 'Credentials',
  secret TEXT,
  iv VARCHAR(64) DEFAULT '',
  tag VARCHAR(64) DEFAULT '',
  is_client_encrypted TINYINT(1) DEFAULT 1,
  notes TEXT,
  file_path VARCHAR(255) DEFAULT '',
  file_name VARCHAR(255) DEFAULT '',
  file_size INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS nominees (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  name VARCHAR(100),
  email VARCHAR(100),
  phone VARCHAR(30) DEFAULT '',
  relation VARCHAR(50),
  claim_token VARCHAR(64) UNIQUE,
  status VARCHAR(30) DEFAULT 'pending',
  has_approved_release TINYINT(1) DEFAULT 0,
  encrypted_vault_key TEXT DEFAULT NULL,
  approval_timestamp TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS vault_nominee_access (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vault_id INT NOT NULL,
  nominee_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (vault_id) REFERENCES vaults(id) ON DELETE CASCADE,
  FOREIGN KEY (nominee_id) REFERENCES nominees(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS capsules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  recipient_name VARCHAR(100),
  recipient_email VARCHAR(100),
  title VARCHAR(150),
  message TEXT,
  iv VARCHAR(64) DEFAULT '',
  tag VARCHAR(64) DEFAULT '',
  unlock_date DATE NOT NULL,
  access_token VARCHAR(64) UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS dispatched_notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  recipient_email VARCHAR(100),
  notification_type VARCHAR(50),
  subject VARCHAR(200),
  sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  action VARCHAR(50),
  details TEXT,
  ip_address VARCHAR(45),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
