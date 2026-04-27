<?php
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/auth.php";
require_role("PARTNER");

$partner_id = current_user_id();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    // SEND INVITE
    if ($action === "invite") {
        $email = trim($_POST["email"] ?? "");

        if ($email === "") {
            flash_set("Email required.", "bad");
        } else {
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res->num_rows === 0) {
                flash_set("User not found.", "bad");
            } else {
                $target_id = (int)$res->fetch_assoc()["user_id"];

                if ($target_id === $partner_id) {
                    flash_set("You cannot invite yourself.", "bad");
                } else {
                    $stmt = $conn->prepare("
                        INSERT IGNORE INTO user_connections (user_id, partner_user_id, status)
                        VALUES (?, ?, 'PENDING')
                    ");
                    $stmt->bind_param("ii", $partner_id, $target_id);
                    $stmt->execute();

                    if ($stmt->affected_rows > 0) {
                        flash_set("Invitation sent.", "ok");
                    } else {
                        flash_set("Invitation already exists.", "bad");
                    }
                }
            }
        }

        header("Location: partner.php");
        exit;
    }

    // ACCEPT / REJECT INVITE
    if ($action === "respond_invite") {
        $connection_id = (int)($_POST["connection_id"] ?? 0);
        $response = $_POST["response"] ?? "";

        if (!in_array($response, ["ACCEPTED", "REJECTED"], true)) {
            flash_set("Invalid response.", "bad");
        } else {
            $stmt = $conn->prepare("
                UPDATE user_connections
                SET status = ?
                WHERE connection_id = ?
                  AND partner_user_id = ?
            ");
            $stmt->bind_param("sii", $response, $connection_id, $partner_id);
            $stmt->execute();

            flash_set("Response saved.", "ok");
        }

        header("Location: partner.php");
        exit;
    }
}

// ACCEPTED PARTNERS
$stmt = $conn->prepare("
    SELECT u.full_name, u.email
    FROM user_connections c
    JOIN users u ON u.user_id = c.partner_user_id
    WHERE c.user_id = ? AND c.status = 'ACCEPTED'
");
$stmt->bind_param("i", $partner_id);
$stmt->execute();
$accepted = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// PENDING INVITES (INCOMING)
$stmt = $conn->prepare("
    SELECT c.connection_id, u.full_name, u.email
    FROM user_connections c
    JOIN users u ON u.user_id = c.user_id
    WHERE c.partner_user_id = ? AND c.status = 'PENDING'
");
$stmt->bind_param("i", $partner_id);
$stmt->execute();
$pending = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = "HabitOS — Partner View";
require_once __DIR__ . "/includes/header.php";
?>

<div class="grid">

  <!-- INVITE CARD -->
  <div class="card">
    <h2 class="h2">Invite Another Partner</h2>
    <p class="p">Send an invitation to another user.</p>

    <form method="POST">
      <input type="hidden" name="action" value="invite">

      <label class="label">User Email</label>
      <input class="input" type="email" name="email" placeholder="user@email.com" required>

      <div style="margin-top:14px;">
        <button class="btn btnPrimary">Send Invite</button>
      </div>
    </form>
  </div>

  <!-- ACCEPTED CONNECTIONS -->
  <div class="card">
    <h2 class="h2">Accepted Partners</h2>

    <?php if (empty($accepted)): ?>
      <p class="p">No accepted partners yet.</p>
    <?php else: ?>
      <table class="table">
        <thead>
          <tr><th>Name</th><th>Email</th></tr>
        </thead>
        <tbody>
        <?php foreach ($accepted as $p): ?>
          <tr>
            <td><?php echo htmlspecialchars($p["full_name"]); ?></td>
            <td><?php echo htmlspecialchars($p["email"]); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

</div>

<div class="card" style="margin-top:20px;">
  <h2 class="h2">Pending Invitations</h2>

  <?php if (empty($pending)): ?>
    <p class="p">No pending invitations.</p>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr><th>Name</th><th>Email</th><th>Action</th></tr>
      </thead>
      <tbody>
      <?php foreach ($pending as $p): ?>
        <tr>
          <td><?php echo htmlspecialchars($p["full_name"]); ?></td>
          <td><?php echo htmlspecialchars($p["email"]); ?></td>
          <td>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="action" value="respond_invite">
              <input type="hidden" name="connection_id" value="<?php echo $p["connection_id"]; ?>">
              <input type="hidden" name="response" value="ACCEPTED">
              <button class="btn btnPrimary">Accept</button>
            </form>

            <form method="POST" style="display:inline;">
              <input type="hidden" name="action" value="respond_invite">
              <input type="hidden" name="connection_id" value="<?php echo $p["connection_id"]; ?>">
              <input type="hidden" name="response" value="REJECTED">
              <button class="btn btnDanger">Reject</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . "/includes/footer.php"; ?>