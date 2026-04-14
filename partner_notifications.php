<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    die('You must be logged in.');
}

$currentUserId = (int) $_SESSION['user_id'];
$targetUserId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
$error = '';
$rows = [];

if ($targetUserId <= 0) {
    $error = 'A target user is required.';
} else {
    // Only allow access if users have an ACCEPTED connection.
    $accessStmt = $conn->prepare(
        'SELECT connection_id
         FROM user_connections
         WHERE ((user_id = ? AND partner_user_id = ?) OR (user_id = ? AND partner_user_id = ?))
           AND status = "ACCEPTED"'
    );
    $accessStmt->bind_param('iiii', $currentUserId, $targetUserId, $targetUserId, $currentUserId);
    $accessStmt->execute();
    $accessStmt->store_result();

    if ($accessStmt->num_rows === 0) {
        $error = 'Access denied. Only accepted accountability partners can view this notification output.';
    } else {
        $summarySql = '
            SELECT
                h.habit_id,
                h.title,
                COUNT(CASE WHEN hc.status = "COMPLETED" THEN 1 END) AS completed_count,
                COUNT(CASE WHEN hc.status = "MISSED" THEN 1 END) AS missed_count,
                COUNT(hc.completion_id) AS total_logged_days
            FROM habits h
            LEFT JOIN habit_completions hc ON h.habit_id = hc.habit_id
            WHERE h.user_id = ? AND h.is_active = 1
            GROUP BY h.habit_id, h.title
            ORDER BY h.created_at DESC';

        $summaryStmt = $conn->prepare($summarySql);
        $summaryStmt->bind_param('i', $targetUserId);
        $summaryStmt->execute();
        $result = $summaryStmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $summaryStmt->close();
    }
    $accessStmt->close();
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>HabitOS - Habit Notification Output</title>
    <?php include 'system/header.php'; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
    <link rel="stylesheet" href="stylesheets/default.css">
</head>
<body>
<div class="w3-container mt-4">
    <h1>Shared Habit Notification Output</h1>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php else: ?>
        <p class="text-muted">This page returns the selected user's habits and completion counts.</p>
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Habit</th>
                        <th>Completed</th>
                        <th>Missed</th>
                        <th>Total Logged</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="4">No habits found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['title']); ?></td>
                                <td><?php echo (int) $row['completed_count']; ?></td>
                                <td><?php echo (int) $row['missed_count']; ?></td>
                                <td><?php echo (int) $row['total_logged_days']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php include 'system/footer.php'; ?>
</body>
</html>
