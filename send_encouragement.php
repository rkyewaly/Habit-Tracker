<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    die('You must be logged in.');
}

$currentUserId = (int) $_SESSION['user_id'];
$connectionId = isset($_GET['connection_id']) ? (int) $_GET['connection_id'] : (int) ($_POST['connection_id'] ?? 0);
$message = '';
$error = '';

// Verify the connection exists and is ACCEPTED for this user.
$connectionStmt = $conn->prepare(
    'SELECT connection_id, user_id, partner_user_id, status
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
    $error = 'You can only send encouragement to accepted connections.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    $messageText = trim($_POST['message_text'] ?? '');

    if ($messageText === '') {
        $error = 'Please enter a short message.';
    } elseif (mb_strlen($messageText) > 255) {
        $error = 'Please keep the message under 255 characters.';
    } else {
        $insertStmt = $conn->prepare(
            'INSERT INTO encouragement_messages (connection_id, sender_user_id, message_text)
             VALUES (?, ?, ?)'
        );
        $insertStmt->bind_param('iis', $connectionId, $currentUserId, $messageText);

        if ($insertStmt->execute()) {
            $message = 'Encouragement message sent and stored successfully.';
        } else {
            $error = 'Unable to save message: ' . $conn->error;
        }
        $insertStmt->close();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>HabitOS - Send Encouragement</title>
    <?php include 'system/header.php'; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
    <link rel="stylesheet" href="stylesheets/default.css">
</head>
<body>
<div class="w3-container mt-4">
    <h1>Send Encouragement</h1>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($message !== ''): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if ($error === '' || ($_SERVER['REQUEST_METHOD'] !== 'POST')): ?>
        <form action="send_encouragement.php" method="post">
            <input type="hidden" name="connection_id" value="<?php echo (int) $connectionId; ?>">
            <div class="mb-3">
                <label for="message_text" class="form-label">Short Encouragement Message</label>
                <textarea class="form-control" id="message_text" name="message_text" rows="4" maxlength="255" required></textarea>
            </div>
            <button type="submit" class="btn btn-outline-success">Send Message</button>
        </form>
    <?php endif; ?>
</div>
<?php include 'system/footer.php'; ?>
</body>
</html>
