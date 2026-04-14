<?php
session_start();
require_once 'db_connect.php';

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$error = '';
$success = '';
$invitation = null;

if ($token === '') {
    die('Invitation token is missing.');
}

$inviteStmt = $conn->prepare(
    'SELECT invitation_id, inviter_user_id, invitee_email, status, expires_at
     FROM user_invitations
     WHERE invitation_token = ?'
);
$inviteStmt->bind_param('s', $token);
$inviteStmt->execute();
$result = $inviteStmt->get_result();
$invitation = $result->fetch_assoc();
$inviteStmt->close();

if (!$invitation) {
    die('Invitation not found.');
}

if ($invitation['status'] !== 'PENDING') {
    die('This invitation is no longer active.');
}

if (strtotime($invitation['expires_at']) < time()) {
    $expireStmt = $conn->prepare('UPDATE user_invitations SET status = "EXPIRED" WHERE invitation_id = ?');
    $expireStmt->bind_param('i', $invitation['invitation_id']);
    $expireStmt->execute();
    $expireStmt->close();
    die('This invitation has expired.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Please complete all fields correctly.';
    } elseif (strcasecmp($email, $invitation['invitee_email']) !== 0) {
        $error = 'The email must match the invited email address.';
    } else {
        $checkUserStmt = $conn->prepare('SELECT user_id FROM users WHERE email = ?');
        $checkUserStmt->bind_param('s', $email);
        $checkUserStmt->execute();
        $checkUserStmt->store_result();

        if ($checkUserStmt->num_rows > 0) {
            $error = 'An account already exists for this email.';
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $role = 'PARTNER';

            $conn->begin_transaction();
            try {
                $insertUserStmt = $conn->prepare(
                    'INSERT INTO users (full_name, email, password_hash, role)
                     VALUES (?, ?, ?, ?)'
                );
                $insertUserStmt->bind_param('ssss', $fullName, $email, $passwordHash, $role);
                $insertUserStmt->execute();
                $newUserId = $insertUserStmt->insert_id;
                $insertUserStmt->close();

                $insertConnectionStmt = $conn->prepare(
                    'INSERT INTO user_connections (user_id, partner_user_id, status)
                     VALUES (?, ?, "PENDING")'
                );
                $insertConnectionStmt->bind_param('ii', $invitation['inviter_user_id'], $newUserId);
                $insertConnectionStmt->execute();
                $insertConnectionStmt->close();

                $updateInviteStmt = $conn->prepare(
                    'UPDATE user_invitations
                     SET status = "REGISTERED", accepted_user_id = ?
                     WHERE invitation_id = ?'
                );
                $updateInviteStmt->bind_param('ii', $newUserId, $invitation['invitation_id']);
                $updateInviteStmt->execute();
                $updateInviteStmt->close();

                $insertPrefsStmt = $conn->prepare(
                    'INSERT INTO user_preferences (user_id, nudges_enabled) VALUES (?, 1)'
                );
                $insertPrefsStmt->bind_param('i', $newUserId);
                $insertPrefsStmt->execute();
                $insertPrefsStmt->close();

                $conn->commit();
                $success = 'Account created from invitation. A PENDING connection was also created.';
            } catch (Throwable $e) {
                $conn->rollback();
                $error = 'Registration failed: ' . $e->getMessage();
            }
        }
        $checkUserStmt->close();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>HabitOS - Register with Invitation</title>
    <?php include 'system/header.php'; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
    <link rel="stylesheet" href="stylesheets/default.css">
</head>
<body>
<div class="w3-container mt-4">
    <h1>Register Your Partner Account</h1>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success !== ''): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form action="register_with_invite.php" method="post">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
        <div class="mb-3">
            <label for="full_name" class="form-label">Full Name</label>
            <input type="text" class="form-control" id="full_name" name="full_name" required>
        </div>
        <div class="mb-3">
            <label for="email" class="form-label">Invited Email</label>
            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($invitation['invitee_email']); ?>" required>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input type="password" class="form-control" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-outline-success">Create Account</button>
    </form>
</div>
<?php include 'system/footer.php'; ?>
</body>
</html>
