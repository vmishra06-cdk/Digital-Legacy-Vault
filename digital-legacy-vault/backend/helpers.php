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
 * Calculates Password Entropy in Bits using NIST combinatorial pool metrics
 */
function calculate_password_entropy($password) {
    if (empty($password)) return 0;
    $pool = 0;
    if (preg_match('/[a-z]/', $password)) $pool += 26;
    if (preg_match('/[A-Z]/', $password)) $pool += 26;
    if (preg_match('/[0-9]/', $password)) $pool += 10;
    if (preg_match('/[^a-zA-Z0-9]/', $password)) $pool += 33;
    if ($pool === 0) return 0;
    $length = strlen($password);
    return (int)round($length * (log($pool) / log(2)));
}

/**
 * Calculates Vault Security Health Score (0 - 100%)
 * 5 Security Pillars:
 * 1. Master Password Entropy (20 pts)
 * 2. Two-Factor Authentication TOTP (20 pts)
 * 3. Beneficiary Coverage (20 pts)
 * 4. Switch Protocol Freshness (20 pts)
 * 5. Offline Cold Backup (20 pts)
 */
function calculate_vault_health($user, $vaults, $nominees) {
    $score = 0;
    $metrics = [];
    $recommendations = [];
    $unassignedVaults = [];

    // 1. Master Password Entropy Check (Max: 20 pts)
    $entropyBits = isset($user['password_entropy_score']) && $user['password_entropy_score'] > 0 
        ? intval($user['password_entropy_score']) 
        : 65;
    $entropyScore = 0;
    $entropyStatus = 'danger';
    if ($entropyBits >= 65) {
        $entropyScore = 20;
        $entropyStatus = 'optimal';
    } elseif ($entropyBits >= 50) {
        $entropyScore = 15;
        $entropyStatus = 'warning';
    } elseif ($entropyBits >= 35) {
        $entropyScore = 10;
        $entropyStatus = 'warning';
    } else {
        $entropyScore = 5;
        $entropyStatus = 'danger';
    }
    $score += $entropyScore;
    $metrics['entropy'] = [
        'title' => 'Master Password Entropy',
        'score' => $entropyScore,
        'max' => 20,
        'bits' => $entropyBits,
        'status' => $entropyStatus,
        'value' => "{$entropyBits} bits",
        'description' => 'Evaluates cryptographic strength against dictionary and brute-force attacks.',
        'action_url' => '?tab=security#change-pwd',
        'action_label' => 'Strengthen Password'
    ];
    if ($entropyScore < 20) {
        $recommendations[] = [
            'id' => 'rec_entropy',
            'severity' => $entropyScore < 12 ? 'HIGH' : 'MEDIUM',
            'title' => 'Upgrade Master Password Entropy',
            'desc' => "Current password strength is rated at {$entropyBits} bits. Expand to 14+ characters combining mixed casing, numbers, and symbols to achieve 65+ bits.",
            'action_url' => '?tab=security#change-pwd',
            'action_label' => 'Update Password'
        ];
    }

    // 2. Two-Factor Authentication Check (Max: 20 pts)
    $twoFaEnabled = !empty($user['two_factor_enabled']);
    $twoFaScore = $twoFaEnabled ? 20 : 0;
    $score += $twoFaScore;
    $metrics['two_factor'] = [
        'title' => 'Two-Factor Authentication (2FA)',
        'score' => $twoFaScore,
        'max' => 20,
        'enabled' => $twoFaEnabled,
        'status' => $twoFaEnabled ? 'optimal' : 'danger',
        'value' => $twoFaEnabled ? 'TOTP Active' : 'Not Configured',
        'description' => 'Eliminates single point of failure using Google Authenticator, Authy, or 1Password.',
        'action_url' => '?tab=security#totp-setup',
        'action_label' => $twoFaEnabled ? 'Manage 2FA' : 'Activate 2FA'
    ];
    if (!$twoFaEnabled) {
        $recommendations[] = [
            'id' => 'rec_2fa',
            'severity' => 'HIGH',
            'title' => 'Activate Two-Factor Authentication',
            'desc' => 'Your vault relies entirely on a single master password. Link a TOTP authenticator app and save emergency recovery codes to block unauthorized logins.',
            'action_url' => '?tab=security#totp-setup',
            'action_label' => 'Enable 2FA'
        ];
    }

    // 3. Beneficiary Coverage Check (Max: 20 pts)
    $totalVaults = count($vaults);
    $assignedCount = 0;
    foreach ($vaults as $v) {
        if (!empty($v['assigned_nominees'])) {
            $assignedCount++;
        } else {
            $unassignedVaults[] = $v;
        }
    }

    $coverageScore = 0;
    $coveragePercent = 0;
    $coverageStatus = 'danger';
    if ($totalVaults === 0) {
        $coverageScore = 10;
        $coveragePercent = 0;
        $coverageStatus = 'warning';
    } else {
        $coveragePercent = (int)round(($assignedCount / $totalVaults) * 100);
        $coverageScore = (int)round(($assignedCount / $totalVaults) * 20);
        if ($coveragePercent === 100) {
            $coverageStatus = 'optimal';
        } elseif ($coveragePercent >= 50) {
            $coverageStatus = 'warning';
        } else {
            $coverageStatus = 'danger';
        }
    }
    $score += $coverageScore;
    $metrics['coverage'] = [
        'title' => 'Beneficiary Coverage',
        'score' => $coverageScore,
        'max' => 20,
        'percent' => $coveragePercent,
        'status' => $coverageStatus,
        'value' => $totalVaults > 0 ? "{$assignedCount}/{$totalVaults} assigned ({$coveragePercent}%)" : '0 records',
        'description' => 'Verifies that every stored secret is mapped to a designated beneficiary for emergency release.',
        'action_url' => '?tab=vault',
        'action_label' => 'Review Assignments',
        'unassigned_count' => count($unassignedVaults)
    ];
    if (count($unassignedVaults) > 0) {
        $recommendations[] = [
            'id' => 'rec_coverage',
            'severity' => count($unassignedVaults) > 3 ? 'HIGH' : 'MEDIUM',
            'title' => 'Assign Beneficiaries to Unprotected Secrets',
            'desc' => count($unassignedVaults) . ' secret item(s) are currently unassigned to any beneficiary. In an emergency, trustees cannot claim unassigned items.',
            'action_url' => '?tab=vault',
            'action_label' => 'Assign Beneficiaries'
        ];
    } elseif ($totalVaults === 0) {
        $recommendations[] = [
            'id' => 'rec_empty_vault',
            'severity' => 'LOW',
            'title' => 'Populate Your Encrypted Vault',
            'desc' => 'Add your critical passwords, cryptocurrency seeds, financial accounts, or legal directives to the vault.',
            'action_url' => '?tab=vault',
            'action_label' => 'Add Secret'
        ];
    }

    // 4. Switch Protocol Freshness (Max: 20 pts)
    $freqDays = intval($user['check_in_frequency_days'] ?? 30);
    $freqScore = 10;
    if ($freqDays <= 30) {
        $freqScore = 10;
    } elseif ($freqDays <= 60) {
        $freqScore = 7;
    } elseif ($freqDays <= 90) {
        $freqScore = 4;
    } else {
        $freqScore = 1;
    }

    $lastCheckIn = strtotime($user['last_check_in'] ?? '2000-01-01');
    $daysSincePing = max(0, round((time() - $lastCheckIn) / 86400));
    $pingScore = 10;
    if ($daysSincePing <= 7) {
        $pingScore = 10;
    } elseif ($daysSincePing <= 15) {
        $pingScore = 7;
    } elseif ($daysSincePing <= 30) {
        $pingScore = 4;
    } else {
        $pingScore = 0;
    }

    $switchScore = $freqScore + $pingScore;
    $score += $switchScore;
    $switchStatus = ($switchScore >= 17) ? 'optimal' : (($switchScore >= 11) ? 'warning' : 'danger');
    $metrics['freshness'] = [
        'title' => 'Switch Protocol Freshness',
        'score' => $switchScore,
        'max' => 20,
        'status' => $switchStatus,
        'freq_days' => $freqDays,
        'days_since_ping' => $daysSincePing,
        'value' => "Interval: {$freqDays}d | Ping: {$daysSincePing}d ago",
        'description' => 'Penalizes check-in frequencies set to excessively long intervals (>30d) and aging pulse check-ins.',
        'action_url' => '?tab=pulse',
        'action_label' => 'Adjust Switch Settings'
    ];
    if ($freqDays > 45) {
        $recommendations[] = [
            'id' => 'rec_switch_freq',
            'severity' => 'MEDIUM',
            'title' => 'Reduce Dead Man Switch Frequency',
            'desc' => "Your check-in interval is set to {$freqDays} days. A shorter window (14 to 30 days) ensures prompt protocol execution if unexpected events occur.",
            'action_url' => '?tab=pulse',
            'action_label' => 'Shorten Interval'
        ];
    }
    if ($daysSincePing > 14) {
        $recommendations[] = [
            'id' => 'rec_switch_ping',
            'severity' => 'HIGH',
            'title' => 'Send Fresh Pulse Check-In',
            'desc' => "Last check-in was {$daysSincePing} days ago. Send a pulse now to confirm vitality and prevent premature grace period triggers.",
            'action_url' => '?tab=pulse',
            'action_label' => 'Send Pulse'
        ];
    }

    // 5. Offline Cold Backup Check (Max: 20 pts)
    $backupScore = 0;
    $backupStatus = 'danger';
    $backupValue = 'Never Exported';
    $daysSinceBackup = null;
    if (!empty($user['last_backup_export'])) {
        $daysSinceBackup = max(0, round((time() - strtotime($user['last_backup_export'])) / 86400));
        if ($daysSinceBackup <= 30) {
            $backupScore = 20;
            $backupStatus = 'optimal';
            $backupValue = "{$daysSinceBackup}d ago (Fresh)";
        } elseif ($daysSinceBackup <= 60) {
            $backupScore = 10;
            $backupStatus = 'warning';
            $backupValue = "{$daysSinceBackup}d ago (Aging)";
        } else {
            $backupScore = 0;
            $backupStatus = 'danger';
            $backupValue = "{$daysSinceBackup}d ago (Stale)";
        }
    }
    $score += $backupScore;
    $metrics['backup'] = [
        'title' => 'Offline Cold Backup',
        'score' => $backupScore,
        'max' => 20,
        'status' => $backupStatus,
        'days_ago' => $daysSinceBackup,
        'value' => $backupValue,
        'description' => 'Validates an encrypted offline JSON export was generated within the last 30 days for air-gapped disaster recovery.',
        'action_url' => '?tab=backup',
        'action_label' => 'Export Backup'
    ];
    if ($backupScore < 20) {
        $recommendations[] = [
            'id' => 'rec_backup',
            'severity' => $backupScore === 0 ? 'HIGH' : 'MEDIUM',
            'title' => 'Export Fresh Offline Backup',
            'desc' => !empty($daysSinceBackup) 
                ? "Your last offline vault backup was {$daysSinceBackup} days ago (> 30 days). Download an updated encrypted export to protect against cloud service outages."
                : 'No offline backup has been exported yet. Generate an encrypted JSON archive for air-gapped USB or hardware security storage.',
            'action_url' => '?tab=backup',
            'action_label' => 'Export JSON Backup'
        ];
    }

    // Determine Overall Rating
    $score = min(100, max(0, $score));
    $ratingLabel = 'CRITICAL VULNERABILITY';
    $ratingColor = '#ef4444';
    $ratingBadge = 'danger';
    if ($score >= 85) {
        $ratingLabel = 'FORTIFIED';
        $ratingColor = '#10b981';
        $ratingBadge = 'success';
    } elseif ($score >= 70) {
        $ratingLabel = 'SECURE';
        $ratingColor = '#00f0ff';
        $ratingBadge = 'primary';
    } elseif ($score >= 50) {
        $ratingLabel = 'ATTENTION NEEDED';
        $ratingColor = '#f59e0b';
        $ratingBadge = 'warning';
    }

    return [
        'score' => $score,
        'rating_label' => $ratingLabel,
        'rating_color' => $ratingColor,
        'rating_badge' => $ratingBadge,
        'metrics' => $metrics,
        'unassigned_vaults' => $unassignedVaults,
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
