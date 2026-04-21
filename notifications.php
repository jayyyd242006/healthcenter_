<?php
session_start();
require 'database.php';

if (!isset($_SESSION['patient_logged_in']) || $_SESSION['patient_logged_in'] !== true) {
    header('Location: patient_login.php');
    exit();
}

$user_id    = (int) $_SESSION['patient_user_id'];
$patient_id = (int) $_SESSION['patient_id'];

// ── Mark all as read (mark sent) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    $stmt = $conn->prepare("UPDATE notifications SET is_sent = 1 WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
}

// ── Mark single as read ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_id'])) {
    $nid = (int) $_POST['mark_id'];
    $stmt = $conn->prepare("UPDATE notifications SET is_sent = 1 WHERE notification_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $nid, $user_id);
    $stmt->execute();
}

// ── Fetch notifications ──
$stmt = $conn->prepare("
    SELECT n.notification_id, n.type, n.subject, n.message, n.is_sent, n.created_at,
           a.appointment_date, a.appointment_time
    FROM notifications n
    LEFT JOIN appointments a ON n.appointment_id = a.appointment_id
    WHERE n.user_id = ?
    ORDER BY n.created_at DESC
    LIMIT 50
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifs = $stmt->get_result();

// ── Unread count ──
$stmt2 = $conn->prepare("SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND is_sent = 0");
$stmt2->bind_param("i", $user_id);
$stmt2->execute();
$unread = $stmt2->get_result()->fetch_assoc()['c'];

$active_page = 'notifications';

$type_icons = [
  'confirmation' => '✅',
  'reminder'     => '⏰',
  'cancellation' => '❌',
  'reschedule'   => '🔄',
  'general'      => '📢',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Notifications — HealthCenter</title>
  <link rel="stylesheet" href="global.css"/>
  <style>
    .notif-item { background:white; border-radius:10px; padding:16px 20px; margin-bottom:10px; border:1px solid rgba(0,0,0,0.05); display:flex; gap:14px; align-items:flex-start; transition:background 0.2s; }
    .notif-item.unread { background:#f0fff6; border-color:var(--green-accent); }
    .notif-icon { font-size:1.4rem; flex-shrink:0; margin-top:2px; }
    .notif-content { flex:1; }
    .notif-subject { font-weight:600; font-size:0.92rem; margin-bottom:4px; }
    .notif-message { font-size:0.85rem; color:var(--text-muted); line-height:1.55; }
    .notif-meta { font-size:0.75rem; color:var(--text-muted); margin-top:8px; }
    .notif-actions { display:flex; align-items:center; gap:8px; flex-shrink:0; }
    .btn-read { background:none; border:1px solid var(--cream-dark); color:var(--text-muted); padding:4px 10px; border-radius:6px; font-size:0.75rem; cursor:pointer; }
    .btn-read:hover { border-color:var(--green-light); color:var(--green-light); }
    .unread-dot { width:8px; height:8px; background:var(--green-accent); border-radius:50%; flex-shrink:0; margin-top:6px; }
    .empty-state { text-align:center; padding:60px 20px; color:var(--text-muted); }
    .empty-state .icon { font-size:3rem; margin-bottom:16px; }
    .header-actions { display:flex; align-items:center; gap:12px; }
  </style>
</head>
<body>
<div class="layout">
  <?php include 'patient_sidebar.php'; ?>

  <div class="main">
    <div class="topbar">
      <div>
        <h1>Notifications</h1>
        <p><?= $unread > 0 ? "$unread unread notification" . ($unread > 1 ? 's' : '') : 'All caught up!' ?></p>
      </div>
      <?php if ($unread > 0): ?>
      <div class="header-actions">
        <form method="POST">
          <input type="hidden" name="mark_all_read" value="1"/>
          <button type="submit" class="btn btn-outline">✓ Mark all as read</button>
        </form>
      </div>
      <?php endif; ?>
    </div>

    <?php if ($notifs->num_rows === 0): ?>
      <div class="empty-state">
        <div class="icon">🔔</div>
        <p>No notifications yet. You'll be notified when your appointment status changes.</p>
      </div>
    <?php else: while ($row = $notifs->fetch_assoc()):
        $icon = $type_icons[$row['type']] ?? '📢';
        $is_unread = !$row['is_sent'];
    ?>
    <div class="notif-item <?= $is_unread ? 'unread' : '' ?>">
      <div class="notif-icon"><?= $icon ?></div>
      <div class="notif-content">
        <?php if ($row['subject']): ?>
          <div class="notif-subject"><?= htmlspecialchars($row['subject']) ?></div>
        <?php endif; ?>
        <div class="notif-message"><?= nl2br(htmlspecialchars($row['message'])) ?></div>
        <div class="notif-meta">
          <?= ucfirst($row['type']) ?> ·
          <?= date('M j, Y g:i A', strtotime($row['created_at'])) ?>
          <?php if ($row['appointment_date']): ?>
            · Appt: <?= date('M j, Y', strtotime($row['appointment_date'])) ?>
          <?php endif; ?>
        </div>
      </div>
      <?php if ($is_unread): ?>
      <div class="notif-actions">
        <div class="unread-dot"></div>
        <form method="POST">
          <input type="hidden" name="mark_id" value="<?= $row['notification_id'] ?>"/>
          <button type="submit" class="btn-read">Mark read</button>
        </form>
      </div>
      <?php endif; ?>
    </div>
    <?php endwhile; endif; ?>

  </div>
</div>
</body>
</html>