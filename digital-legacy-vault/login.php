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

  <div class="auth-container">
    <div class="glass-card auth-card tilt-3d">
      <div class="brand-symbol">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#00f0ff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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

  <script src="assets/js/crypto-zk.js"></script>
  <script src="assets/js/bg3d.js"></script>
  <script src="assets/js/vault3d.js"></script>
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
