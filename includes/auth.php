<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function flash_set(string $msg, string $type = "ok"): void {
    $_SESSION["flash"] = ["msg" => $msg, "type" => $type];
}
function flash_get(): ?array {
    if (!isset($_SESSION["flash"])) return null;
    $f = $_SESSION["flash"];
    unset($_SESSION["flash"]);
    return $f;
}

function is_logged_in(): bool  { return isset($_SESSION["user_id"]); }
function current_user_id(): int { return (int)($_SESSION["user_id"] ?? 0); }
function current_role(): string { return (string)($_SESSION["role"] ?? "GUEST"); }
function current_name(): string { return (string)($_SESSION["full_name"] ?? ""); }

function require_login(): void {
    if (!is_logged_in()) { header("Location: login.php"); exit; }
}
function require_role(string $role): void {
    require_login();
    if (current_role() !== $role) { http_response_code(403); die("403 Forbidden"); }
}
