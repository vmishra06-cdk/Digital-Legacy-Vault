<?php
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/helpers.php";

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

$uid = $_SESSION['user_id'];

// Fetch user data
$uStmt = $conn->prepare("SELECT name, email, created_at, last_check_in FROM users WHERE id = ?");
$uStmt->bind_param("i", $uid);
$uStmt->execute();
$userData = $uStmt->get_result()->fetch_assoc();

// Fetch vaults
$vStmt = $conn->prepare("SELECT id, title, category, secret, iv, tag, notes, created_at, updated_at FROM vaults WHERE user_id = ?");
$vStmt->bind_param("i", $uid);
$vStmt->execute();
$vaultRows = $vStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$decryptedVaults = [];
foreach ($vaultRows as $row) {
    $decryptedVaults[] = [
        'id' => $row['id'],
        'title' => $row['title'],
        'category' => $row['category'],
        'secret' => decrypt_vault_secret($row['secret'], $row['iv'] ?? '', $row['tag'] ?? ''),
        'notes' => $row['notes'],
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at']
    ];
}

// Fetch nominees
$nStmt = $conn->prepare("SELECT id, name, email, phone, relation, status, created_at FROM nominees WHERE user_id = ?");
$nStmt->bind_param("i", $uid);
$nStmt->execute();
$nominees = $nStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$export = [
    'application' => 'Digital Legacy Vault',
    'export_date' => date('c'),
    'user' => $userData,
    'vault_items' => $decryptedVaults,
    'nominees' => $nominees
];

log_audit($conn, $uid, 'EXPORT_DATA', "User exported decrypted vault backup");

header('Content-Type: application/json');
header('Content-Disposition: attachment; filename="digital_legacy_vault_backup_' . date('Y-m-d_His') . '.json"');
echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
exit;
?>
