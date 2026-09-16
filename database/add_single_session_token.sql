-- Run once on an existing CAP+ database to enable one active login per account.
ALTER TABLE users ADD COLUMN session_token CHAR(64) NULL AFTER is_active;
