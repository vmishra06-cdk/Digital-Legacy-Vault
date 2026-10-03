<?php
// Root router: forward to main application
if (file_exists(__DIR__ . '/digital-legacy-vault/index.php')) {
    header("Location: digital-legacy-vault/index.php");
    exit;
} else {
    // Inside Docker container where files were copied to webroot
    require_once __DIR__ . '/index.php';
}
?>
