<?php
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/auth.php";
require_role("HABIT_USER");

$user_id = current_user_id();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if ($action === "invite_partner") {
        $email = trim($_POST["email"] ?? "");

        if ($email === "") {
            flash_set("Partner email is required.", "bad");
            header("Location: habit-user.php");
            exit;
        }

        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND role = 'PARTNER'");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            flash_set("No partner account was found with that email.", "bad");
            header("Location: habit-user.php");
            exit;
        }

        $partner_id = (int)$result->fetch_assoc()["user_id"];

        if ($partner_id === $user_id) {
            flash_set("You cannot invite yourself.", "bad");
            header("Location: habit-user.php");
            exit;
        }

        $stmt = $conn->prepare("SELECT connection_id, status FROM user_connections WHERE user_id = ? AND partner_user_id = ?");
        $stmt->bind_param("ii", $user_id, $partner_id);
        $stmt->execute();
        $existing = $stmt->get_result();

        if ($existing->num_rows > 0) {
            $row = $existing->fetch_assoc();
            flash_set("A connection already exists with status: " . $row["status"], "bad");
            header("Location: habit-user.php");
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO user_connections (user_id, partner_user_id, status) VALUES (?, ?, 'PENDING')");
        $stmt->bind_param("ii", $user_id, $partner_id);

        if ($stmt->execute()) {
            flash_set("Partner invitation sent.", "ok");
        } else {
            flash_set("Could not send invitation.", "bad");
        }

        header("Location: habit-user.php");
        exit;
    }
}

$pageTitle = "HabitOS — Today";
require_once __DIR__ . "/includes/header.php";

$stmt = $conn->prepare("\n    SELECT\n      h.habit_id,\n      h.title,\n      COALESCE(cat.name, 'Uncategorized') AS category_name,\n      SUM(CASE WHEN hc.status = 'COMPLETED' THEN 1 ELSE 0 END) AS completed_count,\n      SUM(CASE WHEN hc.status = 'MISSED' THEN 1 ELSE 0 END) AS missed_count,\n      COUNT(hc.completion_id) AS total_logs\n    FROM habits h\n    LEFT JOIN categories cat ON cat.category_id = h.category_id\n    LEFT JOIN habit_completions hc ON hc.habit_id = h.habit_id\n    WHERE h.user_id = ? AND h.is_active = 1\n    GROUP BY h.habit_id, h.title, cat.name\n    ORDER BY h.created_at DESC\n");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$habits = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt = $conn->prepare("\n    SELECT c.connection_id, c.status, u.full_name, u.email\n    FROM user_connections c\n    JOIN users u ON u.user_id = c.partner_user_id\n    WHERE c.user_id = ?\n    ORDER BY c.created_at DESC\n");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$connections = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt = $conn->prepare("\n    SELECT em.message_text, em.sent_at, sender.full_name AS sender_name\n    FROM encouragement_messages em\n    JOIN user_connections c ON c.connection_id = em.connection_id\n    JOIN users sender ON sender.user_id = em.sender_user_id\n    WHERE c.user_id = ?\n    ORDER BY em.sent_at DESC\n    LIMIT 10\n");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="grid">
  <div class="card">
    <h1 class="h1">My Habit Summary</h1>
    <p class="p">View active habits and basic progress counts.</p>

    <hr class="sep">
    <table class="table">
      <thead>
        <tr>
          <th>Habit</th>
          <th>Category</th>
          <th>Completed</th>
          <th>Missed</th>
          <th>Total</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($habits) === 0): ?>
          <tr><td colspan="5" class="small">No active habits found.</td></tr>
        <?php endif; ?>
        <?php foreach ($habits as $h): ?>
          <tr>
            <td><?php echo htmlspecialchars($h["title"]); ?></td>
            <td><?php echo htmlspecialchars($h["category_name"]); ?></td>
            <td><?php echo (int)$h["completed_count"]; ?></td>
            <td><?php echo (int)$h["missed_count"]; ?></td>
            <td><?php echo (int)$h["total_logs"]; ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="card">
    <h2 class="h2">Invite Accountability Partner</h2>
    <p class="p">Send an invitation to an existing user with the PARTNER role.</p>
    <form method="POST">
      <input type="hidden" name="action" value="invite_partner">
      <label class="label">Partner Email</label>
      <input class="input" type="email" name="email" placeholder="partner@email.com" required>
      <div style="margin-top:14px;">
        <button class="btn btnPrimary" type="submit">Send Invite</button>
      </div>
    </form>

    <hr class="sep">
    <h2 class="h2">Partner Connections</h2>
    <table class="table">
      <thead><tr><th>Name</th><th>Email</th><th>Status</th></tr></thead>
      <tbody>
        <?php if (count($connections) === 0): ?>
          <tr><td colspan="3" class="small">No partner invitations yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($connections as $c): ?>
          <tr>
            <td><?php echo htmlspecialchars($c["full_name"]); ?></td>
            <td><?php echo htmlspecialchars($c["email"]); ?></td>
            <td><?php echo htmlspecialchars($c["status"]); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="card">
    <h2 class="h2">Encouragement Messages</h2>
    <p class="p">Recent messages from accepted accountability partners.</p>
    <hr class="sep">
    <?php if (count($messages) === 0): ?>
      <p class="small">No messages yet.</p>
    <?php endif; ?>
    <?php foreach ($messages as $m): ?>
      <div style="padding:10px 0;border-bottom:1px solid #f2f4f8;">
        <p class="p"><b><?php echo htmlspecialchars($m["sender_name"]); ?>:</b> <?php echo htmlspecialchars($m["message_text"]); ?></p>
        <p class="small"><?php echo htmlspecialchars($m["sent_at"]); ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php require_once __DIR__ . "/includes/footer.php"; ?>
