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

// Create Time Capsule
if (isset($_POST['create_capsule'])) {
    $rName = trim($_POST['recipient_name'] ?? '');
    $rEmail = trim($_POST['recipient_email'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $unlockDate = trim($_POST['unlock_date'] ?? '');

    if (empty($rName) || empty($rEmail) || empty($title) || empty($message) || empty($unlockDate)) {
        header("Location: ../dashboard.php?tab=capsules&error=" . urlencode("All fields are required."));
        exit;
    }

    if (strtotime($unlockDate) <= strtotime(date('Y-m-d'))) {
        header("Location: ../dashboard.php?tab=capsules&error=" . urlencode("Unlock date must be in the future."));
        exit;
    }

    $enc = encrypt_vault_secret($message);
    $token = bin2hex(random_bytes(24));

    $stmt = $conn->prepare("INSERT INTO capsules (user_id, recipient_name, recipient_email, title, message, iv, tag, unlock_date, access_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssssss", $uid, $rName, $rEmail, $title, $enc['ciphertext'], $enc['iv'], $enc['tag'], $unlockDate, $token);

    if ($stmt->execute()) {
        log_audit($conn, $uid, 'CAPSULE_CREATED', "Created time capsule '{$title}' locked until {$unlockDate}");
        header("Location: ../dashboard.php?tab=capsules&success=" . urlencode("Time capsule scheduled and encrypted."));
        exit;
    } else {
        header("Location: ../dashboard.php?tab=capsules&error=" . urlencode("Failed to create time capsule."));
        exit;
    }
}

// Delete Time Capsule
if (isset($_POST['delete_capsule'])) {
    $cId = (int)($_POST['capsule_id'] ?? 0);
    $stmt = $conn->prepare("DELETE FROM capsules WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $cId, $uid);
    $stmt->execute();

    log_audit($conn, $uid, 'CAPSULE_DELETED', "Deleted time capsule ID #{$cId}");
    header("Location: ../dashboard.php?tab=capsules&success=" . urlencode("Time capsule removed."));
    exit;
}
?>
