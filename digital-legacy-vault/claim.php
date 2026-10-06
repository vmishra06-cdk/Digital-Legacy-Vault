<?php
require_once __DIR__ . "/backend/db.php";
require_once __DIR__ . "/backend/helpers.php";

$token = trim($_GET['token'] ?? $_POST['claim_token'] ?? '');
$nominee = null;
$vaultOwner = null;
$items = [];
$error = '';
$isReleased = false;

if (!empty($token)) {
    // Look up nominee by token
    $stmt = $conn->prepare("SELECT n.*, u.name as owner_name, u.email as owner_email, u.status as owner_status, u.last_check_in, u.check_in_frequency_days, u.grace_period_days 
                           FROM nominees n 
                           JOIN users u ON n.user_id = u.id 
                           WHERE n.claim_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $nominee = $res->fetch_assoc();
        $switchStatus = get_dead_man_switch_status([
            'status' => $nominee['owner_status'],
            'last_check_in' => $nominee['last_check_in'],
            'check_in_frequency_days' => $nominee['check_in_frequency_days'],
            'grace_period_days' => $nominee['grace_period_days']
        ]);

        $isReleased = $switchStatus['is_triggered'];

        if ($isReleased) {
            // Fetch items assigned to this nominee (or all user's items if not specifically mapped)
            $vStmt = $conn->prepare("
                SELECT DISTINCT v.* 
                FROM vaults v
                LEFT JOIN vault_nominee_access vna ON v.id = vna.vault_id
                WHERE (vna.nominee_id = ? OR NOT EXISTS (SELECT 1 FROM vault_nominee_access WHERE nominee_id = ?)) 
                  AND v.user_id = ?
                ORDER BY v.id DESC
            ");
            $vStmt->bind_param("iii", $nominee['id'], $nominee['id'], $nominee['user_id']);
            $vStmt->execute();
            $items = $vStmt->get_result()->fetch_all(MYSQLI_ASSOC);

            // Update nominee status
            $upN = $conn->prepare("UPDATE nominees SET status = 'claimed' WHERE id = ?");
            $upN->bind_param("i", $nominee['id']);
            $upN->execute();

            log_audit($conn, $nominee['user_id'], 'NOMINEE_CLAIM_ACCESSED', "Nominee {$nominee['name']} accessed decrypted legacy vault items via token");
        }
    } else {
        $error = "Invalid or expired Claim Token. Please verify with the vault owner.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Beneficiary Claim Portal - Digital Legacy Vault</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
</head>
<body class="claim-body">
  <div id="canvas-3d-bg"></div>

  <div class="claim-container glass-card">
    <div class="brand-header">
      <div class="brand-symbol" style="margin-bottom: 12px;">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#00f0ff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
          <path d="M12 8v4"/>
          <path d="M12 16h.01"/>
        </svg>
      </div>
      <h1>Digital Legacy Claim Portal</h1>
      <p class="subtitle">Secure Beneficiary Decryption Gateway</p>
    </div>

    <?php if(!empty($error)): ?>
      <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if(!$nominee): ?>
      <!-- Token Input Form -->
      <form action="claim.php" method="GET" class="claim-form">
        <label for="token">Enter Your Secret Claim Key</label>
        <div class="input-glow-group">
          <input type="text" id="token" name="token" placeholder="e.g. 7f3b890a2c4e..." required autocomplete="off">
        </div>
        <button type="submit" class="cyber-btn primary-glow">Authenticate Claim Key</button>
      </form>
      <div class="back-link" style="margin-top: 20px; text-align: center;">
        <a href="index.php" style="color: var(--text-muted); text-decoration: none; font-size: 0.9rem;">Return to Vault Home</a>
      </div>
    <?php else: ?>

      <!-- Nominee Verified -->
      <div class="nominee-card" style="background: rgba(255,255,255,0.04); padding: 18px; border-radius: 12px; margin-bottom: 20px; border: 1px solid rgba(255,255,255,0.08);">
        <h3>Beneficiary Identified: <span class="highlight" style="color: var(--cyber-blue);"><?php echo htmlspecialchars($nominee['name']); ?></span></h3>
        <p style="margin-top: 6px; font-size: 0.9rem; color: var(--text-muted);">
          Designated Relation: <strong style="color: var(--text-main);"><?php echo htmlspecialchars($nominee['relation']); ?></strong> | 
          Vault Owner: <strong style="color: var(--text-main);"><?php echo htmlspecialchars($nominee['owner_name']); ?></strong> (<?php echo htmlspecialchars($nominee['owner_email']); ?>)
        </p>
      </div>

      <?php if(!$isReleased): ?>
        <!-- Switch NOT Triggered -->
        <div class="status-box status-locked">
          <div class="status-icon" style="margin-bottom: 12px;">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          </div>
          <h2>Vault Locked: Protocol Active</h2>
          <p>The owner's Dead Man's Switch is currently <strong>armed and actively checked in</strong>.</p>
          <div class="info-pill" style="margin: 14px 0; display: inline-block; padding: 6px 16px; border-radius: 20px; background: rgba(245, 158, 11, 0.2); color: #fbbf24; font-weight: 600;">
            Status: <?php echo $switchStatus['label']; ?>
          </div>
          <p class="small-text" style="font-size: 0.85rem; color: var(--text-muted); max-width: 500px; margin: 0 auto 18px auto;">
            Confidential legacy records and files will only be decrypted and released if the owner fails to respond after their configured verification window.
          </p>
          <a href="claim.php" class="cyber-btn secondary">Try Another Key</a>
        </div>
      <?php else: ?>
        <!-- Switch Triggered - SECRETS RELEASED -->
        <div class="status-box status-released">
          <div class="status-icon" style="margin-bottom: 12px;">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg>
          </div>
          <h2>Digital Legacy Released</h2>
          <p>The Dead Man's Switch protocol has completed. Below are the decrypted confidential records and documents entrusted directly to you.</p>
        </div>

        <div class="legacy-items-list">
          <?php if(empty($items)): ?>
            <p class="empty-state" style="text-align: center; color: var(--text-muted); padding: 40px;">No specific records were designated under this key.</p>
          <?php else: ?>
            <?php foreach($items as $item): ?>
              <?php 
                $isClient = !empty($item['is_client_encrypted']);
                $serverSecret = (!$isClient) ? decrypt_vault_secret($item['secret'], $item['iv'] ?? '', $item['tag'] ?? '') : '';
              ?>
              <div class="legacy-item-card">
                <div class="item-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                  <span class="category-tag">
                    <?php echo htmlspecialchars($item['category']); ?>
                  </span>
                  <?php if ($isClient): ?>
                    <span style="font-size: 0.75rem; color: var(--cyber-blue); border: 1px solid rgba(0, 240, 255, 0.3); padding: 2px 6px; border-radius: 4px;">Zero-Knowledge Encrypted</span>
                  <?php endif; ?>
                  <h4><?php echo htmlspecialchars($item['title']); ?></h4>
                </div>
                
                <div class="secret-box"
                     id="claim-box-<?php echo $item['id']; ?>"
                     data-ciphertext="<?php echo htmlspecialchars($item['secret']); ?>"
                     data-iv="<?php echo htmlspecialchars($item['iv'] ?? ''); ?>"
                     data-tag="<?php echo htmlspecialchars($item['tag'] ?? ''); ?>"
                     data-is-client="<?php echo $isClient ? '1' : '0'; ?>">
                  <label style="font-size: 0.8rem; color: var(--text-muted); display: block; margin-bottom: 6px;">Message / Credentials:</label>
                  <pre class="secret-text" id="claim-text-<?php echo $item['id']; ?>"><?php echo htmlspecialchars($serverSecret ?: '•••••••••••••••• (Encrypted Client-Side)'); ?></pre>
                  <?php if ($isClient): ?>
                    <button type="button" class="cyber-btn secondary btn-sm" style="margin-top: 10px;" onclick="decryptClaimSecret(<?php echo $item['id']; ?>)">
                      Decrypt Client-Side
                    </button>
                  <?php endif; ?>
                </div>

                <?php if(!empty($item['file_path'])): ?>
                  <div style="margin-top: 14px; padding: 12px; background: rgba(0, 240, 255, 0.06); border: 1px solid var(--glass-border); border-radius: 8px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                      <strong style="color: var(--cyber-blue); font-size: 0.9rem;">Attached Document:</strong>
                      <span style="font-size: 0.85rem; color: var(--text-main); margin-left: 6px;"><?php echo htmlspecialchars($item['file_name'] ?: 'Encrypted File'); ?></span>
                      <?php if (!empty($item['file_size'])): ?>
                        <small style="color: var(--text-muted); margin-left: 6px;">(<?php echo round($item['file_size'] / 1024, 1); ?> KB)</small>
                      <?php endif; ?>
                    </div>
                    <?php if ($isClient): ?>
                      <button type="button" class="cyber-btn primary-glow btn-sm" onclick="downloadClaimFile(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars(addslashes($item['file_name'])); ?>', '<?php echo urlencode($token); ?>')">
                        Decrypt & Download
                      </button>
                    <?php else: ?>
                      <a href="backend/download.php?id=<?php echo $item['id']; ?>&token=<?php echo urlencode($token); ?>" class="cyber-btn primary-glow btn-sm">
                        Download File
                      </a>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>

                <?php if(!empty($item['notes'])): ?>
                  <div class="notes-box" style="margin-top: 12px;">
                    <label style="font-size: 0.8rem; color: var(--text-muted); display: block; margin-bottom: 4px;">Instructions from Owner:</label>
                    <p style="font-size: 0.9rem; color: var(--text-main);"><?php echo nl2br(htmlspecialchars($item['notes'])); ?></p>
                  </div>
                <?php endif; ?>
                <div class="item-footer" style="margin-top: 14px; border-top: 1px solid rgba(255,255,255,0.06); padding-top: 8px;">
                  <small style="color: var(--text-muted);">Recorded: <?php echo date("M d, Y", strtotime($item['created_at'])); ?></small>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <div class="action-row" style="margin-top: 25px; display: flex; gap: 12px; justify-content: center;">
          <button onclick="window.print()" class="cyber-btn primary-glow">Print Records</button>
          <a href="claim.php" class="cyber-btn secondary">Exit Portal</a>
        </div>
      <?php endif; ?>

    <?php endif; ?>
  </div>

  <script src="assets/js/crypto-zk.js"></script>
  <script src="assets/js/bg3d.js"></script>
  <script>
    let beneficiaryKey = null;

    async function promptBeneficiaryKey() {
      if (beneficiaryKey) return beneficiaryKey;
      const pwd = prompt("Enter the Vault Key or Master Password provided by the account owner:");
      if (!pwd) return null;
      const ownerEmail = "<?php echo htmlspecialchars($nominee['owner_email'] ?? ($nominee['email'] ?? 'legacy')); ?>";
      beneficiaryKey = await window.DLVCrypto.deriveKeyFromPassword(pwd, ownerEmail);
      return beneficiaryKey;
    }

    async function decryptClaimSecret(id) {
      const box = document.getElementById('claim-box-' + id);
      const textElem = document.getElementById('claim-text-' + id);
      const cipher = box.getAttribute('data-ciphertext');
      const iv = box.getAttribute('data-iv');
      const tag = box.getAttribute('data-tag');

      try {
        const key = await promptBeneficiaryKey();
        if (!key) return;
        const dec = await window.DLVCrypto.decryptText(cipher, iv, tag, key);
        textElem.innerText = dec;
      } catch(err) {
        alert("Decryption failed. Please verify the key: " + err.message);
      }
    }

    async function downloadClaimFile(id, fileName, token) {
      try {
        const key = await promptBeneficiaryKey();
        if (!key) return;
        const resp = await fetch(`backend/download.php?id=${id}&token=${token}`);
        if (!resp.ok) throw new Error('Download failed');
        const buffer = await resp.arrayBuffer();
        const decryptedBlob = await window.DLVCrypto.decryptFile(buffer, key);
        const url = URL.createObjectURL(decryptedBlob);
        const a = document.createElement('a');
        a.href = url;
        a.download = fileName;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
      } catch(err) {
        alert("File decryption failed: " + err.message);
      }
    }
  </script>
</body>
</html>
