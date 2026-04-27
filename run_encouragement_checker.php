<?php
require_once __DIR__ . "/includes/db.php";

$today = date("Y-m-d");
$current_time = date("H:i:00");
$current_day = (int)date("w"); // Sunday = 0, Monday = 1, etc.
$sentCount = 0;

$hasScheduleTable = $conn->query("SHOW TABLES LIKE 'scheduled_encouragements'");
if (!$hasScheduleTable || $hasScheduleTable->num_rows === 0) {
    die("scheduled_encouragements table not found. Run scheduled_encouragements_schema.sql first.");
}

// 1. Time-based encouragements.
$stmt = $conn->prepare("\n    SELECT scheduled_id, connection_id, sender_user_id, message_text\n    FROM scheduled_encouragements\n    WHERE is_active = 1\n      AND trigger_type = 'TIME_BASED'\n      AND send_time <= ?\n      AND (last_sent_date IS NULL OR last_sent_date < ?)\n");
$stmt->bind_param("ss", $current_time, $today);
$stmt->execute();
$timeMessages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

foreach ($timeMessages as $m) {
    $insert = $conn->prepare("\n        INSERT INTO encouragement_messages (connection_id, sender_user_id, message_text)\n        VALUES (?, ?, ?)\n    ");
    $insert->bind_param("iis", $m["connection_id"], $m["sender_user_id"], $m["message_text"]);

    if ($insert->execute()) {
        $sentCount++;
        $update = $conn->prepare("UPDATE scheduled_encouragements SET last_sent_date = ? WHERE scheduled_id = ?");
        $update->bind_param("si", $today, $m["scheduled_id"]);
        $update->execute();
    }
}

// 2. Streak-risk encouragements.
$stmt = $conn->prepare("\n    SELECT scheduled_id, connection_id, sender_user_id, target_user_id, message_text\n    FROM scheduled_encouragements\n    WHERE is_active = 1\n      AND trigger_type = 'STREAK_RISK'\n      AND (last_sent_date IS NULL OR last_sent_date < ?)\n");
$stmt->bind_param("s", $today);
$stmt->execute();
$riskMessages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

foreach ($riskMessages as $m) {
    $targetUserId = (int)$m["target_user_id"];

    // At risk means: user has an active scheduled habit due today, but no COMPLETED record today.
    $habitCheck = $conn->prepare("\n        SELECT h.habit_id\n        FROM habits h\n        JOIN habit_schedules hs ON hs.habit_id = h.habit_id\n        LEFT JOIN schedule_days sd ON sd.schedule_id = hs.schedule_id\n        LEFT JOIN habit_completions hc\n          ON hc.habit_id = h.habit_id\n         AND hc.completion_date = ?\n         AND hc.status = 'COMPLETED'\n        WHERE h.user_id = ?\n          AND h.is_active = 1\n          AND hs.enabled = 1\n          AND hc.completion_id IS NULL\n          AND (\n            hs.frequency = 'DAILY'\n            OR (hs.frequency IN ('WEEKLY', 'CUSTOM') AND sd.day_of_week = ?)\n          )\n        LIMIT 1\n    ");
    $habitCheck->bind_param("sii", $today, $targetUserId, $current_day);
    $habitCheck->execute();
    $atRisk = $habitCheck->get_result();

    if ($atRisk->num_rows > 0) {
        $insert = $conn->prepare("\n            INSERT INTO encouragement_messages (connection_id, sender_user_id, message_text)\n            VALUES (?, ?, ?)\n        ");
        $insert->bind_param("iis", $m["connection_id"], $m["sender_user_id"], $m["message_text"]);

        if ($insert->execute()) {
            $sentCount++;
            $update = $conn->prepare("UPDATE scheduled_encouragements SET last_sent_date = ? WHERE scheduled_id = ?");
            $update->bind_param("si", $today, $m["scheduled_id"]);
            $update->execute();
        }
    }
}

echo "Encouragement checker completed. Messages sent: " . $sentCount;
