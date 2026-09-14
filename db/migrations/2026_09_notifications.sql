-- In-app notifications: Admin announcements / updates / reminders shown to
-- users via the bell in the top bar. Non-destructive (only creates tables).

CREATE TABLE IF NOT EXISTS `t_notification` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `body` text NULL DEFAULT NULL,
  `type` enum('info','update','reminder') NOT NULL DEFAULT 'info',
  `audience` enum('all','user') NOT NULL DEFAULT 'all',
  `user_id` int(11) NULL DEFAULT NULL COMMENT 'target user when audience = user',
  `created_by` varchar(150) NULL DEFAULT NULL COMMENT 'admin name who sent it',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notif_audience` (`audience`, `user_id`),
  KEY `idx_notif_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Per-viewer read state. user_id = 0 represents the shared Admin identity.
-- No FK on user_id so Admin (0) and any viewer can be recorded freely.
CREATE TABLE IF NOT EXISTS `t_notification_read` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `read_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notification_id`, `user_id`),
  CONSTRAINT `fk_notifread_notif` FOREIGN KEY (`notification_id`) REFERENCES `t_notification` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
