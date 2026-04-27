<?php
// dashboard.php — role-based router
// The old session-based prototype has been replaced.
// After login, users land here and are forwarded to their actual page.
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/auth.php";
require_login();

switch (current_role()) {
    case "ADMIN":
        header("Location: admin.php"); break;
    case "PARTNER":
        header("Location: partner.php"); break;
    case "HABIT_USER":
    default:
        header("Location: habit-user.php"); break;
}
exit;
