-- Adds scheduled encouragement support to HabitOS
-- Run this once in phpMyAdmin after importing habitos_db.sql.

CREATE TABLE IF NOT EXISTS scheduled_encouragements (
  scheduled_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  connection_id INT UNSIGNED NOT NULL,
  sender_user_id INT UNSIGNED NOT NULL,
  target_user_id INT UNSIGNED NOT NULL,
  message_text TEXT NOT NULL,
  send_time TIME DEFAULT NULL,
  trigger_type ENUM('TIME_BASED','STREAK_RISK') NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_sent_date DATE DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (scheduled_id),
  KEY idx_scheduled_connection_id (connection_id),
  KEY idx_scheduled_sender_user_id (sender_user_id),
  KEY idx_scheduled_target_user_id (target_user_id),
  CONSTRAINT fk_scheduled_connection
    FOREIGN KEY (connection_id)
    REFERENCES user_connections(connection_id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_scheduled_sender
    FOREIGN KEY (sender_user_id)
    REFERENCES users(user_id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT fk_scheduled_target
    FOREIGN KEY (target_user_id)
    REFERENCES users(user_id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
