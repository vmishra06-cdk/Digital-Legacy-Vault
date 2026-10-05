<?php
require_once __DIR__ . "/backend/db.php";
require_once __DIR__ . "/backend/helpers.php";

$token = trim($_GET['token'] ?? '');
$capsule = null;
$error = '';
$isUnlocked = false;
$daysLeft = 0;

if (!empty($token)) {
    $stmt = $conn->prepare("SELECT c.*, u.name as sender_name, u.email as sender_email FROM capsules c JOIN users u ON c.user_id = u.id WHERE c.access_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $capsule = $stmt->get_result()->fetch_assoc();

    if ($capsule) {
        $today = strtotime(date('Y-m-d'));
        $target = strtotime($capsule['unlock_date']);
        if ($today >= $target) {
            $isUnlocked = true;
            $decryptedMessage = decrypt_vault_secret($capsule['message'], $capsule['iv'] ?? '', $capsule['tag'] ?? '');
        } else {
            $daysLeft = ceil(($target - $today) / 86400);
        }
    } else {
        $error = "Invalid or expired time capsule link.";
    }
} else {
    $error = "No access token provided.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Time Capsule Letter - Digital Legacy Vault</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
</head>
<body class="claim-body">
  <div id="canvas-3d-bg"></div>

  <div class="claim-container glass-card">
    <div class="brand-header">
      <div class="brand-symbol" style="margin-bottom: 12px;">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#00f0ff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"/>
          <polyline points="12 6 12 12 16 14"/>
        </svg>
      </div>
      <h1>Time-Locked Capsule Protocol</h1>
      <p class="subtitle">Confidential Scheduled Milestone Delivery</p>
    </div>

    <?php if(!empty($error)): ?>
      <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
      <div style="text-align: center; margin-top: 20px;">
        <a href="index.php" class="cyber-btn secondary">Return to Home</a>
      </div>
    <?php elseif($capsule): ?>
      <div style="background: rgba(255,255,255,0.04); padding: 18px; border-radius: 12px; margin-bottom: 20px; border: 1px solid rgba(255,255,255,0.08);">
        <h3>Recipient: <span style="color: var(--cyber-blue);"><?php echo htmlspecialchars($capsule['recipient_name']); ?></span></h3>
        <p style="margin-top: 6px; font-size: 0.9rem; color: var(--text-muted);">
          Sender: <strong style="color: var(--text-main);"><?php echo htmlspecialchars($capsule['sender_name']); ?></strong> (<?php echo htmlspecialchars($capsule['sender_email']); ?>) | 
          Scheduled Unlock Date: <strong style="color: var(--text-main);"><?php echo date("F j, Y", strtotime($capsule['unlock_date'])); ?></strong>
        </p>
      </div>

      <?php if(!$isUnlocked): ?>
        <div class="status-box status-locked">
          <div class="status-icon" style="margin-bottom: 12px;">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          </div>
          <h2>Time Capsule Locked</h2>
          <p>This message was configured to remain encrypted until <strong><?php echo date("F j, Y", strtotime($capsule['unlock_date'])); ?></strong>.</p>
          <div class="info-pill" style="margin: 18px 0; display: inline-block; padding: 8px 20px; border-radius: 20px; background: rgba(245, 158, 11, 0.2); color: #fbbf24; font-weight: 700; font-size: 1.1rem;">
            Time Remaining: <?php echo $daysLeft; ?> Days
          </div>
          <p class="small-text" style="font-size: 0.85rem; color: var(--text-muted); max-width: 480px; margin: 0 auto;">
            The cryptographic ciphertext cannot be unlocked or previewed before the scheduled date arrives. Please retain this link and revisit on the unlock date.
          </p>
        </div>
      <?php else: ?>
        <div class="status-box status-released">
          <div class="status-icon" style="margin-bottom: 12px;">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
          </div>
          <h2>Milestone Arrived: Capsule Unlocked</h2>
          <p>The scheduled release date has been reached. Below is your personal decrypted letter.</p>
        </div>

        <div class="legacy-item-card">
          <h3 style="font-size: 1.3rem; margin-bottom: 14px; color: var(--cyber-blue);"><?php echo htmlspecialchars($capsule['title']); ?></h3>
          <div class="secret-box" style="padding: 20px; font-size: 1.05rem; line-height: 1.6;">
            <p style="white-space: pre-wrap; color: var(--text-main); font-family: inherit;"><?php echo htmlspecialchars($decryptedMessage); ?></p>
          </div>
          <div style="margin-top: 14px; text-align: right;">
            <small style="color: var(--text-muted);">Recorded on <?php echo date("M d, Y", strtotime($capsule['created_at'])); ?></small>
          </div>
        </div>

        <div style="margin-top: 25px; display: flex; gap: 12px; justify-content: center;">
          <button onclick="window.print()" class="cyber-btn primary-glow">Print Letter</button>
          <a href="index.php" class="cyber-btn secondary">Return to Home</a>
        </div>
      <?php endif; ?>

    <?php endif; ?>
  </div>

  <script src="assets/js/bg3d.js"></script>
</body>
</html>
