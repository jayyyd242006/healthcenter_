<?php
session_start();
require 'database.php';

if (!isset($_SESSION['patient_logged_in']) || $_SESSION['patient_logged_in'] !== true) {
    header("Location: patient_login.php");
    exit();
}

$patient_id   = (int) $_SESSION['patient_id'];   // cast to int for safety
$patient_name = $_SESSION['patient_name'];

$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM appointments WHERE patient_id = ? AND status = 'confirmed'");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$confirmed_count = $stmt->get_result()->fetch_assoc()['total'];

$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM appointments WHERE patient_id = ? AND status = 'pending'");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$pending_count = $stmt->get_result()->fetch_assoc()['total'];

$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM appointments WHERE patient_id = ? AND status = 'completed'");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$completed_count = $stmt->get_result()->fetch_assoc()['total'];

// Get appointment list — join doctors + users; status matches ENUM in DB
// ('pending','confirmed','completed','cancelled','no_show')
$appt = $conn->prepare("
    SELECT a.appointment_date, a.appointment_time, a.status, a.reason,
           d.specialty, u.full_name AS doctor_name
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.doctor_id
    JOIN users u   ON d.user_id   = u.user_id
    WHERE a.patient_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
    LIMIT 20
");
$appt->bind_param("i", $patient_id);
$appt->execute();
$appointments = $appt->get_result();

$today = date('l, F j, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Dashboard — HealthCenter</title>
  <link rel="stylesheet" href="global.css"/>
</head>
<body>

<div class="layout">

  <!-- SIDEBAR -->
  <div class="sidebar">
    <div class="sidebar-header">🏥 HealthCenter</div>

    <p class="user">👋 <?= htmlspecialchars($patient_name) ?></p>

    <nav>
      <a href="patient_dashboard.php" class="active">🏠 Dashboard</a>
      <a href="book_appointment.php">📅 Book Appointment</a>
      <a href="my_appointments.php">📋 My Appointments</a>
      <a href="medical_records.php">🧾 Medical Records</a>
      <a href="notifications.php">🔔 Notifications</a>
      <a href="logout.php" class="logout">🚪 Logout</a>
    </nav>
  </div>

  <!-- MAIN CONTENT -->
  <div class="main">

    <!-- TOPBAR -->
    <div class="topbar">
      <div>
        <h1>My Dashboard</h1>
        <p>Welcome back, <?= htmlspecialchars($patient_name) ?> 👋</p>
      </div>
      <div class="topbar-date">📆 <?= $today ?></div>
    </div>

    <!-- STAT CARDS -->
    <div class="stat-grid">
      <div class="stat-card">
        <div class="stat-value"><?= $confirmed_count ?></div>
        <div class="stat-label">Upcoming Appointments</div>
        <span class="stat-change up">✓ Confirmed</span>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?= $pending_count ?></div>
        <div class="stat-label">Pending Approval</div>
        <?php if ($pending_count > 0): ?>
          <span class="stat-change down">⏳ Awaiting confirmation</span>
        <?php else: ?>
          <span class="stat-change up">✓ All clear</span>
        <?php endif; ?>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?= $completed_count ?></div>
        <div class="stat-label">Completed Visits</div>
        <span class="stat-change up">📋 Total visits</span>
      </div>
    </div>

    <!-- QUICK ACTIONS -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">Quick Actions</div>
      </div>
      <div class="card-body">
        <div class="quick-actions">
          <a href="book_appointment.php" class="quick-action-btn">📅 Book Appointment</a>
          <a href="medical_records.php" class="quick-action-btn">🧾 My Records</a>
          <a href="notifications.php" class="quick-action-btn">🔔 Notifications</a>
          <a href="profile.php" class="quick-action-btn">👤 My Profile</a>
        </div>
      </div>
    </div>

    <!-- APPOINTMENTS TABLE -->
    <div class="card">
      <div class="card-header">
        <div>
          <div class="card-title">My Appointments</div>
          <div class="card-subtitle">Most recent first</div>
        </div>
        <a href="book_appointment.php" class="btn">+ Book New</a>
      </div>
      <div class="card-body" style="padding:0;">
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Doctor</th>
                <th>Specialty</th>
                <th>Date</th>
                <th>Time</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($appointments->num_rows === 0): ?>
                <tr>
                  <td colspan="5" style="text-align:center; color:var(--text-muted); padding: 32px;">
                    No appointments yet. <a href="book_appointment.php">Book your first one!</a>
                  </td>
                </tr>
              <?php else: ?>
                <?php while ($row = $appointments->fetch_assoc()): ?>
                <tr>
                  <td><?= htmlspecialchars($row['doctor_name']) ?></td>
                  <td><?= htmlspecialchars($row['specialty']) ?></td>
                  <td><?= htmlspecialchars(date('M j, Y', strtotime($row['appointment_date']))) ?></td>
                  <td><?= htmlspecialchars(date('g:i A', strtotime($row['appointment_time']))) ?></td>
                  <td>
                    <span class="status <?= htmlspecialchars($row['status']) ?>">
                      <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $row['status']))) ?>
                    </span>
                  </td>
                </tr>
                <?php endwhile; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div><!-- /.main -->
</div><!-- /.layout -->

</body>
</html>