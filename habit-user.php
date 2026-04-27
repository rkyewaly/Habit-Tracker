<?php
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/auth.php";
require_login();

$uid    = current_user_id();
$labels = ["Mon","Tue","Wed","Thu","Fri","Sat","Sun"];

/* ── Helpers ─────────────────────────────────────────────── */

function streak_for(mysqli $conn, int $habit_id): int {
  $stmt = $conn->prepare("
    SELECT completion_date FROM habit_completions
    WHERE habit_id=? AND status='COMPLETED'
    ORDER BY completion_date DESC LIMIT 90
  ");
  $stmt->bind_param("i", $habit_id);
  $stmt->execute();
  $res   = $stmt->get_result();
  $dates = [];
  while ($r = $res->fetch_assoc()) $dates[] = $r["completion_date"];
  if (!$dates) return 0;
  $set    = array_flip($dates);
  $today  = date("Y-m-d");
  $cursor = isset($set[$today]) ? $today : date("Y-m-d", strtotime("-1 day"));
  $count  = 0;
  while (isset($set[$cursor])) {
    $count++;
    $cursor = date("Y-m-d", strtotime($cursor . " -1 day"));
  }
  return $count;
}

function cat_color(string $name): string {
  $map = [
    "fitness"     => "cat-green",
    "health"      => "cat-pink",
    "study"       => "cat-blue",
    "learning"    => "cat-blue",
    "work"        => "cat-purple",
    "mindful"     => "cat-teal",
    "mindfulness" => "cat-teal",
    "sleep"       => "cat-teal",
    "nutrition"   => "cat-amber",
    "food"        => "cat-amber",
  ];
  return $map[strtolower(trim($name))] ?? "cat-gray";
}

function cat_icon(string $name): string {
  $map = [
    "fitness"     => "&#127939;",
    "health"      => "&#128167;",
    "study"       => "&#128214;",
    "learning"    => "&#128214;",
    "work"        => "&#128188;",
    "mindful"     => "&#129488;",
    "mindfulness" => "&#129488;",
    "nutrition"   => "&#129369;",
    "food"        => "&#129369;",
    "sleep"       => "&#128564;",
  ];
  return $map[strtolower(trim($name))] ?? "&#9670;";
}

/* ── POST handlers ──────────────────────────────────────── */

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $action = $_POST["action"] ?? "";

  if ($action === "accept_conn" || $action === "reject_conn") {
    $cid       = (int)($_POST["connection_id"] ?? 0);
    $newStatus = $action === "accept_conn" ? "ACCEPTED" : "REJECTED";
    $stmt = $conn->prepare("UPDATE user_connections SET status=? WHERE connection_id=? AND user_id=?");
    $stmt->bind_param("sii", $newStatus, $cid, $uid);
    $stmt->execute();
    flash_set("Connection $newStatus.", "ok");
    header("Location: habit-user.php"); exit;
  }

  if ($action === "add_habit") {
    $title       = trim($_POST["title"] ?? "");
    $category_id = ($_POST["category_id"] ?? "") !== "" ? (int)$_POST["category_id"] : null;
    $freq        = $_POST["frequency"] ?? "DAILY";
    $start_date  = $_POST["start_date"] ?? date("Y-m-d");
    $days        = $_POST["days"] ?? [];

    if ($title === "") {
      flash_set("Habit title is required.", "bad");
      header("Location: habit-user.php"); exit;
    }
    if (($freq === "WEEKLY" || $freq === "CUSTOM") && count($days) === 0) {
      flash_set("Pick at least one day for WEEKLY / CUSTOM.", "bad");
      header("Location: habit-user.php"); exit;
    }

    if ($category_id === null) {
      $stmt = $conn->prepare("INSERT INTO habits(user_id,title,category_id,is_active) VALUES(?,?,NULL,1)");
      $stmt->bind_param("is", $uid, $title);
    } else {
      $stmt = $conn->prepare("INSERT INTO habits(user_id,title,category_id,is_active) VALUES(?,?,?,1)");
      $stmt->bind_param("isi", $uid, $title, $category_id);
    }
    $stmt->execute();
    $habit_id = $conn->insert_id;

    $stmt = $conn->prepare("INSERT INTO habit_schedules(habit_id,frequency,start_date,enabled) VALUES(?,?,?,1)");
    $stmt->bind_param("iss", $habit_id, $freq, $start_date);
    $stmt->execute();
    $schedule_id = $conn->insert_id;

    if ($freq !== "DAILY") {
      $ins = $conn->prepare("INSERT IGNORE INTO schedule_days(schedule_id,day_of_week) VALUES(?,?)");
      foreach ($days as $d) {
        $d = (int)$d;
        $ins->bind_param("ii", $schedule_id, $d);
        $ins->execute();
      }
    }

    flash_set("Habit created!", "ok");
    header("Location: habit-user.php"); exit;
  }

  if ($action === "complete") {
    $habit_id = (int)($_POST["habit_id"] ?? 0);
    $stmt = $conn->prepare("
      INSERT INTO habit_completions(habit_id, completion_date, status)
      VALUES(?, CURDATE(), 'COMPLETED')
      ON DUPLICATE KEY UPDATE status='COMPLETED'
    ");
    $stmt->bind_param("i", $habit_id);
    $stmt->execute();
    flash_set("Habit marked done!", "ok");
    header("Location: habit-user.php"); exit;
  }
}

/* ── Data queries ───────────────────────────────────────── */

$pageTitle = "HabitOS — Today";

$cats = $conn->query("SELECT category_id, name FROM categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$pendingStmt = $conn->prepare("
  SELECT uc.connection_id, u.full_name, u.email
  FROM user_connections uc
  JOIN users u ON u.user_id = uc.partner_user_id
  WHERE uc.user_id=? AND uc.status='PENDING'
  ORDER BY uc.created_at DESC
");
$pendingStmt->bind_param("i", $uid);
$pendingStmt->execute();
$pendingRows = $pendingStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$todayDueStmt = $conn->prepare("
  SELECT DISTINCT h.habit_id, h.title, c.name AS category_name, hs.frequency
  FROM habits h
  JOIN habit_schedules hs ON hs.habit_id = h.habit_id AND hs.enabled = 1
  LEFT JOIN categories c  ON c.category_id = h.category_id
  LEFT JOIN schedule_days sd ON sd.schedule_id = hs.schedule_id
  WHERE h.user_id=? AND h.is_active=1 AND hs.start_date <= CURDATE()
    AND (
      hs.frequency = 'DAILY'
      OR (hs.frequency IN ('WEEKLY','CUSTOM') AND sd.day_of_week = WEEKDAY(CURDATE()))
    )
  ORDER BY h.title
");
$todayDueStmt->bind_param("i", $uid);
$todayDueStmt->execute();
$todayDue = $todayDueStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Which habits are already done today?
$doneSetStmt = $conn->prepare("
  SELECT hc.habit_id
  FROM habit_completions hc
  JOIN habits h ON h.habit_id = hc.habit_id
  WHERE h.user_id=? AND hc.completion_date = CURDATE() AND hc.status='COMPLETED'
");
$doneSetStmt->bind_param("i", $uid);
$doneSetStmt->execute();
$doneSet = array_flip(array_column($doneSetStmt->get_result()->fetch_all(MYSQLI_ASSOC), "habit_id"));

// Progress ring
$totalDue       = count($todayDue);
$doneTodayCount = 0;
foreach ($todayDue as $h) { if (isset($doneSet[$h["habit_id"]])) $doneTodayCount++; }
$ringPct       = $totalDue > 0 ? round(($doneTodayCount / $totalDue) * 100) : 0;
$circumference = 175.9; // 2 * pi * 28
$dashOffset    = $circumference - ($circumference * $ringPct / 100);

// All habits list
$allHabitsStmt = $conn->prepare("
  SELECT h.habit_id, h.title, c.name AS category_name, hs.frequency, hs.start_date
  FROM habits h
  JOIN habit_schedules hs ON hs.habit_id = h.habit_id
  LEFT JOIN categories c ON c.category_id = h.category_id
  WHERE h.user_id=? AND h.is_active=1
  ORDER BY h.created_at DESC
");
$allHabitsStmt->bind_param("i", $uid);
$allHabitsStmt->execute();
$allHabits = $allHabitsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// 7-day activity dots
$sevenDayStmt = $conn->prepare("
  SELECT DATE(hc.completion_date) AS day
  FROM habit_completions hc
  JOIN habits h ON h.habit_id = hc.habit_id
  WHERE h.user_id=? AND hc.completion_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    AND hc.status='COMPLETED'
  GROUP BY DATE(hc.completion_date)
");
$sevenDayStmt->bind_param("i", $uid);
$sevenDayStmt->execute();
$activeDaySet = array_flip(array_column($sevenDayStmt->get_result()->fetch_all(MYSQLI_ASSOC), "day"));

// Partner messages
$msgStmt = $conn->prepare("
  SELECT em.message_text, em.sent_at, u.full_name
  FROM encouragement_messages em
  JOIN user_connections uc ON uc.connection_id = em.connection_id
  JOIN users u ON u.user_id = em.sender_user_id
  WHERE uc.user_id=? AND uc.status='ACCEPTED'
  ORDER BY em.sent_at DESC LIMIT 5
");
$msgStmt->bind_param("i", $uid);
$msgStmt->execute();
$messages = $msgStmt->get_result()->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . "/includes/header.php";
?>

<style>
.hu-layout {
  display: grid;
  grid-template-columns: 1fr 290px;
  gap: 20px;
  align-items: start;
}
@media (max-width: 760px) { .hu-layout { grid-template-columns: 1fr; } }

/* Progress banner */
.progress-banner {
  display: flex; align-items: center; gap: 20px;
  background: #fff; border: 1px solid #e4e8f0;
  border-radius: 12px; padding: 18px 22px; margin-bottom: 20px;
}
.progress-banner.all-done { border-color: #a3d9b0; background: #f0faf3; }
.ring-info .big { font-size: 1.3rem; font-weight: 700; }
.ring-info .sub { font-size: .85rem; color: #888; margin-top: 3px; }

/* Section label */
.section-label {
  font-size: .73rem; font-weight: 700; color: #aaa;
  text-transform: uppercase; letter-spacing: .7px; margin: 0 0 12px;
}

/* Habit cards */
.habit-card {
  display: flex; align-items: center; gap: 14px;
  background: #fff; border: 1px solid #e4e8f0;
  border-radius: 12px; padding: 14px 16px; margin-bottom: 10px;
  transition: box-shadow .2s;
}
.habit-card:hover { box-shadow: 0 2px 10px rgba(0,0,0,.06); }
.habit-card.is-done { opacity: .5; background: #fafbfd; }
.habit-icon {
  width: 42px; height: 42px; border-radius: 11px;
  background: #eef0f7; display: flex; align-items: center;
  justify-content: center; font-size: 1.2rem; flex-shrink: 0;
}
.habit-info { flex: 1; min-width: 0; }
.habit-title {
  font-size: .95rem; font-weight: 600; color: #1a1a2e;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.habit-meta { display: flex; align-items: center; gap: 8px; margin-top: 5px; flex-wrap: wrap; }
.freq-badge {
  font-size: .7rem; font-weight: 700; color: #888;
  background: #f2f4f8; padding: 2px 8px; border-radius: 20px;
}
.streak-badge { font-size: .78rem; font-weight: 600; color: #c95f0a; }
.streak-zero  { color: #ccc; }

/* Category chips */
.cat-chip { font-size: .7rem; font-weight: 700; padding: 2px 8px; border-radius: 20px; }
.cat-green  { background: #d8f3e3; color: #1a6e35; }
.cat-blue   { background: #dbeafe; color: #1e40af; }
.cat-pink   { background: #fce7f3; color: #9d174d; }
.cat-purple { background: #ede9fe; color: #5b21b6; }
.cat-teal   { background: #d1fae5; color: #065f46; }
.cat-amber  { background: #fef3c7; color: #92400e; }
.cat-gray   { background: #f2f4f8; color: #555;    }

/* Check button */
.check-form { margin: 0; }
.check-btn {
  width: 36px; height: 36px; border-radius: 50%;
  border: 2px solid #d0d5e8; background: transparent;
  cursor: pointer; display: flex; align-items: center;
  justify-content: center; font-size: 1rem;
  color: transparent; flex-shrink: 0; transition: border-color .2s, background .2s;
}
.check-btn:hover { border-color: #5a6fd6; background: #eef0f7; color: #5a6fd6; }
.check-btn.done { background: #5a6fd6; border-color: #5a6fd6; color: #fff; }

/* Sidebar */
.side-card {
  background: #fff; border: 1px solid #e4e8f0;
  border-radius: 12px; padding: 18px; margin-bottom: 16px;
}
.week-strip { display: grid; grid-template-columns: repeat(7,1fr); gap: 6px; margin-top: 10px; }
.week-day   { display: flex; flex-direction: column; align-items: center; gap: 4px; }
.week-day-label { font-size: .68rem; color: #aaa; font-weight: 600; }
.week-dot   { width: 22px; height: 22px; border-radius: 50%; background: #edf0f5; }
.week-dot.active      { background: #5a6fd6; }
.week-dot.today-ring  { background: transparent; border: 2px solid #5a6fd6; }

/* Add form */
.add-input {
  width: 100%; padding: 8px 10px;
  border: 1px solid #d8dce8; border-radius: 8px;
  font-size: .85rem; background: #fafbfd; color: #1a1a2e;
  outline: none; margin-bottom: 8px; transition: border-color .2s;
}
.add-input:focus { border-color: #5a6fd6; background: #fff; }
.day-checks { display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 10px; }
.day-checks label { cursor: pointer; }
.day-checks input[type=checkbox] { display: none; }
.day-checks input[type=checkbox]:checked ~ .day-pill {
  background: #5a6fd6; color: #fff; border-color: #5a6fd6;
}
.day-pill {
  display: inline-block; font-size: .75rem; padding: 3px 9px;
  border-radius: 20px; border: 1px solid #d8dce8;
  color: #555; background: #fafbfd;
}

/* Partner request */
.request-card {
  display: flex; align-items: center; gap: 12px;
  background: #fff8ec; border: 1px solid #fcd27a;
  border-radius: 10px; padding: 12px 14px; margin-bottom: 10px;
}
.request-info { flex: 1; font-size: .88rem; }
.request-info strong { display: block; }
.request-info span   { color: #888; font-size: .78rem; }
.request-btns { display: flex; gap: 6px; }

/* Message bubble */
.msg-bubble {
  background: #f6f7fb; border-radius: 10px;
  padding: 10px 12px; margin-bottom: 8px;
  font-size: .85rem; color: #333;
  border-left: 3px solid #5a6fd6;
}
.msg-meta { font-size: .73rem; color: #aaa; margin-top: 4px; }

/* All habits section */
.all-habits-wrap {
  background: #fff; border: 1px solid #e4e8f0;
  border-radius: 12px; padding: 20px 24px; margin-top: 20px;
}
</style>

<?php $dayName = date("l"); ?>

<!-- Progress banner -->
<div class="progress-banner <?php echo ($doneTodayCount === $totalDue && $totalDue > 0) ? 'all-done' : ''; ?>">
  <svg width="64" height="64" viewBox="0 0 64 64" style="flex-shrink:0;">
    <circle cx="32" cy="32" r="28" fill="none" stroke="#edf0f5" stroke-width="7"/>
    <circle cx="32" cy="32" r="28" fill="none"
      stroke="<?php echo ($ringPct === 100) ? '#34c97e' : '#5a6fd6'; ?>"
      stroke-width="7"
      stroke-dasharray="<?php echo $circumference; ?>"
      stroke-dashoffset="<?php echo $dashOffset; ?>"
      stroke-linecap="round"
      transform="rotate(-90 32 32)"/>
    <text x="32" y="37" text-anchor="middle" font-size="12" font-weight="700"
      fill="<?php echo ($ringPct === 100) ? '#1a6e35' : '#1a1a2e'; ?>"
      font-family="system-ui,sans-serif"><?php echo $ringPct; ?>%</text>
  </svg>
  <div class="ring-info">
    <?php if ($totalDue === 0): ?>
      <div class="big">No habits due today</div>
      <div class="sub">Add a habit in the sidebar to get started.</div>
    <?php elseif ($doneTodayCount === $totalDue): ?>
      <div class="big">All done for <?php echo htmlspecialchars($dayName); ?>!</div>
      <div class="sub">Every habit completed. Nice work.</div>
    <?php else: ?>
      <div class="big"><?php echo $doneTodayCount; ?> of <?php echo $totalDue; ?> done today</div>
      <div class="sub"><?php echo ($totalDue - $doneTodayCount); ?> habit<?php echo ($totalDue - $doneTodayCount > 1 ? 's' : ''); ?> remaining &mdash; <?php echo htmlspecialchars($dayName); ?></div>
    <?php endif; ?>
  </div>
</div>

<!-- Layout -->
<div class="hu-layout">

  <!-- Left column -->
  <div>

    <!-- Pending partner requests -->
    <?php if (count($pendingRows) > 0): ?>
      <p class="section-label">Partner requests</p>
      <?php foreach ($pendingRows as $r): ?>
        <div class="request-card">
          <div class="request-info">
            <strong><?php echo htmlspecialchars($r["full_name"]); ?></strong>
            <span><?php echo htmlspecialchars($r["email"]); ?> wants to be your accountability partner</span>
          </div>
          <div class="request-btns">
            <form class="check-form" method="POST">
              <input type="hidden" name="action" value="accept_conn">
              <input type="hidden" name="connection_id" value="<?php echo (int)$r["connection_id"]; ?>">
              <button class="btn btnPrimary" style="padding:5px 12px;font-size:.8rem;" type="submit">Accept</button>
            </form>
            <form class="check-form" method="POST">
              <input type="hidden" name="action" value="reject_conn">
              <input type="hidden" name="connection_id" value="<?php echo (int)$r["connection_id"]; ?>">
              <button class="btn btnSecondary" style="padding:5px 12px;font-size:.8rem;" type="submit">Decline</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
      <div style="margin-bottom:20px;"></div>
    <?php endif; ?>

    <!-- Today's habit cards -->
    <p class="section-label">Due today &mdash; <?php echo htmlspecialchars($dayName); ?></p>

    <?php if (count($todayDue) === 0): ?>
      <div class="habit-card" style="justify-content:center;color:#aaa;font-size:.9rem;padding:22px;">
        No habits scheduled for today &mdash; add one in the sidebar.
      </div>
    <?php else: ?>
      <?php foreach ($todayDue as $h):
        $done    = isset($doneSet[$h["habit_id"]]);
        $streak  = streak_for($conn, (int)$h["habit_id"]);
        $catName = $h["category_name"] ?? "";
        $catCls  = $catName !== "" ? cat_color($catName) : "cat-gray";
        $icon    = cat_icon($catName);
      ?>
        <div class="habit-card <?php echo $done ? 'is-done' : ''; ?>">
          <div class="habit-icon"><?php echo $icon; ?></div>
          <div class="habit-info">
            <div class="habit-title">
              <?php echo htmlspecialchars($h["title"]); ?>
              <?php if ($catName !== ''): ?>
                <span class="cat-chip <?php echo $catCls; ?>" style="margin-left:6px;vertical-align:middle;">
                  <?php echo htmlspecialchars($catName); ?>
                </span>
              <?php endif; ?>
            </div>
            <div class="habit-meta">
              <span class="freq-badge"><?php echo htmlspecialchars($h["frequency"]); ?></span>
              <?php if ($streak > 0): ?>
                <span class="streak-badge">&#128293; <?php echo $streak; ?>-day streak</span>
              <?php else: ?>
                <span class="streak-badge streak-zero">No streak yet</span>
              <?php endif; ?>
            </div>
          </div>
          <?php if (!$done): ?>
            <form class="check-form" method="POST">
              <input type="hidden" name="action" value="complete">
              <input type="hidden" name="habit_id" value="<?php echo (int)$h["habit_id"]; ?>">
              <button class="check-btn" type="submit" title="Mark complete">&#10003;</button>
            </form>
          <?php else: ?>
            <div class="check-btn done" title="Done today">&#10003;</div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <!-- All habits table -->
    <?php if (count($allHabits) > 0): ?>
      <div class="all-habits-wrap">
        <p class="section-label" style="margin-bottom:14px;">All my habits</p>
        <table class="table">
          <thead>
            <tr><th>Habit</th><th>Category</th><th>Frequency</th><th>Since</th><th>Streak</th></tr>
          </thead>
          <tbody>
          <?php foreach ($allHabits as $h):
            $catName = $h["category_name"] ?? "";
            $catCls  = $catName !== "" ? cat_color($catName) : "cat-gray";
            $streak  = streak_for($conn, (int)$h["habit_id"]);
          ?>
            <tr>
              <td style="font-weight:600;"><?php echo htmlspecialchars($h["title"]); ?></td>
              <td>
                <?php if ($catName !== ''): ?>
                  <span class="cat-chip <?php echo $catCls; ?>"><?php echo htmlspecialchars($catName); ?></span>
                <?php else: ?>
                  <span style="color:#ddd;">&#8212;</span>
                <?php endif; ?>
              </td>
              <td><span class="freq-badge"><?php echo htmlspecialchars($h["frequency"]); ?></span></td>
              <td class="small"><?php echo htmlspecialchars($h["start_date"]); ?></td>
              <td>
                <?php if ($streak > 0): ?>
                  <span class="streak-badge">&#128293; <?php echo $streak; ?></span>
                <?php else: ?>
                  <span style="color:#ddd;">&#8212;</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

  </div><!-- /left -->

  <!-- Right sidebar -->
  <div>

    <!-- 7-day activity strip -->
    <div class="side-card">
      <p class="section-label" style="margin:0 0 2px;">Last 7 days</p>
      <div class="week-strip">
        <?php for ($i = 6; $i >= 0; $i--):
          $d       = date("Y-m-d", strtotime("-{$i} day"));
          $isToday = ($i === 0);
          $active  = isset($activeDaySet[$d]);
          $lbl     = strtoupper(substr(date("D", strtotime($d)), 0, 1));
          $cls     = $isToday ? "week-dot today-ring" : ($active ? "week-dot active" : "week-dot");
        ?>
          <div class="week-day">
            <span class="week-day-label"><?php echo $lbl; ?></span>
            <div class="<?php echo $cls; ?>"></div>
          </div>
        <?php endfor; ?>
      </div>
      <p style="font-size:.72rem;color:#bbb;margin-top:8px;">Filled = at least one habit completed</p>
    </div>

    <!-- Add habit -->
    <div class="side-card">
      <p class="section-label" style="margin:0 0 12px;">Add a habit</p>
      <form method="POST">
        <input type="hidden" name="action" value="add_habit">
        <input class="add-input" name="title" placeholder="e.g., Meditate, Gym, Read" required>

        <select class="add-input" name="category_id">
          <option value="">(no category)</option>
          <?php foreach ($cats as $c): ?>
            <option value="<?php echo (int)$c["category_id"]; ?>"><?php echo htmlspecialchars($c["name"]); ?></option>
          <?php endforeach; ?>
        </select>

        <select class="add-input" name="frequency" id="freq-select">
          <option value="DAILY">DAILY</option>
          <option value="WEEKLY">WEEKLY</option>
          <option value="CUSTOM">CUSTOM</option>
        </select>

        <input class="add-input" type="date" name="start_date" value="<?php echo date('Y-m-d'); ?>">

        <div class="day-checks" id="day-checks-wrap" style="display:none;">
          <?php for ($i = 0; $i < 7; $i++): ?>
            <label>
              <input type="checkbox" name="days[]" value="<?php echo $i; ?>">
              <span class="day-pill"><?php echo $labels[$i]; ?></span>
            </label>
          <?php endfor; ?>
        </div>

        <button class="btn btnPrimary" type="submit" style="width:100%;margin-top:4px;">+ Add habit</button>
      </form>
    </div>

    <!-- Partner messages -->
    <?php if (count($messages) > 0): ?>
      <div class="side-card">
        <p class="section-label" style="margin:0 0 10px;">From your partner</p>
        <?php foreach ($messages as $m): ?>
          <div class="msg-bubble">
            <?php echo nl2br(htmlspecialchars($m["message_text"])); ?>
            <div class="msg-meta">
              <?php echo htmlspecialchars($m["full_name"]); ?> &mdash;
              <?php echo htmlspecialchars(substr($m["sent_at"], 0, 10)); ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </div><!-- /sidebar -->

</div><!-- /hu-layout -->

<script>
(function() {
  var sel  = document.getElementById("freq-select");
  var wrap = document.getElementById("day-checks-wrap");
  function toggle() {
    wrap.style.display = (sel.value === "WEEKLY" || sel.value === "CUSTOM") ? "flex" : "none";
  }
  sel.addEventListener("change", toggle);
  toggle();
})();
</script>

<?php require_once __DIR__ . "/includes/footer.php"; ?>
