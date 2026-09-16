-- Run on the same MySQL database as the application. Existing customer tables are not modified.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id BIGINT UNSIGNED NOT NULL,
    username VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    session_token CHAR(64) NULL,
    last_login_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id),
    INDEX idx_users_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional per-user overrides: allow=1 grants and allow=0 denies a role permission.
CREATE TABLE IF NOT EXISTS user_permissions (
    user_id BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    allow TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (user_id, permission_id),
    CONSTRAINT fk_up_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_up_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) NULL,
    entity_id VARCHAR(100) NULL,
    description TEXT NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_activity_user_date (user_id, created_at),
    INDEX idx_activity_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    username_attempt VARCHAR(100) NOT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_login_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_login_username_date (username_attempt, created_at),
    INDEX idx_login_ip_date (ip_address, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (name, slug, description) VALUES
('Admin', 'admin', 'จัดการระบบทั้งหมด'),
('Manager', 'manager', 'ดู Dashboard รายงาน Export และข้อมูลทั้งหมด'),
('Staff', 'staff', 'ดูและแก้ไขข้อมูลตามสิทธิ์'),
('Viewer', 'viewer', 'ดูข้อมูลเท่านั้น'),
('Auditor', 'auditor', 'ดูข้อมูลและประวัติการใช้งาน')
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

INSERT INTO permissions (name, slug, description) VALUES
('ดู Dashboard', 'view_dashboard', 'เข้าดู Dashboard และกราฟ'),
('ดูลูกค้า', 'view_customer', 'ดูรายการและรายละเอียดลูกค้า'),
('เพิ่มลูกค้า', 'create_customer', 'เพิ่มข้อมูลลูกค้า'),
('แก้ไขลูกค้า', 'edit_customer', 'แก้ไขข้อมูลลูกค้า'),
('ลบลูกค้า', 'delete_customer', 'ลบข้อมูลลูกค้า'),
('Export ลูกค้า', 'export_customer', 'Export Excel และ CSV'),
('จัดการผู้ใช้', 'manage_users', 'จัดการผู้ใช้ Role และ Permission'),
('ดู Activity Log', 'view_activity_log', 'ดู Login History และ Activity Log')
ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

-- Admin receives all permissions.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.slug = 'admin';

-- Standard role defaults (editable later in Admin > Roles).
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.slug IN ('view_dashboard','view_customer','export_customer') WHERE r.slug = 'manager';
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.slug IN ('view_dashboard','view_customer','edit_customer') WHERE r.slug = 'staff';
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.slug IN ('view_dashboard','view_customer') WHERE r.slug = 'viewer';
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.slug IN ('view_dashboard','view_customer','view_activity_log') WHERE r.slug = 'auditor';

-- Create the first admin safely with: php database/create_admin.php admin "Administrator" "StrongPassword"
