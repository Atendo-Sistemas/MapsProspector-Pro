-- v65.0.0 — Auditoria visível de criação de acessos
-- Seguro para reexecução em MySQL/MariaDB via procedure temporária.

SET @schema_name = DATABASE();

SET @has_created_by_user_id = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'created_by_user_id'
);
SET @sql = IF(@has_created_by_user_id = 0,
    'ALTER TABLE users ADD COLUMN created_by_user_id INT NULL AFTER phone',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_created_by_name = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'created_by_name'
);
SET @sql = IF(@has_created_by_name = 0,
    'ALTER TABLE users ADD COLUMN created_by_name VARCHAR(255) NULL AFTER created_by_user_id',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_creator_index = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'users'
      AND INDEX_NAME = 'idx_users_created_by'
);
SET @sql = IF(@has_creator_index = 0,
    'CREATE INDEX idx_users_created_by ON users (created_by_user_id)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
