<?php
require_once __DIR__."/includes/auth.php";

if (is_logged_in()) {
    if (current_role() === "HABIT_USER") {
        header("Location: habit-user.php");
        exit;
    } elseif (current_role() === "PARTNER") {
        header("Location: partner.php");
        exit;
    } elseif (current_role() === "ADMIN") {
        header("Location: admin.php");
        exit;
    }
}

$pageTitle="HabitOS — Home";
require_once __DIR__."/includes/header.php";
?>

<div class="grid">
  <div class="card">
    <h1 class="h1">HabitOS</h1>
    <p class="p">Track habits, schedules, streaks. Add an accountability partner.</p>
    <hr class="sep">
    <a class="btn btnPrimary" href="register.php">Register</a>
    <a class="btn btnSecondary" href="login.php" style="margin-left:10px;">Login</a>
  </div>
</div>

<?php require_once __DIR__."/includes/footer.php"; ?>