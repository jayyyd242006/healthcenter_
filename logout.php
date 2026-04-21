<?php
session_start();

// Detect portal BEFORE clearing session
$redirect = isset($_SESSION['admin_logged_in']) ? 'admin_login.php' : 'patient_login.php';

// Clear session data
$_SESSION = [];

// Expire the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

header("Location: " . $redirect);
exit();