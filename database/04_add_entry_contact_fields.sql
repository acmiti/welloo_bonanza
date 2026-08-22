-- Migration: 04_add_entry_contact_fields
-- Adds email and invoice/bill number fields to entries so admins can record
-- and edit them from the Entries management screen.

ALTER TABLE `bonanza_entries`
    ADD COLUMN IF NOT EXISTS `email` VARCHAR(150) NULL,
    ADD COLUMN IF NOT EXISTS `invoice_number` VARCHAR(100) NULL;
