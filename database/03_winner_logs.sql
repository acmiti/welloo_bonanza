-- Migration: 03_winner_logs
-- Adds a proper "won at" timestamp + verification status to winning entries,
-- and an audit log table for disqualify/re-spin actions.

ALTER TABLE `bonanza_entries`
    ADD COLUMN IF NOT EXISTS `won_at` TIMESTAMP NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `verification_status` ENUM('pending', 'verified') NOT NULL DEFAULT 'pending';

CREATE TABLE IF NOT EXISTS `disqualifications` (
    `id`               INT AUTO_INCREMENT PRIMARY KEY,
    `entry_id`         INT NOT NULL,
    `batch_id`         INT NOT NULL,
    `reason`           VARCHAR(255) NOT NULL,
    `disqualified_by`  INT NULL,
    `disqualified_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Foreign keys added separately (see note in 02_create_draw_batches.sql on why
-- these aren't wrapped in IF NOT EXISTS). Safe to ignore an error here on a re-run.
ALTER TABLE `disqualifications`
    ADD CONSTRAINT `fk_disqualifications_entry` FOREIGN KEY (`entry_id`) REFERENCES `bonanza_entries` (`id`);
ALTER TABLE `disqualifications`
    ADD CONSTRAINT `fk_disqualifications_batch` FOREIGN KEY (`batch_id`) REFERENCES `draw_batches` (`id`);
ALTER TABLE `disqualifications`
    ADD CONSTRAINT `fk_disqualifications_user` FOREIGN KEY (`disqualified_by`) REFERENCES `users` (`id`);
