<?php
// Usage: include 'includes/admin_sidebar.php';
// Set $active_page before including, e.g. $active_page = 'appointments';
$active_page = $active_page ?? '';
?>
<aside class="sidebar">
  <div class="sidebar-header">🏥 HealthCenter</div>
  <nav class="sidebar-menu">
    <a href="admin_dashboard.php" <?= $active_page === 'dashboard'     ? 'class="active"' : '' ?>>🏠 Dashboard</a>
    <a href="appointments.php"    <?= $active_page === 'appointments'   ? 'class="active"' : '' ?>>📅 Appointments</a>
    <a href="patients.php"        <?= $active_page === 'patients'       ? 'class="active"' : '' ?>>👥 Patients</a>
    <a href="reports.php"         <?= $active_page === 'reports'        ? 'class="active"' : '' ?>>📊 Reports</a>
    <a href="settings.php"        <?= $active_page === 'settings'       ? 'class="active"' : '' ?>>⚙️ Settings</a>
    <a href="logout.php" class="logout">🚪 Logout</a>
  </nav>
</aside>