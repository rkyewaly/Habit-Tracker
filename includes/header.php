<?php
require_once __DIR__ . "/auth.php";
$flash = flash_get();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($pageTitle ?? "HabitOS"); ?></title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: system-ui, -apple-system, sans-serif;
      background: #f0f2f7;
      color: #1a1a2e;
      min-height: 100vh;
    }

    /* Nav */
    .nav {
      background: #1a1a2e;
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 24px;
      height: 54px;
      position: sticky;
      top: 0;
      z-index: 100;
    }
    .nav-brand { font-weight: 700; font-size: 1.1rem; letter-spacing: .4px; }
    .nav-brand span {
      display: inline-block;
      background: #5a6fd6;
      color: #fff;
      font-size: .7rem;
      font-weight: 600;
      padding: 2px 8px;
      border-radius: 20px;
      margin-left: 8px;
      vertical-align: middle;
    }
    .nav-links { display: flex; align-items: center; gap: 4px; }
    .nav-link {
      font-size: .82rem;
      padding: 6px 12px;
      border-radius: 20px;
      color: #b0b8d1;
      text-decoration: none;
      transition: background .15s, color .15s;
    }
    .nav-link:hover { background: rgba(255,255,255,.1); color: #fff; }
    .nav-link.active { background: rgba(90,111,214,.35); color: #fff; }
    .nav-user { font-size: .8rem; color: #7880a0; }

    /* Page shell */
    .page { max-width: 1080px; margin: 0 auto; padding: 24px 20px; }

    /* Flash */
    .flash {
      padding: 11px 16px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-size: .88rem;
    }
    .flash.ok  { background: #d4edda; color: #155724; border: 1px solid #b7dfc4; }
    .flash.bad { background: #f8d7da; color: #721c24; border: 1px solid #f1b0b7; }

    /* Generic card */
    .card {
      background: #fff;
      border-radius: 12px;
      border: 1px solid #e4e8f0;
      padding: 24px;
    }

    /* Two-column grid (admin, partner, generic pages) */
    .grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
      gap: 20px;
    }

    /* Typography */
    .h1   { font-size: 1.4rem; font-weight: 700; margin-bottom: 4px; }
    .h2   { font-size: 1.05rem; font-weight: 600; margin-bottom: 6px; }
    .p    { color: #555; line-height: 1.6; margin-bottom: 8px; font-size: .92rem; }
    .small{ font-size: .8rem; color: #888; }
    .sep  { border: none; border-top: 1px solid #edf0f5; margin: 18px 0; }

    /* Form elements */
    .label {
      display: block;
      font-size: .82rem;
      font-weight: 600;
      margin: 12px 0 4px;
      color: #444;
    }
    .input {
      width: 100%;
      padding: 9px 12px;
      border: 1px solid #d8dce8;
      border-radius: 8px;
      font-size: .9rem;
      background: #fafbfd;
      color: #1a1a2e;
      outline: none;
      transition: border-color .2s, box-shadow .2s;
    }
    .input:focus {
      border-color: #5a6fd6;
      box-shadow: 0 0 0 3px rgba(90,111,214,.12);
      background: #fff;
    }

    /* Buttons */
    .btn {
      display: inline-block;
      padding: 8px 18px;
      border-radius: 8px;
      font-size: .87rem;
      font-weight: 600;
      cursor: pointer;
      border: none;
      text-decoration: none;
      transition: opacity .15s, transform .1s;
    }
    .btn:hover  { opacity: .88; }
    .btn:active { transform: scale(.97); }
    .btnPrimary   { background: #5a6fd6; color: #fff; }
    .btnSecondary { background: #eef0f7; color: #3a3f6e; }
    .btnDanger    { background: #e05252; color: #fff; }

    /* Table */
    .table { width: 100%; border-collapse: collapse; font-size: .88rem; }
    .table th {
      font-size: .75rem; font-weight: 600; color: #888;
      text-transform: uppercase; letter-spacing: .5px;
      padding: 8px 10px; border-bottom: 2px solid #edf0f5;
      text-align: left;
    }
    .table td { padding: 10px; border-bottom: 1px solid #f2f4f8; }
    .table tbody tr:last-child td { border-bottom: none; }
    .table tbody tr:hover td { background: #fafbfd; }
  </style>
</head>
<body>

<nav class="nav">
  <div class="nav-brand">
    HabitOS
    <?php if (is_logged_in()): ?>
      <span><?php echo htmlspecialchars(current_role()); ?></span>
    <?php endif; ?>
  </div>

  <div class="nav-links">
    <?php if (is_logged_in()): ?>
      <?php if (current_role() === "HABIT_USER"): ?>
        <a class="nav-link active" href="habit-user.php">Today</a>
      <?php elseif (current_role() === "PARTNER"): ?>
        <a class="nav-link active" href="partner.php">Partner View</a>
      <?php elseif (current_role() === "ADMIN"): ?>
        <a class="nav-link active" href="admin.php">Admin</a>
      <?php endif; ?>
      <a class="nav-link" href="logout.php">Logout</a>
    <?php else: ?>
      <a class="nav-link" href="login.php">Login</a>
      <a class="nav-link" href="register.php">Register</a>
    <?php endif; ?>
  </div>

  <?php if (is_logged_in()): ?>
    <span class="nav-user"><?php echo htmlspecialchars(current_name()); ?></span>
  <?php endif; ?>
</nav>

<div class="page">

<?php if ($flash): ?>
  <div class="flash <?php echo htmlspecialchars($flash['type']); ?>">
    <?php echo htmlspecialchars($flash['msg']); ?>
  </div>
<?php endif; ?>
