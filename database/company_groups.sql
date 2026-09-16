-- Run on the same database as companies.sql. When 2+ companies are saved together from the
-- "เพิ่มบริษัท / แก้ไขบริษัท" modal, a combined tab (e.g. "CAP+, NOVA") is shown alongside the
-- individual company tabs, filtering customers that belong to any of the grouped companies.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS company_groups (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS company_group_members (
    group_id BIGINT UNSIGNED NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (group_id, company_id),
    CONSTRAINT fk_company_group_members_group FOREIGN KEY (group_id) REFERENCES company_groups(id) ON DELETE CASCADE,
    CONSTRAINT fk_company_group_members_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
