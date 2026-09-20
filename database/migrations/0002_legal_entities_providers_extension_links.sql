-- ============================================================================
-- Миграция 0002: юрлица, провайдеры, привязки внешних номеров
--
-- Применять на УЖЕ РАЗВЁРНУТОЙ базе (там, где schema.sql уже был накатан
-- раньше и есть данные). Для новой установки миграция не нужна — всё уже
-- входит в schema.sql.
--
-- Запуск:
--   mysql --default-character-set=utf8mb4 -u <пользователь> -p <база> \
--       < database/migrations/0002_legal_entities_providers_extension_links.sql
--
-- Миграция идемпотентна: повторный запуск не приведёт к ошибке благодаря
-- проверкам через information_schema.
-- ============================================================================

SET NAMES utf8mb4;

-- ----------------------------------------------------------------------------
-- Юрлица
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS legal_entities (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(190)        NOT NULL,
    inn             VARCHAR(20)         NULL,
    created_by      INT UNSIGNED        NULL,
    updated_by      INT UNSIGNED        NULL,
    created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_legal_entities_name (name),
    KEY idx_legal_entities_inn (inn),
    CONSTRAINT fk_legal_entities_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_legal_entities_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Провайдеры
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS providers (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(190)        NOT NULL,
    contract_number VARCHAR(100)        NULL,
    support_phone   VARCHAR(50)         NULL,
    note            TEXT                NULL,
    created_by      INT UNSIGNED        NULL,
    updated_by      INT UNSIGNED        NULL,
    created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_providers_name (name),
    CONSTRAINT fk_providers_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_providers_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS provider_managers (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider_id     INT UNSIGNED        NOT NULL,
    name            VARCHAR(150)        NULL,
    phone           VARCHAR(50)         NULL,
    sort_order      TINYINT UNSIGNED    NOT NULL DEFAULT 0,
    KEY idx_provider_managers_provider (provider_id),
    CONSTRAINT fk_provider_managers_provider FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS provider_legal_entities (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider_id     INT UNSIGNED        NOT NULL,
    legal_entity_id INT UNSIGNED        NOT NULL,
    manager_id      INT UNSIGNED        NULL,
    KEY idx_ple_provider (provider_id),
    KEY idx_ple_legal_entity (legal_entity_id),
    UNIQUE KEY uq_provider_legal_entity (provider_id, legal_entity_id),
    CONSTRAINT fk_ple_provider FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE,
    CONSTRAINT fk_ple_legal_entity FOREIGN KEY (legal_entity_id) REFERENCES legal_entities(id) ON DELETE CASCADE,
    CONSTRAINT fk_ple_manager FOREIGN KEY (manager_id) REFERENCES provider_managers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- extensions: новые колонки legal_entity_id, provider_id, trunk_id
-- (добавляются только если их ещё нет — безопасно перезапускать)
-- ----------------------------------------------------------------------------
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'extensions' AND COLUMN_NAME = 'legal_entity_id'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE extensions ADD COLUMN legal_entity_id INT UNSIGNED NULL AFTER department_id',
    'SELECT ''column legal_entity_id already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'extensions' AND COLUMN_NAME = 'provider_id'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE extensions ADD COLUMN provider_id INT UNSIGNED NULL AFTER legal_entity_id',
    'SELECT ''column provider_id already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'extensions' AND COLUMN_NAME = 'trunk_id'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE extensions ADD COLUMN trunk_id INT UNSIGNED NULL AFTER provider_id',
    'SELECT ''column trunk_id already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Внешние ключи на новые колонки (тоже с проверкой на повтор)
SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'extensions' AND CONSTRAINT_NAME = 'fk_extensions_legal_entity'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE extensions ADD CONSTRAINT fk_extensions_legal_entity FOREIGN KEY (legal_entity_id) REFERENCES legal_entities(id) ON DELETE SET NULL',
    'SELECT ''fk fk_extensions_legal_entity already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'extensions' AND CONSTRAINT_NAME = 'fk_extensions_provider'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE extensions ADD CONSTRAINT fk_extensions_provider FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE SET NULL',
    'SELECT ''fk fk_extensions_provider already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'extensions' AND CONSTRAINT_NAME = 'fk_extensions_trunk'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE extensions ADD CONSTRAINT fk_extensions_trunk FOREIGN KEY (trunk_id) REFERENCES trunks(id) ON DELETE SET NULL',
    'SELECT ''fk fk_extensions_trunk already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ----------------------------------------------------------------------------
-- Переносим старое значение type='did' (до миграции 0001) в новый набор
-- значений для внешних номеров: 'did' -> 'did_city' (Городской DID).
-- Безопасно перезапускать — после первого запуска строк со старым 'did' не остаётся.
-- ----------------------------------------------------------------------------
UPDATE extensions SET type = 'did_city' WHERE type = 'did';
