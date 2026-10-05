<?php
require_once __DIR__ . "/backend/config.php";

if (!isset($_SESSION['2fa_pending_uid'])) {
    header("Location: login.php");
    exit;
}

$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Two-Factor Authentication - Digital Legacy Vault</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
</head>
<body>
  <!-- Ambient 3D canvas background -->
  <div id="canvas-3d-bg"></div>

  <div class="auth-container">
    <div class="glass-card auth-card tilt-3d">
      <div class="brand-symbol">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#00f0ff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
          <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
        </svg>
      </div>
      <h2>Two-Factor Verification</h2>
      <p class="auth-sub" id="formDesc">Enter the 6-digit verification code from your authenticator app</p>

      <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <form action="backend/auth.php" method="POST" id="twoFactorForm">
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

        <!-- Mode 1: 6-digit TOTP -->
        <div class="form-group" id="totpGroup">
          <label for="totpCode">6-Digit Authenticator Code</label>
          <input type="text" id="totpCode" name="totp_code" maxlength="6" pattern="[0-9]{6}" required placeholder="000000" autocomplete="one-time-code" autofocus style="text-align: center; letter-spacing: 8px; font-size: 1.5rem; font-family: monospace;">
        </div>

        <!-- Mode 2: Emergency Recovery Code -->
        <div class="form-group" id="recoveryGroup" style="display: none;">
          <label for="recoveryCode">Emergency Recovery Code (e.g. A1B2-C3D4)</label>
          <input type="text" id="recoveryCode" name="recovery_code" maxlength="12" placeholder="XXXX-XXXX" autocomplete="off" style="text-align: center; letter-spacing: 4px; font-size: 1.2rem; font-family: monospace; text-transform: uppercase;">
          <small style="color: var(--text-muted); display: block; margin-top: 6px;">Single-use backup codes generated during 2FA setup.</small>
        </div>

        <button type="submit" name="verify_2fa" class="cyber-btn primary-glow" style="width: 100%; justify-content: center; margin-top: 10px;">
          Authenticate
        </button>
      </form>

      <div style="margin-top: 20px; font-size: 0.9rem;">
        <button type="button" id="toggleModeBtn" class="cyber-btn secondary btn-sm" onclick="toggleAuthMode()" style="width: 100%; justify-content: center;">
          Lost your device? Use Emergency Recovery Code
        </button>
      </div>

      <div style="margin-top: 18px; font-size: 0.85rem; color: var(--text-muted);">
        <p><a href="login.php" style="color: var(--text-muted);">Cancel and Return to Sign In</a></p>
      </div>
    </div>
  </div>

  <script src="assets/js/bg3d.js"></script>
  <script src="assets/js/vault3d.js"></script>
  <script>
    let isRecoveryMode = false;
    function toggleAuthMode() {
      isRecoveryMode = !isRecoveryMode;
      const totpGroup = document.getElementById('totpGroup');
      const recoveryGroup = document.getElementById('recoveryGroup');
      const totpCode = document.getElementById('totpCode');
      const recoveryCode = document.getElementById('recoveryCode');
      const toggleBtn = document.getElementById('toggleModeBtn');
      const formDesc = document.getElementById('formDesc');

      if (isRecoveryMode) {
        totpGroup.style.display = 'none';
        totpCode.required = false;
        recoveryGroup.style.display = 'block';
        recoveryCode.required = true;
        recoveryCode.focus();
        toggleBtn.innerText = 'Use 6-Digit Authenticator App Code';
        formDesc.innerText = 'Enter one of your 8-character single-use emergency backup codes';
      } else {
        totpGroup.style.display = 'block';
        totpCode.required = true;
        recoveryGroup.style.display = 'none';
        recoveryCode.required = false;
        totpCode.focus();
        toggleBtn.innerText = 'Lost your device? Use Emergency Recovery Code';
        formDesc.innerText = 'Enter the 6-digit verification code from your authenticator app';
      }
    }
  </script>
</body>
</html>
