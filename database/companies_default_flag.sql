-- Adds a "default" flag: customers with no explicit company assignment belong to the default company (CAP+).
SET NAMES utf8mb4;

ALTER TABLE companies ADD COLUMN IF NOT EXISTS is_default TINYINT(1) NOT NULL DEFAULT 0;

UPDATE companies SET is_default = 1 WHERE name = 'CAP+';
