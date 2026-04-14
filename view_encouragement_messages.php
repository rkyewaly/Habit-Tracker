<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    die('You must be logged in.');
}

$currentUserId = (int) $_SESSION['user_id'];
$connectionId = isset($_GET['connection_id']) ? (int) $_GET['connection_id'] : 0;
$error = '';
$messages = [];

$connectionStmt = $conn->prepare(
    'SELECT connection_id, status
     FROM user_connections
     WHERE connection_id = ?
       AND (user_id = ? OR partner_user_id = ?)'
);
$connectionStmt->bind_param('iii', $connectionId, $currentUserId, $currentUserId);
$connectionStmt->execute();
$connectionResult = $connectionStmt->get_result();
$connection = $connectionResult->fetch_assoc();
$connectionStmt->close();

if (!$connection) {
    $error = 'Connection not found.';
} elseif ($connection['status'] !== 'ACCEPTED') {
    $error = 'Only accepted connections can view messages.';
} else {
    $messageStmt = $conn->prepare(
        'SELECT em.message_text, em.sent_at, u.full_name AS sender_name
         FROM encouragement_messages em
         INNER JOIN users u ON em.sender_user_id = u.user_id
         WHERE em.connection_id = ?
         ORDER BY em.sent_at DESC'
    );
    $messageStmt->bind_param('i', $connectionId);
    $messageStmt->execute();
    $result = $messageStmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    $messageStmt->close();
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>HabitOS - Encouragement Messages</title>
    <?php include 'system/header.php'; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
    <link rel="stylesheet" href="stylesheets/default.css">
</head>
<body>
<div class="w3-container mt-4">
    <h1>Encouragement Messages</h1>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php else: ?>
        <?php if (empty($messages)): ?>
            <div class="alert alert-info">No encouragement messages yet.</div>
        <?php else: ?>
            <?php foreach ($messages as $msg): ?>
                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($msg['sender_name']); ?></h5>
                        <p class="card-text"><?php echo nl2br(htmlspecialchars($msg['message_text'])); ?></p>
                        <p class="card-text"><small class="text-muted"><?php echo htmlspecialchars($msg['sent_at']); ?></small></p>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php include 'system/footer.php'; ?>
</body>
</html>
