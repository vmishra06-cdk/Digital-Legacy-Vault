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

// Check-in Pulse
if (isset($_POST['check_in'])) {
    $stmt = $conn->prepare("UPDATE users SET last_check_in = NOW(), status = 'active' WHERE id = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();

    log_audit($conn, $uid, 'HEARTBEAT_CHECKIN', "Manual 'I Am Alive' check-in confirmed by user");
    header("Location: ../dashboard.php?tab=pulse&success=" . urlencode("Pulse confirmed! Dead Man's Switch timer reset."));
    exit;
}

// Update Switch Settings
if (isset($_POST['update_switch_settings'])) {
    $freq = max(1, (int)($_POST['frequency_days'] ?? 30));
    $grace = max(1, (int)($_POST['grace_days'] ?? 7));

    $stmt = $conn->prepare("UPDATE users SET check_in_frequency_days = ?, grace_period_days = ? WHERE id = ?");
    $stmt->bind_param("iii", $freq, $grace, $uid);
    $stmt->execute();

    log_audit($conn, $uid, 'SETTINGS_UPDATED', "Protocol updated: check-in every {$freq} days, grace period {$grace} days");
    header("Location: ../dashboard.php?tab=pulse&success=" . urlencode("Switch protocol settings updated!"));
    exit;
}

// Simulate Trigger (Testing / Demonstration)
if (isset($_POST['simulate_trigger'])) {
    $stmt = $conn->prepare("UPDATE users SET status = 'triggered' WHERE id = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();

    log_audit($conn, $uid, 'TRIGGER_SIMULATED', "Dead Man's Switch triggered manually for test. Vault unlocked for nominees.");
    header("Location: ../dashboard.php?tab=pulse&warning=" . urlencode("SIMULATION: Switch triggered! Nominees can now view authorized secrets."));
    exit;
}

// Reset Status to Active
if (isset($_POST['reset_switch'])) {
    $stmt = $conn->prepare("UPDATE users SET status = 'active', last_check_in = NOW() WHERE id = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();

    log_audit($conn, $uid, 'SWITCH_RESET', "Vault status reset to Active and armed");
    header("Location: ../dashboard.php?tab=pulse&success=" . urlencode("Vault reset and armed! Nominee access paused."));
    exit;
}
?>
