<?php
session_start();
require 'database.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit();
}

$success = '';
$error   = '';

// ── Add new patient ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_patient'])) {
    $name    = trim($_POST['full_name']);
    $email   = trim($_POST['email']);
    $phone   = trim($_POST['phone'] ?? '');
    $pass    = $_POST['password'];
    $dob     = $_POST['date_of_birth'] ?? null;
    $sex     = $_POST['sex'] ?? null;
    $address = trim($_POST['address'] ?? '');
    $blood   = trim($_POST['blood_type'] ?? '');

    if (empty($name) || empty($email) || empty($pass)) {
        $error = "Name, email and password are required.";
    } else {
        $chk = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $chk->bind_param("s", $email);
        $chk->execute();
        $chk->store_result();

        if ($chk->num_rows > 0) {
            $error = "Email already registered.";
        } else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, password_hash, phone, role) VALUES (?, ?, ?, ?, 'patient')");
            $stmt->bind_param("ssss", $name, $email, $hash, $phone);

            if ($stmt->execute()) {
                $uid   = $stmt->insert_id;
                $stmt2 = $conn->prepare("INSERT INTO patients (user_id, date_of_birth, sex, address, blood_type) VALUES (?, ?, ?, ?, ?)");
                $stmt2->bind_param("issss", $uid, $dob, $sex, $address, $blood);
                $stmt2->execute();
                $success = "Patient added successfully.";
            } else {
                $error = "Failed to add patient.";
            }
        }
    }
}

// ── Search ──
$search = trim($_GET['search'] ?? '');
$where  = "WHERE u.role = 'patient'";
$params = [];
$types  = "";

if ($search !== '') {
    $where   .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $like     = "%$search%";
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types   .= "sss";
}

$sql = "
    SELECT u.user_id, u.full_name, u.email, u.phone, u.created_at,
           p.patient_id, p.date_of_birth, p.sex, p.blood_type, p.address,
           (SELECT COUNT(*) FROM appointments a WHERE a.patient_id = p.patient_id) AS appt_count
    FROM users u
    LEFT JOIN patients p ON p.user_id = u.user_id
    $where
    ORDER BY u.created_at DESC
";

$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$patients = $stmt->get_result();

$show_form   = isset($_GET['action']) && $_GET['action'] === 'new';
$active_page = 'patients';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Patients — HealthCenter Admin</title>
  <link rel="stylesheet" href="global.css"/>
  <style>
    .filter-bar { display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:20px; }
    .filter-bar input { padding:9px 14px; border:1.5px solid var(--cream-dark); border-radius:8px; font-family:inherit; font-size:0.87rem; background:white; outline:none; min-width:260px; }
    .filter-bar input:focus { border-color:var(--green-light); }
    .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:100; align-items:center; justify-content:center; }
    .modal-overlay.open { display:flex; }
    .modal { background:white; border-radius:16px; padding:32px; width:100%; max-width:520px; max-height:90vh; overflow-y:auto; }
    .modal h2 { font-family:'DM Serif Display',serif; font-weight:normal; margin-bottom:20px; }
    .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    .form-grid .full { grid-column:1/-1; }
    .form-group label { display:block; font-size:0.75rem; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; margin-bottom:6px; }
    .form-group input, .form-group select, .form-group textarea { width:100%; padding:10px 14px; border:1.5px solid var(--cream-dark); border-radius:8px; font-family:inherit; font-size:0.88rem; outline:none; }
    .form-group input:focus, .form-group select:focus { border-color:var(--green-light); }
    .avatar { width:34px; height:34px; border-radius:50%; background:var(--green-deep); color:white; display:inline-flex; align-items:center; justify-content:center; font-size:0.8rem; font-weight:700; flex-shrink:0; }
    .patient-name-cell { display:flex; align-items:center; gap:10px; }
  </style>
