<?php
require_once __DIR__ . "/backend/config.php";

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Vault Account - Digital Legacy Vault</title>
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
      <h2>Create Digital Vault</h2>
      <p class="auth-sub">Initialize zero-knowledge encrypted storage for your legacy</p>

      <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <form action="backend/auth.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

        <div class="form-group">
          <label for="regName">Full Legal Name</label>
          <input type="text" id="regName" name="name" required placeholder="John Doe" autocomplete="name">
        </div>

        <div class="form-group">
          <label for="regEmail">Email Address</label>
          <input type="email" id="regEmail" name="email" required placeholder="you@domain.com" autocomplete="email">
        </div>

        <div class="form-group">
          <label for="regPassword">Master Vault Password (min 6 chars)</label>
          <input type="password" id="regPassword" name="password" minlength="6" required placeholder="••••••••••••" autocomplete="new-password">
        </div>

        <div class="form-group">
          <label for="regConfirmPassword">Re-enter Master Vault Password</label>
          <input type="password" id="regConfirmPassword" name="confirm_password" minlength="6" required placeholder="••••••••••••" autocomplete="new-password">
          <small id="passwordMatchNotice" style="display: none; font-size: 0.8rem; margin-top: 4px;"></small>
        </div>

        <button type="submit" id="regSubmitBtn" name="register" class="cyber-btn primary-glow" style="width: 100%; justify-content: center; margin-top: 10px;">
          Create and Arm Vault
        </button>
      </form>

      <div style="margin-top: 22px; font-size: 0.9rem; color: var(--text-muted);">
        <p>Already have a vault? <a href="login.php" style="color: var(--cyber-blue); font-weight: 600;">Sign In</a></p>
      </div>
    </div>
  </div>

  <script src="assets/js/bg3d.js"></script>
  <script src="assets/js/vault3d.js"></script>
  <script>
    const pwd = document.getElementById('regPassword');
    const confirmPwd = document.getElementById('regConfirmPassword');
    const notice = document.getElementById('passwordMatchNotice');
    const form = document.querySelector('form');

    function checkPasswordMatch() {
      if (!confirmPwd.value) {
        notice.style.display = 'none';
        return true;
      }
      if (pwd.value === confirmPwd.value) {
        notice.innerText = 'Passwords match.';
        notice.style.color = '#10b981';
        notice.style.display = 'block';
        return true;
      } else {
        notice.innerText = 'Passwords do not match.';
        notice.style.color = '#ef4444';
        notice.style.display = 'block';
        return false;
      }
    }

    pwd.addEventListener('input', checkPasswordMatch);
    confirmPwd.addEventListener('input', checkPasswordMatch);

    form.addEventListener('submit', async (e) => {
      if (pwd.value !== confirmPwd.value) {
        e.preventDefault();
        checkPasswordMatch();
        confirmPwd.focus();
        return;
      }
      const email = document.getElementById('regEmail').value.trim();
      const password = pwd.value;
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
  <script src="assets/js/crypto-zk.js"></script>
</body>
</html>
