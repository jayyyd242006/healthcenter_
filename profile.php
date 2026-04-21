<?php
session_start();
require 'database.php';

if (!isset($_SESSION['patient_logged_in']) || $_SESSION['patient_logged_in'] !== true) {
    header('Location: patient_login.php');
    exit();
}

$patient_id = (int) $_SESSION['patient_id'];
$user_id    = (int) $_SESSION['patient_user_id'];
$success = '';
$error   = '';

// ── Fetch current info ──
$stmt = $conn->prepare("
    SELECT u.full_name, u.email, u.phone,
           p.date_of_birth, p.sex, p.address, p.blood_type,
           p.allergies, p.emergency_contact_name, p.emergency_contact_phone
    FROM users u
    JOIN patients p ON p.user_id = u.user_id
    WHERE u.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$info = $stmt->get_result()->fetch_assoc();

// ── Update profile ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name    = trim($_POST['full_name']);
    $email   = trim($_POST['email']);
    $phone   = trim($_POST['phone'] ?? '');
    $dob     = $_POST['date_of_birth'] ?: null;
    $sex     = $_POST['sex'] ?: null;
    $address = trim($_POST['address'] ?? '');
    $blood   = trim($_POST['blood_type'] ?? '');
    $allerg  = trim($_POST['allergies'] ?? '');
    $ecname  = trim($_POST['emergency_contact_name'] ?? '');
    $ecphone = trim($_POST['emergency_contact_phone'] ?? '');

    if (empty($name) || empty($email)) {
        $error = "Name and email are required.";
    } else {
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE user_id = ?");
        $stmt->bind_param("sssi", $name, $email, $phone, $user_id);
        $stmt->execute();

        $stmt2 = $conn->prepare("
            UPDATE patients SET date_of_birth = ?, sex = ?, address = ?, blood_type = ?,
            allergies = ?, emergency_contact_name = ?, emergency_contact_phone = ?
            WHERE patient_id = ?
        ");
        $stmt2->bind_param("sssssssi", $dob, $sex, $address, $blood, $allerg, $ecname, $ecphone, $patient_id);

        if ($stmt2->execute()) {
            $_SESSION['patient_name'] = $name;
            $info = array_merge($info, compact('name','email','phone','dob','sex','address','blood','allerg','ecname','ecphone'));
            $info['full_name'] = $name;
            $success = "Profile updated successfully.";
        } else {
            $error = "Failed to update profile.";
        }
    }
}

// ── Change password ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new_p   = $_POST['new_password'];
    $conf    = $_POST['confirm_password'];

    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!password_verify($current, $row['password_hash'])) {
        $error = "Current password is incorrect.";
    } elseif (strlen($new_p) < 8) {
        $error = "New password must be at least 8 characters.";
    } elseif ($new_p !== $conf) {
        $error = "Passwords do not match.";
    } else {
        $hash = password_hash($new_p, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
        $stmt->bind_param("si", $hash, $user_id);
        $stmt->execute() ? $success = "Password changed successfully." : $error = "Failed to change password.";
    }
}

$active_page = 'profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Profile — HealthCenter</title>
  <link rel="stylesheet" href="global.css"/>
  <style>
    .profile-grid { display:grid; grid-template-columns:1fr 1fr; gap:24px; }
    @media(max-width:900px){ .profile-grid{ grid-template-columns:1fr; } }
    .profile-card { background:white; border-radius:12px; border:1px solid rgba(0,0,0,0.05); overflow:hidden; margin-bottom:0; }
    .profile-card .card-header { padding:16px 24px; border-bottom:1px solid var(--cream-dark); }
    .profile-card .card-body   { padding:24px; }
    .form-group { margin-bottom:16px; }
    .form-group label { display:block; font-size:0.75rem; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; margin-bottom:7px; }
    .form-group input, .form-group select, .form-group textarea { width:100%; padding:11px 14px; border:1.5px solid var(--cream-dark); border-radius:8px; font-family:inherit; font-size:0.9rem; outline:none; }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color:var(--green-light); box-shadow:0 0 0 3px rgba(30,138,96,0.1); }
    .form-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    .avatar-big { width:72px; height:72px; border-radius:50%; background:var(--green-deep); color:white; display:flex; align-items:center; justify-content:center; font-size:1.6rem; font-weight:700; margin-bottom:16px; }
  </style>
