-- ============================================================================
-- PBX Doc — схема базы данных (MySQL / MariaDB, InnoDB, utf8mb4)
-- Документация по номерам, транкам, маршрутам и сценариям Asterisk
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- Пользователи и роли
-- Роли: admin (администратор), user (пользователь), observer (наблюдатель)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150)        NOT NULL,
    email           VARCHAR(190)        NOT NULL,
    password_hash   VARCHAR(255)        NOT NULL,
    role            VARCHAR(20)         NOT NULL DEFAULT 'observer',
    is_active       TINYINT(1)          NOT NULL DEFAULT 1,
    created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Транки (SIP/PJSIP/IAX2 и т.п.)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS trunks (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150)        NOT NULL,
    type            VARCHAR(30)         NOT NULL DEFAULT 'PJSIP',
    provider        VARCHAR(150)        NULL,
    host            VARCHAR(190)        NULL,
    context         VARCHAR(100)        NULL,
    status          VARCHAR(20)         NOT NULL DEFAULT 'active',
    description     TEXT                NULL,
    created_by      INT UNSIGNED        NULL,
    updated_by      INT UNSIGNED        NULL,
    created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_trunks_name (name),
    KEY idx_trunks_status (status),
    CONSTRAINT fk_trunks_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_trunks_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Сценарии (диалплан / IVR)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS scenarios (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150)        NOT NULL,
    context         VARCHAR(100)        NULL,
    description     TEXT                NULL,
    dialplan_code   TEXT                NULL,
    created_by      INT UNSIGNED        NULL,
    updated_by      INT UNSIGNED        NULL,
    created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_scenarios_name (name),
    CONSTRAINT fk_scenarios_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_scenarios_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Маршруты (входящие/исходящие правила маршрутизации)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS routes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150)        NOT NULL,
    direction       VARCHAR(20)         NOT NULL DEFAULT 'outbound',
    pattern         VARCHAR(190)        NULL,
    trunk_id        INT UNSIGNED        NULL,
    context         VARCHAR(100)        NULL,
    description     TEXT                NULL,
    created_by      INT UNSIGNED        NULL,
    updated_by      INT UNSIGNED        NULL,
    created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_routes_name (name),
    KEY idx_routes_direction (direction),
    CONSTRAINT fk_routes_trunk FOREIGN KEY (trunk_id) REFERENCES trunks(id) ON DELETE SET NULL,
    CONSTRAINT fk_routes_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_routes_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Подразделения (справочник для внутренних номеров)
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
-- Типы оборудования (вложенный справочник: каталог моделей/брендов)
-- category: ip_phone | softphone | dect_base | gateway
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
-- Оборудование (шлюзы, базовые станции IP DECT, телефоны и т.п.)
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
-- Номера / экстеншены
-- category: internal (внутренний) | external (внешний)
-- department_id заполняется только для внутренних номеров
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS extensions (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    number          VARCHAR(50)         NOT NULL,
    category        VARCHAR(20)         NOT NULL DEFAULT 'internal',
    type            VARCHAR(20)         NOT NULL DEFAULT 'internal',
    owner           VARCHAR(150)        NULL,
    context         VARCHAR(100)        NULL,
    scenario_id     INT UNSIGNED        NULL,
    department_id   INT UNSIGNED        NULL,
    status          VARCHAR(20)         NOT NULL DEFAULT 'active',
    description     TEXT                NULL,
    created_by      INT UNSIGNED        NULL,
    updated_by      INT UNSIGNED        NULL,
    created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_extensions_number (number),
    KEY idx_extensions_type (type),
    KEY idx_extensions_category (category),
    CONSTRAINT fk_extensions_scenario FOREIGN KEY (scenario_id) REFERENCES scenarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_extensions_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    CONSTRAINT fk_extensions_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_extensions_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Журнал аудита — кто, что и когда изменил
-- entity_type: trunk | route | scenario | extension | user | department | equipment_type | equipment
-- action:      create | update | delete
-- changes:     JSON вида {"field": {"old": ..., "new": ...}, ...}
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_logs (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_type     VARCHAR(30)         NOT NULL,
    entity_id       INT UNSIGNED        NOT NULL,
    action          VARCHAR(20)         NOT NULL,
    user_id         INT UNSIGNED        NULL,
    changes         LONGTEXT            NULL,
    created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_entity (entity_type, entity_id),
    KEY idx_audit_user (user_id),
    KEY idx_audit_created (created_at),
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
