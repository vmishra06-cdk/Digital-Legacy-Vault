<?php
require_once __DIR__ . "/backend/config.php";

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Digital Legacy Vault</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
</head>
<body>
  <!-- Ambient 3D canvas background -->
  <div id="canvas-3d-bg"></div>

  <div class="auth-container auth-showcase-container">
    <div class="glass-card auth-split-card tilt-3d">
      
      <!-- Left Column: Interactive 3D Model Showcase -->
      <div class="auth-3d-panel">
        <div class="auth-3d-header">
          <div class="status-badge success" style="margin-top: 0; padding: 4px 10px; font-size: 0.72rem;">
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            ZERO-KNOWLEDGE CORE
          </div>
          <span style="font-size: 0.75rem; color: var(--text-muted); letter-spacing: 0.5px;">WEBGL 3D MATRIX</span>
        </div>

        <!-- 3D Canvas Viewport -->
        <div id="login-3d-viewport" class="login-3d-canvas-box" title="Click or drag to interact with 3D model"></div>

        <!-- Interactive 3D Model Switcher Bar -->
        <div class="model-switcher-bar">
          <button type="button" class="model-switch-btn active" data-model="vault" onclick="switchLogin3DModel('vault')">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            Vault Core
          </button>
          <button type="button" class="model-switch-btn" data-model="key" onclick="switchLogin3DModel('key')">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 2l-2 2m-2-2l2 2m-4 4l-4 4-2-2-4 4m6-6l-2-2"/></svg>
            Quantum Key
          </button>
          <button type="button" class="model-switch-btn" data-model="shield" onclick="switchLogin3DModel('shield')">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            Security Shield
          </button>
        </div>

        <!-- Real-Time Telemetry HUD -->
        <div class="auth-3d-telemetry">
          <div class="telemetry-item">
            <span class="telemetry-label">CIPHER SUITE</span>
            <span class="telemetry-val">AES-256-GCM</span>
          </div>
          <div class="telemetry-item">
            <span class="telemetry-label">DERIVATION</span>
            <span class="telemetry-val">PBKDF2-100K</span>
          </div>
          <div class="telemetry-item">
            <span class="telemetry-label">STATUS</span>
            <span class="telemetry-val" id="model-status-text">VAULT CORE ARMED</span>
          </div>
        </div>
      </div>

      <!-- Right Column: Login Authentication Form -->
      <div class="auth-form-panel">
        <div class="brand-symbol">
          <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="#00f0ff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
          </svg>
        </div>
        <h2>Access Your Vault</h2>
        <p class="auth-sub">Authenticate to decrypt your digital legacy assets locally</p>

        <?php if (!empty($error)): ?>
          <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
          <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form action="backend/auth.php" method="POST" id="loginForm">
          <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

          <div class="form-group">
            <label for="loginEmail">Email Address</label>
            <input type="email" id="loginEmail" name="email" required placeholder="you@domain.com" autocomplete="email">
          </div>

          <div class="form-group">
            <label for="loginPassword">Master Vault Password</label>
            <input type="password" id="loginPassword" name="password" required placeholder="••••••••••••" autocomplete="current-password">
          </div>

          <button type="submit" name="login" id="loginBtn" class="cyber-btn primary-glow" style="width: 100%; justify-content: center; margin-top: 10px;">
            Unlock Vault
          </button>
        </form>

        <div style="margin-top: 22px; font-size: 0.9rem; color: var(--text-muted);">
          <p>New to Digital Legacy? <a href="register.php" style="color: var(--cyber-blue); font-weight: 600;">Create Account</a></p>
          <p style="margin-top: 8px;"><a href="claim.php" style="color: var(--text-muted); font-size: 0.85rem;">Are you a designated beneficiary? Claim here</a></p>
        </div>
      </div>

    </div>
  </div>

  <script src="assets/js/crypto-zk.js"></script>
  <script src="assets/js/bg3d.js"></script>
  <script src="assets/js/vault3d.js"></script>
  <script src="assets/js/login3d.js"></script>
  <script>
    const form = document.getElementById('loginForm');
    form.addEventListener('submit', async function(e) {
      const email = document.getElementById('loginEmail').value.trim();
      const password = document.getElementById('loginPassword').value;
      if (email && password && window.DLVCrypto && window.DLVCrypto.isSupported()) {
        try {
          const key = await window.DLVCrypto.deriveKeyFromPassword(password, email);
          await window.DLVCrypto.saveKeyToSession(email, key);
        } catch (err) {
          console.warn('Client key derivation notice:', err);
        }
      }
    });
  </script>
</body>
</html>
