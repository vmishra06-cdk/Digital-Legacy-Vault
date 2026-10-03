<?php
require_once __DIR__ . "/config.php";

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Failed: " . $conn->connect_error);
}

echo "Database connected successfully";
