<?php
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/auth.php";
$pageTitle = "Register";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $full  = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"]     ?? "");
    $pass  = $_POST["password"]       ?? "";
    $role  = $_POST["role"]           ?? "HABIT_USER";

    // Whitelist allowed roles so a user can't register as ADMIN via POST
    $allowed_roles = ["HABIT_USER", "PARTNER"];
    if (!in_array($role, $allowed_roles, true)) $role = "HABIT_USER";

    if ($full === "" || $email === "" || $pass === "") {
        flash_set("All fields are required.", "bad");
    } else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users(full_name, email, password_hash, role) VALUES(?,?,?,?)");
        $stmt->bind_param("ssss", $full, $email, $hash, $role);

        // FIX: wrap execute() in try/catch so a duplicate-email unique-key
        // violation is caught gracefully instead of crashing with an exception.
        try {
            $ok = $stmt->execute();
        } catch (mysqli_sql_exception $e) {
            $ok = false;
        }

        if ($ok) {
            flash_set("Registered successfully. Please login.", "ok");
            header("Location: login.php"); exit;
        } else {
            flash_set("Registration failed — that email may already be in use.", "bad");
        }
    }
}

require_once __DIR__ . "/includes/header.php";
?>
<div class="card" style="max-width:560px;">
  <h1 class="h1">Register</h1>
  <form method="POST">
    <label class="label">Full name</label>
    <input class="input" name="full_name" required>

    <label class="label">Email</label>
    <input class="input" type="email" name="email" required>

    <label class="label">Password</label>
    <input class="input" type="password" name="password" required>

    <label class="label">Role</label>
    <select class="input" name="role">
      <option value="HABIT_USER">Habit User</option>
      <option value="PARTNER">Partner</option>
      <!-- ADMIN accounts must be set directly in the DB for security -->
    </select>

    <div style="margin-top:14px; display:flex; gap:10px;">
      <button class="btn btnPrimary" type="submit">Create account</button>
      <a class="btn btnSecondary" href="login.php">Already registered?</a>
    </div>
  </form>
</div>
<?php require_once __DIR__ . "/includes/footer.php"; ?>
