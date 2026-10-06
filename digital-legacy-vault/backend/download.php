<?php
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/helpers.php";

$vaultId = (int)($_GET['id'] ?? 0);
$token = trim($_GET['token'] ?? '');

if ($vaultId <= 0) {
    http_response_code(400);
    die("Invalid request");
}

// Fetch vault record
$stmt = $conn->prepare("SELECT v.*, u.status as owner_status, u.last_check_in, u.check_in_frequency_days, u.grace_period_days 
                       FROM vaults v 
                       JOIN users u ON v.user_id = u.id 
                       WHERE v.id = ?");
$stmt->bind_param("i", $vaultId);
$stmt->execute();
$vault = $stmt->get_result()->fetch_assoc();

if (!$vault || empty($vault['file_path'])) {
    http_response_code(404);
    die("File not found or not attached to this record.");
}

$authorized = false;

// Case 1: Owner is logged in
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $vault['user_id']) {
    $authorized = true;
}

// Case 2: Beneficiary with valid token and triggered status
if (!$authorized && !empty($token)) {
    $nStmt = $conn->prepare("SELECT id, user_id FROM nominees WHERE claim_token = ?");
    $nStmt->bind_param("s", $token);
    $nStmt->execute();
    $nominee = $nStmt->get_result()->fetch_assoc();

    if ($nominee && $nominee['user_id'] == $vault['user_id']) {
        // Check if vault is released
        $switch = get_dead_man_switch_status([
            'status' => $vault['owner_status'],
            'last_check_in' => $vault['last_check_in'],
            'check_in_frequency_days' => $vault['check_in_frequency_days'],
            'grace_period_days' => $vault['grace_period_days']
        ]);

        if ($switch['is_triggered']) {
            // Check specific access
            $accStmt = $conn->prepare("SELECT 1 FROM vault_nominee_access WHERE vault_id = ? AND nominee_id = ?");
            $accStmt->bind_param("ii", $vaultId, $nominee['id']);
            $accStmt->execute();
            $hasSpecific = $accStmt->get_result()->num_rows > 0;

            // Check if user has restricted access
            $anyAcc = $conn->prepare("SELECT 1 FROM vault_nominee_access WHERE vault_id = ?");
            $anyAcc->bind_param("i", $vaultId);
            $anyAcc->execute();
            $isRestricted = $anyAcc->get_result()->num_rows > 0;

            if ($hasSpecific || !$isRestricted) {
                $authorized = true;
            }
        }
    }
}

if (!$authorized) {
    http_response_code(403);
    die("Access denied. You are not authorized to download this file.");
}

$fullPath = __DIR__ . "/../" . $vault['file_path'];

if (!file_exists($fullPath)) {
    http_response_code(404);
    die("File asset no longer exists on disk.");
}

$originalName = $vault['file_name'] ?: basename($fullPath);
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($fullPath) ?: "application/octet-stream";

header('Content-Description: File Transfer');
header('X-Client-Encrypted: ' . (!empty($vault['is_client_encrypted']) ? '1' : '0'));
header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . addslashes($originalName) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($fullPath));
readfile($fullPath);
exit;
?>
