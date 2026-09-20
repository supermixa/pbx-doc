-- ============================================================================
-- Миграция 0001: подразделения, оборудование, категории номеров
--
-- Применять на УЖЕ РАЗВЁРНУТОЙ базе (там, где schema.sql уже был накатан
-- раньше и есть данные). Для новой установки миграция не нужна — всё уже
-- входит в schema.sql.
--
-- Запуск:
--   mysql --default-character-set=utf8mb4 -u <пользователь> -p <база> \
--       < database/migrations/0001_departments_equipment_extension_categories.sql
--
-- Миграция идемпотентна: повторный запуск не приведёт к ошибке благодаря
-- проверкам через information_schema.
-- ============================================================================

SET NAMES utf8mb4;

-- ----------------------------------------------------------------------------
-- Подразделения
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS departments (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150)        NOT NULL,
    description     TEXT                NULL,
    created_by      INT UNSIGNED        NULL,
    updated_by      INT UNSIGNED        NULL,
    created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_departments_name (name),
    CONSTRAINT fk_departments_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_departments_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Типы оборудования (вложенный справочник)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS equipment_types (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category        VARCHAR(20)         NOT NULL DEFAULT 'ip_phone',
    name            VARCHAR(150)        NOT NULL,
    model           VARCHAR(150)        NULL,
    brand           VARCHAR(150)        NULL,
    created_by      INT UNSIGNED        NULL,
    updated_by      INT UNSIGNED        NULL,
    created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_equipment_types_category (category),
    KEY idx_equipment_types_name (name),
    CONSTRAINT fk_equipment_types_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_equipment_types_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Оборудование
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS equipment (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    equipment_type_id  INT UNSIGNED        NULL,
    location            VARCHAR(190)        NULL,
    ip_address          VARCHAR(50)         NULL,
    description         TEXT                NULL,
    created_by          INT UNSIGNED        NULL,
    updated_by          INT UNSIGNED        NULL,
    created_at          DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_equipment_location (location),
    CONSTRAINT fk_equipment_type FOREIGN KEY (equipment_type_id) REFERENCES equipment_types(id) ON DELETE SET NULL,
    CONSTRAINT fk_equipment_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_equipment_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- extensions: новые колонки category и department_id
-- (добавляются только если их ещё нет — безопасно перезапускать)
-- ----------------------------------------------------------------------------
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'extensions' AND COLUMN_NAME = 'category'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE extensions ADD COLUMN category VARCHAR(20) NOT NULL DEFAULT ''internal'' AFTER number',
    'SELECT ''column category already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'extensions' AND COLUMN_NAME = 'department_id'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE extensions ADD COLUMN department_id INT UNSIGNED NULL AFTER scenario_id',
    'SELECT ''column department_id already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Индекс и внешний ключ на department_id (тоже с проверкой на повтор)
SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'extensions' AND INDEX_NAME = 'idx_extensions_category'
);
SET @sql := IF(@idx_exists = 0,
    'ALTER TABLE extensions ADD KEY idx_extensions_category (category)',
    'SELECT ''index idx_extensions_category already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'extensions' AND CONSTRAINT_NAME = 'fk_extensions_department'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE extensions ADD CONSTRAINT fk_extensions_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL',
    'SELECT ''fk fk_extensions_department already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ----------------------------------------------------------------------------
-- Заполняем category у уже существующих номеров на основе старого поля type:
-- type = 'did' -> внешний, остальные -> внутренний (можно скорректировать вручную)
-- ----------------------------------------------------------------------------
UPDATE extensions SET category = 'external' WHERE type = 'did';
UPDATE extensions SET category = 'internal' WHERE type != 'did';
