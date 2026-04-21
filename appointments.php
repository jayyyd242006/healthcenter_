<?php
session_start();
require 'database.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit();
}

$success = '';
$error   = '';

// ── Handle status update (approve / cancel / complete / no_show) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $appt_id    = (int) $_POST['appointment_id'];
    $new_status = $_POST['action'];
    $allowed    = ['confirmed', 'cancelled', 'completed', 'no_show'];

    if (in_array($new_status, $allowed)) {
        $admin_id = (int) $_SESSION['admin_id'];
        $stmt = $conn->prepare("
            UPDATE appointments
            SET status = ?, confirmed_by = ?, confirmed_at = NOW()
            WHERE appointment_id = ?
        ");
        $stmt->bind_param("sii", $new_status, $admin_id, $appt_id);
        $stmt->execute() ? $success = "Appointment updated successfully." : $error = "Update failed.";
    }
}

// ── Handle new appointment submission ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_appointment'])) {
    $patient_id  = (int) $_POST['patient_id'];
    $doctor_id   = (int) $_POST['doctor_id'];
    $schedule_id = (int) $_POST['schedule_id'];
    $appt_date   = $_POST['appointment_date'];
    $appt_time   = $_POST['appointment_time'];
    $reason      = trim($_POST['reason'] ?? '');

    $stmt = $conn->prepare("
        INSERT INTO appointments (patient_id, doctor_id, schedule_id, appointment_date, appointment_time, reason, status)
        VALUES (?, ?, ?, ?, ?, ?, 'confirmed')
    ");
    $stmt->bind_param("iiisss", $patient_id, $doctor_id, $schedule_id, $appt_date, $appt_time, $reason);
    $stmt->execute() ? $success = "Appointment created successfully." : $error = "Failed to create appointment.";
}

// ── Filters ──
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$date   = $_GET['date'] ?? '';

$where  = "WHERE 1=1";
$params = [];
$types  = "";

if ($filter !== 'all') {
    $where   .= " AND a.status = ?";
    $params[] = $filter;
    $types   .= "s";
}
if ($search !== '') {
    $where   .= " AND (u.full_name LIKE ? OR du.full_name LIKE ?)";
    $like     = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $types   .= "ss";
}
if ($date !== '') {
    $where   .= " AND a.appointment_date = ?";
    $params[] = $date;
    $types   .= "s";
}

$sql = "
    SELECT a.appointment_id, a.appointment_date, a.appointment_time,
           a.status, a.reason,
           u.full_name  AS patient_name,
           du.full_name AS doctor_name,
           d.specialty
    FROM appointments a
    JOIN patients p ON a.patient_id = p.patient_id
    JOIN users u    ON p.user_id    = u.user_id
    JOIN doctors d  ON a.doctor_id  = d.doctor_id
    JOIN users du   ON d.user_id    = du.user_id
    $where
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
    LIMIT 100
";

$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$appointments = $stmt->get_result();

