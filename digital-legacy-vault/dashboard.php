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
                  $decrypted = decrypt_vault_secret($v['secret'], $v['iv'] ?? '', $v['tag'] ?? ''); 
                ?>
                <div class="vault-item-card tilt-3d" data-category="<?php echo htmlspecialchars($v['category']); ?>" data-title="<?php echo strtolower(htmlspecialchars($v['title'])); ?>" data-notes="<?php echo strtolower(htmlspecialchars(($v['notes'] ?? '') . ' ' . ($v['file_name'] ?? ''))); ?>">
                  <div class="item-top">
                    <div>
                      <span class="category-tag"><?php echo htmlspecialchars($v['category']); ?></span>
                      <h4 class="item-title"><?php echo htmlspecialchars($v['title']); ?></h4>
                    </div>
                    <form action="backend/vault.php" method="POST" onsubmit="return confirm('Are you sure you want to permanently delete this secret?');">
                      <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                      <input type="hidden" name="vault_id" value="<?php echo $v['id']; ?>">
                      <button type="submit" name="delete_vault" class="cyber-btn danger btn-sm" title="Delete record" style="padding: 4px 10px;">Delete</button>
                    </form>
                  </div>

                  <!-- Secret Box with Show/Hide and Copy -->
                  <div class="secret-display-box">
                    <div class="secret-display-text" id="secret-text-<?php echo $v['id']; ?>" data-masked="true">••••••••••••••••</div>
                    <div class="secret-actions-row">
                      <button type="button" class="cyber-btn secondary btn-sm" onclick="toggleSecret(<?php echo $v['id']; ?>, '<?php echo htmlspecialchars(addslashes($decrypted)); ?>')">
                        <span id="toggle-lbl-<?php echo $v['id']; ?>">Reveal</span>
                      </button>
                      <button type="button" class="cyber-btn secondary btn-sm" onclick="copyToClipboard('<?php echo htmlspecialchars(addslashes($decrypted)); ?>', 'Secret copied to clipboard')">
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
                      <a href="backend/download.php?id=<?php echo $v['id']; ?>" class="cyber-btn primary-glow btn-sm">
                        Download
                      </a>
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
              <a href="backend/export.php" class="cyber-btn primary-glow">
                Download Vault Archive (.json)
              </a>
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
  <script src="assets/js/bg3d.js"></script>
  <script src="assets/js/vault3d.js"></script>
  <script>
    // Initialize 3D Holographic Vault in Sidebar
    document.addEventListener("DOMContentLoaded", () => {
      const status = '<?php echo $switchStatus['status']; ?>';
      window.CyberVault = new CyberVaultScene('vault-3d-canvas-container', status);

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

    // Reveal / Mask Secret
    function toggleSecret(id, decryptedText) {
      const elem = document.getElementById(`secret-text-${id}`);
      const btnLbl = document.getElementById(`toggle-lbl-${id}`);
      const isMasked = elem.getAttribute('data-masked') === 'true';

      if (isMasked) {
        elem.innerText = decryptedText;
        elem.setAttribute('data-masked', 'false');
        btnLbl.innerText = 'Hide';
        window.VaultAudioInstance.unlock();
      } else {
        elem.innerText = '••••••••••••••••';
        elem.setAttribute('data-masked', 'true');
        btnLbl.innerText = 'Reveal';
        window.VaultAudioInstance.click();
      }
    }

    // Copy to Clipboard
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
      document.getElementById('vSecret').required = true;
      document.getElementById('vNotes').value = '';
      document.getElementById('vFile').value = '';
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
  </script>
</body>
</html>
