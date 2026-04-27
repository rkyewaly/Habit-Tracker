<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log'); // Habit-Tracker/php_errors.log

echo "STEP A: db_test.php loaded ✅<br>";

require_once __DIR__ . "/includes/db.php";

echo "STEP B: db.php included ✅<br>";

if (!isset($conn)) {
  die("ERROR: \$conn is not set (db.php did not create it).");
}

$res = $conn->query("SELECT DATABASE() AS db");
$row = $res ? $res->fetch_assoc() : null;
echo "Connected DB: " . htmlspecialchars($row["db"] ?? "unknown") . "<br><br>";

echo "<b>Tables:</b><br>";
$res = $conn->query("SHOW TABLES");
if (!$res) {
  die("SHOW TABLES failed: " . $conn->error);
}
while ($r = $res->fetch_row()) {
  echo htmlspecialchars($r[0]) . "<br>";
}