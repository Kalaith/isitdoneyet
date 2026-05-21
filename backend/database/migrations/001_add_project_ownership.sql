-- Adds WebHatchery user ownership to Is It Done Yet projects.
-- Before running on legacy public data, optionally set:
--   SET @legacy_owner_id = 'webhatchery-user-id';

SET @legacy_owner_id = IF(COALESCE(@legacy_owner_id, '') = '', 'legacy-owner', @legacy_owner_id);

SET @owner_column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'projects'
      AND COLUMN_NAME = 'owner_id'
);

SET @ddl = IF(
    @owner_column_exists = 0,
    'ALTER TABLE `projects` ADD COLUMN `owner_id` VARCHAR(191) NULL AFTER `id`',
    'SELECT "owner_id already exists"'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE `projects`
SET `owner_id` = @legacy_owner_id
WHERE `owner_id` IS NULL OR `owner_id` = '';

SET @owner_column_nullable = (
    SELECT IS_NULLABLE
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'projects'
      AND COLUMN_NAME = 'owner_id'
);

SET @ddl = IF(
    @owner_column_nullable = 'YES',
    'ALTER TABLE `projects` MODIFY COLUMN `owner_id` VARCHAR(191) NOT NULL',
    'SELECT "owner_id already not null"'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @owner_index_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'projects'
      AND INDEX_NAME = 'idx_projects_owner_parent_completed'
);

SET @ddl = IF(
    @owner_index_exists = 0,
    'CREATE INDEX `idx_projects_owner_parent_completed` ON `projects` (`owner_id`, `parent_id`, `completed`)',
    'SELECT "owner index already exists"'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
