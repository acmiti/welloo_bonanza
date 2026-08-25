-- Migration: 05_add_entry_multiplier
-- Adds a winning-chance multiplier per entry. An entry with multiplier = N gets
-- N slices in the draw pool / wheel, multiplying its odds of being picked.

ALTER TABLE `bonanza_entries`
    ADD COLUMN IF NOT EXISTS `multiplier` INT UNSIGNED NOT NULL DEFAULT 1;
