<?php
session_start();
require 'database.php';

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

        $name     = trim($_POST['full_name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $phone    = trim($_POST['phone'] ?? '');

        if (empty($name) || empty($email) || empty($password)) {
            $error = 'Please fill in all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } else {
            // Check if email already exists
            $check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
            $check->bind_param("s", $email);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $error = 'An account with that email already exists.';
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $conn->prepare("
                    INSERT INTO users (full_name, email, password_hash, phone, role)
                    VALUES (?, ?, ?, ?, 'patient')
                ");
                $stmt->bind_param("ssss", $name, $email, $hashedPassword, $phone);

                if ($stmt->execute()) {
                    $user_id = $stmt->insert_id;

                    $stmt2 = $conn->prepare("INSERT INTO patients (user_id) VALUES (?)");
                    $stmt2->bind_param("i", $user_id);
                    $stmt2->execute();

                    $success = 'Registration successful! You can now log in.';
                } else {
                    $error = 'Something went wrong. Please try again.';
                }
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
  <title>Patient Register — HealthCenter</title>
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
      <h1>Create your<br><em>patient account.</em></h1>
      <p>Join hundreds of patients already managing their care online.</p>
    </div>

    <div class="stat-row">
      <div class="stat">
        <div class="stat-num">Free</div>
        <div class="stat-label">Registration</div>
      </div>
      <div class="stat">
        <div class="stat-num">2 min</div>
        <div class="stat-label">Setup time</div>
      </div>
    </div>
  </div>

  <!-- RIGHT PANEL -->
  <div class="right-panel">
    <div class="form-container">

      <div class="form-header">
        <div class="access-badge">New Patient</div>
        <h2>Create Account</h2>
        <p>Register to book appointments and access your health records.</p>
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

      <form method="POST" action="" id="registerForm" novalidate>

        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"/>

        <div class="field">
          <label for="full_name">Full Name <span style="color:var(--error-text)">*</span></label>
          <div class="input-wrap">
            <span class="input-icon">👤</span>
            <input
              type="text"
              id="full_name"
              name="full_name"
              placeholder="Juan Dela Cruz"
              value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
              required
            />
          </div>
        </div>

        <div class="field">
          <label for="email">Email Address <span style="color:var(--error-text)">*</span></label>
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
          <label for="phone">Phone Number</label>
          <div class="input-wrap">
            <span class="input-icon">📱</span>
            <input
              type="tel"
              id="phone"
              name="phone"
              placeholder="+63 9XX XXX XXXX"
              value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
            />
          </div>
        </div>

        <div class="field">
          <label for="password">Password <span style="color:var(--error-text)">*</span></label>
          <div class="input-wrap">
            <span class="input-icon">🔒</span>
            <input
              type="password"
              id="password"
              name="password"
              placeholder="At least 8 characters"
              autocomplete="new-password"
              required
            />
            <button type="button" class="toggle-pw" id="togglePw" aria-label="Toggle password visibility">👁</button>
          </div>
        </div>

        <button type="submit" class="btn-login" id="submitBtn">Create Account</button>

        <div class="divider">Already have an account?</div>

        <div class="register-link">
          <a href="patient_login.php">Sign in here</a>
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

  const form      = document.getElementById('registerForm');
  const submitBtn = document.getElementById('submitBtn');
  if (form && submitBtn) {
    form.addEventListener('submit', function () {
      submitBtn.textContent = 'Creating account…';
      submitBtn.disabled    = true;
    });
  }

  // Redirect to login after successful registration
  const successAlert = document.querySelector('.alert-success');
  if (successAlert) {
    setTimeout(function () {
      window.location.href = 'patient_login.php';
    }, 2500);
  }
});
</script>

</body>
</html>