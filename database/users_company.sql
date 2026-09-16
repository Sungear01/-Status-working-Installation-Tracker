-- Lets an Admin tag each user with the company (CAP+ / NOVA / ...) they belong to.
SET NAMES utf8mb4;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS company_id BIGINT UNSIGNED NULL AFTER role_id,
    ADD INDEX IF NOT EXISTS idx_users_company (company_id);
