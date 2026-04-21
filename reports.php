<?php
session_start();
require 'database.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit();
}

// ── Date range filter ──
$from = $_GET['from'] ?? date('Y-m-01');        // default: start of this month
$to   = $_GET['to']   ?? date('Y-m-d');         // default: today

// ── Summary counts ──
$stmt = $conn->prepare("SELECT status, COUNT(*) AS total FROM appointments WHERE appointment_date BETWEEN ? AND ? GROUP BY status");
$stmt->bind_param("ss", $from, $to);
$stmt->execute();
$status_rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$status_map = ['pending'=>0,'confirmed'=>0,'completed'=>0,'cancelled'=>0,'no_show'=>0];
foreach ($status_rows as $r) $status_map[$r['status']] = $r['total'];
$total_appts = array_sum($status_map);

// ── By doctor ──
$stmt = $conn->prepare("
    SELECT du.full_name AS doctor_name, d.specialty,
           COUNT(*) AS total,
           SUM(a.status = 'completed') AS completed,
           SUM(a.status = 'cancelled') AS cancelled,
           SUM(a.status = 'no_show')   AS no_show
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.doctor_id
    JOIN users du  ON d.user_id   = du.user_id
    WHERE a.appointment_date BETWEEN ? AND ?
    GROUP BY a.doctor_id
    ORDER BY total DESC
");
$stmt->bind_param("ss", $from, $to);
$stmt->execute();
$by_doctor = $stmt->get_result();

// ── By day (last 7 days within range) ──
$stmt = $conn->prepare("
    SELECT appointment_date, COUNT(*) AS total
    FROM appointments
    WHERE appointment_date BETWEEN ? AND ?
    GROUP BY appointment_date
    ORDER BY appointment_date ASC
");
$stmt->bind_param("ss", $from, $to);
$stmt->execute();
$by_day = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ── New patients in range ──
$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM users WHERE role = 'patient' AND DATE(created_at) BETWEEN ? AND ?");
$stmt->bind_param("ss", $from, $to);
$stmt->execute();
$new_patients = $stmt->get_result()->fetch_assoc()['c'];

$active_page = 'reports';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Reports — HealthCenter Admin</title>
  <link rel="stylesheet" href="global.css"/>
  <style>
    .filter-bar { display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:24px; }
    .filter-bar input { padding:9px 14px; border:1.5px solid var(--cream-dark); border-radius:8px; font-family:inherit; font-size:0.87rem; background:white; outline:none; }
    .filter-bar input:focus { border-color:var(--green-light); }
    .report-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:16px; margin-bottom:24px; }
    .report-card { background:white; border-radius:12px; padding:20px; border:1px solid rgba(0,0,0,0.05); text-align:center; }
    .report-card .big { font-family:'DM Serif Display',serif; font-size:2.4rem; color:var(--green-deep); }
    .report-card .lbl { font-size:0.78rem; color:var(--text-muted); margin-top:4px; text-transform:uppercase; letter-spacing:0.05em; }
    .bar-wrap { margin-top:8px; height:8px; background:var(--cream-dark); border-radius:99px; overflow:hidden; }
    .bar-fill  { height:100%; background:var(--green-accent); border-radius:99px; transition:width 0.6s; }
  </style>
</head>
<body>
<div class="admin-layout">
  <?php include 'admin_sidebar.php'; ?>

  <div class="main-content">
    <div class="topbar">
      <div>
        <h1>Reports</h1>
        <p>Appointment statistics and clinic insights</p>
      </div>
    </div>

    <!-- DATE RANGE -->
    <form method="GET" class="filter-bar">
      <label style="font-size:0.82rem;font-weight:600;color:var(--text-muted);">From</label>
      <input type="date" name="from" value="<?= htmlspecialchars($from) ?>"/>
      <label style="font-size:0.82rem;font-weight:600;color:var(--text-muted);">To</label>
      <input type="date" name="to" value="<?= htmlspecialchars($to) ?>"/>
      <button type="submit" class="btn">Generate</button>
      <a href="reports.php" class="btn btn-outline">This Month</a>
    </form>

    <!-- SUMMARY CARDS -->
    <div class="report-grid">
      <div class="report-card">
        <div class="big"><?= $total_appts ?></div>
        <div class="lbl">Total Appointments</div>
      </div>
      <div class="report-card">
        <div class="big" style="color:var(--success-text);"><?= $status_map['completed'] ?></div>
        <div class="lbl">Completed</div>
      </div>
      <div class="report-card">
        <div class="big" style="color:#b7791f;"><?= $status_map['pending'] ?></div>
        <div class="lbl">Pending</div>
      </div>
      <div class="report-card">
        <div class="big" style="color:#2b6cb0;"><?= $status_map['confirmed'] ?></div>
        <div class="lbl">Confirmed</div>
      </div>
      <div class="report-card">
        <div class="big" style="color:var(--error-text);"><?= $status_map['cancelled'] ?></div>
        <div class="lbl">Cancelled</div>
      </div>
      <div class="report-card">
        <div class="big" style="color:#6b21a8;"><?= $status_map['no_show'] ?></div>
        <div class="lbl">No Shows</div>
      </div>
      <div class="report-card">
        <div class="big"><?= $new_patients ?></div>
        <div class="lbl">New Patients</div>
      </div>
    </div>

    <!-- DAILY BREAKDOWN -->
    <?php if (count($by_day) > 0): ?>
    <div class="card" style="margin-bottom:20px;">
      <div class="card-header">
        <div class="card-title">Daily Appointment Volume</div>
        <div class="card-subtitle"><?= date('M j', strtotime($from)) ?> — <?= date('M j, Y', strtotime($to)) ?></div>
      </div>
      <div class="card-body" style="padding:0;">
        <div class="table-wrap">
          <table>
            <thead>
              <tr><th>Date</th><th>Day</th><th>Appointments</th><th>Volume</th></tr>
            </thead>
            <tbody>
              <?php
              $max_day = max(array_column($by_day,'total')) ?: 1;
              foreach ($by_day as $d):
                $pct = round($d['total'] / $max_day * 100);
              ?>
              <tr>
                <td><?= date('M j, Y', strtotime($d['appointment_date'])) ?></td>
                <td><?= date('l', strtotime($d['appointment_date'])) ?></td>
                <td><?= $d['total'] ?></td>
                <td style="width:200px;">
                  <div class="bar-wrap"><div class="bar-fill" style="width:<?= $pct ?>%"></div></div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- BY DOCTOR -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">Performance by Doctor</div>
      </div>
      <div class="card-body" style="padding:0;">
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Doctor</th>
                <th>Specialty</th>
                <th>Total</th>
                <th>Completed</th>
                <th>Cancelled</th>
                <th>No Show</th>
                <th>Completion Rate</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($by_doctor->num_rows === 0): ?>
                <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:32px;">No data for this period.</td></tr>
              <?php else: while ($row = $by_doctor->fetch_assoc()):
                $rate = $row['total'] > 0 ? round($row['completed'] / $row['total'] * 100) : 0;
              ?>
              <tr>
                <td><?= htmlspecialchars($row['doctor_name']) ?></td>
                <td><?= htmlspecialchars($row['specialty']) ?></td>
                <td><?= $row['total'] ?></td>
                <td><span class="status completed"><?= $row['completed'] ?></span></td>
                <td><span class="status cancelled"><?= $row['cancelled'] ?></span></td>
                <td><span class="status no_show"><?= $row['no_show'] ?></span></td>
                <td>
                  <?= $rate ?>%
                  <div class="bar-wrap"><div class="bar-fill" style="width:<?= $rate ?>%"></div></div>
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