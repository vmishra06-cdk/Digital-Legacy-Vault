<?php
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/helpers.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
}

// User Registration
if (isset($_POST['register'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($name) || empty($email) || empty($password) || empty($confirmPassword)) {
        header("Location: ../register.php?error=" . urlencode("All fields including password confirmation are required."));
        exit;
    }

    if ($password !== $confirmPassword) {
        header("Location: ../register.php?error=" . urlencode("Passwords do not match. Please re-enter your password."));
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: ../register.php?error=" . urlencode("Invalid email format."));
        exit;
    }

    if (strlen($password) < 6) {
        header("Location: ../register.php?error=" . urlencode("Password must be at least 6 characters."));
        exit;
    }

    // Check if email already registered
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $checkStmt->bind_param("s", $email);
    $checkStmt->execute();
    if ($checkStmt->get_result()->num_rows > 0) {
        header("Location: ../register.php?error=" . urlencode("Email is already registered. Please log in."));
        exit;
    }

    $passHash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, last_check_in, status) VALUES (?, ?, ?, NOW(), 'active')");
    $stmt->bind_param("sss", $name, $email, $passHash);

    if ($stmt->execute()) {
        $userId = $stmt->insert_id;
        log_audit($conn, $userId, 'REGISTER', "Account created successfully for {$email}");
        header("Location: ../login.php?success=" . urlencode("Account created! Please log in."));
        exit;
    } else {
        header("Location: ../register.php?error=" . urlencode("Failed to create account. Please try again."));
        exit;
    }
}

// User Login
if (isset($_POST['login'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];

        // Automatically record check-in pulse on login
        $upStmt = $conn->prepare("UPDATE users SET last_check_in = NOW() WHERE id = ?");
        $upStmt->bind_param("i", $user['id']);
        $upStmt->execute();

        log_audit($conn, $user['id'], 'LOGIN_SUCCESS', "User logged in from " . ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'));
        header("Location: ../dashboard.php");
        exit;
    } else {
        if ($user) {
            log_audit($conn, $user['id'], 'LOGIN_FAILED', "Failed login attempt with incorrect password");
        }
        header("Location: ../login.php?error=" . urlencode("Invalid email or password."));
        exit;
    }
}

// Change Password
if (isset($_POST['change_password'])) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login.php");
        exit;
    }

    $uid = $_SESSION['user_id'];
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_new_password'] ?? '';

    if (empty($current) || empty($new) || empty($confirm)) {
        header("Location: ../dashboard.php?tab=backup&error=" . urlencode("All password fields are required."));
        exit;
    }

    if ($new !== $confirm) {
        header("Location: ../dashboard.php?tab=backup&error=" . urlencode("New passwords do not match. Please re-enter your new password."));
        exit;
    }

    if (strlen($new) < 6) {
        header("Location: ../dashboard.php?tab=backup&error=" . urlencode("New password must be at least 6 characters."));
        exit;
    }

    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();

    if (!$u || !password_verify($current, $u['password'])) {
        header("Location: ../dashboard.php?tab=backup&error=" . urlencode("Current password is incorrect."));
        exit;
    }

    $hash = password_hash($new, PASSWORD_DEFAULT);
    $up = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $up->bind_param("si", $hash, $uid);
    $up->execute();

    log_audit($conn, $uid, 'PASSWORD_CHANGED', "Master vault password changed successfully");
    header("Location: ../dashboard.php?tab=backup&success=" . urlencode("Master password updated successfully."));
    exit;
}
?>
