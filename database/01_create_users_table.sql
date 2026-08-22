-- Migration: 01_create_users_table
-- Creates the users table backing the admin authentication system
-- and seeds a default Super Admin account.

CREATE TABLE IF NOT EXISTS `users` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `username`      VARCHAR(50)  NOT NULL UNIQUE,
    `email`         VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role`          ENUM('admin', 'data_entry', 'draw_manager') NOT NULL DEFAULT 'data_entry',
    `status`        ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed: default Super Admin account.
-- password_hash below was generated with PHP's password_hash($plainPassword, PASSWORD_DEFAULT).
-- The plaintext password is NOT stored anywhere in this repo — it was shared with the
-- project owner out of band. Log in once and change it immediately via the Users screen.
INSERT INTO `users` (`username`, `email`, `password_hash`, `role`, `status`)
VALUES (
    'superadmin',
    'ac@inotrend.net',
    '$2y$10$mIXA7F7AC5ouTy/9oO1szOts5jfMBcH1z7/YGnSNtxTaAN7rdz08K',
    'admin',
    'active'
)
ON DUPLICATE KEY UPDATE `username` = `username`;