// ── Fetch patients + doctors for new appointment form ──
$patients_list = $conn->query("
    SELECT p.patient_id, u.full_name
    FROM patients p JOIN users u ON p.user_id = u.user_id
    ORDER BY u.full_name
");
$doctors_list = $conn->query("
    SELECT d.doctor_id, u.full_name, d.specialty
    FROM doctors d JOIN users u ON d.user_id = u.user_id
    WHERE d.is_available = 1
    ORDER BY u.full_name
");
$schedules_list = $conn->query("
    SELECT s.schedule_id, s.schedule_date, s.start_time, s.end_time,
           u.full_name AS doctor_name
    FROM schedules s
    JOIN doctors d ON s.doctor_id = d.doctor_id
    JOIN users u   ON d.user_id   = u.user_id
    WHERE s.schedule_date >= CURDATE() AND s.is_open = 1
    ORDER BY s.schedule_date, s.start_time
");

$show_form = isset($_GET['action']) && $_GET['action'] === 'new';
$active_page = 'appointments';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Appointments — HealthCenter Admin</title>
  <link rel="stylesheet" href="global.css"/>
  <style>
    .filter-bar { display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:20px; }
    .filter-bar input, .filter-bar select { padding:9px 14px; border:1.5px solid var(--cream-dark); border-radius:8px; font-family:inherit; font-size:0.87rem; background:white; outline:none; }
    .filter-bar input:focus, .filter-bar select:focus { border-color:var(--green-light); }
    .filter-bar .btn { height:38px; }
    .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:100; align-items:center; justify-content:center; }
    .modal-overlay.open { display:flex; }
    .modal { background:white; border-radius:16px; padding:32px; width:100%; max-width:500px; max-height:90vh; overflow-y:auto; }
    .modal h2 { font-family:'DM Serif Display',serif; font-weight:normal; margin-bottom:20px; }
    .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    .form-grid .full { grid-column: 1/-1; }
    .form-group label { display:block; font-size:0.75rem; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; margin-bottom:6px; }
    .form-group select, .form-group input, .form-group textarea { width:100%; padding:10px 14px; border:1.5px solid var(--cream-dark); border-radius:8px; font-family:inherit; font-size:0.88rem; outline:none; }
    .form-group select:focus, .form-group input:focus, .form-group textarea:focus { border-color:var(--green-light); }
    .action-btns { display:flex; gap:6px; }
    .btn-sm { padding:4px 12px; font-size:0.78rem; border-radius:6px; border:none; cursor:pointer; font-weight:600; }
    .btn-confirm  { background:var(--success-bg); color:var(--success-text); }
    .btn-cancel   { background:var(--error-bg);   color:var(--error-text); }
    .btn-complete { background:#e8f4fd; color:#2b6cb0; }
    .btn-noshow   { background:#f3e8ff; color:#6b21a8; }
  </style>
</head>
<body>
<div class="admin-layout">
  <?php include 'admin_sidebar.php'; ?>

  <div class="main-content">
    <div class="topbar">
      <div>
        <h1>Appointments</h1>
        <p>Manage and track all patient appointments</p>
      </div>
      <button class="btn" onclick="document.getElementById('newModal').classList.add('open')">
        + New Appointment
      </button>
    </div>

    <?php if ($success): ?>
      <div class="alert alert-success" style="margin-bottom:16px;">✅ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-error" style="margin-bottom:16px;">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- FILTER BAR -->
    <form method="GET" class="filter-bar">
      <input type="text" name="search" placeholder="🔍 Search patient or doctor..." value="<?= htmlspecialchars($search) ?>"/>
      <input type="date" name="date" value="<?= htmlspecialchars($date) ?>"/>
      <select name="filter">
        <option value="all"       <?= $filter==='all'       ?'selected':'' ?>>All Status</option>
        <option value="pending"   <?= $filter==='pending'   ?'selected':'' ?>>Pending</option>
        <option value="confirmed" <?= $filter==='confirmed' ?'selected':'' ?>>Confirmed</option>
        <option value="completed" <?= $filter==='completed' ?'selected':'' ?>>Completed</option>
        <option value="cancelled" <?= $filter==='cancelled' ?'selected':'' ?>>Cancelled</option>
        <option value="no_show"   <?= $filter==='no_show'   ?'selected':'' ?>>No Show</option>
      </select>
      <button type="submit" class="btn">Filter</button>
      <a href="appointments.php" class="btn btn-outline">Reset</a>
    </form>

    <!-- TABLE -->
    <div class="card">
      <div class="card-body" style="padding:0;">
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>Patient</th>
                <th>Doctor</th>
                <th>Specialty</th>
                <th>Date</th>
                <th>Time</th>
                <th>Reason</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($appointments->num_rows === 0): ?>
                <tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:32px;">No appointments found.</td></tr>
              <?php else: while ($row = $appointments->fetch_assoc()): ?>
                <tr>
                  <td><?= $row['appointment_id'] ?></td>
                  <td><?= htmlspecialchars($row['patient_name']) ?></td>
                  <td><?= htmlspecialchars($row['doctor_name']) ?></td>
                  <td><?= htmlspecialchars($row['specialty']) ?></td>
                  <td><?= date('M j, Y', strtotime($row['appointment_date'])) ?></td>
                  <td><?= date('g:i A', strtotime($row['appointment_time'])) ?></td>
                  <td><?= htmlspecialchars($row['reason'] ?? '—') ?></td>
                  <td><span class="status <?= $row['status'] ?>"><?= ucfirst(str_replace('_',' ',$row['status'])) ?></span></td>
                  <td>
                    <form method="POST" style="display:inline;">
                      <input type="hidden" name="appointment_id" value="<?= $row['appointment_id'] ?>">
                      <div class="action-btns">
                        <?php if ($row['status'] === 'pending'): ?>
                          <button name="action" value="confirmed"  class="btn-sm btn-confirm" >✓ Confirm</button>
                          <button name="action" value="cancelled"  class="btn-sm btn-cancel"  >✕ Cancel</button>
                        <?php elseif ($row['status'] === 'confirmed'): ?>
                          <button name="action" value="completed"  class="btn-sm btn-complete">✓ Done</button>
                          <button name="action" value="no_show"    class="btn-sm btn-noshow"  >✗ No Show</button>
                          <button name="action" value="cancelled"  class="btn-sm btn-cancel"  >✕ Cancel</button>
                        <?php else: ?>
                          <span style="color:var(--text-muted);font-size:0.78rem;">—</span>
                        <?php endif; ?>
                      </div>
                    </form>
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

<!-- NEW APPOINTMENT MODAL -->
<div class="modal-overlay <?= $show_form ? 'open' : '' ?>" id="newModal">
  <div class="modal">
    <h2>📝 New Appointment</h2>
    <form method="POST">
      <input type="hidden" name="new_appointment" value="1"/>
      <div class="form-grid">
        <div class="form-group full">
          <label>Patient</label>
          <select name="patient_id" required>
            <option value="">— Select Patient —</option>
            <?php while ($p = $patients_list->fetch_assoc()): ?>
              <option value="<?= $p['patient_id'] ?>"><?= htmlspecialchars($p['full_name']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="form-group full">
          <label>Doctor</label>
          <select name="doctor_id" required>
            <option value="">— Select Doctor —</option>
            <?php while ($d = $doctors_list->fetch_assoc()): ?>
              <option value="<?= $d['doctor_id'] ?>"><?= htmlspecialchars($d['full_name']) ?> — <?= htmlspecialchars($d['specialty']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="form-group full">
          <label>Schedule Slot</label>
          <select name="schedule_id" required>
            <option value="">— Select Schedule —</option>
            <?php while ($s = $schedules_list->fetch_assoc()): ?>
              <option value="<?= $s['schedule_id'] ?>">
                <?= htmlspecialchars($s['doctor_name']) ?> — <?= date('M j, Y', strtotime($s['schedule_date'])) ?> (<?= date('g:i A', strtotime($s['start_time'])) ?> - <?= date('g:i A', strtotime($s['end_time'])) ?>)
              </option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Date</label>
          <input type="date" name="appointment_date" required min="<?= date('Y-m-d') ?>"/>
        </div>
        <div class="form-group">
          <label>Time</label>
          <input type="time" name="appointment_time" required/>
        </div>
        <div class="form-group full">
          <label>Reason / Notes</label>
          <textarea name="reason" rows="3" placeholder="Reason for visit..."></textarea>
        </div>
      </div>
      <div style="display:flex;gap:10px;margin-top:20px;">
        <button type="submit" class="btn-login" style="flex:1;">Create Appointment</button>
        <button type="button" class="btn btn-outline" onclick="document.getElementById('newModal').classList.remove('open')" style="flex:0 0 auto;">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
// Close modal on overlay click
document.getElementById('newModal').addEventListener('click', function(e) {
  if (e.target === this) this.classList.remove('open');
});
</script>
</body>
</html>