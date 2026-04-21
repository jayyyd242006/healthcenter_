<?php
session_start();
require 'database.php';

if (!isset($_SESSION['patient_logged_in']) || $_SESSION['patient_logged_in'] !== true) {
    header('Location: patient_login.php');
    exit();
}

$patient_id = (int) $_SESSION['patient_id'];

// Fetch medical records
$stmt = $conn->prepare("
    SELECT mr.record_id, mr.diagnosis, mr.prescription, mr.lab_requests,
           mr.follow_up_date, mr.created_at,
           a.appointment_date, a.appointment_time, a.reason,
           u.full_name AS doctor_name, d.specialty
    FROM medical_records mr
    JOIN appointments a ON mr.appointment_id = a.appointment_id
    JOIN doctors d      ON mr.doctor_id      = d.doctor_id
    JOIN users u        ON d.user_id         = u.user_id
    WHERE mr.patient_id = ?
    ORDER BY mr.created_at DESC
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$records = $stmt->get_result();

$active_page = 'records';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Medical Records — HealthCenter</title>
  <link rel="stylesheet" href="global.css"/>
  <style>
    .record-card { background:white; border-radius:12px; border:1px solid rgba(0,0,0,0.05); margin-bottom:16px; overflow:hidden; }
    .record-header { padding:16px 20px; background:var(--green-deep); color:white; display:flex; justify-content:space-between; align-items:center; }
    .record-header .doc { font-weight:600; font-size:0.95rem; }
    .record-header .meta { font-size:0.78rem; opacity:0.75; margin-top:3px; }
    .record-date { font-size:0.78rem; opacity:0.75; }
    .record-body { padding:20px; display:grid; grid-template-columns:1fr 1fr; gap:20px; }
    @media(max-width:600px){ .record-body{ grid-template-columns:1fr; } }
    .record-field label { font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:var(--text-muted); display:block; margin-bottom:6px; }
    .record-field p { font-size:0.9rem; color:var(--text-dark); line-height:1.6; margin:0; }
    .empty-state { text-align:center; padding:60px 20px; color:var(--text-muted); }
    .empty-state .icon { font-size:3rem; margin-bottom:16px; }
    .empty-state p { font-size:0.9rem; margin-bottom:20px; }
  </style>
</head>
<body>
<div class="layout">
  <?php include 'patient_sidebar.php'; ?>

  <div class="main">
    <div class="topbar">
      <div>
        <h1>Medical Records</h1>
        <p>Your complete health history from all visits</p>
      </div>
    </div>

    <?php if ($records->num_rows === 0): ?>
      <div class="empty-state">
        <div class="icon">🧾</div>
        <p>No medical records yet. Records will appear here after your appointments are completed by a doctor.</p>
        <a href="book_appointment.php" class="btn">📅 Book an Appointment</a>
      </div>
    <?php else: while ($row = $records->fetch_assoc()): ?>

    <div class="record-card">
      <div class="record-header">
        <div>
          <div class="doc">👨‍⚕️ <?= htmlspecialchars($row['doctor_name']) ?> — <?= htmlspecialchars($row['specialty']) ?></div>
          <div class="meta">
            Visit: <?= date('F j, Y', strtotime($row['appointment_date'])) ?>
            at <?= date('g:i A', strtotime($row['appointment_time'])) ?>
            <?php if ($row['reason']): ?> · <?= htmlspecialchars($row['reason']) ?><?php endif; ?>
          </div>
        </div>
        <div class="record-date">Recorded: <?= date('M j, Y', strtotime($row['created_at'])) ?></div>
      </div>

      <div class="record-body">
        <div class="record-field">
          <label>🔍 Diagnosis</label>
          <p><?= $row['diagnosis'] ? nl2br(htmlspecialchars($row['diagnosis'])) : '<em style="color:var(--text-muted)">Not recorded</em>' ?></p>
        </div>
        <div class="record-field">
          <label>💊 Prescription</label>
          <p><?= $row['prescription'] ? nl2br(htmlspecialchars($row['prescription'])) : '<em style="color:var(--text-muted)">None</em>' ?></p>
        </div>
        <div class="record-field">
          <label>🧪 Lab Requests</label>
          <p><?= $row['lab_requests'] ? nl2br(htmlspecialchars($row['lab_requests'])) : '<em style="color:var(--text-muted)">None</em>' ?></p>
        </div>
        <div class="record-field">
          <label>📅 Follow-up Date</label>
          <p><?= $row['follow_up_date'] ? date('F j, Y', strtotime($row['follow_up_date'])) : '<em style="color:var(--text-muted)">None scheduled</em>' ?></p>
        </div>
      </div>
    </div>

    <?php endwhile; endif; ?>
  </div>
</div>
</body>
</html>