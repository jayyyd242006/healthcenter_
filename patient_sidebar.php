<?php
// Usage: include 'includes/patient_sidebar.php';
// Set $active_page before including, e.g. $active_page = 'book';
$active_page  = $active_page ?? '';
$patient_name = $_SESSION['patient_name'] ?? 'Patient';
?>
<div class="sidebar">
  <div class="sidebar-header">🏥 HealthCenter</div>
  <p class="user">👋 <?= htmlspecialchars($patient_name) ?></p>
  <nav>
    <a href="patient_dashboard.php"  <?= $active_page === 'dashboard'      ? 'class="active"' : '' ?>>🏠 Dashboard</a>
    <a href="book_appointment.php"   <?= $active_page === 'book'           ? 'class="active"' : '' ?>>📅 Book Appointment</a>
    <a href="my_appointments.php"    <?= $active_page === 'appointments'   ? 'class="active"' : '' ?>>📋 My Appointments</a>
    <a href="medical_records.php"    <?= $active_page === 'records'        ? 'class="active"' : '' ?>>🧾 Medical Records</a>
    <a href="notifications.php"      <?= $active_page === 'notifications'  ? 'class="active"' : '' ?>>🔔 Notifications</a>
    <a href="profile.php"            <?= $active_page === 'profile'        ? 'class="active"' : '' ?>>👤 My Profile</a>
    <a href="logout.php" class="logout">🚪 Logout</a>
  </nav>
</div>