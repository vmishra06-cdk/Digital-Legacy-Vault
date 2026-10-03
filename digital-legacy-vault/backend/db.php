<?php
require_once __DIR__ . "/config.php";

$port = defined("DB_PORT") ? DB_PORT : 3306;
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, $port);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Auto-initialize tables if first time setup on cloud
$check = $conn->query("SHOW TABLES LIKE 'users'");
if ($check && $check->num_rows === 0) {
    $schemaFile = __DIR__ . "/../database.sql";
    if (file_exists($schemaFile)) {
        $sql = file_get_contents($schemaFile);
        $conn->multi_query($sql);
        while ($conn->next_result()) {;}
    }
}
?>
