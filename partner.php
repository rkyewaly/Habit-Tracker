<?php
require_once __DIR__."/includes/db.php";
require_once __DIR__."/includes/auth.php";
require_login();

if (current_role()!=="PARTNER") { /* allow for demo, but you can enforce if you want */ }

$pid = current_user_id();

if ($_SERVER["REQUEST_METHOD"]==="POST") {
  $action = $_POST["action"] ?? "";

  if ($action==="request_conn") {
    $email = trim($_POST["user_email"] ?? "");
    $stmt=$conn->prepare("SELECT user_id FROM users WHERE email=? AND role='HABIT_USER'");
    $stmt->bind_param("s",$email);
    $stmt->execute();
    $u=$stmt->get_result()->fetch_assoc();

    if (!$u) {
      flash_set("Habit User not found for that email.","bad");
    } else {
      $uid=(int)$u["user_id"];
      $ins=$conn->prepare("INSERT IGNORE INTO user_connections(user_id, partner_user_id, status) VALUES(?,?, 'PENDING')");
      $ins->bind_param("ii",$uid,$pid);
      $ins->execute();
      flash_set("Connection request sent.","ok");
    }
    header("Location: partner.php"); exit;
  }

  if ($action==="send_msg") {
    $cid = (int)($_POST["connection_id"] ?? 0);
    $text = trim($_POST["message_text"] ?? "");
    if ($text==="") {
      flash_set("Message cannot be empty.","bad");
    } else {
      // verify accepted connection belongs to me
      $chk=$conn->prepare("SELECT connection_id FROM user_connections WHERE connection_id=? AND partner_user_id=? AND status='ACCEPTED'");
      $chk->bind_param("ii",$cid,$pid);
      $chk->execute();
      if (!$chk->get_result()->fetch_assoc()) {
        flash_set("Not allowed.","bad");
      } else {
        $ins=$conn->prepare("INSERT INTO encouragement_messages(connection_id, sender_user_id, message_text) VALUES(?,?,?)");
        $ins->bind_param("iis",$cid,$pid,$text);
        $ins->execute();
        flash_set("Message sent.","ok");
      }
    }
    header("Location: partner.php"); exit;
  }
}

$pageTitle="HabitOS — Partner";
require_once __DIR__."/includes/header.php";

// my connections
$stmt=$conn->prepare("
  SELECT uc.connection_id, uc.status, u.full_name, u.email, u.user_id
  FROM user_connections uc
  JOIN users u ON u.user_id = uc.user_id
  WHERE uc.partner_user_id=?
  ORDER BY uc.created_at DESC
");
$stmt->bind_param("i",$pid);
$stmt->execute();
$conns=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<div class="card">
  <h1 class="h1">Partner</h1>
  <p class="p">Request connection, view shared progress, send encouragement.</p>

  <hr class="sep">
  <h2 class="h2">Request connection</h2>
  <form method="POST" style="max-width:560px;">
    <input type="hidden" name="action" value="request_conn">
    <label class="label">Habit User email</label>
    <input class="input" name="user_email" placeholder="habituser@email.com">
    <div style="margin-top:14px;">
      <button class="btn btnPrimary" type="submit">Send request</button>
    </div>
  </form>

  <hr class="sep">
  <h2 class="h2">My connections</h2>
  <?php if (count($conns)===0): ?>
    <p class="p">No connections yet.</p>
  <?php else: ?>
    <table class="table">
      <thead><tr><th>Habit User</th><th>Status</th><th>7-day completions</th><th>Message</th></tr></thead>
      <tbody>
      <?php foreach($conns as $c): ?>
        <?php
          $completed7 = 0;
          if ($c["status"]==="ACCEPTED") {
            $q=$conn->prepare("
              SELECT COUNT(*) AS cnt
              FROM habit_completions hc
              JOIN habits h ON h.habit_id = hc.habit_id
              WHERE h.user_id=? AND hc.status='COMPLETED' AND hc.completion_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            ");
            $uid=(int)$c["user_id"];
            $q->bind_param("i",$uid);
            $q->execute();
            $completed7 = (int)($q->get_result()->fetch_assoc()["cnt"] ?? 0);
          }
        ?>
        <tr>
          <td><?php echo htmlspecialchars($c["full_name"]); ?><br><span class="small"><?php echo htmlspecialchars($c["email"]); ?></span></td>
          <td><?php echo htmlspecialchars($c["status"]); ?></td>
          <td><?php echo $c["status"]==="ACCEPTED" ? $completed7 : "-"; ?></td>
          <td>
            <?php if ($c["status"]==="ACCEPTED"): ?>
              <form method="POST">
                <input type="hidden" name="action" value="send_msg">
                <input type="hidden" name="connection_id" value="<?php echo (int)$c["connection_id"]; ?>">
                <input class="input" name="message_text" placeholder="Quick encouragement..." style="max-width:260px;">
                <button class="btn btnSecondary" type="submit" style="margin-top:8px;">Send</button>
              </form>
            <?php else: ?>
              <span class="small">Waiting for acceptance.</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php require_once __DIR__."/includes/footer.php"; ?>