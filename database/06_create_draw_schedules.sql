-- Migration: 06_create_draw_schedules
-- Adds the Draw Scheduling & Auto-Rollover queue. Each row is one planned draw
-- window: it opens at `start_time`, closes (draw is taken) at `cutoff_time`, and
-- the api/get_active_draw.php endpoint promotes/completes rows automatically as
-- the Asia/Colombo server clock crosses those boundaries.
--
-- All datetimes are stored as Sri Lanka wall-clock (Asia/Colombo, UTC+5:30) —
-- config/db.php pins both PHP and the MySQL session to +05:30, so NOW() here
-- and every comparison below evaluate against Sri Lanka time.

CREATE TABLE IF NOT EXISTS `draw_schedules` (
    `id`           INT AUTO_INCREMENT PRIMARY KEY,
    `title`        VARCHAR(150) NOT NULL,                 -- e.g. "Draw 1 - Week 1"
    `start_time`   DATETIME NOT NULL,                     -- when this draw becomes active
    `cutoff_time`  DATETIME NOT NULL,                     -- entry cut-off / draw moment
    `status`       ENUM('scheduled', 'active', 'completed') NOT NULL DEFAULT 'scheduled',
    `filter_rules` JSON NULL,                             -- inclusion/exclusion presets (see api/get_draw_pool.php keys)
    `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_draw_schedules_status` (`status`),
    INDEX `idx_draw_schedules_start`  (`start_time`),
    INDEX `idx_draw_schedules_cutoff` (`cutoff_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- NOTE: the JSON column type needs MySQL 5.7.8+ / MariaDB 10.2.7+. On MariaDB
-- JSON is a LONGTEXT alias, which is fine — the app stores/reads a JSON string.
-- If your server predates that, change `filter_rules` JSON NULL to
-- `filter_rules` LONGTEXT NULL and re-run.
