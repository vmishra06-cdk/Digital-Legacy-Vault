<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

// Database configuration supporting Railway, Docker, and local development
define("DB_HOST", getenv("MYSQLHOST") ?: getenv("DB_HOST") ?: "127.0.0.1");
define("DB_USER", getenv("MYSQLUSER") ?: getenv("DB_USER") ?: "root");
define("DB_PASS", getenv("MYSQLPASSWORD") ?: getenv("MYSQL_ROOT_PASSWORD") ?: getenv("DB_PASS") ?: "root");
define("DB_NAME", getenv("MYSQLDATABASE") ?: getenv("DB_NAME") ?: "digital_legacy");
define("DB_PORT", (int)(getenv("MYSQLPORT") ?: getenv("DB_PORT") ?: 3306));

// Master application key for vault encryption (in production, loaded from environment)
$masterSecret = getenv("VAULT_MASTER_KEY") ?: 'digital-legacy-vault-master-secret-key-2026';
define("VAULT_MASTER_KEY", hash('sha256', $masterSecret, true));

// CSRF helper
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function csrf_token() {
    return $_SESSION['csrf_token'];
}

function verify_csrf() {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        die("CSRF verification failed. Please refresh and try again.");
    }
}
?>
