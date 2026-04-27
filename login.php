<?php
require_once __DIR__."/includes/db.php";
require_once __DIR__."/includes/auth.php";
$pageTitle="Login";

if ($_SERVER["REQUEST_METHOD"]==="POST") {
  $email=trim($_POST["email"]??"");
  $pass=$_POST["password"]??"";

  $stmt=$conn->prepare("SELECT user_id, full_name, password_hash, role FROM users WHERE email=?");
  $stmt->bind_param("s",$email);
  $stmt->execute();
  $res=$stmt->get_result();
  $u=$res->fetch_assoc();

  if (!$u || !password_verify($pass,$u["password_hash"])) {
    flash_set("Invalid email or password.","bad");
  } else {
    $_SESSION["user_id"]=(int)$u["user_id"];
    $_SESSION["full_name"]=$u["full_name"];
    $_SESSION["role"]=$u["role"];
    flash_set("Logged in.","ok");
    if ($u["role"]==="ADMIN") header("Location: admin.php");
    elseif ($u["role"]==="PARTNER") header("Location: partner.php");
    else header("Location: habit-user.php");
    exit;
  }
}
require_once __DIR__."/includes/header.php";
?>
<div class="card" style="max-width:560px;">
  <h1 class="h1">Login</h1>
  <form method="POST">
    <label class="label">Email</label>
    <input class="input" name="email">
    <label class="label">Password</label>
    <input class="input" type="password" name="password">
    <div style="margin-top:14px;display:flex;gap:10px;">
      <button class="btn btnPrimary" type="submit">Login</button>
      <a class="btn btnSecondary" href="register.php">Register</a>
    </div>
  </form>
</div>
<?php require_once __DIR__."/includes/footer.php"; ?>