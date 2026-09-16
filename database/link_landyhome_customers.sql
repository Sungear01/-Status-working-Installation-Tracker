-- Read master customer/zone data from landyhome without modifying it.
ALTER TABLE customer_installations
    MODIFY customer_name VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS worksheet_id INT UNSIGNED NULL AFTER id,
    ADD COLUMN IF NOT EXISTS customer_id INT UNSIGNED NULL AFTER worksheet_id,
    ADD COLUMN IF NOT EXISTS zone VARCHAR(100) NULL AFTER customer_id,
    ADD UNIQUE INDEX IF NOT EXISTS uq_tracking_worksheet (worksheet_id),
    ADD INDEX IF NOT EXISTS idx_tracking_customer (customer_id);

ALTER TABLE customer_installations
    ADD COLUMN IF NOT EXISTS construction_notes TEXT NULL AFTER notes,
    ADD COLUMN IF NOT EXISTS work_status VARCHAR(255) NULL AFTER construction_notes,
    ADD COLUMN IF NOT EXISTS cap_notes TEXT NULL AFTER work_status,
    ADD COLUMN IF NOT EXISTS installation_date_3 DATE NULL AFTER engineer_appointment_3,
    MODIFY status VARCHAR(100) NOT NULL DEFAULT 'STATUS';

-- Keep existing notes available in the new CAP+ note field.
UPDATE customer_installations
SET cap_notes = notes
WHERE cap_notes IS NULL AND notes IS NOT NULL AND notes <> '';

UPDATE customer_installations
SET status = 'STATUS'
WHERE status = 'PENDING';

CREATE OR REPLACE VIEW customer_installation_view AS
SELECT
    w.worksheet_id AS id,
    COALESCE(NULLIF(ci.customer_name, ''), c.customer_name) AS customer_name,
    COALESCE(NULLIF(ci.zone, ''), w.zone) AS zone,
    ci.machine_size AS machine_size,
    ci.po_sent_date AS po_sent_date,
    CASE WHEN ci.po_sent_date IS NULL THEN NULL ELSE DATE_ADD(ci.po_sent_date, INTERVAL 61 DAY) END AS po_due_date,
    ci.engineer_appointment_1 AS engineer_appointment_1,
    ci.installation_date_1 AS installation_date_1,
    ci.engineer_appointment_2 AS engineer_appointment_2,
    ci.installation_date_2 AS installation_date_2,
    ci.engineer_appointment_3 AS engineer_appointment_3,
    ci.installation_date_3 AS installation_date_3,
    CASE WHEN ci.po_sent_date IS NULL THEN NULL ELSE DATE_ADD(ci.po_sent_date, INTERVAL 60 DAY) END AS due_60_date,
    COALESCE(NULLIF(ci.status, ''), 'STATUS') AS status,
    ci.notes AS notes,
    ci.construction_notes AS construction_notes,
    ci.work_status AS work_status,
    ci.cap_notes AS cap_notes,
    ci.updated_by AS updated_by,
    ci.updated_at AS updated_at,
    -- From landyhome.projects: the real customer contract-signing date (Customer Service module). 1970-01-01 is the unset placeholder there.
    CASE WHEN p.sign_contract_date IS NULL OR p.sign_contract_date <= '1970-01-02' THEN NULL ELSE DATE(p.sign_contract_date) END AS contract_sign_date
FROM landyhome.worksheets AS w
INNER JOIN landyhome.customers AS c
    ON c.customer_id = w.customer_id
LEFT JOIN cap_plus.customer_installations AS ci
    ON ci.worksheet_id = w.worksheet_id
LEFT JOIN landyhome.projects AS p
    ON p.project_id = w.project_id
UNION ALL
-- Local customers created by an Admin. Negative IDs distinguish them from Landy Home worksheets.
SELECT
    -ci.id AS id,
    ci.customer_name AS customer_name,
    ci.zone AS zone,
    ci.machine_size AS machine_size,
    ci.po_sent_date AS po_sent_date,
    CASE WHEN ci.po_sent_date IS NULL THEN NULL ELSE DATE_ADD(ci.po_sent_date, INTERVAL 61 DAY) END AS po_due_date,
    ci.engineer_appointment_1 AS engineer_appointment_1,
    ci.installation_date_1 AS installation_date_1,
    ci.engineer_appointment_2 AS engineer_appointment_2,
    ci.installation_date_2 AS installation_date_2,
    ci.engineer_appointment_3 AS engineer_appointment_3,
    ci.installation_date_3 AS installation_date_3,
    CASE WHEN ci.po_sent_date IS NULL THEN NULL ELSE DATE_ADD(ci.po_sent_date, INTERVAL 60 DAY) END AS due_60_date,
    COALESCE(NULLIF(ci.status, ''), 'STATUS') AS status,
    ci.notes AS notes,
    ci.construction_notes AS construction_notes,
    ci.work_status AS work_status,
    ci.cap_notes AS cap_notes,
    ci.updated_by AS updated_by,
    ci.updated_at AS updated_at,
    -- Locally-created rows have no linked landyhome project/contract.
    NULL AS contract_sign_date
FROM cap_plus.customer_installations AS ci
WHERE ci.worksheet_id IS NULL;
