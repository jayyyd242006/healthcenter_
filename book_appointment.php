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

// ── Submit booking ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doctor_id   = (int) $_POST['doctor_id'];
    $schedule_id = (int) $_POST['schedule_id'];
    $appt_date   = $_POST['appointment_date'];
    $appt_time   = $_POST['appointment_time'];
    $reason      = trim($_POST['reason'] ?? '');

    if (!$doctor_id || !$schedule_id || !$appt_date || !$appt_time) {
        $error = "Please fill in all required fields.";
    } else {
        // Check for duplicate booking same day same doctor
        $chk = $conn->prepare("
            SELECT appointment_id FROM appointments
            WHERE patient_id = ? AND doctor_id = ? AND appointment_date = ?
            AND status NOT IN ('cancelled','no_show')
        ");
        $chk->bind_param("iis", $patient_id, $doctor_id, $appt_date);
        $chk->execute();
        $chk->store_result();

        if ($chk->num_rows > 0) {
            $error = "You already have an appointment with this doctor on that date.";
        } else {
            $stmt = $conn->prepare("
                INSERT INTO appointments (patient_id, doctor_id, schedule_id, appointment_date, appointment_time, reason, status)
                VALUES (?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->bind_param("iiisss", $patient_id, $doctor_id, $schedule_id, $appt_date, $appt_time, $reason);
            $stmt->execute() ? $success = "Appointment request submitted! Awaiting confirmation." : $error = "Booking failed. Please try again.";
        }
    }
}

// ── Fetch available doctors ──
$doctors = $conn->query("
    SELECT d.doctor_id, u.full_name, d.specialty, d.bio
    FROM doctors d
    JOIN users u ON d.user_id = u.user_id
    WHERE d.is_available = 1 AND u.is_active = 1
    ORDER BY d.specialty, u.full_name
");

// ── Fetch all schedules (Removed date restrictions so users can pick any existing schedules) ──
$schedules = $conn->query("
    SELECT s.schedule_id, s.doctor_id, s.schedule_date, s.start_time, s.end_time,
           s.slot_duration, s.max_patients,
           u.full_name AS doctor_name, d.specialty,
           (SELECT COUNT(*) FROM appointments a
            WHERE a.schedule_id = s.schedule_id AND a.status NOT IN ('cancelled','no_show')) AS booked
    FROM schedules s
    JOIN doctors d ON s.doctor_id = d.doctor_id
    JOIN users u   ON d.user_id   = u.user_id
    WHERE s.is_open = 1
    ORDER BY s.schedule_date ASC, s.start_time ASC
");

$active_page = 'book';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Book Appointment — HealthCenter</title>
  <link rel="stylesheet" href="global.css"/>
  <style>
    .booking-grid { display:grid; grid-template-columns:1fr 1fr; gap:24px; }
    @media(max-width:820px){ .booking-grid{ grid-template-columns:1fr; } }
    .doctor-cards { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:14px; margin-bottom:24px; }
    .doctor-card { background:white; border-radius:12px; padding:18px; border:2px solid var(--cream-dark); cursor:pointer; transition:border-color 0.2s, box-shadow 0.2s; }
    .doctor-card:hover { border-color:var(--green-light); box-shadow:0 4px 16px rgba(30,138,96,0.12); }
    .doctor-card.selected { border-color:var(--green-accent); background:var(--success-bg); }
    .doctor-card .doc-name { font-weight:600; font-size:0.95rem; margin-bottom:4px; }
    .doctor-card .doc-spec { font-size:0.78rem; color:var(--text-muted); }
    .doc-avatar { width:42px; height:42px; border-radius:50%; background:var(--green-deep); color:white; display:flex; align-items:center; justify-content:center; font-size:1rem; font-weight:700; margin-bottom:12px; }
    .schedule-list { display:flex; flex-direction:column; gap:10px; }
    .schedule-item { background:white; border:1.5px solid var(--cream-dark); border-radius:10px; padding:14px 18px; cursor:pointer; transition:border-color 0.2s; }
    .schedule-item:hover { border-color:var(--green-light); }
    .schedule-item.selected { border-color:var(--green-accent); background:var(--success-bg); }
    .schedule-item.full { opacity:0.5; cursor:not-allowed; }
    .sched-header { display:flex; justify-content:space-between; align-items:center; }
    .sched-doc { font-weight:600; font-size:0.9rem; }
    .sched-date { font-size:0.82rem; color:var(--text-muted); margin-top:3px; }
    .sched-slots { font-size:0.75rem; padding:3px 10px; border-radius:99px; font-weight:600; }
    .slots-ok   { background:var(--success-bg);  color:var(--success-text); }
    .slots-full { background:var(--error-bg);     color:var(--error-text);   }
    .form-group { margin-bottom:16px; }
    .form-group label { display:block; font-size:0.75rem; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; margin-bottom:7px; }
    .form-group input, .form-group select, .form-group textarea { width:100%; padding:11px 14px; border:1.5px solid var(--cream-dark); border-radius:8px; font-family:inherit; font-size:0.9rem; outline:none; }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color:var(--green-light); box-shadow:0 0 0 3px rgba(30,138,96,0.1); }
    .step-label { font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; color:var(--green-light); margin-bottom:10px; display:block; }
  </style>
</head>
<body>
<div class="layout">
  <?php include 'patient_sidebar.php'; ?>

  <div class="main">
    <div class="topbar">
      <div>
        <h1>Book Appointment</h1>
        <p>Select a doctor and available schedule slot</p>
      </div>
      <div class="topbar-date">📆 <?= date('l, F j, Y') ?></div>
    </div>

    <?php if ($success): ?>
      <div class="alert alert-success" style="margin-bottom:20px;">
        ✅ <?= htmlspecialchars($success) ?>
        <a href="my_appointments.php" style="margin-left:12px;font-weight:600;">View My Appointments →</a>
      </div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-error" style="margin-bottom:20px;">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" id="bookingForm">

      <div class="card" style="margin-bottom:20px;">
        <div class="card-header">
          <div>
            <span class="step-label">Step 1</span>
            <div class="card-title">Choose a Doctor</div>
          </div>
        </div>
        <div class="card-body">
          <div class="doctor-cards">
            <?php while ($doc = $doctors->fetch_assoc()): ?>
            <div class="doctor-card" onclick="selectDoctor(<?= $doc['doctor_id'] ?>, this)">
              <div class="doc-avatar"><?= strtoupper(substr($doc['full_name'],0,2)) ?></div>
              <div class="doc-name"><?= htmlspecialchars($doc['full_name']) ?></div>
              <div class="doc-spec">🩺 <?= htmlspecialchars($doc['specialty']) ?></div>
            </div>
            <?php endwhile; ?>
          </div>
          <input type="hidden" name="doctor_id" id="doctor_id" required/>
        </div>
      </div>

      <div class="card" style="margin-bottom:20px;">
        <div class="card-header">
          <div>
            <span class="step-label">Step 2</span>
            <div class="card-title">Choose a Schedule</div>
          </div>
        </div>
        <div class="card-body">
          <div class="schedule-list" id="scheduleList">
            <?php
            $all_schedules = [];
            while ($s = $schedules->fetch_assoc()) $all_schedules[] = $s;
            foreach ($all_schedules as $s):
              $available = $s['max_patients'] - $s['booked'];
              $is_full   = $available <= 0;
            ?>
            <div class="schedule-item <?= $is_full ? 'full' : '' ?>"
                 data-doctor="<?= $s['doctor_id'] ?>"
                 onclick="<?= $is_full ? '' : "selectSchedule({$s['schedule_id']}, '{$s['schedule_date']}', '{$s['start_time']}', this)" ?>">
              <div class="sched-header">
                <div>
                  <div class="sched-doc"><?= htmlspecialchars($s['doctor_name']) ?> — <?= htmlspecialchars($s['specialty']) ?></div>
                  <div class="sched-date">
                    📅 <?= date('l, M j, Y', strtotime($s['schedule_date'])) ?>
                    &nbsp;·&nbsp;
                    🕐 <?= date('g:i A', strtotime($s['start_time'])) ?> – <?= date('g:i A', strtotime($s['end_time'])) ?>
                  </div>
                </div>
                <span class="sched-slots <?= $is_full ? 'slots-full' : 'slots-ok' ?>">
                  <?= $is_full ? 'Full' : "$available slots left" ?>
                </span>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <input type="hidden" name="schedule_id"       id="schedule_id"/>
          <input type="hidden" name="appointment_date"  id="appointment_date"/>
          <input type="hidden" name="appointment_time"  id="appointment_time"/>
        </div>
      </div>

      <div class="card" style="margin-bottom:20px;">
        <div class="card-header">
          <div>
            <span class="step-label">Step 3</span>
            <div class="card-title">Reason for Visit</div>
          </div>
        </div>
        <div class="card-body">
          <div class="form-group">
            <label>Describe your concern (optional)</label>
            <textarea name="reason" rows="3" placeholder="e.g. Routine check-up, fever for 3 days, follow-up..."></textarea>
          </div>
          <button type="submit" class="btn-login" style="max-width:300px;">
            📅 Submit Appointment Request
          </button>
        </div>
      </div>

    </form>
  </div>
</div>

<script>
function selectDoctor(id, el) {
  document.querySelectorAll('.doctor-card').forEach(c => c.classList.remove('selected'));
  el.classList.add('selected');
  document.getElementById('doctor_id').value = id;

  // Filter schedules
  document.querySelectorAll('.schedule-item').forEach(s => {
    s.style.display = s.dataset.doctor == id ? '' : 'none';
  });
}

function selectSchedule(id, date, time, el) {
  document.querySelectorAll('.schedule-item').forEach(c => c.classList.remove('selected'));
  el.classList.add('selected');
  document.getElementById('schedule_id').value      = id;
  document.getElementById('appointment_date').value = date;
  document.getElementById('appointment_time').value = time;
}
</script>
</body>
</html>
