<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit();
}

require 'database.php';

$admin_name = $_SESSION['admin_name'] ?? 'Administrator';
$today      = date('l, F j, Y');
$hour       = (int) date('H');
$greeting   = $hour < 12 ? 'morning' : ($hour < 18 ? 'afternoon' : 'evening');

// Live stats
$today_date = date('Y-m-d');

$r = $conn->query("SELECT COUNT(*) AS c FROM appointments WHERE appointment_date = '$today_date'");
$todays_appts = $r->fetch_assoc()['c'];

$r = $conn->query("SELECT COUNT(*) AS c FROM patients");
$total_patients = $r->fetch_assoc()['c'];

$r = $conn->query("SELECT COUNT(*) AS c FROM appointments WHERE status = 'pending'");
$pending_count = $r->fetch_assoc()['c'];

$r = $conn->query("SELECT COUNT(*) AS c FROM appointments WHERE appointment_date = '$today_date' AND status = 'no_show'");
$noshows = $r->fetch_assoc()['c'];

// Today's appointments list
$stmt = $conn->prepare("
    SELECT a.appointment_id, a.appointment_time, a.status, a.reason,
           u.full_name AS patient_name, d.specialty
    FROM appointments a
    JOIN patients p  ON a.patient_id = p.patient_id
    JOIN users u     ON p.user_id    = u.user_id
    JOIN doctors d   ON a.doctor_id  = d.doctor_id
    WHERE a.appointment_date = ?
    ORDER BY a.appointment_time ASC
");
$stmt->bind_param("s", $today_date);
$stmt->execute();
$todays_list = $stmt->get_result();

$active_page = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Dashboard — HealthCenter Admin</title>
  <link rel="stylesheet" href="global.css"/>
</head>
<body>

<div class="admin-layout">

  <!-- SIDEBAR -->
  <?php include 'admin_sidebar.php'; ?>

  <!-- MAIN CONTENT -->
  <div class="main-content">

    <!-- TOPBAR -->
    <div class="topbar">
      <div>
        <h1>Dashboard</h1>
        <p>Good <?= $greeting ?>, <?= htmlspecialchars($admin_name) ?> 👋</p>
      </div>
      <div class="topbar-date">📆 <?= $today ?></div>
    </div>

    <!-- STATS -->
    <div class="stat-grid">
      <div class="stat-card">
        <div class="stat-value"><?= $todays_appts ?></div>
        <div class="stat-label">Today's Appointments</div>
        <span class="stat-change up">📅 <?= date('M j') ?></span>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?= $total_patients ?></div>
        <div class="stat-label">Total Patients</div>
        <span class="stat-change up">👥 Registered</span>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?= $pending_count ?></div>
        <div class="stat-label">Pending Confirmations</div>
        <?php if ($pending_count > 0): ?>
          <span class="stat-change down">⚠️ Needs action</span>
        <?php else: ?>
          <span class="stat-change up">✓ All clear</span>
        <?php endif; ?>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?= $noshows ?></div>
        <div class="stat-label">No-shows Today</div>
        <span class="stat-change <?= $noshows > 0 ? 'down' : 'up' ?>">
          <?= $noshows > 0 ? '↑ Recorded' : '✓ None yet' ?>
        </span>
      </div>
    </div>

    <!-- DASHBOARD CONTENT -->
    <div class="dashboard-grid">

      <!-- QUICK ACTIONS -->
      <div class="card">
        <div class="card-header">
          <div class="card-title">Quick Actions</div>
        </div>
        <div class="card-body">
          <div class="quick-actions">
            <a href="appointments.php?action=new" class="quick-action-btn">📝 New Appointment</a>
            <a href="patients.php?action=new" class="quick-action-btn">➕ Add Patient</a>
            <a href="appointments.php?filter=pending" class="quick-action-btn">⏳ Pending</a>
            <a href="reports.php" class="quick-action-btn">📊 Reports</a>
          </div>
        </div>
      </div>

      <!-- TODAY'S APPOINTMENTS TABLE -->
      <div class="card">
        <div class="card-header">
          <div>
            <div class="card-title">Today's Appointments</div>
            <div class="card-subtitle"><?= date('F j, Y') ?> · <?= date('l') ?></div>
          </div>
          <a href="appointments.php" class="btn">View All</a>
        </div>
        <div class="card-body" style="padding:0;">
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Time</th>
                  <th>Patient</th>
                  <th>Service</th>
                  <th>Status</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($todays_list->num_rows === 0): ?>
                  <tr>
                    <td colspan="5" style="text-align:center;color:var(--text-muted);padding:32px;">
                      No appointments scheduled for today.
                    </td>
                  </tr>
                <?php else: while ($row = $todays_list->fetch_assoc()): ?>
                  <tr>
                    <td><?= date('g:i A', strtotime($row['appointment_time'])) ?></td>
                    <td><?= htmlspecialchars($row['patient_name']) ?></td>
                    <td><?= htmlspecialchars($row['specialty']) ?></td>
                    <td><span class="status <?= $row['status'] ?>"><?= ucfirst(str_replace('_',' ',$row['status'])) ?></span></td>
                    <td><a href="appointments.php?id=<?= $row['appointment_id'] ?>">View</a></td>
                  </tr>
                <?php endwhile; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div><!-- /.dashboard-grid -->

  </div><!-- /.main-content -->
</div><!-- /.admin-layout -->

</body>
</html>