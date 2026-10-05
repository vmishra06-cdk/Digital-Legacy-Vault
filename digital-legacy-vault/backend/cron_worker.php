<?php
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/helpers.php";

// Allow execution via CLI or via HTTP with cron_key
$cronSecret = getenv("CRON_SECRET") ?: "digital_legacy_cron_2026";
if (php_sapi_name() !== 'cli') {
    $providedKey = $_GET['key'] ?? $_SERVER['HTTP_X_CRON_KEY'] ?? '';
    if (!hash_equals($cronSecret, $providedKey)) {
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "Unauthorized cron invocation"]);
        exit;
    }
}

$report = [
    "timestamp" => date("c"),
    "warnings_sent" => 0,
    "vaults_triggered" => 0,
    "capsules_released" => 0,
    "details" => []
];

// 1. Process all active users for Dead Man's Switch
$uStmt = $conn->query("SELECT * FROM users WHERE status != 'triggered'");
$users = $uStmt ? $uStmt->fetch_all(MYSQLI_ASSOC) : [];

foreach ($users as $u) {
    $switch = get_dead_man_switch_status($u);

    // Case A: Grace period expired -> TRIGGER VAULT
    if ($switch['status'] === 'TRIGGERED' && $u['status'] !== 'triggered') {
        $up = $conn->prepare("UPDATE users SET status = 'triggered' WHERE id = ?");
        $up->bind_param("i", $u['id']);
        $up->execute();

        log_audit($conn, $u['id'], 'SWITCH_AUTO_TRIGGERED', 'Inactivity verification window and grace period expired. Vault released.');

        // Notify all nominees
        $nStmt = $conn->prepare("SELECT * FROM nominees WHERE user_id = ?");
        $nStmt->bind_param("i", $u['id']);
        $nStmt->execute();
        $nominees = $nStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
        foreach ($nominees as $n) {
            $claimUrl = "http://{$host}/claim.php?token=" . $n['claim_token'];
            $subject = "Digital Legacy Release: Instructions Entrusted to You by " . $u['name'];
            $body = "Hello {$n['name']},\n\n"
                  . "You have been designated as a trusted beneficiary by {$u['name']}.\n"
                  . "The automated Dead Man's Switch protocol has completed, and your designated confidential records and documents are now unlocked.\n\n"
                  . "Access your secure beneficiary decryption gateway here:\n{$claimUrl}\n\n"
                  . "Digital Legacy Vault Automated Security Engine";

            dispatch_notification($conn, $u['id'], $n['email'], 'BENEFICIARY_RELEASE', $subject, $body);
        }

        $report['vaults_triggered']++;
        $report['details'][] = "User ID {$u['id']} ({$u['email']}) triggered. Released to " . count($nominees) . " nominees.";
    }

    // Case B: In grace period -> Send urgent warning to owner
    elseif ($switch['status'] === 'GRACE_PERIOD') {
        // Prevent spamming: only send once per 24 hours
        $chk = $conn->prepare("SELECT id FROM dispatched_notifications WHERE user_id = ? AND notification_type = 'GRACE_WARNING' AND sent_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $chk->bind_param("i", $u['id']);
        $chk->execute();

        if ($chk->get_result()->num_rows === 0) {
            $subject = "URGENT: Dead Man's Switch Overdue - {$switch['days_remaining']} Days Remaining";
            $body = "Hello {$u['name']},\n\n"
                  . "Your scheduled verification check-in is overdue. Your vault is currently in GRACE PERIOD.\n"
                  . "Remaining time before automated release to beneficiaries: {$switch['days_remaining']} days.\n\n"
                  . "Please log in immediately and click 'Send Pulse Check-In' to re-arm your protocol.\n\n"
                  . "Digital Legacy Vault Automated Security Engine";

            dispatch_notification($conn, $u['id'], $u['email'], 'GRACE_WARNING', $subject, $body);
            $report['warnings_sent']++;
        }
    }

    // Case C: 3 days or fewer remaining before deadline -> Pre-warning
    elseif ($switch['status'] === 'ACTIVE' && $switch['days_remaining'] <= 3) {
        $chk = $conn->prepare("SELECT id FROM dispatched_notifications WHERE user_id = ? AND notification_type = 'PRE_WARNING' AND sent_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $chk->bind_param("i", $u['id']);
        $chk->execute();

        if ($chk->get_result()->num_rows === 0) {
            $subject = "Reminder: Digital Legacy Vault Verification Due in {$switch['days_remaining']} Days";
            $body = "Hello {$u['name']},\n\n"
                  . "This is a reminder that your scheduled check-in is due in {$switch['days_remaining']} days.\n"
                  . "Log in to confirm you are safe and reset your verification timer.\n\n"
                  . "Digital Legacy Vault Automated Security Engine";

            dispatch_notification($conn, $u['id'], $u['email'], 'PRE_WARNING', $subject, $body);
            $report['warnings_sent']++;
        }
    }
}

// 2. Process Time Capsules (Future Letters)
$cStmt = $conn->query("SELECT c.*, u.name as owner_name FROM capsules c JOIN users u ON c.user_id = u.id WHERE c.unlock_date <= CURRENT_DATE");
$capsules = $cStmt ? $cStmt->fetch_all(MYSQLI_ASSOC) : [];

$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
foreach ($capsules as $cap) {
    // Check if notice already sent
    $chk = $conn->prepare("SELECT id FROM dispatched_notifications WHERE user_id = ? AND notification_type = 'CAPSULE_UNLOCKED' AND recipient_email = ?");
    $chk->bind_param("is", $cap['user_id'], $cap['recipient_email']);
    $chk->execute();

    if ($chk->get_result()->num_rows === 0) {
        $capUrl = "http://{$host}/capsule_view.php?token=" . $cap['access_token'];
        $subject = "A Scheduled Message Has Unlocked for You: " . $cap['title'];
        $body = "Hello {$cap['recipient_name']},\n\n"
              . "A time-locked digital letter scheduled by {$cap['owner_name']} has reached its unlock date.\n"
              . "View your unlocked letter here:\n{$capUrl}\n\n"
              . "Digital Legacy Vault Time Capsule Protocol";

        dispatch_notification($conn, $cap['user_id'], $cap['recipient_email'], 'CAPSULE_UNLOCKED', $subject, $body);
        $report['capsules_released']++;
    }
}

header('Content-Type: application/json');
echo json_encode($report, JSON_PRETTY_PRINT);
?>
