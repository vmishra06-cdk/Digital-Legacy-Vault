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

// Enable 2FA
if (isset($_POST['enable_2fa'])) {
    $secret = trim($_POST['totp_secret'] ?? '');
    $code = trim($_POST['totp_code'] ?? '');

    if (empty($secret) || empty($code)) {
        header("Location: ../dashboard.php?tab=security&error=" . urlencode("Secret and 6-digit verification code are required."));
        exit;
    }

    if (verify_totp_code($secret, $code)) {
        $recoveryCodes = generate_recovery_codes(8);
        $encodedCodes = json_encode($recoveryCodes);

        $stmt = $conn->prepare("UPDATE users SET two_factor_secret = ?, two_factor_enabled = 1, two_factor_recovery_codes = ? WHERE id = ?");
        $stmt->bind_param("ssi", $secret, $encodedCodes, $uid);
        $stmt->execute();

        $_SESSION['new_recovery_codes'] = $recoveryCodes;

        log_audit($conn, $uid, '2FA_ENABLED', "Two-factor authentication activated with emergency recovery codes");
        header("Location: ../dashboard.php?tab=security&success=" . urlencode("Two-factor authentication activated! Save your emergency recovery codes below."));
        exit;
    } else {
        header("Location: ../dashboard.php?tab=security&error=" . urlencode("Invalid 6-digit code. Please verify time synchronization on your authenticator app."));
        exit;
    }
}

// Regenerate Recovery Codes
if (isset($_POST['regenerate_recovery_codes'])) {
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT password, two_factor_enabled FROM users WHERE id = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();

    if ($u && $u['two_factor_enabled'] && password_verify($password, $u['password'])) {
        $recoveryCodes = generate_recovery_codes(8);
        $encodedCodes = json_encode($recoveryCodes);

        $up = $conn->prepare("UPDATE users SET two_factor_recovery_codes = ? WHERE id = ?");
        $up->bind_param("si", $encodedCodes, $uid);
        $up->execute();

        $_SESSION['new_recovery_codes'] = $recoveryCodes;

        log_audit($conn, $uid, '2FA_RECOVERY_REGENERATED', "Emergency recovery codes regenerated");
        header("Location: ../dashboard.php?tab=security&success=" . urlencode("New emergency recovery codes generated. Please copy them now."));
        exit;
    } else {
        header("Location: ../dashboard.php?tab=security&error=" . urlencode("Incorrect master password."));
        exit;
    }
}

// Disable 2FA
if (isset($_POST['disable_2fa'])) {
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();

    if ($u && password_verify($password, $u['password'])) {
        $up = $conn->prepare("UPDATE users SET two_factor_secret = '', two_factor_enabled = 0, two_factor_recovery_codes = '' WHERE id = ?");
        $up->bind_param("i", $uid);
        $up->execute();

        unset($_SESSION['new_recovery_codes']);

        log_audit($conn, $uid, '2FA_DISABLED', "Two-factor authentication disabled by user");
        header("Location: ../dashboard.php?tab=security&success=" . urlencode("Two-factor authentication has been disabled."));
        exit;
    } else {
        header("Location: ../dashboard.php?tab=security&error=" . urlencode("Incorrect master password."));
        exit;
    }
}

// Set / Update Duress Password
if (isset($_POST['set_duress_password'])) {
    $duress = trim($_POST['duress_password'] ?? '');
    $masterPass = $_POST['master_password'] ?? '';

    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();

    if (!$u || !password_verify($masterPass, $u['password'])) {
        header("Location: ../dashboard.php?tab=security&error=" . urlencode("Incorrect master password."));
        exit;
    }

    if (!empty($duress)) {
        if (strlen($duress) < 6) {
            header("Location: ../dashboard.php?tab=security&error=" . urlencode("Duress password must be at least 6 characters."));
            exit;
        }
        if (password_verify($duress, $u['password'])) {
            header("Location: ../dashboard.php?tab=security&error=" . urlencode("Duress password cannot be identical to your master password."));
            exit;
        }

        $hash = password_hash($duress, PASSWORD_DEFAULT);
        $up = $conn->prepare("UPDATE users SET duress_password = ? WHERE id = ?");
        $up->bind_param("si", $hash, $uid);
        $up->execute();
        log_audit($conn, $uid, 'DURESS_CONFIGURED', "Duress decoy PIN configured");
        header("Location: ../dashboard.php?tab=security&success=" . urlencode("Duress decoy password successfully armed."));
        exit;
    } else {
        // Clear duress password
        $up = $conn->prepare("UPDATE users SET duress_password = '' WHERE id = ?");
        $up->bind_param("i", $uid);
        $up->execute();
        log_audit($conn, $uid, 'DURESS_REMOVED', "Duress password removed");
        header("Location: ../dashboard.php?tab=security&success=" . urlencode("Duress decoy mode disabled."));
        exit;
    }
}

// Update Trustee Consensus Threshold
if (isset($_POST['set_consensus_threshold'])) {
    $threshold = max(1, (int)($_POST['consensus_threshold'] ?? 1));

    $stmt = $conn->prepare("UPDATE users SET consensus_threshold = ? WHERE id = ?");
    $stmt->bind_param("ii", $threshold, $uid);
    $stmt->execute();

    log_audit($conn, $uid, 'CONSENSUS_UPDATED', "Multi-trustee approval threshold set to {$threshold}");
    header("Location: ../dashboard.php?tab=nominees&success=" . urlencode("Consensus threshold updated: {$threshold} trustee approvals required."));
    exit;
}
?>
