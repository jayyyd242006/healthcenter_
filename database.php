<?php
$host     = "localhost";
$db_user  = "root";
$db_pass  = "";
$database = "health_center_db";

$conn = new mysqli($host, $db_user, $db_pass, $database);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");