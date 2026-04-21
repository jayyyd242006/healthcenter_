<?php
session_start();
require 'database.php';

// Redirect if already logged in
if (isset($_SESSION['patient_logged_in']) && $_SESSION['patient_logged_in'] === true) {
    header('Location: patient_dashboard.php');
    exit();
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF check
    if (
        empty($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $error = 'Invalid request. Please try again.';
    } else {

        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please fill in all fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Look up user by email, JOIN patients to get patient_id
            $stmt = $conn->prepare("
                SELECT u.user_id, u.full_name, u.password_hash, p.patient_id
                FROM users u
                INNER JOIN patients p ON p.user_id = u.user_id
                WHERE u.email = ? AND u.role = 'patient' AND u.is_active = 1
                LIMIT 1
            ");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user   = $result->fetch_assoc();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Login successful
                session_regenerate_id(true);
                $_SESSION['patient_logged_in'] = true;
                $_SESSION['patient_id']        = $user['patient_id'];   // patients.patient_id
                $_SESSION['patient_user_id']   = $user['user_id'];      // users.user_id
                $_SESSION['patient_name']      = $user['full_name'];
                $success = 'Login successful! Redirecting…';
            } else {
                $error = 'Invalid email or password. Please try again.';
            }
        }
    }
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Patient Login — HealthCenter</title>
  <link rel="stylesheet" href="global.css"/>
</head>
<body>

<div class="page">

  <!-- LEFT PANEL -->
  <div class="left-panel">
    <div class="left-pattern"></div>

    <div class="brand">
      <div class="brand-icon">🏥</div>
      <div class="brand-name">
        HealthCenter
        <span>Online Appointment System</span>
      </div>
    </div>

    <div class="hero-text">
      <h1>Your health,<br><em>our priority.</em></h1>
      <p>Book appointments, view your records, and manage your healthcare journey — all in one place.</p>
    </div>

    <div class="stat-row">
      <div class="stat">
        <div class="stat-num">500+</div>
        <div class="stat-label">Patients served</div>
      </div>
      <div class="stat">
        <div class="stat-num">15+</div>
        <div class="stat-label">Specialists</div>
      </div>
      <div class="stat">
        <div class="stat-num">5★</div>
        <div class="stat-label">Patient rating</div>
      </div>
    </div>
  </div>

  <!-- RIGHT PANEL -->
  <div class="right-panel">
    <div class="form-container">

      <div class="form-header">
        <div class="access-badge">Patient Portal</div>
        <h2>Welcome back</h2>
        <p>Sign in to view your appointments and medical records.</p>
      </div>

      <?php if (!empty($error)): ?>
        <div class="alert alert-error">
          <span class="alert-icon">⚠️</span>
          <span><?= htmlspecialchars($error) ?></span>
        </div>
      <?php endif; ?>

      <?php if (!empty($success)): ?>
        <div class="alert alert-success">
          <span class="alert-icon">✅</span>
          <span><?= htmlspecialchars($success) ?></span>
        </div>
      <?php endif; ?>

      <form method="POST" action="" id="loginForm" novalidate>

        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"/>

        <div class="field">
          <label for="email">Email Address</label>
          <div class="input-wrap">
            <span class="input-icon">✉️</span>
            <input
              type="email"
              id="email"
              name="email"
              placeholder="your@email.com"
              value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
              autocomplete="email"
              required
            />
          </div>
        </div>

        <div class="field">
          <label for="password">Password</label>
          <div class="input-wrap">
            <span class="input-icon">🔒</span>
            <input
              type="password"
              id="password"
              name="password"
              placeholder="Enter your password"
              autocomplete="current-password"
              required
            />
            <button type="button" class="toggle-pw" id="togglePw" aria-label="Toggle password visibility">👁</button>
          </div>
        </div>

        <div class="field-row">
          <label class="checkbox-label">
            <input type="checkbox" name="remember" id="remember" <?= isset($_POST['remember']) ? 'checked' : '' ?>/>
            <span>Remember me</span>
          </label>
          <a href="forgot_password.php" class="forgot-link">Forgot password?</a>
        </div>

        <button type="submit" class="btn-login" id="submitBtn">Sign In</button>

        <div class="divider">Don't have an account?</div>

        <div class="register-link">
          New patient?
          <a href="patient_register.php">Create an account</a>
        </div>

      </form>
    </div>
  </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const togglePw = document.getElementById('togglePw');
  const pwInput  = document.getElementById('password');
  if (togglePw && pwInput) {
    togglePw.addEventListener('click', function () {
      const isHidden = pwInput.type === 'password';
      pwInput.type         = isHidden ? 'text' : 'password';
      togglePw.textContent = isHidden ? '🙈' : '👁';
    });
  }

  const loginForm = document.getElementById('loginForm');
  const submitBtn = document.getElementById('submitBtn');
  if (loginForm && submitBtn) {
    loginForm.addEventListener('submit', function () {
      submitBtn.textContent = 'Signing in…';
      submitBtn.disabled    = true;
    });
  }

  const successAlert = document.querySelector('.alert-success');
  if (successAlert) {
    setTimeout(function () {
      window.location.href = 'patient_dashboard.php';
    }, 1800);
  }
});
</script>

</body>
</html>