<?php
session_start();
require_once 'db_connect.php';

// Assumes the logged-in user id is stored in session.
if (!isset($_SESSION['user_id'])) {
    die('You must be logged in to send invitations.');
}

$inviterUserId = (int) $_SESSION['user_id'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inviteeEmail = trim($_POST['invitee_email'] ?? '');

    if (!filter_var($inviteeEmail, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
    } else {
        // Stop duplicate pending invitations from the same inviter to the same email.
        $checkStmt = $conn->prepare(
            'SELECT invitation_id
             FROM user_invitations
             WHERE inviter_user_id = ? AND invitee_email = ? AND status = "PENDING"'
        );
        $checkStmt->bind_param('is', $inviterUserId, $inviteeEmail);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {
            $message = 'A pending invitation already exists for this email.';
        } else {
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));

            $insertStmt = $conn->prepare(
                'INSERT INTO user_invitations (inviter_user_id, invitee_email, invitation_token, expires_at)
                 VALUES (?, ?, ?, ?)'
            );
            $insertStmt->bind_param('isss', $inviterUserId, $inviteeEmail, $token, $expiresAt);

            if ($insertStmt->execute()) {
                // Replace this with real email sending if the project already has mail support.
                $inviteLink = 'http://localhost/HabitOS/register_with_invite.php?token=' . urlencode($token);
                $message = 'Invitation created. Share this registration link: ' . htmlspecialchars($inviteLink);
            } else {
                $message = 'Unable to create invitation: ' . $conn->error;
            }
            $insertStmt->close();
        }
        $checkStmt->close();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>HabitOS - Send Invitation</title>
    <?php include 'system/header.php'; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
    <link rel="stylesheet" href="stylesheets/default.css">
</head>
<body>
<div class="w3-container mt-4">
    <h1>Send Account Invitation</h1>
    <?php if ($message !== ''): ?>
        <div class="alert alert-info"><?php echo $message; ?></div>
    <?php endif; ?>

    <form action="send_invite.php" method="post" class="mt-3">
        <div class="mb-3">
            <label for="invitee_email" class="form-label">Invitee Email</label>
            <input type="email" class="form-control" id="invitee_email" name="invitee_email" required>
        </div>
        <button type="submit" class="btn btn-outline-success">Create Invite</button>
    </form>
</div>
<?php include 'system/footer.php'; ?>
</body>
</html>
