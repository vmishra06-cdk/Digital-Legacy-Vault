<?php
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/helpers.php";

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
}

$uid = $_SESSION['user_id'];
$uploadDir = __DIR__ . "/../uploads/";
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

function process_uploaded_file($file, $uploadDir, $uid) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $maxBytes = 25 * 1024 * 1024; // 25MB limit
    if ($file['size'] > $maxBytes) {
        return ['error' => 'Uploaded file exceeds 25MB limit.'];
    }

    $origName = basename($file['name']);
    $ext = pathinfo($origName, PATHINFO_EXTENSION);
    $safeExt = preg_replace('/[^a-zA-Z0-9]/', '', $ext);
    $storedName = "vault_" . $uid . "_" . bin2hex(random_bytes(10)) . ($safeExt ? "." . $safeExt : "");
    $targetPath = $uploadDir . $storedName;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return [
            'file_path' => "uploads/" . $storedName,
            'file_name' => $origName,
            'file_size' => (int)$file['size']
        ];
    }

    return ['error' => 'Failed to save uploaded file.'];
}

// Save New Vault Item
if (isset($_POST['save_vault'])) {
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? 'Credentials');
    $secret = $_POST['secret'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    $nomineeIds = $_POST['nominee_ids'] ?? [];

    if (empty($title) || empty($secret)) {
        header("Location: ../dashboard.php?tab=vault&error=" . urlencode("Title and secret content are required."));
        exit;
    }

    $isClientEncrypted = !empty($_POST['is_client_encrypted']) ? 1 : 0;
    $clientIv = trim($_POST['client_iv'] ?? '');
    $clientTag = trim($_POST['client_tag'] ?? '');

    if ($isClientEncrypted && !empty($clientIv) && !empty($clientTag)) {
        // Zero-Knowledge Architecture: Browser already encrypted via Web Crypto API
        $ciphertext = $secret;
        $iv = $clientIv;
        $tag = $clientTag;
        $isClient = 1;
    } else {
        // Fallback server-side encryption
        $enc = encrypt_vault_secret($secret);
        $ciphertext = $enc['ciphertext'];
        $iv = $enc['iv'];
        $tag = $enc['tag'];
        $isClient = 0;
    }

    // Process file upload
    $filePath = '';
    $fileName = '';
    $fileSize = 0;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploadResult = process_uploaded_file($_FILES['attachment'], $uploadDir, $uid);
        if (isset($uploadResult['error'])) {
            header("Location: ../dashboard.php?tab=vault&error=" . urlencode($uploadResult['error']));
            exit;
        }
        if ($uploadResult) {
            $filePath = $uploadResult['file_path'];
            $fileName = $uploadResult['file_name'];
            $fileSize = $uploadResult['file_size'];
        }
    }

    $stmt = $conn->prepare("INSERT INTO vaults (user_id, title, category, secret, iv, tag, is_client_encrypted, notes, file_path, file_name, file_size) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssssisssi", $uid, $title, $category, $ciphertext, $iv, $tag, $isClient, $notes, $filePath, $fileName, $fileSize);

    if ($stmt->execute()) {
        $vaultId = $stmt->insert_id;

        // Assign to selected nominees
        if (!empty($nomineeIds) && is_array($nomineeIds)) {
            $nomStmt = $conn->prepare("INSERT INTO vault_nominee_access (vault_id, nominee_id) VALUES (?, ?)");
            foreach ($nomineeIds as $nId) {
                $nId = (int)$nId;
                $nomStmt->bind_param("ii", $vaultId, $nId);
                $nomStmt->execute();
            }
        }

        $logMsg = $isClient ? "Stored new Zero-Knowledge encrypted item: {$title} ({$category})" : "Stored new encrypted item: {$title} ({$category})";
        log_audit($conn, $uid, 'VAULT_CREATED', $logMsg);
        header("Location: ../dashboard.php?tab=vault&success=" . urlencode("Record securely encrypted with AES-256-GCM."));
        exit;
    } else {
        header("Location: ../dashboard.php?tab=vault&error=" . urlencode("Failed to store vault record."));
        exit;
    }
}

