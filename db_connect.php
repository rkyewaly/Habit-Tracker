<?php
// Simple mysqli connection file.
// Update these values for your local environment.
$host = '127.0.0.1';
$db   = 'habitos_db';
$user = 'root';
$pass = '';
$port = 3306;

$conn = new mysqli($host, $user, $pass, $db, $port);
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}
$conn->set_charset('utf8mb4');
?>
