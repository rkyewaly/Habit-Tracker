<?php
require_once __DIR__."/includes/db.php";
require_once __DIR__."/includes/auth.php";
require_role("ADMIN");

if ($_SERVER["REQUEST_METHOD"]==="POST") {
  $action = $_POST["action"] ?? "";
  if ($action==="add_category") {
    $name = trim($_POST["name"] ?? "");
    if ($name==="") {
      flash_set("Category name required.","bad");
    } else {
      $stmt=$conn->prepare("INSERT IGNORE INTO categories(name) VALUES(?)");
      $stmt->bind_param("s",$name);
      $stmt->execute();
      flash_set("Category saved.","ok");
    }
    header("Location: admin.php"); exit;
  }
}

$pageTitle="HabitOS — Admin";
require_once __DIR__."/includes/header.php";

$cats=$conn->query("SELECT category_id, name FROM categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$stats = [];
foreach ([
  "users"=>"SELECT COUNT(*) c FROM users",
  "habits"=>"SELECT COUNT(*) c FROM habits",
  "completions"=>"SELECT COUNT(*) c FROM habit_completions",
  "connections"=>"SELECT COUNT(*) c FROM user_connections",
  "messages"=>"SELECT COUNT(*) c FROM encouragement_messages"
] as $k=>$sql) {
  $stats[$k] = (int)($conn->query($sql)->fetch_assoc()["c"] ?? 0);
}
?>
<div class="grid">
  <div class="card">
    <h1 class="h1">Admin</h1>
    <p class="p">Manage categories + view system stats.</p>

    <hr class="sep">
    <h2 class="h2">Add category</h2>
    <form method="POST" style="max-width:520px;">
      <input type="hidden" name="action" value="add_category">
      <label class="label">Category name</label>
      <input class="input" name="name" placeholder="e.g., Fitness">
      <div style="margin-top:14px;">
        <button class="btn btnPrimary" type="submit">Save</button>
      </div>
    </form>

    <hr class="sep">
    <h2 class="h2">Categories</h2>
    <table class="table">
      <thead><tr><th>Name</th><th>Created</th></tr></thead>
      <tbody>
      <?php foreach($cats as $c): ?>
        <tr><td><?php echo htmlspecialchars($c["name"]); ?></td><td class="small"><?php echo  ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="card">
    <h2 class="h2">Stats</h2>
    <table class="table">
      <tbody>
        <tr><th>Users</th><td><?php echo $stats["users"]; ?></td></tr>
        <tr><th>Habits</th><td><?php echo $stats["habits"]; ?></td></tr>
        <tr><th>Completions</th><td><?php echo $stats["completions"]; ?></td></tr>
        <tr><th>Connections</th><td><?php echo $stats["connections"]; ?></td></tr>
        <tr><th>Messages</th><td><?php echo $stats["messages"]; ?></td></tr>
      </tbody>
    </table>
    <p class="small" style="margin-top:10px;">Admin-only access enforced.</p>
  </div>
</div>
<?php require_once __DIR__."/includes/footer.php"; ?>