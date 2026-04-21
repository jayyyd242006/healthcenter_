<?php
session_start();

// Redirect if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: admin_dashboard.php');
    exit();
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF validation
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
            require_once 'database.php';

            $stmt = $conn->prepare("
                SELECT user_id, full_name, password_hash
                FROM users
                WHERE email = ? AND role = 'admin' AND is_active = 1
                LIMIT 1
            ");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user   = $result->fetch_assoc();

            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_name']      = $user['full_name'];
                $_SESSION['admin_email']     = $email;
                $_SESSION['admin_id']        = $user['user_id'];
                $success = 'Login successful! Redirecting to dashboard…';
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
  <title>Admin Login — HealthCenter</title>
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
      <h1>Manage care,<br><em>effortlessly.</em></h1>
      <p>Your complete clinic management hub — appointments, patients, and schedules in one place.</p>
    </div>

    <div class="stat-row">
      <div class="stat">
        <div class="stat-num">98%</div>
        <div class="stat-label">Booking accuracy</div>
      </div>
      <div class="stat">
        <div class="stat-num">24/7</div>
        <div class="stat-label">System uptime</div>
      </div>
      <div class="stat">
        <div class="stat-num">↓40%</div>
        <div class="stat-label">No-show rate</div>
      </div>
    </div>
  </div>

  <!-- RIGHT PANEL -->
  <div class="right-panel">
    <div class="form-container">

      <div class="form-header">
        <div class="access-badge">Admin Access</div>
        <h2>Welcome back</h2>
        <p>Sign in to manage appointments, patients, and clinic schedules.</p>
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

        <!-- Email -->
        <div class="field">
          <label for="email">Email Address</label>
          <div class="input-wrap">
            <span class="input-icon">✉️</span>
            <input
              type="email"
              id="email"
              name="email"
              placeholder="admin@healthcenter.com"
              value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
              autocomplete="email"
              required
            />
          </div>
        </div>

        <!-- Password -->
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

        <!-- Remember me / Forgot -->
        <div class="field-row">
          <label class="checkbox-label">
            <input type="checkbox" name="remember" id="remember" <?= isset($_POST['remember']) ? 'checked' : '' ?>/>
            <span>Remember me</span>
          </label>
          <a href="forgot_password.php" class="forgot-link">Forgot password?</a>
        </div>

        <button type="submit" class="btn-login" id="submitBtn">
          Sign In to Dashboard
        </button>

        <div class="divider">Authorized personnel only</div>

        <div class="security-note">
          <div class="lock-icon">🔐</div>
          <span>This portal is protected by session-based authentication.<br>All activity is logged for security.</span>
        </div>

      </form>
    </div>
  </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  // Toggle Password Visibility
  const togglePw = document.getElementById('togglePw');
  const pwInput  = document.getElementById('password');
  if (togglePw && pwInput) {
    togglePw.addEventListener('click', function () {
      const isHidden = pwInput.type === 'password';
      pwInput.type         = isHidden ? 'text' : 'password';
      togglePw.textContent = isHidden ? '🙈' : '👁';
    });
  }

  // Submit button loading state
  const loginForm = document.getElementById('loginForm');
  const submitBtn = document.getElementById('submitBtn');
  if (loginForm && submitBtn) {
    loginForm.addEventListener('submit', function () {
      submitBtn.textContent = 'Signing in…';
      submitBtn.disabled    = true;
    });
  }

  // Auto-redirect after success
  const successAlert = document.querySelector('.alert-success');
  if (successAlert) {
    setTimeout(function () {
      window.location.href = 'admin_dashboard.php';
    }, 1800);
  }
});
</script>

</body>
</html>