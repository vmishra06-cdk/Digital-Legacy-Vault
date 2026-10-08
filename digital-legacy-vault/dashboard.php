<?php
require_once __DIR__ . "/backend/db.php";
require_once __DIR__ . "/backend/helpers.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$uid = $_SESSION['user_id'];

// Fetch current user data
$uStmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$uStmt->bind_param("i", $uid);
$uStmt->execute();
$user = $uStmt->get_result()->fetch_assoc();

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit;
}

$switchStatus = get_dead_man_switch_status($user);

// Fetch Vault items with assigned nominees
$vStmt = $conn->prepare("
    SELECT v.*, GROUP_CONCAT(n.name SEPARATOR ', ') as assigned_nominees 
    FROM vaults v
    LEFT JOIN vault_nominee_access vna ON v.id = vna.vault_id
    LEFT JOIN nominees n ON vna.nominee_id = n.id
    WHERE v.user_id = ?
    GROUP BY v.id
    ORDER BY v.id DESC
");
$vStmt->bind_param("i", $uid);
$vStmt->execute();
$vaults = $vStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch Nominees
$nStmt = $conn->prepare("SELECT * FROM nominees WHERE user_id = ? ORDER BY id DESC");
$nStmt->bind_param("i", $uid);
$nStmt->execute();
$nominees = $nStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch Audit Logs (latest 20)
$aStmt = $conn->prepare("SELECT * FROM audit_logs WHERE user_id = ? ORDER BY id DESC LIMIT 20");
$aStmt->bind_param("i", $uid);
$aStmt->execute();
$auditLogs = $aStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// 2FA Setup state
$twoFactorEnabled = !empty($user['two_factor_enabled']);
$totpSetupSecret = '';
$totpQrUrl = '';
if (!$twoFactorEnabled) {
    if (empty($_SESSION['totp_setup_secret'])) {
        $_SESSION['totp_setup_secret'] = generate_totp_secret();
    }
    $totpSetupSecret = $_SESSION['totp_setup_secret'];
    $totpQrUrl = get_totp_qr_url($user['email'], $totpSetupSecret, 'Digital Legacy Vault');
}

// Flash recovery codes (displayed immediately after generating)
$justGeneratedRecoveryCodes = $_SESSION['new_recovery_codes'] ?? null;
unset($_SESSION['new_recovery_codes']);

// Remaining emergency recovery codes
$remainingRecoveryCodes = [];
if (!empty($user['two_factor_recovery_codes'])) {
    $decodedCodes = json_decode($user['two_factor_recovery_codes'], true);
    if (is_array($decodedCodes)) {
        $remainingRecoveryCodes = $decodedCodes;
    }
}

// Calculate Vault Security Health Score (5 pillars)
$vaultHealth = calculate_vault_health($user, $vaults, $nominees);

$activeTab = $_GET['tab'] ?? 'vault';
$successMsg = $_GET['success'] ?? '';
$errorMsg = $_GET['error'] ?? '';
$warningMsg = $_GET['warning'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Digital Legacy Vault - Cyber Dashboard</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
</head>
<body>
  <!-- Ambient 3D canvas background -->
  <div id="canvas-3d-bg"></div>

  <!-- Top Navigation Bar -->
  <header class="top-nav">
    <a href="dashboard.php" class="nav-brand">
      <span class="brand-logo-glow">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#00f0ff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
        </svg>
      </span>
      <span>DIGITAL LEGACY VAULT</span>
    </a>

    <div class="nav-actions">
      <div id="zk-status-badge" class="status-badge success" style="cursor: pointer; padding: 6px 12px; margin-top: 0;" onclick="ensureVaultKey()" title="Click to verify or unlock client encryption keys">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        ZERO-KNOWLEDGE
      </div>

      <button id="sound-toggle-btn" class="cyber-btn secondary btn-sm" title="Toggle Sound FX">
        Sound: ON
      </button>

      <a href="claim.php" target="_blank" class="cyber-btn secondary btn-sm" title="Open Nominee Claim Gateway">
        Beneficiary Portal
      </a>

      <div class="user-badge">
        <span class="user-avatar"><?php echo strtoupper(substr($user['name'] ?: 'U', 0, 1)); ?></span>
        <span><?php echo htmlspecialchars($user['name'] ?: $user['email']); ?></span>
      </div>

      <a href="logout.php" class="cyber-btn danger btn-sm">Logout</a>
    </div>
  </header>

  <!-- Main Container -->
  <div class="app-container dashboard-grid">

    <!-- Left Sidebar: 3D Holographic Vault & Navigation -->
    <aside class="sidebar-col">
      <div class="glass-card vault-3d-card tilt-3d">
        <h3 style="font-size: 1.05rem; letter-spacing: 0.5px; margin-bottom: 8px;">HOLOGRAPHIC VAULT CORE</h3>
        
        <!-- 3D Canvas Box -->
        <div id="vault-3d-canvas-container"></div>

        <div class="status-badge <?php echo $switchStatus['badge']; ?>">
          STATUS: <?php echo $switchStatus['status']; ?>
        </div>
        <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 6px;">
          <?php echo $switchStatus['label']; ?>
        </p>

        <!-- Quick Pulse Button -->
        <form action="backend/pulse.php" method="POST" style="width: 100%; margin-top: 14px;">
          <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
          <button type="submit" name="check_in" class="cyber-btn primary-glow" style="width: 100%; justify-content: center;" onclick="VaultAudioInstance.pulse()">
            Send Pulse Check-In
          </button>
        </form>

        <!-- Sidebar Navigation Tabs -->
        <nav class="nav-tabs">
          <a href="?tab=vault" class="tab-link <?php echo ($activeTab === 'vault') ? 'active' : ''; ?>">
            <span>Vault Records</span> (<?php echo count($vaults); ?>)
          </a>
          <a href="?tab=nominees" class="tab-link <?php echo ($activeTab === 'nominees') ? 'active' : ''; ?>">
            <span>Beneficiaries</span> (<?php echo count($nominees); ?>)
          </a>
          <a href="?tab=pulse" class="tab-link <?php echo ($activeTab === 'pulse') ? 'active' : ''; ?>">
            <span>Dead Man Switch</span>
          </a>
          <a href="?tab=security" class="tab-link <?php echo ($activeTab === 'security') ? 'active' : ''; ?>">
            <span>Security & 2FA</span>
          </a>
          <a href="?tab=health" class="tab-link <?php echo ($activeTab === 'health') ? 'active' : ''; ?>">
            <span>Health Audit</span>
            <span class="status-badge <?php echo $vaultHealth['rating_badge']; ?>" style="padding: 1px 6px; font-size: 0.7rem; margin-top: 0;">
              <?php echo $vaultHealth['score']; ?>%
            </span>
          </a>
          <a href="?tab=logs" class="tab-link <?php echo ($activeTab === 'logs') ? 'active' : ''; ?>">
            <span>Security Logs</span>
          </a>
          <a href="?tab=backup" class="tab-link <?php echo ($activeTab === 'backup') ? 'active' : ''; ?>">
            <span>Backup and Export</span>
          </a>
        </nav>
      </div>
    </aside>

    <!-- Main Content Area -->
    <main class="main-content-col">
      <div class="glass-card content-area">

        <!-- Flash Notices -->
        <?php if (!empty($successMsg)): ?>
          <div class="alert alert-success"><?php echo htmlspecialchars($successMsg); ?></div>
        <?php endif; ?>
        <?php if (!empty($errorMsg)): ?>
          <div class="alert alert-danger"><?php echo htmlspecialchars($errorMsg); ?></div>
        <?php endif; ?>
        <?php if (!empty($warningMsg)): ?>
          <div class="alert alert-warning"><?php echo htmlspecialchars($warningMsg); ?></div>
        <?php endif; ?>

        <!-- INTERACTIVE VAULT SECURITY HEALTH SCORE WIDGET -->
        <div class="vault-health-widget glass-card" id="vaultHealthWidget">
          <div class="health-widget-header">
            <div class="health-title-group">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="<?php echo $vaultHealth['rating_color']; ?>" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
              </svg>
              <h2>VAULT SECURITY HEALTH SCORE</h2>
              <span class="health-rating-pill <?php echo $vaultHealth['rating_badge']; ?>">
                <?php echo $vaultHealth['rating_label']; ?>
              </span>
            </div>
            <div style="display: flex; align-items: center; gap: 10px;">
              <a href="?tab=health" class="cyber-btn secondary btn-xs" title="Open Deep-Dive Security Simulation and Checklist">
                Full Audit Details &rarr;
              </a>
              <button type="button" class="health-toggle-btn" onclick="toggleHealthDetails()" id="toggleHealthBtn">
                <span id="toggleHealthText">Hide Breakdown</span>
                <svg id="toggleHealthIcon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="18 15 12 9 6 15"/></svg>
              </button>
            </div>
          </div>

          <!-- Score Gauge & 5-Pillar Cards -->
          <div class="health-overview-row" id="healthOverviewBody">
            <!-- Left Circular Progress Gauge -->
            <div class="health-gauge-box">
              <svg class="health-ring-svg" viewBox="0 0 120 120" width="120" height="120">
                <circle class="health-ring-bg" cx="60" cy="60" r="50" stroke="rgba(255,255,255,0.08)" stroke-width="10" fill="none" />
                <circle class="health-ring-meter" cx="60" cy="60" r="50" stroke="<?php echo $vaultHealth['rating_color']; ?>" stroke-width="10" fill="none"
                        stroke-linecap="round" stroke-dasharray="314.16" stroke-dashoffset="<?php echo round(314.16 * (1 - ($vaultHealth['score'] / 100)), 2); ?>" />
                <text x="60" y="54" text-anchor="middle" dominant-baseline="central" class="health-ring-text-num" font-size="24"><?php echo $vaultHealth['score']; ?>%</text>
                <text x="60" y="74" text-anchor="middle" dominant-baseline="central" class="health-ring-text-lbl" font-size="9" fill="var(--text-muted)">AUDIT SCORE</text>
              </svg>
              <span style="font-size: 0.72rem; color: var(--text-muted); margin-top: 10px; text-align: center;">
                5-Pillar Verification
              </span>
            </div>

            <!-- Right 5 Pillars Grid -->
            <div class="health-pillars-grid">
              <!-- Pillar 1: Password Entropy -->
              <div class="health-pillar-card">
                <div>
                  <div class="pillar-header">
                    <span class="pillar-title">Entropy</span>
                    <span class="pillar-pts-badge <?php echo $vaultHealth['metrics']['entropy']['status']; ?>">
                      <?php echo $vaultHealth['metrics']['entropy']['score']; ?>/20 PTS
                    </span>
                  </div>
                  <div class="pillar-value"><?php echo $vaultHealth['metrics']['entropy']['value']; ?></div>
                  <div class="pillar-desc"><?php echo $vaultHealth['metrics']['entropy']['description']; ?></div>
                </div>
                <div class="pillar-action">
                  <a href="<?php echo $vaultHealth['metrics']['entropy']['action_url']; ?>" class="cyber-btn secondary btn-xs">
                    <?php echo $vaultHealth['metrics']['entropy']['action_label']; ?>
                  </a>
                </div>
              </div>

              <!-- Pillar 2: 2FA Authentication -->
              <div class="health-pillar-card">
                <div>
                  <div class="pillar-header">
                    <span class="pillar-title">2FA Auth</span>
                    <span class="pillar-pts-badge <?php echo $vaultHealth['metrics']['two_factor']['status']; ?>">
                      <?php echo $vaultHealth['metrics']['two_factor']['score']; ?>/20 PTS
                    </span>
                  </div>
                  <div class="pillar-value"><?php echo $vaultHealth['metrics']['two_factor']['value']; ?></div>
                  <div class="pillar-desc"><?php echo $vaultHealth['metrics']['two_factor']['description']; ?></div>
                </div>
                <div class="pillar-action">
                  <a href="<?php echo $vaultHealth['metrics']['two_factor']['action_url']; ?>" class="cyber-btn secondary btn-xs">
                    <?php echo $vaultHealth['metrics']['two_factor']['action_label']; ?>
                  </a>
                </div>
              </div>

              <!-- Pillar 3: Beneficiary Coverage -->
              <div class="health-pillar-card">
                <div>
                  <div class="pillar-header">
                    <span class="pillar-title">Beneficiaries</span>
                    <span class="pillar-pts-badge <?php echo $vaultHealth['metrics']['coverage']['status']; ?>">
                      <?php echo $vaultHealth['metrics']['coverage']['score']; ?>/20 PTS
                    </span>
                  </div>
                  <div class="pillar-value"><?php echo $vaultHealth['metrics']['coverage']['value']; ?></div>
                  <div class="pillar-desc"><?php echo $vaultHealth['metrics']['coverage']['description']; ?></div>
                </div>
                <div class="pillar-action">
                  <a href="<?php echo $vaultHealth['metrics']['coverage']['action_url']; ?>" class="cyber-btn secondary btn-xs">
                    <?php echo $vaultHealth['metrics']['coverage']['action_label']; ?>
                  </a>
                </div>
              </div>

              <!-- Pillar 4: Switch Freshness -->
              <div class="health-pillar-card">
                <div>
                  <div class="pillar-header">
                    <span class="pillar-title">Switch Freshness</span>
                    <span class="pillar-pts-badge <?php echo $vaultHealth['metrics']['freshness']['status']; ?>">
                      <?php echo $vaultHealth['metrics']['freshness']['score']; ?>/20 PTS
                    </span>
                  </div>
                  <div class="pillar-value"><?php echo $vaultHealth['metrics']['freshness']['value']; ?></div>
                  <div class="pillar-desc"><?php echo $vaultHealth['metrics']['freshness']['description']; ?></div>
                </div>
                <div class="pillar-action">
                  <a href="<?php echo $vaultHealth['metrics']['freshness']['action_url']; ?>" class="cyber-btn secondary btn-xs">
                    <?php echo $vaultHealth['metrics']['freshness']['action_label']; ?>
                  </a>
                </div>
              </div>

              <!-- Pillar 5: Offline Cold Backup -->
              <div class="health-pillar-card">
                <div>
                  <div class="pillar-header">
                    <span class="pillar-title">Cold Backup</span>
                    <span class="pillar-pts-badge <?php echo $vaultHealth['metrics']['backup']['status']; ?>">
                      <?php echo $vaultHealth['metrics']['backup']['score']; ?>/20 PTS
                    </span>
                  </div>
                  <div class="pillar-value"><?php echo $vaultHealth['metrics']['backup']['value']; ?></div>
                  <div class="pillar-desc"><?php echo $vaultHealth['metrics']['backup']['description']; ?></div>
                </div>
                <div class="pillar-action">
                  <a href="<?php echo $vaultHealth['metrics']['backup']['action_url']; ?>" class="cyber-btn secondary btn-xs">
                    <?php echo $vaultHealth['metrics']['backup']['action_label']; ?>
                  </a>
                </div>
              </div>
            </div>
          </div>

          <!-- Beneficiary Coverage Gap Alert (Flags items not yet assigned) -->
          <?php if (!empty($vaultHealth['unassigned_vaults'])): ?>
            <div class="security-gap-alert" id="unassignedGapAlert">
              <div class="gap-alert-header">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                  <line x1="12" y1="9" x2="12" y2="13"/>
                  <line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
                <span>Beneficiary Coverage Gap: <?php echo count($vaultHealth['unassigned_vaults']); ?> Secret Item(s) Not Assigned to Any Trustee</span>
              </div>
              <p class="gap-alert-desc">
                If emergency protocol executes, unassigned secrets remain orphaned and will not be accessible to any beneficiary. Map trustee permissions to resolve this vulnerability.
              </p>
              <div class="gap-items-row">
                <?php foreach ($vaultHealth['unassigned_vaults'] as $uv): ?>
                  <div class="gap-item-chip">
                    <span style="font-weight: 600;"><?php echo htmlspecialchars($uv['title']); ?></span>
                    <span class="category-badge cat-<?php echo htmlspecialchars($uv['category']); ?>" style="font-size: 0.65rem; padding: 2px 6px;">
                      <?php echo htmlspecialchars($uv['category']); ?>
                    </span>
                    <button type="button" class="cyber-btn secondary btn-xs" style="padding: 2px 6px; font-size: 0.68rem;" onclick="openEditVaultModal(<?php echo htmlspecialchars(json_encode($uv)); ?>)">
                      Assign Trustee
                    </button>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

          <!-- Actionable Recommendations List -->
          <?php if (!empty($vaultHealth['recommendations'])): ?>
            <div class="security-recs-container" id="healthRecommendationsContainer">
              <div class="security-recs-header">
                <h4>
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#00f0ff" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>
                  </svg>
                  Actionable Security Recommendations (<?php echo count($vaultHealth['recommendations']); ?>)
                </h4>
              </div>
              <div class="recommendations-list">
                <?php foreach ($vaultHealth['recommendations'] as $rec): ?>
                  <div class="rec-item">
                    <div class="rec-info">
                      <div class="rec-title-row">
                        <span class="rec-severity-badge <?php echo $rec['severity']; ?>">
                          <?php echo $rec['severity']; ?>
                        </span>
                        <span class="rec-title"><?php echo htmlspecialchars($rec['title']); ?></span>
                      </div>
                      <p class="rec-desc"><?php echo htmlspecialchars($rec['desc']); ?></p>
                    </div>
                    <div>
                      <a href="<?php echo htmlspecialchars($rec['action_url']); ?>" class="cyber-btn primary btn-xs" style="white-space: nowrap;">
                        <?php echo htmlspecialchars($rec['action_label']); ?> &rarr;
                      </a>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>

        <!-- TAB 1: VAULT ITEMS -->
        <div id="tab-vault" class="tab-pane <?php echo ($activeTab === 'vault') ? 'active' : ''; ?>">
          <div class="section-header">
            <div>
              <h2>Encrypted Vault Records</h2>
              <p style="color: var(--text-muted); font-size: 0.9rem;">Protected with AES-256-GCM Authenticated Encryption with File Attachments</p>
            </div>
            <button class="cyber-btn primary-glow" onclick="openAddVaultModal()">
              + Add Secret Record
            </button>
          </div>

          <!-- Controls: Category Filters & Search -->
          <div class="vault-controls">
            <input type="text" id="vaultSearch" class="search-input" placeholder="Search secrets by title, notes, or filename..." oninput="filterVaultItems()">
            <select id="categoryFilter" class="search-input" style="max-width: 180px;" onchange="filterVaultItems()">
              <option value="ALL">All Categories</option>
              <option value="Financial">Financial / Banking</option>
              <option value="Credentials">Credentials / Logins</option>
              <option value="Legal">Legal and Wills</option>
              <option value="Crypto">Crypto and Seeds</option>
              <option value="Personal">Personal Messages</option>
            </select>
          </div>

          <!-- Vault Grid -->
          <div class="vault-grid" id="vaultCardsGrid">
            <?php if (empty($vaults)): ?>
              <div style="grid-column: 1 / -1; text-align: center; padding: 50px; color: var(--text-muted);">
                <div style="margin-bottom: 12px;">
                  <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </div>
                <h3>Your vault is currently empty.</h3>
                <p style="margin-top: 6px;">Add private credentials, crypto seeds, bank pins, legal wills, or encrypted documents to preserve them.</p>
              </div>
            <?php else: ?>
              <?php foreach ($vaults as $v): ?>
                <?php 
                  $isClient = !isset($v['is_client_encrypted']) || !empty($v['is_client_encrypted']);
                  $serverFallback = (!$isClient) ? decrypt_vault_secret($v['secret'], $v['iv'] ?? '', $v['tag'] ?? '') : '';
                ?>
                <div class="vault-item-card tilt-3d" data-category="<?php echo htmlspecialchars($v['category']); ?>" data-title="<?php echo strtolower(htmlspecialchars($v['title'])); ?>" data-notes="<?php echo strtolower(htmlspecialchars(($v['notes'] ?? '') . ' ' . ($v['file_name'] ?? ''))); ?>">
                  <div class="item-top">
                    <div>
                      <span class="category-tag"><?php echo htmlspecialchars($v['category']); ?></span>
                      <?php if ($isClient): ?>
                        <span style="font-size: 0.7rem; color: var(--cyber-blue); border: 1px solid rgba(0, 240, 255, 0.3); padding: 2px 6px; border-radius: 4px; margin-left: 6px;">Zero-Knowledge</span>
                      <?php endif; ?>
                      <h4 class="item-title"><?php echo htmlspecialchars($v['title']); ?></h4>
                    </div>
                    <form action="backend/vault.php" method="POST" onsubmit="return confirm('Are you sure you want to permanently delete this secret?');">
                      <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                      <input type="hidden" name="vault_id" value="<?php echo $v['id']; ?>">
                      <button type="submit" name="delete_vault" class="cyber-btn danger btn-sm" title="Delete record" style="padding: 4px 10px;">Delete</button>
                    </form>
                  </div>

                  <!-- Secret Box with Zero-Knowledge Client-Side Decrypt and Copy -->
                  <div class="secret-display-box"
                       id="secret-box-<?php echo $v['id']; ?>"
                       data-vault-id="<?php echo $v['id']; ?>"
                       data-ciphertext="<?php echo htmlspecialchars($v['secret']); ?>"
                       data-iv="<?php echo htmlspecialchars($v['iv'] ?? ''); ?>"
                       data-tag="<?php echo htmlspecialchars($v['tag'] ?? ''); ?>"
                       data-is-client="<?php echo $isClient ? '1' : '0'; ?>"
                       data-fallback="<?php echo htmlspecialchars(addslashes($serverFallback)); ?>">
                    <div class="secret-display-text" id="secret-text-<?php echo $v['id']; ?>" data-masked="true">••••••••••••••••</div>
                    <div class="secret-actions-row">
                      <button type="button" class="cyber-btn secondary btn-sm" onclick="toggleSecret(<?php echo $v['id']; ?>)">
                        <span id="toggle-lbl-<?php echo $v['id']; ?>">Reveal</span>
                      </button>
                      <button type="button" class="cyber-btn secondary btn-sm" onclick="copySecret(<?php echo $v['id']; ?>)">
                        Copy
                      </button>
                    </div>
                  </div>

                  <!-- File Attachment Box if exists -->
                  <?php if (!empty($v['file_path'])): ?>
                    <div style="background: rgba(0, 240, 255, 0.05); border: 1px solid var(--glass-border); border-radius: 8px; padding: 10px 12px; display: flex; justify-content: space-between; align-items: center;">
                      <div style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 170px;">
                        <span style="font-size: 0.8rem; color: var(--text-muted); display: block;">Attachment:</span>
                        <strong style="font-size: 0.85rem; color: var(--cyber-blue);"><?php echo htmlspecialchars($v['file_name'] ?: 'Uploaded File'); ?></strong>
                        <?php if (!empty($v['file_size'])): ?>
                          <small style="color: var(--text-muted);">(<?php echo round($v['file_size'] / 1024, 1); ?> KB)</small>
                        <?php endif; ?>
                      </div>
                      <button type="button" class="cyber-btn primary-glow btn-sm" onclick="downloadFile(<?php echo $v['id']; ?>, '<?php echo htmlspecialchars(addslashes($v['file_name'])); ?>', <?php echo $isClient ? 1 : 0; ?>)">
                        Download
                      </button>
                    </div>
                  <?php endif; ?>

                  <?php if (!empty($v['notes'])): ?>
                    <p class="notes-preview">Notes: <?php echo nl2br(htmlspecialchars($v['notes'])); ?></p>
                  <?php endif; ?>

                  <div class="item-bottom">
                    <small style="color: var(--text-muted); font-size: 0.75rem;">
                      <?php if (!empty($v['assigned_nominees'])): ?>
                        Beneficiaries: <span style="color: var(--cyber-blue);"><?php echo htmlspecialchars($v['assigned_nominees']); ?></span>
                      <?php else: ?>
                        All Beneficiaries
                      <?php endif; ?>
                    </small>
                    <button class="cyber-btn secondary btn-sm" onclick="openEditVaultModal(<?php echo htmlspecialchars(json_encode($v)); ?>)">
                      Edit
                    </button>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- TAB 2: NOMINEES / BENEFICIARIES -->
        <div id="tab-nominees" class="tab-pane <?php echo ($activeTab === 'nominees') ? 'active' : ''; ?>">
          <div class="section-header">
            <div>
              <h2>Designated Beneficiaries & Trustees</h2>
              <p style="color: var(--text-muted); font-size: 0.9rem;">Assign trusted individuals who will receive access to your vault if the Dead Man's Switch activates.</p>
            </div>
            <button class="cyber-btn primary-glow" onclick="document.getElementById('nomineeModal').showModal()">
              + Add Beneficiary
            </button>
          </div>

          <?php if (empty($nominees)): ?>
            <div style="text-align: center; padding: 50px; color: var(--text-muted);">
              <div style="margin-bottom: 12px;">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
              </div>
              <h3>No Beneficiaries Added Yet.</h3>
              <p style="margin-top: 6px;">Add trusted family members, lawyers, or trustees to inherit your digital vault.</p>
            </div>
          <?php else: ?>
            <div style="overflow-x: auto;">
              <table class="custom-table">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Email / Phone</th>
                    <th>Relation</th>
                    <th>Claim Gateway Key</th>
                    <th>Status</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($nominees as $n): ?>
                    <?php 
                      $claimUrl = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/claim.php?token=" . $n['claim_token']; 
                    ?>
                    <tr>
                      <td><strong><?php echo htmlspecialchars($n['name']); ?></strong></td>
                      <td>
                        <?php echo htmlspecialchars($n['email']); ?>
                        <?php if(!empty($n['phone'])): ?><br><small style="color: var(--text-muted);"><?php echo htmlspecialchars($n['phone']); ?></small><?php endif; ?>
                      </td>
                      <td><span class="category-tag"><?php echo htmlspecialchars($n['relation']); ?></span></td>
                      <td>
                        <div class="token-copy-box">
                          <span class="token-code"><?php echo substr($n['claim_token'], 0, 14); ?>...</span>
                          <button class="cyber-btn secondary btn-sm" onclick="copyToClipboard('<?php echo $claimUrl; ?>', 'Direct Beneficiary Claim Link copied')">
                            Copy Link
                          </button>
                        </div>
                      </td>
                      <td>
                        <span class="status-badge <?php echo ($n['status'] === 'claimed') ? 'success' : 'warning'; ?>">
                          <?php echo strtoupper($n['status']); ?>
                        </span>
                      </td>
                      <td>
                        <form action="backend/nominee.php" method="POST" onsubmit="return confirm('Remove this beneficiary?');">
                          <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                          <input type="hidden" name="nominee_id" value="<?php echo $n['id']; ?>">
                          <button type="submit" name="delete_nominee" class="cyber-btn danger btn-sm">Remove</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>

          <div style="margin-top: 30px; background: rgba(0, 240, 255, 0.05); border: 1px solid var(--glass-border); padding: 20px; border-radius: 12px;">
            <h4 style="color: var(--cyber-blue); margin-bottom: 8px;">How Beneficiary Claiming Works</h4>
            <p style="font-size: 0.9rem; color: var(--text-muted); line-height: 1.5;">
              Each beneficiary receives a unique, unguessable Claim Link. While your Dead Man's Switch is healthy and regularly verified, the claim portal remains strictly locked. If you fail to check in and the grace period expires, the portal unlocks and automatically decrypts records and files authorized for that beneficiary.
            </p>
          </div>
        </div>

        <!-- TAB 3: DEAD MAN'S SWITCH / PULSE PROTOCOL -->
        <div id="tab-pulse" class="tab-pane <?php echo ($activeTab === 'pulse') ? 'active' : ''; ?>">
          <div class="section-header">
            <div>
              <h2>Dead Man's Switch Protocol</h2>
              <p style="color: var(--text-muted); font-size: 0.9rem;">Automated inactivity monitor ensuring your legacy is safely delivered if you are unable to check in.</p>
            </div>
          </div>

          <div class="pulse-hud">
            <!-- Center Hero Check-In Card -->
            <div class="glass-card pulse-hero-card tilt-3d">
              <span class="status-badge <?php echo $switchStatus['badge']; ?>" style="font-size: 0.9rem; padding: 6px 18px;">
                STATUS: <?php echo $switchStatus['status']; ?> (<?php echo $switchStatus['label']; ?>)
              </span>

              <form action="backend/pulse.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <button type="submit" name="check_in" class="pulse-heart-btn" onclick="VaultAudioInstance.pulse()">
                  <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 4px;">
                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                  </svg>
                  <span>I AM ALIVE</span>
                  <span style="font-size: 0.75rem; opacity: 0.9;">Send Pulse</span>
                </button>
              </form>

              <div style="margin-top: 15px;">
                <h3 style="font-size: 1.8rem; font-weight: 700;">
                  <?php echo $switchStatus['days_remaining']; ?> Days / <?php echo $switchStatus['hours_remaining']; ?> Hours
                </h3>
                <p style="color: var(--text-muted); font-size: 0.9rem;">Remaining until next verification deadline</p>
              </div>

              <!-- Progress Bar -->
              <div class="pulse-progress-bar-container">
                <div class="pulse-progress-fill" style="width: <?php echo $switchStatus['percent']; ?>%;"></div>
              </div>
              <small style="color: var(--text-muted);">
                Last verified: <strong><?php echo date("F j, Y - g:i A", strtotime($user['last_check_in'])); ?></strong>
              </small>
            </div>

            <!-- Settings & Simulation Grid -->
            <div class="switch-settings-grid">
              <!-- Switch Settings Form -->
              <div class="glass-card" style="padding: 24px;">
                <h3 style="margin-bottom: 12px;">Protocol Timing Settings</h3>
                <form action="backend/pulse.php" method="POST">
                  <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                  <div class="form-group">
                    <label>Check-in Interval (Days)</label>
                    <input type="number" name="frequency_days" min="1" max="365" value="<?php echo htmlspecialchars($user['check_in_frequency_days'] ?: 30); ?>" required>
                    <small style="color: var(--text-muted);">How often you must confirm your presence</small>
                  </div>
                  <div class="form-group">
                    <label>Grace Period (Days)</label>
                    <input type="number" name="grace_days" min="1" max="90" value="<?php echo htmlspecialchars($user['grace_period_days'] ?: 7); ?>" required>
                    <small style="color: var(--text-muted);">Warning window before triggering release</small>
                  </div>
                  <button type="submit" name="update_switch_settings" class="cyber-btn primary-glow">Save Protocol Settings</button>
                </form>
              </div>

              <!-- Sandbox Simulation -->
              <div class="glass-card" style="padding: 24px; border-color: rgba(245, 158, 11, 0.3);">
                <h3 style="margin-bottom: 12px; color: var(--cyber-amber);">Simulation Sandbox</h3>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 16px;">
                  Test what happens when the Dead Man's Switch triggers without waiting days.
                </p>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                  <form action="backend/pulse.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                    <button type="submit" name="simulate_trigger" class="cyber-btn danger" style="width: 100%; justify-content: center;" onclick="VaultAudioInstance.alert()">
                      Simulate Switch Trigger (Unlock Vault for Nominees)
                    </button>
                  </form>
                  <form action="backend/pulse.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                    <button type="submit" name="reset_switch" class="cyber-btn secondary" style="width: 100%; justify-content: center;">
                      Reset Switch to Active (Re-Arm Protocol)
                    </button>
                  </form>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- TAB 4: AUDIT LOGS -->
        <div id="tab-logs" class="tab-pane <?php echo ($activeTab === 'logs') ? 'active' : ''; ?>">
          <div class="section-header">
            <div>
              <h2>Immutable Security Audit Trail</h2>
              <p style="color: var(--text-muted); font-size: 0.9rem;">Chronological record of all authentication, check-ins, record edits, and access attempts.</p>
            </div>
          </div>

          <?php if (empty($auditLogs)): ?>
            <p style="color: var(--text-muted); text-align: center; padding: 40px;">No audit events recorded yet.</p>
          <?php else: ?>
            <table class="custom-table">
              <thead>
                <tr>
                  <th>Timestamp</th>
                  <th>Action</th>
                  <th>Details</th>
                  <th>IP Address</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($auditLogs as $log): ?>
                  <tr>
                    <td><small style="color: var(--text-muted);"><?php echo date("M d, Y H:i:s", strtotime($log['created_at'])); ?></small></td>
                    <td><span class="category-tag"><?php echo htmlspecialchars($log['action']); ?></span></td>
                    <td><?php echo htmlspecialchars($log['details']); ?></td>
                    <td><small style="font-family: monospace; color: var(--cyber-blue);"><?php echo htmlspecialchars($log['ip_address']); ?></small></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>

        <!-- TAB: TWO-FACTOR AUTHENTICATION & SECURITY -->
        <div id="tab-security" class="tab-pane <?php echo ($activeTab === 'security') ? 'active' : ''; ?>">
          <div class="section-header">
            <div>
              <h2>Two-Factor Authentication (2FA / TOTP)</h2>
              <p style="color: var(--text-muted); font-size: 0.9rem;">
                Eliminate single points of failure with RFC 6238 Time-Based One-Time Passwords (TOTP) and offline emergency recovery keys.
              </p>
            </div>
            <div>
              <?php if ($twoFactorEnabled): ?>
                <span class="status-badge success" style="margin-top: 0; background: rgba(16, 185, 129, 0.15); color: var(--cyber-green); border: 1px solid rgba(16, 185, 129, 0.4);">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                  2FA Active & Enforced
                </span>
              <?php else: ?>
                <span class="status-badge warning" style="margin-top: 0; background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3);">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                  2FA Not Configured
                </span>
              <?php endif; ?>
            </div>
          </div>

          <?php if (!empty($justGeneratedRecoveryCodes)): ?>
            <!-- Newly Generated Recovery Codes Banner -->
            <div class="glass-card" style="padding: 24px; margin-bottom: 24px; border: 1px solid var(--cyber-green); background: rgba(16, 185, 129, 0.08);">
              <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 12px;">
                <h3 style="color: var(--cyber-green); margin: 0; display: flex; align-items: center; gap: 8px;">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                  Emergency Recovery Backup Codes
                </h3>
                <div style="display: flex; gap: 10px;">
                  <button type="button" class="cyber-btn secondary btn-sm" onclick="copyRecoveryCodes()">
                    Copy All Codes
                  </button>
                  <button type="button" class="cyber-btn secondary btn-sm" onclick="downloadRecoveryCodes()">
                    Download .txt
                  </button>
                </div>
              </div>
              <p style="font-size: 0.9rem; color: var(--text-main); margin-bottom: 16px;">
                <strong>CRITICAL:</strong> Store these 8 single-use emergency recovery codes safely offline (in a password manager or physical safe). Each code can be used exactly once to log in if you lose access to your authenticator app. <em>These codes will NOT be displayed again!</em>
              </p>
              <div id="recoveryCodesList" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px; background: rgba(0, 0, 0, 0.4); padding: 16px; border-radius: 8px; border: 1px dashed rgba(16, 185, 129, 0.4);">
                <?php foreach ($justGeneratedRecoveryCodes as $rcode): ?>
                  <div style="font-family: monospace; font-size: 1.05rem; font-weight: 700; letter-spacing: 2px; color: var(--cyber-green); text-align: center; background: rgba(255,255,255,0.04); padding: 8px 10px; border-radius: 6px;">
                    <?php echo htmlspecialchars($rcode); ?>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

          <?php if (!$twoFactorEnabled): ?>
            <!-- 2FA Setup Flow -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px;">
              <!-- Step 1: Scan QR Code -->
              <div class="glass-card" style="padding: 28px;">
                <h3 style="margin-bottom: 12px; display: flex; align-items: center; gap: 10px;">
                  <span style="background: var(--cyber-blue); color: #000; width: 26px; height: 26px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 0.85rem; font-weight: bold;">1</span>
                  Scan Setup QR Code
                </h3>
                <p style="color: var(--text-muted); font-size: 0.88rem; margin-bottom: 20px;">
                  Open <strong>Google Authenticator</strong>, <strong>Authy</strong>, <strong>1Password</strong>, or <strong>Microsoft Authenticator</strong> on your mobile device and scan this QR code.
                </p>

                <div style="text-align: center; margin-bottom: 20px;">
                  <img src="<?php echo htmlspecialchars($totpQrUrl); ?>" alt="2FA Setup QR Code" style="background: #ffffff; padding: 12px; border-radius: 12px; box-shadow: 0 4px 25px rgba(0,0,0,0.5); width: 200px; height: 200px; display: inline-block;">
                </div>

                <div style="background: rgba(0,0,0,0.3); padding: 14px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.08);">
                  <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 6px;">Can't scan the QR code? Enter this secret key manually:</div>
                  <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">
                    <code id="manualTotpSecret" style="font-family: monospace; font-size: 0.95rem; letter-spacing: 2px; color: var(--cyber-blue); word-break: break-all;">
                      <?php echo htmlspecialchars(chunk_split($totpSetupSecret, 4, ' ')); ?>
                    </code>
                    <button type="button" class="cyber-btn secondary btn-sm" onclick="navigator.clipboard.writeText('<?php echo htmlspecialchars($totpSetupSecret); ?>'); this.innerText='Copied!'; setTimeout(()=>this.innerText='Copy', 2000);">
                      Copy
                    </button>
                  </div>
                </div>
              </div>

              <!-- Step 2: Confirm 6-Digit Code -->
              <div class="glass-card" style="padding: 28px;">
                <h3 style="margin-bottom: 12px; display: flex; align-items: center; gap: 10px;">
                  <span style="background: var(--cyber-blue); color: #000; width: 26px; height: 26px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 0.85rem; font-weight: bold;">2</span>
                  Verify & Activate 2FA
                </h3>
                <p style="color: var(--text-muted); font-size: 0.88rem; margin-bottom: 24px;">
                  Enter the 6-digit verification code generated by your authenticator app to verify time synchronization and activate two-factor protection.
                </p>

                <form action="backend/security.php" method="POST">
                  <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                  <input type="hidden" name="totp_secret" value="<?php echo htmlspecialchars($totpSetupSecret); ?>">

                  <div class="form-group">
                    <label for="totpVerifyInput">6-Digit Authenticator Code</label>
                    <input type="text" id="totpVerifyInput" name="totp_code" maxlength="6" pattern="[0-9]{6}" required placeholder="000000" autocomplete="one-time-code" style="text-align: center; letter-spacing: 8px; font-size: 1.4rem; font-family: monospace;">
                    <small style="color: var(--text-muted); margin-top: 6px; display: block;">Codes refresh automatically every 30 seconds.</small>
                  </div>

                  <button type="submit" name="enable_2fa" class="cyber-btn primary-glow" style="width: 100%; justify-content: center; margin-top: 18px;">
                    Verify Code and Enable 2FA
                  </button>
                </form>
              </div>
            </div>

          <?php else: ?>
            <!-- 2FA Active Management -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px;">
              <!-- 2FA Active Status Card -->
              <div class="glass-card" style="padding: 28px;">
                <h3 style="margin-bottom: 14px; display: flex; align-items: center; gap: 8px; color: var(--cyber-green);">
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                  Two-Factor Protection Active
                </h3>
                <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 20px;">
                  Your vault is fortified with Time-Based One-Time Passwords (RFC 6238). Unauthorized logins are blocked even if your master password is leaked or compromised.
                </p>

                <div style="background: rgba(0,0,0,0.3); padding: 18px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.08); margin-bottom: 20px;">
                  <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 0.9rem; color: var(--text-muted);">Emergency Recovery Codes Remaining:</span>
                    <strong style="color: var(--cyber-blue); font-size: 1.1rem; font-family: monospace;">
                      <?php echo count($remainingRecoveryCodes); ?> / 8
                    </strong>
                  </div>
                  <?php if (count($remainingRecoveryCodes) <= 2): ?>
                    <p style="color: var(--cyber-amber); font-size: 0.82rem; margin-top: 8px;">
                      Warning: Low recovery codes count. Regenerate a new batch below before exhausting all keys.
                    </p>
                  <?php endif; ?>
                </div>

                <!-- Form: Regenerate Recovery Codes -->
                <form action="backend/security.php" method="POST" onsubmit="return confirm('Regenerating codes will immediately invalidate all previous recovery codes. Continue?');">
                  <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                  <h4 style="font-size: 0.95rem; margin-bottom: 10px;">Regenerate Emergency Recovery Codes</h4>
                  <div class="form-group" style="margin-bottom: 12px;">
                    <input type="password" name="password" required placeholder="Enter Master Password to Confirm" autocomplete="current-password">
                  </div>
                  <button type="submit" name="regenerate_recovery_codes" class="cyber-btn secondary" style="width: 100%; justify-content: center;">
                    Generate 8 Fresh Recovery Codes
                  </button>
                </form>
              </div>

              <!-- Disable 2FA Card -->
              <div class="glass-card" style="padding: 28px; border-color: rgba(239, 68, 68, 0.3);">
                <h3 style="margin-bottom: 14px; color: var(--cyber-red); display: flex; align-items: center; gap: 8px;">
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                  Deactivate Two-Factor Authentication
                </h3>
                <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 20px;">
                  Disabling 2FA reduces vault security to single-password protection. All stored 2FA secrets and emergency recovery codes will be erased immediately.
                </p>

                <form action="backend/security.php" method="POST" onsubmit="return confirm('Are you certain you wish to disable Two-Factor Authentication? Your vault will be vulnerable to single password compromise.');">
                  <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                  <div class="form-group" style="margin-bottom: 14px;">
                    <label for="disable2faPass">Confirm Master Password</label>
                    <input type="password" id="disable2faPass" name="password" required placeholder="••••••••••••" autocomplete="current-password">
                  </div>
                  <button type="submit" name="disable_2fa" class="cyber-btn danger" style="width: 100%; justify-content: center;">
                    Disable Two-Factor Authentication
                  </button>
                </form>
              </div>
            </div>
          <?php endif; ?>
        </div>

        <!-- TAB 5: BACKUP & EXPORT -->
        <div id="tab-backup" class="tab-pane <?php echo ($activeTab === 'backup') ? 'active' : ''; ?>">
          <div class="section-header">
            <div>
              <h2>Vault Data Backup and Export</h2>
              <p style="color: var(--text-muted); font-size: 0.9rem;">Download an offline encrypted or decrypted emergency archive of your vault records.</p>
            </div>
          </div>

          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px; margin-top: 20px;">
            <div class="glass-card" style="padding: 30px; text-align: center;">
              <div style="margin-bottom: 16px;">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#00f0ff" stroke-width="1.8"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
              </div>
              <h3>Download Complete Vault JSON Archive</h3>
              <p style="color: var(--text-muted); font-size: 0.95rem; margin: 14px 0 24px 0;">
                Generates a verified JSON export containing your decrypted vault secrets, instructions, and designated beneficiary list for safe physical storage on an air-gapped drive.
              </p>
              <div style="display: flex; flex-direction: column; gap: 12px; margin-top: 20px;">
                <button type="button" class="cyber-btn primary-glow" style="justify-content: center;" onclick="exportZeroKnowledgeJSON()">
                  Download Decrypted Archive (Zero-Knowledge .json)
                </button>
                <a href="backend/export.php" class="cyber-btn secondary" style="justify-content: center;">
                  Download Raw Server Archive (.json)
                </a>
              </div>
            </div>

            <div class="glass-card" style="padding: 30px; text-align: left;">
              <h3 style="margin-bottom: 14px;">Update Master Vault Password</h3>
              <form action="backend/auth.php" method="POST" id="changePasswordForm">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

                <div class="form-group">
                  <label for="currentPassword">Current Password</label>
                  <input type="password" id="currentPassword" name="current_password" required placeholder="••••••••••••" autocomplete="current-password">
                </div>

                <div class="form-group">
                  <label for="newPassword">New Password (min 6 chars)</label>
                  <input type="password" id="newPassword" name="new_password" minlength="6" required placeholder="••••••••••••" autocomplete="new-password">
                </div>

                <div class="form-group">
                  <label for="confirmNewPassword">Re-enter New Password</label>
                  <input type="password" id="confirmNewPassword" name="confirm_new_password" minlength="6" required placeholder="••••••••••••" autocomplete="new-password">
                  <small id="newPasswordMatchNotice" style="display: none; font-size: 0.8rem; margin-top: 4px;"></small>
                </div>

                <button type="submit" name="change_password" class="cyber-btn primary-glow" style="width: 100%; justify-content: center; margin-top: 10px;">
                  Update Password
                </button>
              </form>
            </div>
          </div>
        </div>

        <!-- TAB 6: SECURITY HEALTH AUDIT DEEP-DIVE -->
        <div id="tab-health" class="tab-pane <?php echo ($activeTab === 'health') ? 'active' : ''; ?>">
          <div class="section-header">
            <div>
              <h2>Interactive Vault Security Health Audit</h2>
              <p style="color: var(--text-muted); font-size: 0.9rem;">Comprehensive 5-Pillar Cryptographic &amp; Protocol Verification Engine</p>
            </div>
            <div style="display: flex; gap: 10px;">
              <button type="button" class="cyber-btn secondary btn-sm" onclick="window.print()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Print Audit Report
              </button>
              <a href="dashboard.php" class="cyber-btn primary-glow btn-sm">
                Refresh Live Audit
              </a>
            </div>
          </div>

          <!-- Top Audit Score & Status Banner -->
          <div class="glass-card" style="padding: 24px; margin-top: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px;">
            <div style="display: flex; align-items: center; gap: 20px;">
              <div class="health-gauge-box" style="min-width: 130px; padding: 10px;">
                <svg class="health-ring-svg" viewBox="0 0 120 120" width="100" height="100">
                  <circle class="health-ring-bg" cx="60" cy="60" r="50" stroke="rgba(255,255,255,0.08)" stroke-width="10" fill="none" />
                  <circle class="health-ring-meter" cx="60" cy="60" r="50" stroke="<?php echo $vaultHealth['rating_color']; ?>" stroke-width="10" fill="none"
                          stroke-linecap="round" stroke-dasharray="314.16" stroke-dashoffset="<?php echo round(314.16 * (1 - ($vaultHealth['score'] / 100)), 2); ?>" />
                  <text x="60" y="54" text-anchor="middle" dominant-baseline="central" class="health-ring-text-num" font-size="22"><?php echo $vaultHealth['score']; ?>%</text>
                  <text x="60" y="74" text-anchor="middle" dominant-baseline="central" class="health-ring-text-lbl" font-size="9" fill="var(--text-muted)">OVERALL</text>
                </svg>
              </div>
              <div>
                <div style="display: flex; align-items: center; gap: 10px;">
                  <h3 style="margin: 0; font-size: 1.2rem;">System Posture: <?php echo $vaultHealth['rating_label']; ?></h3>
                  <span class="health-rating-pill <?php echo $vaultHealth['rating_badge']; ?>">
                    <?php echo $vaultHealth['score']; ?> / 100 POINTS
                  </span>
                </div>
                <p style="color: var(--text-muted); font-size: 0.88rem; margin-top: 8px; max-width: 650px;">
                  This health score is continuously calculated across 5 security vectors: Master password entropy (20 pts), Two-factor authentication (20 pts), Beneficiary mapping coverage (20 pts), Dead man switch heartbeat freshness (20 pts), and Offline cold backup currency (20 pts).
                </p>
              </div>
            </div>
          </div>

          <!-- Deep-Dive 5 Pillars Breakdown Table/Cards -->
          <h3 style="margin-top: 30px; margin-bottom: 14px; font-size: 1.05rem; letter-spacing: 0.5px;">SECURITY PILLARS DEEP-DIVE</h3>
          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 18px;">
            
            <!-- Pillar 1 -->
            <div class="glass-card" style="padding: 20px;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <h4 style="font-size: 0.95rem; margin: 0; display: flex; align-items: center; gap: 8px;">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#00f0ff" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                  1. Master Password Entropy
                </h4>
                <span class="pillar-pts-badge <?php echo $vaultHealth['metrics']['entropy']['status']; ?>">
                  <?php echo $vaultHealth['metrics']['entropy']['score']; ?>/20 PTS
                </span>
              </div>
              <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 12px;">
                Evaluated Strength: <strong style="color: var(--text-main);"><?php echo $vaultHealth['metrics']['entropy']['value']; ?></strong> (NIST SP 800-63B pool criteria).
              </p>
              <div style="background: rgba(0,0,0,0.3); border-radius: 8px; padding: 10px; font-size: 0.78rem; color: var(--text-muted); line-height: 1.4;">
                A score of 65+ bits prevents offline hashcat/rainbow-table brute forcing against PBKDF2/Argon2 key derivation.
              </div>
              <div style="margin-top: 14px;">
                <a href="<?php echo $vaultHealth['metrics']['entropy']['action_url']; ?>" class="cyber-btn secondary btn-xs" style="width: 100%; justify-content: center;">
                  Update Master Password
                </a>
              </div>
            </div>

            <!-- Pillar 2 -->
            <div class="glass-card" style="padding: 20px;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <h4 style="font-size: 0.95rem; margin: 0; display: flex; align-items: center; gap: 8px;">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#00f0ff" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                  2. Two-Factor Authentication
                </h4>
                <span class="pillar-pts-badge <?php echo $vaultHealth['metrics']['two_factor']['status']; ?>">
                  <?php echo $vaultHealth['metrics']['two_factor']['score']; ?>/20 PTS
                </span>
              </div>
              <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 12px;">
                Status: <strong style="color: var(--text-main);"><?php echo $vaultHealth['metrics']['two_factor']['value']; ?></strong> (RFC 6238 TOTP Standard).
              </p>
              <div style="background: rgba(0,0,0,0.3); border-radius: 8px; padding: 10px; font-size: 0.78rem; color: var(--text-muted); line-height: 1.4;">
                Eliminates the single point of failure by requiring dynamic 6-digit rolling codes and emergency offline recovery keys.
              </div>
              <div style="margin-top: 14px;">
                <a href="<?php echo $vaultHealth['metrics']['two_factor']['action_url']; ?>" class="cyber-btn secondary btn-xs" style="width: 100%; justify-content: center;">
                  Configure 2FA Settings
                </a>
              </div>
            </div>

            <!-- Pillar 3 -->
            <div class="glass-card" style="padding: 20px;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <h4 style="font-size: 0.95rem; margin: 0; display: flex; align-items: center; gap: 8px;">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#00f0ff" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                  3. Beneficiary Mapping Coverage
                </h4>
                <span class="pillar-pts-badge <?php echo $vaultHealth['metrics']['coverage']['status']; ?>">
                  <?php echo $vaultHealth['metrics']['coverage']['score']; ?>/20 PTS
                </span>
              </div>
              <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 12px;">
                Coverage Ratio: <strong style="color: var(--text-main);"><?php echo $vaultHealth['metrics']['coverage']['value']; ?></strong>.
              </p>
              <div style="background: rgba(0,0,0,0.3); border-radius: 8px; padding: 10px; font-size: 0.78rem; color: var(--text-muted); line-height: 1.4;">
                Any secret without a designated trustee is permanently orphaned when protocol triggers. 100% assignment is required for full points.
              </div>
              <div style="margin-top: 14px;">
                <a href="<?php echo $vaultHealth['metrics']['coverage']['action_url']; ?>" class="cyber-btn secondary btn-xs" style="width: 100%; justify-content: center;">
                  Manage Secret Assignments
                </a>
              </div>
            </div>

            <!-- Pillar 4 -->
            <div class="glass-card" style="padding: 20px;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <h4 style="font-size: 0.95rem; margin: 0; display: flex; align-items: center; gap: 8px;">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#00f0ff" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                  4. Switch Protocol Freshness
                </h4>
                <span class="pillar-pts-badge <?php echo $vaultHealth['metrics']['freshness']['status']; ?>">
                  <?php echo $vaultHealth['metrics']['freshness']['score']; ?>/20 PTS
                </span>
              </div>
              <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 12px;">
                Interval Status: <strong style="color: var(--text-main);"><?php echo $vaultHealth['metrics']['freshness']['value']; ?></strong>.
              </p>
              <div style="background: rgba(0,0,0,0.3); border-radius: 8px; padding: 10px; font-size: 0.78rem; color: var(--text-muted); line-height: 1.4;">
                Penalizes check-in frequencies set to excessively long intervals (&gt;30 days) and heartbeats older than 14 days.
              </div>
              <div style="margin-top: 14px;">
                <a href="<?php echo $vaultHealth['metrics']['freshness']['action_url']; ?>" class="cyber-btn secondary btn-xs" style="width: 100%; justify-content: center;">
                  Adjust Switch Frequency
                </a>
              </div>
            </div>

            <!-- Pillar 5 -->
            <div class="glass-card" style="padding: 20px;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <h4 style="font-size: 0.95rem; margin: 0; display: flex; align-items: center; gap: 8px;">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#00f0ff" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                  5. Offline Cold Backup
                </h4>
                <span class="pillar-pts-badge <?php echo $vaultHealth['metrics']['backup']['status']; ?>">
                  <?php echo $vaultHealth['metrics']['backup']['score']; ?>/20 PTS
                </span>
              </div>
              <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 12px;">
                Backup Freshness: <strong style="color: var(--text-main);"><?php echo $vaultHealth['metrics']['backup']['value']; ?></strong>.
              </p>
              <div style="background: rgba(0,0,0,0.3); border-radius: 8px; padding: 10px; font-size: 0.78rem; color: var(--text-muted); line-height: 1.4;">
                Requires an encrypted offline JSON archive generated within the last 30 days to defend against cloud outage or hardware failure.
              </div>
              <div style="margin-top: 14px;">
                <a href="<?php echo $vaultHealth['metrics']['backup']['action_url']; ?>" class="cyber-btn secondary btn-xs" style="width: 100%; justify-content: center;">
                  Generate Offline Backup
                </a>
              </div>
            </div>

          </div>

          <!-- INTERACTIVE "WHAT-IF" HEALTH SCORE SIMULATOR -->
          <div class="glass-card" style="padding: 26px; margin-top: 30px; border: 1px solid rgba(0, 240, 255, 0.25);">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; margin-bottom: 18px;">
              <div>
                <h3 style="font-size: 1.1rem; margin: 0; letter-spacing: 0.5px;">INTERACTIVE WHAT-IF HEALTH SIMULATOR</h3>
                <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 4px;">
                  Toggle theoretical optimizations below to see real-time impact on your vault health score.
                </p>
              </div>
              <div style="display: flex; align-items: center; gap: 14px; background: rgba(0,0,0,0.4); padding: 8px 16px; border-radius: 12px;">
                <span style="font-size: 0.85rem; color: var(--text-muted);">Simulated Score:</span>
                <span id="simulatedScoreText" style="font-size: 1.3rem; font-weight: 800; color: #10b981;"><?php echo $vaultHealth['score']; ?>%</span>
                <span id="simulatedBadge" class="health-rating-pill <?php echo $vaultHealth['rating_badge']; ?>" style="font-size: 0.7rem; padding: 2px 8px;">
                  <?php echo $vaultHealth['rating_label']; ?>
                </span>
              </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 14px;">
              <label style="display: flex; align-items: center; gap: 12px; background: rgba(2, 6, 23, 0.6); padding: 12px; border-radius: 10px; cursor: pointer; border: 1px solid rgba(255,255,255,0.06);">
                <input type="checkbox" id="sim_entropy" onchange="runHealthSimulation()" <?php echo ($vaultHealth['metrics']['entropy']['score'] >= 20) ? 'checked' : ''; ?> style="width: 18px; height: 18px; accent-color: var(--cyber-blue);">
                <div>
                  <div style="font-size: 0.88rem; font-weight: 600;">Upgrade Password to 65+ Bits Entropy</div>
                  <div style="font-size: 0.75rem; color: var(--text-muted);">+<?php echo (20 - $vaultHealth['metrics']['entropy']['score']); ?> pts potential gain</div>
                </div>
              </label>

              <label style="display: flex; align-items: center; gap: 12px; background: rgba(2, 6, 23, 0.6); padding: 12px; border-radius: 10px; cursor: pointer; border: 1px solid rgba(255,255,255,0.06);">
                <input type="checkbox" id="sim_2fa" onchange="runHealthSimulation()" <?php echo ($vaultHealth['metrics']['two_factor']['score'] >= 20) ? 'checked' : ''; ?> style="width: 18px; height: 18px; accent-color: var(--cyber-blue);">
                <div>
                  <div style="font-size: 0.88rem; font-weight: 600;">Activate 2FA TOTP Hardware/App</div>
                  <div style="font-size: 0.75rem; color: var(--text-muted);">+<?php echo (20 - $vaultHealth['metrics']['two_factor']['score']); ?> pts potential gain</div>
                </div>
              </label>

              <label style="display: flex; align-items: center; gap: 12px; background: rgba(2, 6, 23, 0.6); padding: 12px; border-radius: 10px; cursor: pointer; border: 1px solid rgba(255,255,255,0.06);">
                <input type="checkbox" id="sim_coverage" onchange="runHealthSimulation()" <?php echo ($vaultHealth['metrics']['coverage']['score'] >= 20) ? 'checked' : ''; ?> style="width: 18px; height: 18px; accent-color: var(--cyber-blue);">
                <div>
                  <div style="font-size: 0.88rem; font-weight: 600;">Assign Trustees to 100% of Records</div>
                  <div style="font-size: 0.75rem; color: var(--text-muted);">+<?php echo (20 - $vaultHealth['metrics']['coverage']['score']); ?> pts potential gain</div>
                </div>
              </label>

              <label style="display: flex; align-items: center; gap: 12px; background: rgba(2, 6, 23, 0.6); padding: 12px; border-radius: 10px; cursor: pointer; border: 1px solid rgba(255,255,255,0.06);">
                <input type="checkbox" id="sim_freshness" onchange="runHealthSimulation()" <?php echo ($vaultHealth['metrics']['freshness']['score'] >= 20) ? 'checked' : ''; ?> style="width: 18px; height: 18px; accent-color: var(--cyber-blue);">
                <div>
                  <div style="font-size: 0.88rem; font-weight: 600;">Set Frequency &le; 30d &amp; Recent Pulse</div>
                  <div style="font-size: 0.75rem; color: var(--text-muted);">+<?php echo (20 - $vaultHealth['metrics']['freshness']['score']); ?> pts potential gain</div>
                </div>
              </label>

              <label style="display: flex; align-items: center; gap: 12px; background: rgba(2, 6, 23, 0.6); padding: 12px; border-radius: 10px; cursor: pointer; border: 1px solid rgba(255,255,255,0.06);">
                <input type="checkbox" id="sim_backup" onchange="runHealthSimulation()" <?php echo ($vaultHealth['metrics']['backup']['score'] >= 20) ? 'checked' : ''; ?> style="width: 18px; height: 18px; accent-color: var(--cyber-blue);">
                <div>
                  <div style="font-size: 0.88rem; font-weight: 600;">Export Cold Offline Backup Today</div>
                  <div style="font-size: 0.75rem; color: var(--text-muted);">+<?php echo (20 - $vaultHealth['metrics']['backup']['score']); ?> pts potential gain</div>
                </div>
              </label>
            </div>
          </div>
        </div>

      </div>
    </main>
  </div>

  <!-- MODAL: ADD / EDIT VAULT ITEM -->
  <dialog id="vaultModal">
    <div class="modal-header">
      <h3 id="modalTitle">Add New Vault Secret</h3>
      <button class="close-btn" onclick="document.getElementById('vaultModal').close()">&times;</button>
    </div>
    <form id="vaultForm" action="backend/vault.php" method="POST" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
      <input type="hidden" id="vaultId" name="vault_id" value="">
      <input type="hidden" id="isClientEncrypted" name="is_client_encrypted" value="1">
      <input type="hidden" id="clientIv" name="client_iv" value="">
      <input type="hidden" id="clientTag" name="client_tag" value="">

      <div class="form-group">
        <label for="vTitle">Title / Account Name *</label>
        <input type="text" id="vTitle" name="title" placeholder="e.g. Master Crypto Seed / Family Will" required>
      </div>

      <div class="form-group">
        <label for="vCategory">Category</label>
        <select id="vCategory" name="category">
          <option value="Financial">Financial / Banking</option>
          <option value="Credentials">Credentials / Logins</option>
          <option value="Legal">Legal and Wills</option>
          <option value="Crypto">Crypto and Seeds</option>
          <option value="Personal">Personal Messages</option>
        </select>
      </div>

      <div class="form-group">
        <label for="vSecret">Secret Message / Password / Seed Words *</label>
        <textarea id="vSecret" name="secret" rows="4" placeholder="Enter sensitive secret to be encrypted with AES-256-GCM..." required></textarea>
      </div>

      <!-- File Attachment Input -->
      <div class="form-group">
        <label for="vFile">Attach Document / File (PDF, Image, Certificate up to 25MB)</label>
        <input type="file" id="vFile" name="attachment">
        <small id="existingFileNotice" style="display: none; color: var(--cyber-blue); margin-top: 4px;"></small>
      </div>

      <div class="form-group">
        <label for="vNotes">Special Instructions for Beneficiaries</label>
        <textarea id="vNotes" name="notes" rows="2" placeholder="e.g. Access instructions, 2FA backup codes location, lawyer contact"></textarea>
      </div>

      <?php if (!empty($nominees)): ?>
        <div class="form-group">
          <label>Assign to Specific Beneficiaries (Leave unchecked for All):</label>
          <div style="display: flex; flex-direction: column; gap: 8px; max-height: 120px; overflow-y: auto; background: rgba(0,0,0,0.3); padding: 10px; border-radius: 8px;">
            <?php foreach ($nominees as $n): ?>
              <label style="display: flex; align-items: center; gap: 8px; font-weight: normal; cursor: pointer;">
                <input type="checkbox" name="nominee_ids[]" value="<?php echo $n['id']; ?>">
                <span><?php echo htmlspecialchars($n['name']); ?> (<?php echo htmlspecialchars($n['relation']); ?>)</span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 20px;">
        <button type="button" class="cyber-btn secondary" onclick="document.getElementById('vaultModal').close()">Cancel</button>
        <button type="submit" id="vaultSubmitBtn" name="save_vault" class="cyber-btn primary-glow">Secure and Encrypt</button>
      </div>
    </form>
  </dialog>

  <!-- MODAL: ADD NOMINEE -->
  <dialog id="nomineeModal">
    <div class="modal-header">
      <h3>Designate Beneficiary</h3>
      <button class="close-btn" onclick="document.getElementById('nomineeModal').close()">&times;</button>
    </div>
    <form action="backend/nominee.php" method="POST">
      <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

      <div class="form-group">
        <label for="nName">Full Legal Name *</label>
        <input type="text" id="nName" name="name" placeholder="e.g. Jane Doe" required>
      </div>

      <div class="form-group">
        <label for="nEmail">Email Address *</label>
        <input type="email" id="nEmail" name="email" placeholder="jane@example.com" required>
      </div>

      <div class="form-group">
        <label for="nPhone">Phone Number (Optional)</label>
        <input type="tel" id="nPhone" name="phone" placeholder="+1 555-0192">
      </div>

      <div class="form-group">
        <label for="nRelation">Relation</label>
        <select id="nRelation" name="relation">
          <option value="Spouse">Spouse / Partner</option>
          <option value="Child">Child</option>
          <option value="Parent">Parent</option>
          <option value="Sibling">Sibling</option>
          <option value="Lawyer / Trustee">Lawyer / Trustee</option>
          <option value="Friend">Trusted Friend</option>
          <option value="Other">Other</option>
        </select>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 20px;">
        <button type="button" class="cyber-btn secondary" onclick="document.getElementById('nomineeModal').close()">Cancel</button>
        <button type="submit" name="add_nominee" class="cyber-btn primary-glow">Add Beneficiary</button>
      </div>
    </form>
  </dialog>

  <!-- Toast Notification Container -->
  <div id="toast-container"></div>

  <!-- Scripts -->
  <script src="assets/js/crypto-zk.js"></script>
  <script src="assets/js/bg3d.js"></script>
  <script src="assets/js/vault3d.js"></script>
  <script>
    const USER_EMAIL = "<?php echo htmlspecialchars($user['email']); ?>";

    // Initialize 3D Holographic Vault in Sidebar & Check ZK Key
    document.addEventListener("DOMContentLoaded", async () => {
      const status = '<?php echo $switchStatus['status']; ?>';
      window.CyberVault = new CyberVaultScene('vault-3d-canvas-container', status);

      // Check Zero-Knowledge session key
      if (window.DLVCrypto && window.DLVCrypto.isSupported()) {
        const existingKey = await window.DLVCrypto.loadKeyFromSession(USER_EMAIL);
        updateZKStatus(!!existingKey);
      }

      // Audio Toggle Button
      const soundBtn = document.getElementById('sound-toggle-btn');
      if (soundBtn) {
        soundBtn.addEventListener('click', () => {
          const isEnabled = window.VaultAudioInstance.toggle();
          soundBtn.innerText = isEnabled ? 'Sound: ON' : 'Sound: OFF';
          showToast(isEnabled ? 'Sound effects enabled' : 'Sound muted');
        });
      }
    });

    // Zero-Knowledge Key Management
    async function ensureVaultKey() {
      if (!window.DLVCrypto || !window.DLVCrypto.isSupported()) {
        alert('Web Crypto API is not supported in this browser.');
        return null;
      }

      let key = await window.DLVCrypto.loadKeyFromSession(USER_EMAIL);
      if (key) {
        updateZKStatus(true);
        return key;
      }

      const pwd = prompt("ZERO-KNOWLEDGE AUTHENTICATION:\nEnter your Master Password to derive your local client-side cryptographic key:");
      if (!pwd) return null;

      try {
        key = await window.DLVCrypto.deriveKeyFromPassword(pwd, USER_EMAIL);
        await window.DLVCrypto.saveKeyToSession(USER_EMAIL, key);
        updateZKStatus(true);
        showToast('Zero-Knowledge key unlocked in browser memory');
        return key;
      } catch (err) {
        showToast('Failed to derive key: ' + err.message);
        return null;
      }
    }

    function updateZKStatus(active) {
      const badge = document.getElementById('zk-status-badge');
      if (!badge) return;
      if (active) {
        badge.className = 'status-badge success';
        badge.style.background = 'rgba(16, 185, 129, 0.15)';
        badge.style.color = 'var(--cyber-green)';
        badge.style.border = '1px solid rgba(16, 185, 129, 0.4)';
        badge.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> ZERO-KNOWLEDGE ACTIVE';
      } else {
        badge.className = 'status-badge warning';
        badge.style.background = 'rgba(245, 158, 11, 0.15)';
        badge.style.color = '#f59e0b';
        badge.style.border = '1px solid rgba(245, 158, 11, 0.4)';
        badge.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/></svg> KEY LOCKED (CLICK TO UNLOCK)';
      }
    }

    // Reveal / Mask Secret with Client-Side Decryption
    async function toggleSecret(id) {
      const elem = document.getElementById(`secret-text-${id}`);
      const btnLbl = document.getElementById(`toggle-lbl-${id}`);
      const box = document.getElementById(`secret-box-${id}`);
      const isMasked = elem.getAttribute('data-masked') === 'true';

      if (!isMasked) {
        elem.innerText = '••••••••••••••••';
        elem.setAttribute('data-masked', 'true');
        btnLbl.innerText = 'Reveal';
        window.VaultAudioInstance.click();
        return;
      }

      const isClient = box.getAttribute('data-is-client') === '1';
      if (!isClient) {
        // Fallback for legacy items encrypted server-side
        const fallback = box.getAttribute('data-fallback') || '';
        elem.innerText = fallback;
        elem.setAttribute('data-masked', 'false');
        btnLbl.innerText = 'Hide';
        window.VaultAudioInstance.unlock();
        return;
      }

      const ciphertext = box.getAttribute('data-ciphertext');
      const iv = box.getAttribute('data-iv');
      const tag = box.getAttribute('data-tag');

      const key = await ensureVaultKey();
      if (!key) return;

      try {
        const decrypted = await window.DLVCrypto.decryptText(ciphertext, iv, tag, key);
        elem.innerText = decrypted;
        elem.setAttribute('data-masked', 'false');
        btnLbl.innerText = 'Hide';
        window.VaultAudioInstance.unlock();
      } catch (err) {
        showToast('Decryption failed: Incorrect master key');
      }
    }

    // Copy Decrypted Secret to Clipboard
    async function copySecret(id) {
      const box = document.getElementById(`secret-box-${id}`);
      const isClient = box.getAttribute('data-is-client') === '1';

      if (!isClient) {
        const fallback = box.getAttribute('data-fallback') || '';
        copyToClipboard(fallback, 'Secret copied to clipboard');
        return;
      }

      const ciphertext = box.getAttribute('data-ciphertext');
      const iv = box.getAttribute('data-iv');
      const tag = box.getAttribute('data-tag');

      const key = await ensureVaultKey();
      if (!key) return;

      try {
        const decrypted = await window.DLVCrypto.decryptText(ciphertext, iv, tag, key);
        copyToClipboard(decrypted, 'Decrypted secret copied to clipboard');
      } catch (err) {
        showToast('Decryption failed: Incorrect master key');
      }
    }

    // Download & Decrypt File Client-Side
    async function downloadFile(vaultId, origFileName, isClient) {
      if (!isClient) {
        window.location.href = `backend/download.php?id=${vaultId}`;
        return;
      }

      const key = await ensureVaultKey();
      if (!key) return;

      showToast('Decrypting file locally with Web Crypto API...');
      try {
        const resp = await fetch(`backend/download.php?id=${vaultId}`);
        if (!resp.ok) throw new Error('Download failed');
        const encryptedBuffer = await resp.arrayBuffer();

        const decryptedBlob = await window.DLVCrypto.decryptFile(encryptedBuffer, key);
        const blobUrl = URL.createObjectURL(decryptedBlob);
        const a = document.createElement('a');
        a.href = blobUrl;
        a.download = origFileName;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(blobUrl);
        showToast('File decrypted and saved successfully');
        window.VaultAudioInstance.download();
      } catch (err) {
        showToast('File decryption failed: ' + err.message);
      }
    }

    // Copy to Clipboard Utility
    function copyToClipboard(text, successMsg) {
      navigator.clipboard.writeText(text).then(() => {
        showToast(successMsg);
        window.VaultAudioInstance.click();
      }).catch(() => {
        showToast('Failed to copy to clipboard');
      });
    }

    // Toast Notification System
    function showToast(msg) {
      const container = document.getElementById('toast-container');
      const toast = document.createElement('div');
      toast.className = 'toast';
      toast.innerHTML = `<span>${msg}</span>`;
      container.appendChild(toast);
      setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(10px)';
        setTimeout(() => toast.remove(), 300);
      }, 3000);
    }

    // Modal Handlers
    function openAddVaultModal() {
      document.getElementById('modalTitle').innerText = 'Add New Vault Secret';
      document.getElementById('vaultId').value = '';
      document.getElementById('vTitle').value = '';
      document.getElementById('vCategory').value = 'Credentials';
      document.getElementById('vSecret').value = '';
      document.getElementById('vSecret').placeholder = 'Enter sensitive secret to be encrypted with AES-256-GCM...';
      document.getElementById('vSecret').required = true;
      document.getElementById('vNotes').value = '';
      document.getElementById('vFile').value = '';
      document.getElementById('isClientEncrypted').value = '1';
      document.getElementById('clientIv').value = '';
      document.getElementById('clientTag').value = '';
      document.getElementById('existingFileNotice').style.display = 'none';
      document.getElementById('vaultSubmitBtn').name = 'save_vault';
      document.getElementById('vaultSubmitBtn').innerText = 'Secure and Encrypt';
      document.getElementById('vaultModal').showModal();
    }

    function openEditVaultModal(item) {
      document.getElementById('modalTitle').innerText = 'Edit Vault Record';
      document.getElementById('vaultId').value = item.id;
      document.getElementById('vTitle').value = item.title;
      document.getElementById('vCategory').value = item.category;
      document.getElementById('vSecret').value = '';
      document.getElementById('vSecret').placeholder = 'Leave blank to retain existing encrypted secret';
      document.getElementById('vSecret').required = false;
      document.getElementById('vNotes').value = item.notes || '';
      document.getElementById('vFile').value = '';
      document.getElementById('isClientEncrypted').value = '1';
      document.getElementById('clientIv').value = '';
      document.getElementById('clientTag').value = '';

      const notice = document.getElementById('existingFileNotice');
      if (item.file_name) {
        notice.innerText = `Current attachment: ${item.file_name} (Upload new file to replace)`;
        notice.style.display = 'block';
      } else {
        notice.style.display = 'none';
      }

      document.getElementById('vaultSubmitBtn').name = 'update_vault';
      document.getElementById('vaultSubmitBtn').innerText = 'Save Changes';
      document.getElementById('vaultModal').showModal();
    }

    // Client-Side Zero-Knowledge Encryption Interceptor on Form Submission
    const vaultForm = document.getElementById('vaultForm');
    if (vaultForm) {
      vaultForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        const secretElem = document.getElementById('vSecret');
        const plaintext = secretElem.value.trim();
        const vaultId = document.getElementById('vaultId').value;

        // If editing without changing the secret, submit directly
        if (vaultId && !plaintext) {
          vaultForm.submit();
          return;
        }

        if (!plaintext) {
          alert('Please enter secret content to encrypt.');
          return;
        }

        const submitBtn = document.getElementById('vaultSubmitBtn');
        const originalText = submitBtn.innerText;
        submitBtn.disabled = true;
        submitBtn.innerText = 'Encrypting with Web Crypto API...';

        try {
          const key = await ensureVaultKey();
          if (!key) {
            submitBtn.disabled = false;
            submitBtn.innerText = originalText;
            return;
          }

          // 1. Client-Side Text Encryption (AES-256-GCM)
          const enc = await window.DLVCrypto.encryptText(plaintext, key);
          document.getElementById('isClientEncrypted').value = '1';
          document.getElementById('clientIv').value = enc.iv;
          document.getElementById('clientTag').value = enc.tag;
          secretElem.value = enc.ciphertext; // Plaintext NEVER leaves browser!

          // 2. Client-Side File Encryption (if file attached)
          const fileElem = document.getElementById('vFile');
          if (fileElem && fileElem.files && fileElem.files.length > 0) {
            submitBtn.innerText = 'Encrypting File Locally...';
            const originalFile = fileElem.files[0];
            const encryptedFile = await window.DLVCrypto.encryptFile(originalFile, key);
            const dt = new DataTransfer();
            dt.items.add(encryptedFile);
            fileElem.files = dt.files;
          }

          submitBtn.innerText = 'Transmitting Ciphertext...';
          vaultForm.submit();
        } catch (err) {
          alert('Zero-Knowledge encryption error: ' + err.message);
          submitBtn.disabled = false;
          submitBtn.innerText = originalText;
        }
      });
    }

    // Search and Category Filtering
    function filterVaultItems() {
      const q = document.getElementById('vaultSearch').value.toLowerCase();
      const cat = document.getElementById('categoryFilter').value;
      const cards = document.querySelectorAll('.vault-item-card');

      cards.forEach(card => {
        const itemCat = card.getAttribute('data-category');
        const itemTitle = card.getAttribute('data-title');
        const itemNotes = card.getAttribute('data-notes');

        const matchCat = (cat === 'ALL' || itemCat === cat);
        const matchSearch = itemTitle.includes(q) || itemNotes.includes(q);

        if (matchCat && matchSearch) {
          card.style.display = 'flex';
        } else {
          card.style.display = 'none';
        }
      });
    }

    // Change Password Real-Time Match Validation
    const newPwd = document.getElementById('newPassword');
    const confirmNewPwd = document.getElementById('confirmNewPassword');
    const newNotice = document.getElementById('newPasswordMatchNotice');
    const changeForm = document.getElementById('changePasswordForm');

    if (newPwd && confirmNewPwd && newNotice && changeForm) {
      function checkNewPasswordMatch() {
        if (!confirmNewPwd.value) {
          newNotice.style.display = 'none';
          return true;
        }
        if (newPwd.value === confirmNewPwd.value) {
          newNotice.innerText = 'Passwords match.';
          newNotice.style.color = '#10b981';
          newNotice.style.display = 'block';
          return true;
        } else {
          newNotice.innerText = 'Passwords do not match.';
          newNotice.style.color = '#ef4444';
          newNotice.style.display = 'block';
          return false;
        }
      }

      newPwd.addEventListener('input', checkNewPasswordMatch);
      confirmNewPwd.addEventListener('input', checkNewPasswordMatch);

      changeForm.addEventListener('submit', (e) => {
        if (newPwd.value !== confirmNewPwd.value) {
          e.preventDefault();
          checkNewPasswordMatch();
          confirmNewPwd.focus();
        }
      });
    }

    // 2FA Recovery Codes Actions
    function copyRecoveryCodes() {
      const container = document.getElementById('recoveryCodesList');
      if (!container) return;
      const codes = Array.from(container.children).map(el => el.innerText.trim()).join('\n');
      navigator.clipboard.writeText(codes).then(() => {
        alert('Emergency recovery codes copied to clipboard.');
      }).catch(() => {
        prompt('Copy your recovery codes below:', codes);
      });
    }

    function downloadRecoveryCodes() {
      const container = document.getElementById('recoveryCodesList');
      if (!container) return;
      const codes = Array.from(container.children).map(el => el.innerText.trim()).join('\r\n');
      const text = "DIGITAL LEGACY VAULT - EMERGENCY RECOVERY BACKUP CODES\r\n"
                 + "Generated on: " + new Date().toISOString() + "\r\n"
                 + "Notice: Each code can only be used once.\r\n"
                 + "--------------------------------------------------------\r\n\r\n"
                 + codes + "\r\n\r\n"
                 + "Keep this document in an encrypted or physical offline safe.";
      const blob = new Blob([text], { type: 'text/plain;charset=utf-8' });
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = 'digital-legacy-vault-recovery-codes.txt';
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
    }

    // Zero-Knowledge Client-Side Decrypted JSON Export
    async function exportZeroKnowledgeJSON() {
      const key = await ensureVaultKey();
      if (!key) return;

      showToast('Decrypting records locally with Web Crypto API...');

      const cards = document.querySelectorAll('.vault-item-card');
      const items = [];

      for (const card of cards) {
        const box = card.querySelector('.secret-display-box');
        if (!box) continue;

        const id = box.getAttribute('data-vault-id');
        const titleElem = card.querySelector('.item-title');
        const title = titleElem ? titleElem.innerText.trim() : '';
        const catElem = card.querySelector('.category-tag');
        const category = catElem ? catElem.innerText.trim() : '';
        const isClient = box.getAttribute('data-is-client') === '1';

        let secret = '';
        if (isClient) {
          const cipher = box.getAttribute('data-ciphertext');
          const iv = box.getAttribute('data-iv');
          const tag = box.getAttribute('data-tag');
          try {
            secret = await window.DLVCrypto.decryptText(cipher, iv, tag, key);
          } catch(e) {
            secret = '[DECRYPTION_FAILED_KEY_MISMATCH]';
          }
        } else {
          secret = box.getAttribute('data-fallback') || '';
        }

        items.push({
          id: id,
          title: title,
          category: category,
          secret: secret
        });
      }

      const exportObj = {
        application: "Digital Legacy Vault",
        architecture: "Zero-Knowledge End-to-End Client Encryption",
        export_date: new Date().toISOString(),
        user_email: USER_EMAIL,
        vault_items: items
      };

      const jsonStr = JSON.stringify(exportObj, null, 2);
      const blob = new Blob([jsonStr], { type: 'application/json' });
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = `digital-legacy-vault-zk-export-${new Date().toISOString().slice(0, 10)}.json`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);

      // Record offline backup timestamp on backend
      fetch('backend/export.php?ping_only=1').catch(e => console.warn(e));

      showToast('Zero-Knowledge JSON archive downloaded');
    }

    // Toggle Health Widget Breakdown Details
    function toggleHealthDetails() {
      const body = document.getElementById('healthOverviewBody');
      const alert = document.getElementById('unassignedGapAlert');
      const recs = document.getElementById('healthRecommendationsContainer');
      const icon = document.getElementById('toggleHealthIcon');
      const text = document.getElementById('toggleHealthText');
      if (!body) return;

      const isHidden = body.style.display === 'none';
      body.style.display = isHidden ? 'flex' : 'none';
      if (alert) alert.style.display = isHidden ? 'block' : 'none';
      if (recs) recs.style.display = isHidden ? 'block' : 'none';
      text.innerText = isHidden ? 'Hide Breakdown' : 'Show Breakdown';
      icon.innerHTML = isHidden 
        ? '<polyline points="18 15 12 9 6 15"/>' 
        : '<polyline points="6 9 12 15 18 9"/>';
      localStorage.setItem('dlv_health_expanded', isHidden ? '1' : '0');
    }

    // Restore user preference
    document.addEventListener('DOMContentLoaded', () => {
      if (localStorage.getItem('dlv_health_expanded') === '0') {
        toggleHealthDetails();
      }
    });

    // What-If Health Simulator
    function runHealthSimulation() {
      let score = 0;
      const simEntropy = document.getElementById('sim_entropy')?.checked;
      score += simEntropy ? 20 : <?php echo intval($vaultHealth['metrics']['entropy']['score']); ?>;

      const sim2fa = document.getElementById('sim_2fa')?.checked;
      score += sim2fa ? 20 : <?php echo intval($vaultHealth['metrics']['two_factor']['score']); ?>;

      const simCov = document.getElementById('sim_coverage')?.checked;
      score += simCov ? 20 : <?php echo intval($vaultHealth['metrics']['coverage']['score']); ?>;

      const simFresh = document.getElementById('sim_freshness')?.checked;
      score += simFresh ? 20 : <?php echo intval($vaultHealth['metrics']['freshness']['score']); ?>;

      const simBackup = document.getElementById('sim_backup')?.checked;
      score += simBackup ? 20 : <?php echo intval($vaultHealth['metrics']['backup']['score']); ?>;

      score = Math.min(100, Math.max(0, score));

      let label = 'CRITICAL VULNERABILITY';
      let badgeClass = 'danger';
      let color = '#ef4444';
      if (score >= 85) {
        label = 'FORTIFIED';
        badgeClass = 'success';
        color = '#10b981';
      } else if (score >= 70) {
        label = 'SECURE';
        badgeClass = 'primary';
        color = '#00f0ff';
      } else if (score >= 50) {
        label = 'ATTENTION NEEDED';
        badgeClass = 'warning';
        color = '#f59e0b';
      }

      const scoreText = document.getElementById('simulatedScoreText');
      const badge = document.getElementById('simulatedBadge');
      if (scoreText) {
        scoreText.innerText = score + '%';
        scoreText.style.color = color;
      }
      if (badge) {
        badge.innerText = label;
        badge.className = 'health-rating-pill ' + badgeClass;
      }
    }
  </script>
</body>
</html>
