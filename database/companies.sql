-- Run on the same database as schema.sql. Adds company/brand tabs on the customers page.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS companies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    start_year VARCHAR(4) NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- customer_id matches the id shown in customer_installation_view (negative for locally-created rows),
-- so it works for both externally-synced and locally-added customers without touching either source.
CREATE TABLE IF NOT EXISTS customer_companies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_customer_company (customer_id, company_id),
    CONSTRAINT fk_customer_companies_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO companies (name) VALUES ('CAP+')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO permissions (name, slug, description) VALUES
('จัดการบริษัท', 'manage_companies', 'เพิ่ม/แก้ไขแท็บบริษัทและกำหนดลูกค้าในหน้ารายชื่อ')
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

-- Admin receives the new permission automatically, same as every other permission.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.slug = 'admin' AND p.slug = 'manage_companies';
