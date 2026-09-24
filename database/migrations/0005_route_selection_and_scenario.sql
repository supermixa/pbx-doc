-- ============================================================================
-- Миграция 0005: сценарий/тип отбора для маршрутов, состав внутренних номеров
--
-- Применять на УЖЕ РАЗВЁРНУТОЙ базе (там, где schema.sql уже был накатан
-- раньше и есть данные). Для новой установки миграция не нужна — всё уже
-- входит в schema.sql.
--
-- Запуск:
--   mysql --default-character-set=utf8mb4 -u <пользователь> -p <база> \
--       < database/migrations/0005_route_selection_and_scenario.sql
--
-- Миграция идемпотентна: повторный запуск не приведёт к ошибке благодаря
-- проверкам через information_schema.
-- ============================================================================

SET NAMES utf8mb4;

-- ----------------------------------------------------------------------------
-- routes: новые колонки scenario_id, selection_type, department_id,
-- outbound_caller_id_id
-- ----------------------------------------------------------------------------
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'routes' AND COLUMN_NAME = 'scenario_id'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE routes ADD COLUMN scenario_id INT UNSIGNED NULL AFTER direction',
    'SELECT ''column scenario_id already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'routes' AND COLUMN_NAME = 'selection_type'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE routes ADD COLUMN selection_type VARCHAR(20) NULL AFTER pattern',
    'SELECT ''column selection_type already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'routes' AND COLUMN_NAME = 'department_id'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE routes ADD COLUMN department_id INT UNSIGNED NULL AFTER selection_type',
    'SELECT ''column department_id already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'routes' AND COLUMN_NAME = 'outbound_caller_id_id'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE routes ADD COLUMN outbound_caller_id_id INT UNSIGNED NULL AFTER trunk_id',
    'SELECT ''column outbound_caller_id_id already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Внешние ключи новых колонок
SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'routes' AND CONSTRAINT_NAME = 'fk_routes_scenario'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE routes ADD CONSTRAINT fk_routes_scenario FOREIGN KEY (scenario_id) REFERENCES scenarios(id) ON DELETE SET NULL',
    'SELECT ''fk fk_routes_scenario already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'routes' AND CONSTRAINT_NAME = 'fk_routes_department'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE routes ADD CONSTRAINT fk_routes_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL',
    'SELECT ''fk fk_routes_department already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'routes' AND CONSTRAINT_NAME = 'fk_routes_outbound_caller_id'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE routes ADD CONSTRAINT fk_routes_outbound_caller_id FOREIGN KEY (outbound_caller_id_id) REFERENCES extensions(id) ON DELETE SET NULL',
    'SELECT ''fk fk_routes_outbound_caller_id already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ----------------------------------------------------------------------------
-- route_members: состав внутренних номеров для исходящего маршрута
-- с типом отбора "Внутренние номера" (та же схема, что extension_group_members)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS route_members (
    id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    route_id              INT UNSIGNED        NOT NULL,
    member_extension_id   INT UNSIGNED        NOT NULL,
    position              SMALLINT UNSIGNED   NOT NULL DEFAULT 0,
    UNIQUE KEY uq_route_members_route_member (route_id, member_extension_id),
    KEY idx_route_members_route (route_id, position),
    KEY idx_route_members_member (member_extension_id),
    CONSTRAINT fk_route_members_route FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE CASCADE,
    CONSTRAINT fk_route_members_member FOREIGN KEY (member_extension_id) REFERENCES extensions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
