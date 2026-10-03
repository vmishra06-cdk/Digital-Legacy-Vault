<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Digital Legacy Vault - Preserve Your Digital Life</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
</head>
<body>
  <!-- Ambient 3D canvas background -->
  <div id="canvas-3d-bg"></div>

  <!-- Top Navigation -->
  <header class="top-nav">
    <a href="index.php" class="nav-brand">
      <span class="brand-logo-glow">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#00f0ff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
        </svg>
      </span>
      <span>DIGITAL LEGACY VAULT</span>
    </a>
    <div class="nav-actions">
      <a href="claim.php" class="cyber-btn secondary btn-sm">Beneficiary Portal</a>
      <a href="login.php" class="cyber-btn secondary btn-sm">Login</a>
      <a href="register.php" class="cyber-btn primary-glow btn-sm">Get Started</a>
    </div>
  </header>

  <!-- Hero Section -->
  <div class="hero-container">
    <div class="glass-card hero-box tilt-3d">
      <div class="brand-symbol" style="margin-bottom: 16px;">
        <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="#00f0ff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
          <path d="M9 12l2 2 4-4"/>
        </svg>
      </div>
      <h1>Decide Today What Happens to Your Digital Life Tomorrow</h1>
      <p>
        Store your critical passwords, crypto seed phrases, bank credentials, confidential legal documents, and personal letters in an authenticated <strong>AES-256-GCM</strong> vault. Monitored by an automated <strong>Dead Man's Switch</strong> that only releases access to designated beneficiaries if you stop checking in.
      </p>

      <div style="display: flex; justify-content: center; gap: 16px; flex-wrap: wrap;">
        <a href="register.php" class="cyber-btn primary-glow" style="padding: 14px 32px; font-size: 1.05rem;">
          Create Secure Vault
        </a>
        <a href="claim.php" class="cyber-btn secondary" style="padding: 14px 32px; font-size: 1.05rem;">
          Claim as Beneficiary
        </a>
      </div>

      <!-- Feature Badges -->
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-top: 40px; text-align: left;">
        <div style="background: rgba(255,255,255,0.04); padding: 18px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.08);">
          <div style="margin-bottom: 8px;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#00f0ff" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          </div>
          <strong style="color: #fff; font-size: 0.95rem;">AES-256-GCM</strong>
          <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 4px;">Zero-knowledge authenticated encryption with per-record IV and file storage.</p>
        </div>

        <div style="background: rgba(255,255,255,0.04); padding: 18px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.08);">
          <div style="margin-bottom: 8px;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
          </div>
          <strong style="color: #fff; font-size: 0.95rem;">Dead Man's Switch</strong>
          <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 4px;">Configurable heartbeat intervals and automated grace windows.</p>
        </div>

        <div style="background: rgba(255,255,255,0.04); padding: 18px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.08);">
          <div style="margin-bottom: 8px;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          </div>
          <strong style="color: #fff; font-size: 0.95rem;">Granular Trustees</strong>
          <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 4px;">Assign individual records and documents to specific family or legal trustees.</p>
        </div>
      </div>
    </div>
  </div>

  <script src="assets/js/bg3d.js"></script>
  <script src="assets/js/vault3d.js"></script>
</body>
</html>
