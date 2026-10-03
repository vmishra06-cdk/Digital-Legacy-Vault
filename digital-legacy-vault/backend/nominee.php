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

// Add Nominee
if (isset($_POST['add_nominee'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $relation = trim($_POST['relation'] ?? 'Family');

    if (empty($name) || empty($email)) {
        header("Location: ../dashboard.php?tab=nominees&error=" . urlencode("Name and email are required."));
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: ../dashboard.php?tab=nominees&error=" . urlencode("Invalid nominee email format."));
        exit;
    }

    $claimToken = bin2hex(random_bytes(24));

    $stmt = $conn->prepare("INSERT INTO nominees (user_id, name, email, phone, relation, claim_token, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->bind_param("isssss", $uid, $name, $email, $phone, $relation, $claimToken);

    if ($stmt->execute()) {
        log_audit($conn, $uid, 'NOMINEE_ADDED', "Designated {$name} ({$email}) as {$relation}");
        header("Location: ../dashboard.php?tab=nominees&success=" . urlencode("Nominee successfully added with private claim key."));
        exit;
    } else {
        header("Location: ../dashboard.php?tab=nominees&error=" . urlencode("Failed to add nominee."));
        exit;
    }
}

// Delete Nominee
if (isset($_POST['delete_nominee'])) {
    $nomId = (int)($_POST['nominee_id'] ?? 0);

    $stmt = $conn->prepare("DELETE FROM nominees WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $nomId, $uid);
    $stmt->execute();

    log_audit($conn, $uid, 'NOMINEE_DELETED', "Removed nominee ID #{$nomId}");
    header("Location: ../dashboard.php?tab=nominees&success=" . urlencode("Nominee removed."));
    exit;
}
?>