</head>
<body>
<div class="admin-layout">
  <?php include 'admin_sidebar.php'; ?>

  <div class="main-content">
    <div class="topbar">
      <div>
        <h1>Patients</h1>
        <p>Manage registered patient records</p>
      </div>
      <button class="btn" onclick="document.getElementById('newModal').classList.add('open')">
        ➕ Add Patient
      </button>
    </div>

    <?php if ($success): ?>
      <div class="alert alert-success" style="margin-bottom:16px;">✅ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-error" style="margin-bottom:16px;">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- SEARCH -->
    <form method="GET" class="filter-bar">
      <input type="text" name="search" placeholder="🔍 Search by name, email or phone..." value="<?= htmlspecialchars($search) ?>"/>
      <button type="submit" class="btn">Search</button>
      <?php if ($search): ?><a href="patients.php" class="btn btn-outline">Clear</a><?php endif; ?>
    </form>

    <!-- TABLE -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">All Patients</div>
        <div class="card-subtitle"><?= $patients->num_rows ?> record(s) found</div>
      </div>
      <div class="card-body" style="padding:0;">
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Patient</th>
                <th>Email</th>
                <th>Phone</th>
                <th>DOB</th>
                <th>Sex</th>
                <th>Blood</th>
                <th>Appointments</th>
                <th>Registered</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($patients->num_rows === 0): ?>
                <tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:32px;">No patients found.</td></tr>
              <?php else: while ($row = $patients->fetch_assoc()): ?>
                <tr>
                  <td>
                    <div class="patient-name-cell">
                      <div class="avatar"><?= strtoupper(substr($row['full_name'],0,2)) ?></div>
                      <?= htmlspecialchars($row['full_name']) ?>
                    </div>
                  </td>
                  <td><?= htmlspecialchars($row['email']) ?></td>
                  <td><?= htmlspecialchars($row['phone'] ?? '—') ?></td>
                  <td><?= $row['date_of_birth'] ? date('M j, Y', strtotime($row['date_of_birth'])) : '—' ?></td>
                  <td><?= $row['sex'] ? ucfirst($row['sex']) : '—' ?></td>
                  <td><?= $row['blood_type'] ?? '—' ?></td>
                  <td style="text-align:center;"><?= $row['appt_count'] ?></td>
                  <td><?= date('M j, Y', strtotime($row['created_at'])) ?></td>
                  <td>
                    <a href="appointments.php?search=<?= urlencode($row['full_name']) ?>" class="btn btn-outline" style="font-size:0.78rem;padding:4px 10px;">
                      View Appts
                    </a>
                  </td>
                </tr>
              <?php endwhile; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- ADD PATIENT MODAL -->
<div class="modal-overlay <?= $show_form ? 'open' : '' ?>" id="newModal">
  <div class="modal">
    <h2>➕ Add New Patient</h2>
    <form method="POST">
      <input type="hidden" name="new_patient" value="1"/>
      <div class="form-grid">
        <div class="form-group full">
          <label>Full Name *</label>
          <input type="text" name="full_name" required placeholder="Juan Dela Cruz"/>
        </div>
        <div class="form-group">
          <label>Email *</label>
          <input type="email" name="email" required placeholder="juan@email.com"/>
        </div>
        <div class="form-group">
          <label>Phone</label>
          <input type="tel" name="phone" placeholder="09XX XXX XXXX"/>
        </div>
        <div class="form-group">
          <label>Password *</label>
          <input type="password" name="password" required placeholder="Min. 8 characters"/>
        </div>
        <div class="form-group">
          <label>Date of Birth</label>
          <input type="date" name="date_of_birth"/>
        </div>
        <div class="form-group">
          <label>Sex</label>
          <select name="sex">
            <option value="">— Select —</option>
            <option value="male">Male</option>
            <option value="female">Female</option>
            <option value="other">Other</option>
          </select>
        </div>
        <div class="form-group">
          <label>Blood Type</label>
          <select name="blood_type">
            <option value="">— Select —</option>
            <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bt): ?>
              <option value="<?= $bt ?>"><?= $bt ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group full">
          <label>Address</label>
          <input type="text" name="address" placeholder="City, Province"/>
        </div>
      </div>
      <div style="display:flex;gap:10px;margin-top:20px;">
        <button type="submit" class="btn-login" style="flex:1;">Add Patient</button>
        <button type="button" class="btn btn-outline" onclick="document.getElementById('newModal').classList.remove('open')" style="flex:0 0 auto;">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
document.getElementById('newModal').addEventListener('click', function(e) {
  if (e.target === this) this.classList.remove('open');
});
</script>
</body>
</html>