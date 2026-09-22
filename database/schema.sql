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
-- Юрлица (справочник)
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
-- Провайдеры (справочник)
-- № договора теперь привязан не к провайдеру целиком, а к конкретной связи
-- "провайдер — юрлицо" (provider_legal_entities.contract_number) — у одного
-- провайдера может быть разный договор на каждое обслуживаемое юрлицо.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS providers (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(190)        NOT NULL,
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

-- Менеджеры провайдера (до 3 на провайдера — ограничение проверяется в коде,
-- sort_order = позиция слота 0..2 на форме)
CREATE TABLE IF NOT EXISTS provider_managers (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider_id     INT UNSIGNED        NOT NULL,
    name            VARCHAR(150)        NULL,
    phone           VARCHAR(50)         NULL,
    sort_order      TINYINT UNSIGNED    NOT NULL DEFAULT 0,
    KEY idx_provider_managers_provider (provider_id),
    CONSTRAINT fk_provider_managers_provider FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Привязка провайдера к обслуживаемым юрлицам + ответственный менеджер + № договора
CREATE TABLE IF NOT EXISTS provider_legal_entities (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider_id     INT UNSIGNED        NOT NULL,
    legal_entity_id INT UNSIGNED        NOT NULL,
    contract_number VARCHAR(100)        NULL,
    manager_id      INT UNSIGNED        NULL,
    KEY idx_ple_provider (provider_id),
    KEY idx_ple_legal_entity (legal_entity_id),
    UNIQUE KEY uq_provider_legal_entity (provider_id, legal_entity_id),
    CONSTRAINT fk_ple_provider FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE,
    CONSTRAINT fk_ple_legal_entity FOREIGN KEY (legal_entity_id) REFERENCES legal_entities(id) ON DELETE CASCADE,
    CONSTRAINT fk_ple_manager FOREIGN KEY (manager_id) REFERENCES provider_managers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Calltracking (справочник целей звонков по подразделениям)
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
-- Музыка (справочник аудиофайлов WAV/MP3 — фоновая музыка, объявления и т.п.)
-- original_filename — как файл назывался при загрузке (поддерживаются русские
-- названия); stored_filename — безопасное уникальное имя на диске
-- (storage/music/), чтобы не зависеть от кодировки файловой системы сервера.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS music_files (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title               VARCHAR(190)        NOT NULL,
    original_filename   VARCHAR(255)        NOT NULL,
    stored_filename      VARCHAR(255)        NOT NULL,
    mime_type            VARCHAR(100)        NOT NULL,
    size_bytes           INT UNSIGNED        NOT NULL DEFAULT 0,
    created_by           INT UNSIGNED        NULL,
    updated_by           INT UNSIGNED        NULL,
    created_at           DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_music_files_stored_filename (stored_filename),
    KEY idx_music_files_created_at (created_at),
    CONSTRAINT fk_music_files_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_music_files_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Номера / экстеншены
-- category: internal (внутренний) | external (внешний)
-- type для internal: internal | queue | ivr | other
--   (в интерфейсе: Внутренний | Очередь (Queue) | Группа обзвона (Ring Group) | Служебный номер)
-- type для external: did_city | did_mobile | calltracking | other
-- department_id заполняется только для внутренних номеров
-- legal_entity_id / provider_id / trunk_id заполняются только для внешних номеров
-- calltracking_target_id заполняется только когда type = calltracking
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS extensions (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    number                  VARCHAR(50)         NOT NULL,
    category                VARCHAR(20)         NOT NULL DEFAULT 'internal',
    type                    VARCHAR(20)         NOT NULL DEFAULT 'internal',
    owner                   VARCHAR(150)        NULL,
    context                 VARCHAR(100)        NULL,
    scenario_id             INT UNSIGNED        NULL,
    department_id           INT UNSIGNED        NULL,
    legal_entity_id         INT UNSIGNED        NULL,
    provider_id             INT UNSIGNED        NULL,
    trunk_id                INT UNSIGNED        NULL,
    calltracking_target_id  INT UNSIGNED        NULL,
    status                  VARCHAR(20)         NOT NULL DEFAULT 'active',
    description             TEXT                NULL,
    created_by              INT UNSIGNED        NULL,
    updated_by              INT UNSIGNED        NULL,
    created_at              DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_extensions_number (number),
    KEY idx_extensions_type (type),
    KEY idx_extensions_category (category),
    CONSTRAINT fk_extensions_scenario FOREIGN KEY (scenario_id) REFERENCES scenarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_extensions_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    CONSTRAINT fk_extensions_legal_entity FOREIGN KEY (legal_entity_id) REFERENCES legal_entities(id) ON DELETE SET NULL,
    CONSTRAINT fk_extensions_provider FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE SET NULL,
    CONSTRAINT fk_extensions_trunk FOREIGN KEY (trunk_id) REFERENCES trunks(id) ON DELETE SET NULL,
    CONSTRAINT fk_extensions_calltracking_target FOREIGN KEY (calltracking_target_id) REFERENCES calltracking_targets(id) ON DELETE SET NULL,
    CONSTRAINT fk_extensions_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_extensions_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Настройки очереди / группы обзвона — расширяют номер с type = queue | ivr
-- (1 к 1, extension_id одновременно и PK, и FK).
-- ring_strategy для queue: ringall | leastrecent | fewestcalls | random |
--   rrmemory | linear; для ivr (группа обзвона) допустимы только ringall | linear.
-- caller_wait_sound, moh_music_id, announce_*, announcement_music_id,
-- failover* — используются только для type = queue.
-- failover{1,2}_target_type: scenario | extension (extension покрывает и
-- внутренние номера, и очереди/группы обзвона/служебные номера — это всё
-- строки таблицы extensions, различающиеся полем type).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS extension_queue_settings (
    extension_id                INT UNSIGNED        NOT NULL PRIMARY KEY,
    ring_strategy                VARCHAR(20)          NOT NULL DEFAULT 'ringall',
    agent_answer_timeout_sec     SMALLINT UNSIGNED    NULL,
    agent_wrapup_time_sec        SMALLINT UNSIGNED    NULL,
    agent_ring_in_use            TINYINT(1)           NOT NULL DEFAULT 0,
    caller_wait_sound             VARCHAR(20)          NOT NULL DEFAULT 'music',
    moh_music_id                  INT UNSIGNED         NULL,
    announce_position             TINYINT(1)           NOT NULL DEFAULT 0,
    announce_hold_time            TINYINT(1)           NOT NULL DEFAULT 0,
    announcement_music_id         INT UNSIGNED         NULL,
    announcement_repeat_sec       SMALLINT UNSIGNED    NULL,
    failover1_timeout_sec         SMALLINT UNSIGNED    NULL,
    failover1_target_type         VARCHAR(20)          NULL,
    failover1_target_id           INT UNSIGNED         NULL,
    failover2_target_type         VARCHAR(20)          NULL,
    failover2_target_id           INT UNSIGNED         NULL,
    CONSTRAINT fk_eqs_extension FOREIGN KEY (extension_id) REFERENCES extensions(id) ON DELETE CASCADE,
    CONSTRAINT fk_eqs_moh_music FOREIGN KEY (moh_music_id) REFERENCES music_files(id) ON DELETE RESTRICT,
    CONSTRAINT fk_eqs_announcement_music FOREIGN KEY (announcement_music_id) REFERENCES music_files(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Состав очереди / группы обзвона — упорядоченный список номеров-участников.
-- Участником может быть только номер с category=internal и type=internal
-- (проверяется в коде).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS extension_group_members (
    id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    extension_id          INT UNSIGNED        NOT NULL,
    member_extension_id   INT UNSIGNED        NOT NULL,
    position               SMALLINT UNSIGNED   NOT NULL DEFAULT 0,
    UNIQUE KEY uq_egm_extension_member (extension_id, member_extension_id),
    KEY idx_egm_extension (extension_id, position),
    KEY idx_egm_member (member_extension_id),
    CONSTRAINT fk_egm_extension FOREIGN KEY (extension_id) REFERENCES extensions(id) ON DELETE CASCADE,
    CONSTRAINT fk_egm_member FOREIGN KEY (member_extension_id) REFERENCES extensions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Журнал аудита — кто, что и когда изменил
-- entity_type: trunk | route | scenario | extension | user | department |
--              equipment_type | equipment | legal_entity | provider |
--              calltracking_target | music_file
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
