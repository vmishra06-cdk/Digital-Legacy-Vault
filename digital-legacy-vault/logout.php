<?php
session_start();
session_unset();
session_destroy();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Logging Out - Digital Legacy Vault</title>
</head>
<body style="background: #070b14; color: #00f0ff; font-family: monospace; display: flex; align-items: center; justify-content: center; height: 100vh;">
  <div>Purging cryptographic session keys...</div>
  <script>
    try {
      sessionStorage.clear();
    } catch(e) {}
    window.location.href = "login.php";
  </script>
</body>
</html>