</head>
<body>
<div class="layout">
  <?php include 'patient_sidebar.php'; ?>

  <div class="main">
    <div class="topbar">
      <div>
        <h1>My Profile</h1>
        <p>Manage your personal information and account settings</p>
      </div>
    </div>

    <?php if ($success): ?>
      <div class="alert alert-success" style="margin-bottom:20px;">✅ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-error" style="margin-bottom:20px;">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="profile-grid">

      <!-- PERSONAL INFO -->
      <div class="profile-card">
        <div class="card-header">
          <div class="card-title">👤 Personal Information</div>
          <div class="card-subtitle">Basic details and contact info</div>
        </div>
        <div class="card-body">
          <div class="avatar-big"><?= strtoupper(substr($info['full_name'],0,2)) ?></div>
          <form method="POST">
            <input type="hidden" name="update_profile" value="1"/>
            <div class="form-group">
              <label>Full Name *</label>
              <input type="text" name="full_name" value="<?= htmlspecialchars($info['full_name']) ?>" required/>
            </div>
            <div class="form-group">
              <label>Email Address *</label>
              <input type="email" name="email" value="<?= htmlspecialchars($info['email']) ?>" required/>
            </div>
            <div class="form-group">
              <label>Phone Number</label>
              <input type="tel" name="phone" value="<?= htmlspecialchars($info['phone'] ?? '') ?>" placeholder="09XX XXX XXXX"/>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Date of Birth</label>
                <input type="date" name="date_of_birth" value="<?= $info['date_of_birth'] ?? '' ?>"/>
              </div>
              <div class="form-group">
                <label>Sex</label>
                <select name="sex">
                  <option value="">— Select —</option>
                  <option value="male"   <?= ($info['sex']??'')==='male'   ?'selected':'' ?>>Male</option>
                  <option value="female" <?= ($info['sex']??'')==='female' ?'selected':'' ?>>Female</option>
                  <option value="other"  <?= ($info['sex']??'')==='other'  ?'selected':'' ?>>Other</option>
                </select>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Blood Type</label>
                <select name="blood_type">
                  <option value="">— Select —</option>
                  <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bt): ?>
                    <option value="<?= $bt ?>" <?= ($info['blood_type']??'')===$bt?'selected':'' ?>><?= $bt ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label>Address</label>
                <input type="text" name="address" value="<?= htmlspecialchars($info['address'] ?? '') ?>"/>
              </div>
            </div>
            <div class="form-group">
              <label>Allergies</label>
              <textarea name="allergies" rows="2" placeholder="List any known allergies..."><?= htmlspecialchars($info['allergies'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn-login">Save Profile</button>
          </form>
        </div>
      </div>

      <!-- RIGHT COLUMN -->
      <div style="display:flex; flex-direction:column; gap:24px;">

        <!-- EMERGENCY CONTACT -->
        <div class="profile-card">
          <div class="card-header">
            <div class="card-title">🚨 Emergency Contact</div>
            <div class="card-subtitle">Person to contact in case of emergency</div>
          </div>
          <div class="card-body">
            <form method="POST">
              <input type="hidden" name="update_profile" value="1"/>
              <!-- carry over other fields silently -->
              <input type="hidden" name="full_name"      value="<?= htmlspecialchars($info['full_name']) ?>"/>
              <input type="hidden" name="email"          value="<?= htmlspecialchars($info['email']) ?>"/>
              <input type="hidden" name="phone"          value="<?= htmlspecialchars($info['phone'] ?? '') ?>"/>
              <input type="hidden" name="date_of_birth"  value="<?= $info['date_of_birth'] ?? '' ?>"/>
              <input type="hidden" name="sex"            value="<?= $info['sex'] ?? '' ?>"/>
              <input type="hidden" name="blood_type"     value="<?= $info['blood_type'] ?? '' ?>"/>
              <input type="hidden" name="address"        value="<?= htmlspecialchars($info['address'] ?? '') ?>"/>
              <input type="hidden" name="allergies"      value="<?= htmlspecialchars($info['allergies'] ?? '') ?>"/>
              <div class="form-group">
                <label>Contact Name</label>
                <input type="text" name="emergency_contact_name" value="<?= htmlspecialchars($info['emergency_contact_name'] ?? '') ?>" placeholder="Full name"/>
              </div>
              <div class="form-group">
                <label>Contact Phone</label>
                <input type="tel" name="emergency_contact_phone" value="<?= htmlspecialchars($info['emergency_contact_phone'] ?? '') ?>" placeholder="09XX XXX XXXX"/>
              </div>
              <button type="submit" class="btn-login">Save Contact</button>
            </form>
          </div>
        </div>

        <!-- CHANGE PASSWORD -->
        <div class="profile-card">
          <div class="card-header">
            <div class="card-title">🔒 Change Password</div>
            <div class="card-subtitle">Keep your account secure</div>
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

      </div><!-- /.right column -->
    </div><!-- /.profile-grid -->

  </div>
</div>
</body>
</html>