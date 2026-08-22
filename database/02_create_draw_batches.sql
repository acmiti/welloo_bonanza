-- Migration: 02_create_draw_batches
-- Adds weekly draw batches and wires bonanza_entries to them so entries
-- automatically roll over into the next batch once a deadline passes.

CREATE TABLE IF NOT EXISTS `draw_batches` (
    `id`               INT AUTO_INCREMENT PRIMARY KEY,
    `batch_name`       VARCHAR(100) NOT NULL,
    `entry_start_time` DATETIME NOT NULL,
    `entry_deadline`   DATETIME NOT NULL,
    `draw_datetime`    DATETIME NOT NULL,
    `status`           ENUM('draft', 'active', 'locked', 'completed') NOT NULL DEFAULT 'draft',
    `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add batch_id / is_winner to the existing entries table.
-- Uses "IF NOT EXISTS" (MySQL 8.0.29+ / MariaDB 10.0.2+) so this migration
-- is safe to re-run. If your server predates that, drop the "IF NOT EXISTS"
-- on any line phpMyAdmin rejects and re-run just that line once.
ALTER TABLE `bonanza_entries`
    ADD COLUMN IF NOT EXISTS `batch_id` INT NULL,
    ADD COLUMN IF NOT EXISTS `is_winner` TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE `bonanza_entries`
    ADD INDEX IF NOT EXISTS `idx_bonanza_entries_batch_id` (`batch_id`);

-- Foreign key is added separately: it isn't safe to guard with IF NOT EXISTS
-- on every MySQL/MariaDB version, and it requires both tables to be InnoDB.
-- If this line errors because the constraint already exists, that's fine —
-- it means a previous run of this migration already added it.
ALTER TABLE `bonanza_entries`
    ADD CONSTRAINT `fk_bonanza_entries_batch`
    FOREIGN KEY (`batch_id`) REFERENCES `draw_batches` (`id`);

-- Seed an initial active batch, but only if draw_batches is empty —
-- keeps this migration safe to re-run without creating duplicate batches.
INSERT INTO `draw_batches` (`batch_name`, `entry_start_time`, `entry_deadline`, `draw_datetime`, `status`)
SELECT 'Week 1 Draw', NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY), DATE_ADD(NOW(), INTERVAL 7 DAY), 'active'
WHERE NOT EXISTS (SELECT 1 FROM `draw_batches`);
