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

/**
 * RFC 6238 Time-Based One-Time Password (TOTP) Implementation
 */
function generate_totp_secret($length = 16) {
    $base32Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    for ($i = 0; $i < $length; $i++) {
        $secret .= $base32Chars[random_int(0, 31)];
    }
    return $secret;
}

function base32_decode_string($b32) {
    $base32Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper(trim($b32));
    $buffer = 0;
    $bitsLeft = 0;
    $output = '';

    for ($i = 0; $i < strlen($b32); $i++) {
        $char = $b32[$i];
        if ($char === '=') break;
        $val = strpos($base32Chars, $char);
        if ($val === false) continue;

        $buffer = ($buffer << 5) | $val;
        $bitsLeft += 5;

        if ($bitsLeft >= 8) {
            $bitsLeft -= 8;
            $output .= chr(($buffer >> $bitsLeft) & 0xFF);
        }
    }
    return $output;
}

function verify_totp_code($secret, $code, $discrepancy = 1) {
    $secretKey = base32_decode_string($secret);
    if (empty($secretKey)) return false;

    $timeSlice = floor(time() / 30);
    $code = str_pad(trim($code), 6, '0', STR_PAD_LEFT);

    for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
        $slice = $timeSlice + $i;
        $packedTime = pack('N*', 0) . pack('N*', $slice);
        $hmac = hash_hmac('sha1', $packedTime, $secretKey, true);
        $offset = ord(substr($hmac, -1)) & 0x0F;
        $hashPart = substr($hmac, $offset, 4);
        $value = unpack('N', $hashPart)[1] & 0x7FFFFFFF;
        $calculatedCode = str_pad($value % 1000000, 6, '0', STR_PAD_LEFT);

        if (hash_equals($calculatedCode, $code)) {
            return true;
        }
    }
    return false;
}

function get_totp_qr_url($email, $secret, $issuer = 'Digital Legacy Vault') {
    $chl = urlencode("otpauth://totp/{$issuer}:{$email}?secret={$secret}&issuer={$issuer}");
    return "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={$chl}";
}

function generate_recovery_codes($count = 8) {
    $codes = [];
    for ($i = 0; $i < $count; $i++) {
        $part1 = strtoupper(bin2hex(random_bytes(2)));
        $part2 = strtoupper(bin2hex(random_bytes(2)));
        $codes[] = "{$part1}-{$part2}";
    }
    return $codes;
}

/**
 * Calculates Vault Security Health Score (0 - 100%)
 */
function calculate_vault_health($user, $vaults, $nominees) {
    $score = 0;
    $recommendations = [];

    // 1. Two-Factor Authentication (+25)
    if (!empty($user['two_factor_enabled'])) {
        $score += 25;
    } else {
        $recommendations[] = "Enable Two-Factor Authentication (2FA) to protect against credential stuffing.";
    }

    // 2. Beneficiaries assigned (+25)
    if (count($nominees) >= 1) {
        $score += 25;
    } else {
        $recommendations[] = "Designate at least one trusted beneficiary to inherit your vault.";
    }

    // 3. Vault records coverage (+25)
    if (count($vaults) > 0) {
        $unassigned = 0;
        foreach ($vaults as $v) {
            if (empty($v['assigned_nominees'])) {
                $unassigned++;
            }
        }
        $score += 25;
        if ($unassigned > 0) {
            $recommendations[] = "Review and map specific trustee permissions for unassigned records.";
        }
    } else {
        $recommendations[] = "Add your primary passwords, financial assets, or legal wills.";
    }

    // 4. Offline Backup or Recent Check-In (+25)
    $lastCheckIn = strtotime($user['last_check_in'] ?? '2000-01-01');
    $daysSincePing = (time() - $lastCheckIn) / 86400;

    if ($daysSincePing < 15) {
        $score += 15;
    } else {
        $recommendations[] = "Your check-in heartbeat is aging. Confirm your presence with a pulse.";
    }

    if (!empty($user['last_backup_export'])) {
        $daysSinceBackup = (time() - strtotime($user['last_backup_export'])) / 86400;
        if ($daysSinceBackup < 45) {
            $score += 10;
        } else {
            $recommendations[] = "Generate an updated offline JSON backup for air-gapped physical storage.";
        }
    } else {
        $recommendations[] = "Export your first offline vault backup archive.";
    }

    return [
        'score' => min(100, $score),
        'recommendations' => $recommendations
    ];
}

/**
 * Dispatches and logs an automated alert notification
 */
function dispatch_notification($conn, $user_id, $recipient_email, $type, $subject, $body) {
    // Record to database
    $stmt = $conn->prepare("INSERT INTO dispatched_notifications (user_id, recipient_email, notification_type, subject) VALUES (?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("isss", $user_id, $recipient_email, $type, $subject);
        $stmt->execute();
    }

    // Attempt standard mail delivery if sendmail configured
    $headers = "From: no-reply@digitallegacyvault.local\r\nReply-To: no-reply@digitallegacyvault.local\r\nX-Mailer: PHP/" . phpversion();
    @mail($recipient_email, $subject, $body, $headers);
}
?>
