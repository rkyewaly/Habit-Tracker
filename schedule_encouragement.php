<?php
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/auth.php";
require_role("PARTNER");

$partner_id = current_user_id();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if ($action === "create_schedule") {
        $connection_id = (int)($_POST["connection_id"] ?? 0);
        $message = trim($_POST["message"] ?? "");
        $trigger_type = $_POST["trigger_type"] ?? "";
        $send_time = $_POST["send_time"] ?? null;

        if ($message === "" || !in_array($trigger_type, ["TIME_BASED", "STREAK_RISK"], true)) {
            flash_set("Please enter a message and choose a valid trigger.", "bad");
            header("Location: schedule_encouragement.php");
            exit;
        }

        if ($trigger_type === "TIME_BASED" && ($send_time === null || $send_time === "")) {
            flash_set("Please choose a send time for time-based encouragement.", "bad");
            header("Location: schedule_encouragement.php");
            exit;
        }

        if ($trigger_type === "STREAK_RISK") {
            $send_time = null;
        }

        $stmt = $conn->prepare("\n            SELECT connection_id, user_id\n            FROM user_connections\n            WHERE connection_id = ?\n              AND partner_user_id = ?\n              AND status = 'ACCEPTED'\n        ");
        $stmt->bind_param("ii", $connection_id, $partner_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            flash_set("You can only schedule encouragement for accepted connections.", "bad");
            header("Location: schedule_encouragement.php");
            exit;
        }

        $connection = $result->fetch_assoc();
        $target_user_id = (int)$connection["user_id"];

        $stmt = $conn->prepare("\n            INSERT INTO scheduled_encouragements\n            (connection_id, sender_user_id, target_user_id, message_text, send_time, trigger_type)\n            VALUES (?, ?, ?, ?, ?, ?)\n        ");
        $stmt->bind_param("iiisss", $connection_id, $partner_id, $target_user_id, $message, $send_time, $trigger_type);

        if ($stmt->execute()) {
            flash_set("Encouragement schedule saved.", "ok");
        } else {
            flash_set("Could not save schedule. Did you run scheduled_encouragements_schema.sql?", "bad");
        }

        header("Location: schedule_encouragement.php");
        exit;
    }

    if ($action === "toggle_schedule") {
        $scheduled_id = (int)($_POST["scheduled_id"] ?? 0);
        $is_active = (int)($_POST["is_active"] ?? 0);

        $stmt = $conn->prepare("\n            UPDATE scheduled_encouragements\n            SET is_active = ?\n            WHERE scheduled_id = ?\n              AND sender_user_id = ?\n        ");
        $stmt->bind_param("iii", $is_active, $scheduled_id, $partner_id);
        $stmt->execute();

        flash_set("Schedule updated.", "ok");
        header("Location: schedule_encouragement.php");
        exit;
    }
}

$pageTitle = "HabitOS — Schedule Encouragement";
require_once __DIR__ . "/includes/header.php";

$stmt = $conn->prepare("\n    SELECT c.connection_id, u.full_name, u.email\n    FROM user_connections c\n    JOIN users u ON u.user_id = c.user_id\n    WHERE c.partner_user_id = ?\n      AND c.status = 'ACCEPTED'\n    ORDER BY u.full_name\n");
$stmt->bind_param("i", $partner_id);
$stmt->execute();
$connections = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$schedules = [];
$hasScheduleTable = $conn->query("SHOW TABLES LIKE 'scheduled_encouragements'");
if ($hasScheduleTable && $hasScheduleTable->num_rows > 0) {
    $stmt = $conn->prepare("\n        SELECT se.*, u.full_name AS target_name\n        FROM scheduled_encouragements se\n        JOIN users u ON u.user_id = se.target_user_id\n        WHERE se.sender_user_id = ?\n        ORDER BY se.created_at DESC\n    ");
    $stmt->bind_param("i", $partner_id);
    $stmt->execute();
    $schedules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>

<div class="grid">
  <div class="card">
    <h1 class="h1">Schedule Encouragement</h1>
    <p class="p">Create encouragement that sends at a specific time or when a user may lose a streak.</p>

    <?php if (!$hasScheduleTable || $hasScheduleTable->num_rows === 0): ?>
      <hr class="sep">
      <p class="p"><b>Setup needed:</b> Run <code>scheduled_encouragements_schema.sql</code> in phpMyAdmin first.</p>
    <?php endif; ?>

    <hr class="sep">
    <form method="POST">
      <input type="hidden" name="action" value="create_schedule">

      <label class="label">Accepted User</label>
      <select class="input" name="connection_id" required>
        <option value="">Choose a connection</option>
        <?php foreach ($connections as $c): ?>
          <option value="<?php echo (int)$c["connection_id"]; ?>">
            <?php echo htmlspecialchars($c["full_name"] . " — " . $c["email"]); ?>
          </option>
        <?php endforeach; ?>
      </select>

      <label class="label">Trigger Type</label>
      <select class="input" name="trigger_type" required>
        <option value="TIME_BASED">Send at a certain time</option>
        <option value="STREAK_RISK">Send when streak is at risk</option>
      </select>

      <label class="label">Send Time</label>
      <input class="input" type="time" name="send_time">
      <p class="small" style="margin-top:6px;">Only required for time-based messages.</p>

      <label class="label">Message</label>
      <textarea class="input" name="message" rows="4" maxlength="500" placeholder="You got this! Keep your streak going." required></textarea>

      <div style="margin-top:14px;">
        <button class="btn btnPrimary" type="submit">Save Schedule</button>
        <a class="btn btnSecondary" href="partner.php" style="margin-left:8px;">Back to Partner View</a>
      </div>
    </form>
  </div>

  <div class="card">
    <h2 class="h2">My Scheduled Encouragements</h2>
    <hr class="sep">

    <table class="table">
      <thead>
        <tr><th>User</th><th>Trigger</th><th>Time</th><th>Active</th><th>Last Sent</th><th></th></tr>
      </thead>
      <tbody>
        <?php if (count($schedules) === 0): ?>
          <tr><td colspan="6" class="small">No schedules yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($schedules as $s): ?>
          <tr>
            <td><?php echo htmlspecialchars($s["target_name"]); ?></td>
            <td><?php echo htmlspecialchars($s["trigger_type"]); ?></td>
            <td><?php echo htmlspecialchars($s["send_time"] ?? "—"); ?></td>
            <td><?php echo ((int)$s["is_active"] === 1) ? "Yes" : "No"; ?></td>
            <td><?php echo htmlspecialchars($s["last_sent_date"] ?? "—"); ?></td>
            <td>
              <form method="POST">
                <input type="hidden" name="action" value="toggle_schedule">
                <input type="hidden" name="scheduled_id" value="<?php echo (int)$s["scheduled_id"]; ?>">
                <input type="hidden" name="is_active" value="<?php echo ((int)$s["is_active"] === 1) ? 0 : 1; ?>">
                <button class="btn btnSecondary" type="submit">
                  <?php echo ((int)$s["is_active"] === 1) ? "Disable" : "Enable"; ?>
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . "/includes/footer.php"; ?>
