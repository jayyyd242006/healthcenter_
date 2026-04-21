<?php
session_start();
require 'database.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit();
}

$admin_id = (int) $_SESSION['admin_id'];
$success  = '';
$error    = '';

// ── Fetch current admin info ──
$stmt = $conn->prepare("SELECT full_name, email, phone FROM users WHERE user_id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

// ── Update profile ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name  = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone'] ?? '');

    if (empty($name) || empty($email)) {
        $error = "Name and email are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    } else {
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE user_id = ?");
        $stmt->bind_param("sssi", $name, $email, $phone, $admin_id);
        if ($stmt->execute()) {
            $_SESSION['admin_name']  = $name;
            $_SESSION['admin_email'] = $email;
            $admin['full_name'] = $name;
            $admin['email']     = $email;
            $admin['phone']     = $phone;
            $success = "Profile updated successfully.";
        } else {
            $error = "Failed to update profile.";
        }
    }
}

// ── Change password ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current  = $_POST['current_password'];
    $new_pass = $_POST['new_password'];
    $confirm  = $_POST['confirm_password'];

    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!password_verify($current, $row['password_hash'])) {
        $error = "Current password is incorrect.";
    } elseif (strlen($new_pass) < 8) {
        $error = "New password must be at least 8 characters.";
    } elseif ($new_pass !== $confirm) {
        $error = "New passwords do not match.";
    } else {
        $hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
        $stmt->bind_param("si", $hash, $admin_id);
        $stmt->execute() ? $success = "Password changed successfully." : $error = "Failed to change password.";
    }
}

$active_page = 'settings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Settings — HealthCenter Admin</title>
  <link rel="stylesheet" href="global.css"/>
  <style>
    .settings-grid { display:grid; grid-template-columns:1fr 1fr; gap:24px; }
    .settings-card { background:white; border-radius:12px; border:1px solid rgba(0,0,0,0.05); overflow:hidden; }
    .settings-card .card-header { padding:16px 24px; border-bottom:1px solid var(--cream-dark); }
    .settings-card .card-body   { padding:24px; }
    .form-group { margin-bottom:16px; }
    .form-group label { display:block; font-size:0.75rem; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; margin-bottom:7px; }
    .form-group input { width:100%; padding:11px 14px; border:1.5px solid var(--cream-dark); border-radius:8px; font-family:inherit; font-size:0.9rem; outline:none; }
    .form-group input:focus { border-color:var(--green-light); box-shadow:0 0 0 3px rgba(30,138,96,0.1); }
    @media(max-width:820px) { .settings-grid { grid-template-columns:1fr; } }
  </style>
</head>
<body>
<div class="admin-layout">
  <?php include 'admin_sidebar.php'; ?>

  <div class="main-content">
    <div class="topbar">
      <div>
        <h1>Settings</h1>
        <p>Manage your account and preferences</p>
      </div>
    </div>

    <?php if ($success): ?>
      <div class="alert alert-success" style="margin-bottom:20px;">✅ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-error" style="margin-bottom:20px;">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="settings-grid">

      <!-- PROFILE -->
      <div class="settings-card">
        <div class="card-header">
          <div class="card-title">👤 Profile Information</div>
          <div class="card-subtitle">Update your name, email and phone</div>
        </div>
        <div class="card-body">
          <form method="POST">
            <input type="hidden" name="update_profile" value="1"/>
            <div class="form-group">
              <label>Full Name</label>
              <input type="text" name="full_name" value="<?= htmlspecialchars($admin['full_name']) ?>" required/>
            </div>
            <div class="form-group">
              <label>Email Address</label>
              <input type="email" name="email" value="<?= htmlspecialchars($admin['email']) ?>" required/>
            </div>
            <div class="form-group">
              <label>Phone Number</label>
              <input type="tel" name="phone" value="<?= htmlspecialchars($admin['phone'] ?? '') ?>" placeholder="09XX XXX XXXX"/>
            </div>
            <button type="submit" class="btn-login">Save Changes</button>
          </form>
        </div>
      </div>

      <!-- PASSWORD -->
      <div class="settings-card">
        <div class="card-header">
          <div class="card-title">🔒 Change Password</div>
          <div class="card-subtitle">Use a strong password with 8+ characters</div>
        </div>
        <div class="card-body">
          <form method="POST">
            <input type="hidden" name="change_password" value="1"/>
            <div class="form-group">
              <label>Current Password</label>
              <input type="password" name="current_password" required placeholder="Enter current password"/>
            </div>
            <div class="form-group">
              <label>New Password</label>
              <input type="password" name="new_password" required placeholder="At least 8 characters"/>
            </div>
            <div class="form-group">
              <label>Confirm New Password</label>
              <input type="password" name="confirm_password" required placeholder="Repeat new password"/>
            </div>
            <button type="submit" class="btn-login">Change Password</button>
          </form>
        </div>
      </div>

    </div>
  </div>
</div>
</body>
</html>