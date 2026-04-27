<?php
// ── DB connection ──────────────────────────────────────────────────────────
// XAMPP defaults. Change $pass if you set a root password in phpMyAdmin.
$host   = "127.0.0.1";
$dbname = "habitos_db";
$user   = "root";
$pass   = "";

// MYSQLI_REPORT_OFF so existing if($stmt->execute()) checks work without
// uncaught exceptions (e.g. duplicate-email in register.php).
mysqli_report(MYSQLI_REPORT_OFF);

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("DB connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