// Update Vault Item
if (isset($_POST['update_vault'])) {
    $vaultId = (int)($_POST['vault_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? 'Credentials');
    $secret = $_POST['secret'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    $nomineeIds = $_POST['nominee_ids'] ?? [];
    $isClientEncrypted = !empty($_POST['is_client_encrypted']) ? 1 : 0;
    $clientIv = trim($_POST['client_iv'] ?? '');
    $clientTag = trim($_POST['client_tag'] ?? '');

    // Verify ownership
    $chk = $conn->prepare("SELECT id, file_path FROM vaults WHERE id = ? AND user_id = ?");
    $chk->bind_param("ii", $vaultId, $uid);
    $chk->execute();
    $existing = $chk->get_result()->fetch_assoc();
    if (!$existing) {
        header("Location: ../dashboard.php?tab=vault&error=" . urlencode("Vault record not found."));
        exit;
    }

    // Process new file upload if provided
    $filePath = $existing['file_path'];
    $fileName = null;
    $fileSize = null;

    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploadResult = process_uploaded_file($_FILES['attachment'], $uploadDir, $uid);
        if (isset($uploadResult['error'])) {
            header("Location: ../dashboard.php?tab=vault&error=" . urlencode($uploadResult['error']));
            exit;
        }
        if ($uploadResult) {
            // Delete old file if existed
            if (!empty($filePath) && file_exists(__DIR__ . "/../" . $filePath)) {
                unlink(__DIR__ . "/../" . $filePath);
            }
            $filePath = $uploadResult['file_path'];
            $fileName = $uploadResult['file_name'];
            $fileSize = $uploadResult['file_size'];
        }
    }

    if (!empty($secret)) {
        if ($isClientEncrypted && !empty($clientIv) && !empty($clientTag)) {
            $ciphertext = $secret;
            $iv = $clientIv;
            $tag = $clientTag;
            $isClient = 1;
        } else {
            $enc = encrypt_vault_secret($secret);
            $ciphertext = $enc['ciphertext'];
            $iv = $enc['iv'];
            $tag = $enc['tag'];
            $isClient = 0;
        }

        if ($fileName !== null) {
            $stmt = $conn->prepare("UPDATE vaults SET title = ?, category = ?, secret = ?, iv = ?, tag = ?, is_client_encrypted = ?, notes = ?, file_path = ?, file_name = ?, file_size = ? WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ssssssisssii", $title, $category, $ciphertext, $iv, $tag, $isClient, $notes, $filePath, $fileName, $fileSize, $vaultId, $uid);
        } else {
            $stmt = $conn->prepare("UPDATE vaults SET title = ?, category = ?, secret = ?, iv = ?, tag = ?, is_client_encrypted = ?, notes = ? WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ssssssisii", $title, $category, $ciphertext, $iv, $tag, $isClient, $notes, $vaultId, $uid);
        }
    } else {
        if ($fileName !== null) {
            $stmt = $conn->prepare("UPDATE vaults SET title = ?, category = ?, notes = ?, file_path = ?, file_name = ?, file_size = ? WHERE id = ? AND user_id = ?");
            $stmt->bind_param("sssssiii", $title, $category, $notes, $filePath, $fileName, $fileSize, $vaultId, $uid);
        } else {
            $stmt = $conn->prepare("UPDATE vaults SET title = ?, category = ?, notes = ? WHERE id = ? AND user_id = ?");
            $stmt->bind_param("sssii", $title, $category, $notes, $vaultId, $uid);
        }
    }

    $stmt->execute();

    // Re-assign nominees
    $delNom = $conn->prepare("DELETE FROM vault_nominee_access WHERE vault_id = ?");
    $delNom->bind_param("i", $vaultId);
    $delNom->execute();

    if (!empty($nomineeIds) && is_array($nomineeIds)) {
        $nomStmt = $conn->prepare("INSERT INTO vault_nominee_access (vault_id, nominee_id) VALUES (?, ?)");
        foreach ($nomineeIds as $nId) {
            $nId = (int)$nId;
            $nomStmt->bind_param("ii", $vaultId, $nId);
            $nomStmt->execute();
        }
    }

    log_audit($conn, $uid, 'VAULT_UPDATED', "Updated encrypted item: {$title}");
    header("Location: ../dashboard.php?tab=vault&success=" . urlencode("Vault record updated successfully."));
    exit;
}

// Delete Vault Item
if (isset($_POST['delete_vault'])) {
    $vaultId = (int)($_POST['vault_id'] ?? 0);

    // Fetch and remove file if exists
    $fStmt = $conn->prepare("SELECT file_path FROM vaults WHERE id = ? AND user_id = ?");
    $fStmt->bind_param("ii", $vaultId, $uid);
    $fStmt->execute();
    $row = $fStmt->get_result()->fetch_assoc();
    if ($row && !empty($row['file_path']) && file_exists(__DIR__ . "/../" . $row['file_path'])) {
        unlink(__DIR__ . "/../" . $row['file_path']);
    }

    $stmt = $conn->prepare("DELETE FROM vaults WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $vaultId, $uid);
    $stmt->execute();

    log_audit($conn, $uid, 'VAULT_DELETED', "Deleted vault item ID #{$vaultId}");
    header("Location: ../dashboard.php?tab=vault&success=" . urlencode("Item permanently deleted from vault."));
    exit;
}
?>
