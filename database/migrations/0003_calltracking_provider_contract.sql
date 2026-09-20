-- ============================================================================
-- Миграция 0003:
--   1) № договора провайдера переносится с провайдера на конкретную связь
--      "провайдер — юрлицо" (провайдер может обслуживать разные юрлица по
--      разным договорам).
--   2) Справочник "Calltracking" (подразделение + цель).
--   3) У внешних номеров тип CallTracking получает поле "Цель" (ссылка на
--      справочник Calltracking).
--
-- Применять на УЖЕ РАЗВЁРНУТОЙ базе. Для новой установки миграция не нужна —
-- всё уже входит в schema.sql.
--
-- Запуск:
--   mysql --default-character-set=utf8mb4 -u <пользователь> -p <база> \
--       < database/migrations/0003_calltracking_provider_contract.sql
--
-- Миграция идемпотентна: повторный запуск не приведёт к ошибке.
-- ============================================================================

SET NAMES utf8mb4;

-- ----------------------------------------------------------------------------
-- 1) provider_legal_entities.contract_number
-- ----------------------------------------------------------------------------
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provider_legal_entities' AND COLUMN_NAME = 'contract_number'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE provider_legal_entities ADD COLUMN contract_number VARCHAR(100) NULL AFTER legal_entity_id',
    'SELECT ''column provider_legal_entities.contract_number already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Переносим существующее значение providers.contract_number на все связи
-- этого провайдера, если у связи ещё не указан свой номер договора.
-- Безопасно перезапускать.
SET @providers_contract_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'providers' AND COLUMN_NAME = 'contract_number'
);
SET @sql := IF(@providers_contract_exists > 0,
    'UPDATE provider_legal_entities ple
        JOIN providers p ON p.id = ple.provider_id
        SET ple.contract_number = p.contract_number
        WHERE ple.contract_number IS NULL AND p.contract_number IS NOT NULL AND p.contract_number <> ''''',
    'SELECT ''providers.contract_number already removed, nothing to backfill'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(@providers_contract_exists > 0,
    'ALTER TABLE providers DROP COLUMN contract_number',
    'SELECT ''column providers.contract_number already removed'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ----------------------------------------------------------------------------
-- 2) Справочник Calltracking (подразделение + цель)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS calltracking_targets (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    department_id   INT UNSIGNED        NOT NULL,
    target          VARCHAR(190)        NOT NULL,
    created_by      INT UNSIGNED        NULL,
    updated_by      INT UNSIGNED        NULL,
    created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_calltracking_targets_department (department_id),
    KEY idx_calltracking_targets_target (target),
    CONSTRAINT fk_calltracking_targets_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    CONSTRAINT fk_calltracking_targets_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_calltracking_targets_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3) extensions.calltracking_target_id
-- ----------------------------------------------------------------------------
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'extensions' AND COLUMN_NAME = 'calltracking_target_id'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE extensions ADD COLUMN calltracking_target_id INT UNSIGNED NULL AFTER trunk_id',
    'SELECT ''column extensions.calltracking_target_id already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'extensions' AND CONSTRAINT_NAME = 'fk_extensions_calltracking_target'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE extensions ADD CONSTRAINT fk_extensions_calltracking_target FOREIGN KEY (calltracking_target_id) REFERENCES calltracking_targets(id) ON DELETE SET NULL',
    'SELECT ''fk fk_extensions_calltracking_target already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
