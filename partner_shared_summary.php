<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    die('You must be logged in.');
}

$currentUserId = (int) $_SESSION['user_id'];
$targetUserId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
$error = '';
$summaryRows = [];

if ($targetUserId <= 0) {
    $error = 'A target user is required.';
} else {
    $connectionStmt = $conn->prepare(
        'SELECT connection_id
         FROM user_connections
         WHERE ((user_id = ? AND partner_user_id = ?) OR (user_id = ? AND partner_user_id = ?))
           AND status = "ACCEPTED"'
    );
    $connectionStmt->bind_param('iiii', $currentUserId, $targetUserId, $targetUserId, $currentUserId);
    $connectionStmt->execute();
    $connectionStmt->store_result();

    if ($connectionStmt->num_rows === 0) {
        $error = 'Access denied. The accountability connection must be ACCEPTED.';
    } else {
        // Basic progress metric: current streak of consecutive COMPLETED dates.
        $habitStmt = $conn->prepare(
            'SELECT h.habit_id, h.title, c.name AS category_name
             FROM habits h
             LEFT JOIN categories c ON h.category_id = c.category_id
             WHERE h.user_id = ? AND h.is_active = 1
             ORDER BY h.created_at DESC'
        );
        $habitStmt->bind_param('i', $targetUserId);
        $habitStmt->execute();
        $habitResult = $habitStmt->get_result();

        while ($habit = $habitResult->fetch_assoc()) {
            $streakStmt = $conn->prepare(
                'SELECT completion_date
                 FROM habit_completions
                 WHERE habit_id = ? AND status = "COMPLETED"
                 ORDER BY completion_date DESC'
            );
            $streakStmt->bind_param('i', $habit['habit_id']);
            $streakStmt->execute();
            $streakResult = $streakStmt->get_result();

            $streak = 0;
            $expectedDate = new DateTime();

            while ($dateRow = $streakResult->fetch_assoc()) {
                $completionDate = new DateTime($dateRow['completion_date']);

                // Allow streak to begin from today or yesterday depending on whether today's completion exists.
                if ($streak === 0) {
                    $today = new DateTime();
                    $yesterday = (new DateTime())->modify('-1 day');

                    if ($completionDate->format('Y-m-d') === $today->format('Y-m-d')) {
                        $streak = 1;
                        $expectedDate = (clone $today)->modify('-1 day');
                        continue;
                    }
                    if ($completionDate->format('Y-m-d') === $yesterday->format('Y-m-d')) {
                        $streak = 1;
                        $expectedDate = (clone $yesterday)->modify('-1 day');
                        continue;
                    }
                    break;
                }

                if ($completionDate->format('Y-m-d') === $expectedDate->format('Y-m-d')) {
                    $streak++;
                    $expectedDate->modify('-1 day');
                } else {
                    break;
                }
            }
            $streakStmt->close();

            $habit['streak_count'] = $streak;
            $summaryRows[] = $habit;
        }
        $habitStmt->close();
    }
    $connectionStmt->close();
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>HabitOS - Shared Habit Summary</title>
    <?php include 'system/header.php'; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
    <link rel="stylesheet" href="stylesheets/default.css">
</head>
<body>
<div class="w3-container mt-4">
    <h1>Accepted Partner Shared Habit Summary</h1>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Habit</th>
                        <th>Category</th>
                        <th>Current Streak</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($summaryRows)): ?>
                        <tr><td colspan="3">No shared habits are available.</td></tr>
                    <?php else: ?>
                        <?php foreach ($summaryRows as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['title']); ?></td>
                                <td><?php echo htmlspecialchars($row['category_name'] ?? 'Uncategorized'); ?></td>
                                <td><?php echo (int) $row['streak_count']; ?> day(s)</td>
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
