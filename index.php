<?php
$pageTitle="HabitOS — Home";
require_once __DIR__."/includes/header.php";
?>
<div class="grid">
  <div class="card">
    <h1 class="h1">HabitOS</h1>
    <p class="p">Track habits, schedules, streaks. Add an accountability partner. Admin manages categories.</p>
    <hr class="sep">
    <a class="btn btnPrimary" href="register.php">Register</a>
    <a class="btn btnSecondary" href="login.php" style="margin-left:10px;">Login</a>
  </div>
  <div class="card">
    <h2 class="h2">Roles</h2>
    <p class="p"><b>RK</b>: Habit User</p>
    <p class="p"><b>V</b>: Partner</p>
    <p class="p"><b>W</b>: Admin</p>
    <p class="small" style="margin-top:10px;"> Register to create  accounts.</p>
  </div>
</div>
<?php require_once __DIR__."/includes/footer.php"; ?>