<?php
require_once __DIR__ . "/config.php";

/**
 * Encrypts secret plaintext using authenticated AES-256-GCM.
 */
function encrypt_vault_secret($plaintext) {
    $cipher = "aes-256-gcm";
    $iv_length = openssl_cipher_iv_length($cipher);
    $iv = openssl_random_pseudo_bytes($iv_length);
    $tag = "";
    $ciphertext = openssl_encrypt($plaintext, $cipher, VAULT_MASTER_KEY, OPENSSL_RAW_DATA, $iv, $tag);
    
    return [
        'ciphertext' => base64_encode($ciphertext),
        'iv' => base64_encode($iv),
        'tag' => base64_encode($tag)
    ];
}

/**
 * Decrypts secret using authenticated AES-256-GCM, with backward compatibility.
 */
function decrypt_vault_secret($ciphertext_b64, $iv_b64 = '', $tag_b64 = '') {
    if (!empty($iv_b64) && !empty($tag_b64)) {
        $cipher = "aes-256-gcm";
        $ciphertext = base64_decode($ciphertext_b64);
        $iv = base64_decode($iv_b64);
        $tag = base64_decode($tag_b64);
        $plaintext = openssl_decrypt($ciphertext, $cipher, VAULT_MASTER_KEY, OPENSSL_RAW_DATA, $iv, $tag);
        if ($plaintext !== false) {
            return $plaintext;
        }
    }
    // Backward compatibility for legacy AES-128-ECB
    $legacy = openssl_decrypt($ciphertext_b64, "AES-128-ECB", "vault_key");
    return ($legacy !== false) ? $legacy : $ciphertext_b64;
}

/**
 * Logs an action to audit_logs
 */
function log_audit($conn, $user_id, $action, $details) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("isss", $user_id, $action, $details, $ip);
        $stmt->execute();
    }
}

/**
 * Calculates dead man's switch status & time remaining
 */
function get_dead_man_switch_status($user) {
    if ($user['status'] === 'triggered') {
        return [
            'status' => 'TRIGGERED',
            'label' => 'Vault Released to Nominees',
            'badge' => 'danger',
            'days_remaining' => 0,
            'hours_remaining' => 0,
            'percent' => 0,
            'is_triggered' => true
        ];
    }

    $lastCheckIn = strtotime($user['last_check_in'] ?? 'now');
    $freqDays = (int)($user['check_in_frequency_days'] ?: 30);
    $graceDays = (int)($user['grace_period_days'] ?: 7);

    $deadline = $lastCheckIn + ($freqDays * 86400);
    $finalDeadline = $deadline + ($graceDays * 86400);
    $now = time();

    if ($now > $finalDeadline) {
        return [
            'status' => 'TRIGGERED',
            'label' => 'Switch Activated - Vault Released',
            'badge' => 'danger',
            'days_remaining' => 0,
            'hours_remaining' => 0,
            'percent' => 0,
            'is_triggered' => true
        ];
    } elseif ($now > $deadline) {
        $remainingSeconds = $finalDeadline - $now;
        $remainingHours = ceil($remainingSeconds / 3600);
        $remainingDays = ceil($remainingSeconds / 86400);
        return [
            'status' => 'GRACE_PERIOD',
            'label' => "Warning: Check-In Overdue (Grace: {$remainingDays}d left)",
            'badge' => 'warning',
            'days_remaining' => $remainingDays,
            'hours_remaining' => $remainingHours,
            'percent' => max(5, round(($remainingSeconds / ($graceDays * 86400)) * 100)),
            'is_triggered' => false
        ];
    } else {
        $remainingSeconds = $deadline - $now;
        $remainingDays = ceil($remainingSeconds / 86400);
        $remainingHours = ceil($remainingSeconds / 3600);
        $totalWindow = $freqDays * 86400;
        $percent = max(0, min(100, round(($remainingSeconds / $totalWindow) * 100)));
        return [
            'status' => 'ACTIVE',
            'label' => 'Protocol Healthy & Armed',
            'badge' => 'success',
            'days_remaining' => $remainingDays,
            'hours_remaining' => $remainingHours,
            'percent' => $percent,
            'is_triggered' => false
        ];
    }
}
?>
