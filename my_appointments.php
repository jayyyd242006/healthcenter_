<?php
session_start();
require 'database.php';

if (!isset($_SESSION['patient_logged_in']) || $_SESSION['patient_logged_in'] !== true) {
    header('Location: patient_login.php');
    exit();
}

$patient_id = (int) $_SESSION['patient_id'];
$success = '';
$error   = '';

// ── Cancel appointment ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_id'])) {
    $cancel_id = (int) $_POST['cancel_id'];
    $stmt = $conn->prepare("
        UPDATE appointments SET status = 'cancelled'
        WHERE appointment_id = ? AND patient_id = ? AND status IN ('pending','confirmed')
    ");
    $stmt->bind_param("ii", $cancel_id, $patient_id);
    $stmt->execute() && $stmt->affected_rows > 0
        ? $success = "Appointment cancelled successfully."
        : $error   = "Could not cancel this appointment.";
}

// ── Filter ──
$filter = $_GET['filter'] ?? 'all';

$where  = "WHERE a.patient_id = ?";
$params = [$patient_id];
$types  = "i";

if ($filter !== 'all') {
    $where   .= " AND a.status = ?";
    $params[] = $filter;
    $types   .= "s";
}

$stmt = $conn->prepare("
    SELECT a.appointment_id, a.appointment_date, a.appointment_time,
           a.status, a.reason, a.notes,
           u.full_name AS doctor_name, d.specialty
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.doctor_id
    JOIN users u   ON d.user_id   = u.user_id
    $where
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$appointments = $stmt->get_result();

$active_page = 'appointments';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Appointments — HealthCenter</title>
  <link rel="stylesheet" href="global.css"/>
  <style>
    .filter-tabs { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:20px; }
    .tab { padding:7px 18px; border-radius:99px; font-size:0.82rem; font-weight:600; text-decoration:none; border:1.5px solid var(--cream-dark); color:var(--text-muted); background:white; transition:all 0.2s; }
    .tab:hover { border-color:var(--green-light); color:var(--green-light); }
    .tab.active { background:var(--green-deep); color:white; border-color:var(--green-deep); }
    .btn-cancel-sm { background:var(--error-bg); color:var(--error-text); border:none; padding:5px 12px; border-radius:6px; font-size:0.78rem; font-weight:600; cursor:pointer; }
    .btn-cancel-sm:hover { opacity:0.8; }
  </style>
</head>
<body>
<div class="layout">
  <?php include 'patient_sidebar.php'; ?>

  <div class="main">
    <div class="topbar">
      <div>
        <h1>My Appointments</h1>
        <p>View and manage all your appointment bookings</p>
      </div>
      <a href="book_appointment.php" class="btn">+ Book New</a>
    </div>

    <?php if ($success): ?>
      <div class="alert alert-success" style="margin-bottom:16px;">✅ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-error" style="margin-bottom:16px;">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- FILTER TABS -->
    <div class="filter-tabs">
      <a href="?filter=all"       class="tab <?= $filter==='all'       ?'active':'' ?>">All</a>
      <a href="?filter=pending"   class="tab <?= $filter==='pending'   ?'active':'' ?>">⏳ Pending</a>
      <a href="?filter=confirmed" class="tab <?= $filter==='confirmed' ?'active':'' ?>">✅ Confirmed</a>
      <a href="?filter=completed" class="tab <?= $filter==='completed' ?'active':'' ?>">🏁 Completed</a>
      <a href="?filter=cancelled" class="tab <?= $filter==='cancelled' ?'active':'' ?>">✕ Cancelled</a>
      <a href="?filter=no_show"   class="tab <?= $filter==='no_show'   ?'active':'' ?>">✗ No Show</a>
    </div>

    <div class="card">
      <div class="card-body" style="padding:0;">
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Doctor</th>
                <th>Specialty</th>
                <th>Date</th>
                <th>Time</th>
                <th>Reason</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($appointments->num_rows === 0): ?>
                <tr>
                  <td colspan="7" style="text-align:center;color:var(--text-muted);padding:40px;">
                    No appointments found.
                    <a href="book_appointment.php" style="color:var(--green-light);font-weight:600;">Book one now →</a>
                  </td>
                </tr>
              <?php else: while ($row = $appointments->fetch_assoc()): ?>
                <tr>
                  <td><?= htmlspecialchars($row['doctor_name']) ?></td>
                  <td><?= htmlspecialchars($row['specialty']) ?></td>
                  <td><?= date('M j, Y', strtotime($row['appointment_date'])) ?></td>
                  <td><?= date('g:i A', strtotime($row['appointment_time'])) ?></td>
                  <td><?= htmlspecialchars($row['reason'] ?? '—') ?></td>
                  <td>
                    <span class="status <?= $row['status'] ?>">
                      <?= ucfirst(str_replace('_',' ',$row['status'])) ?>
                    </span>
                  </td>
                  <td>
                    <?php if (in_array($row['status'], ['pending','confirmed'])): ?>
                      <form method="POST" onsubmit="return confirm('Cancel this appointment?')">
                        <input type="hidden" name="cancel_id" value="<?= $row['appointment_id'] ?>"/>
                        <button type="submit" class="btn-cancel-sm">✕ Cancel</button>
                      </form>
                    <?php else: ?>
                      <span style="color:var(--text-muted);font-size:0.78rem;">—</span>
                    <?php endif; ?>
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
</body>
</html>