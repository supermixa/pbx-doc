-- ============================================================================
-- Миграция 0004:
--   1) Справочник "Музыка" (WAV/MP3-файлы: фоновая музыка, объявления).
--   2) Настройки очереди / группы обзвона для номеров с type = queue | ivr
--      (extension_queue_settings) и упорядоченный состав участников
--      (extension_group_members).
--
-- Применять на УЖЕ РАЗВЁРНУТОЙ базе. Для новой установки миграция не нужна —
-- всё уже входит в schema.sql.
--
-- Запуск:
--   mysql --default-character-set=utf8mb4 -u <пользователь> -p <база> \
--       < database/migrations/0004_music_and_queue_settings.sql
--
-- Миграция идемпотентна: повторный запуск не приведёт к ошибке (все таблицы
-- создаются через CREATE TABLE IF NOT EXISTS).
--
-- ВАЖНО: после накатки миграции убедитесь, что папка storage/music/ создана
-- на сервере и доступна на запись веб-серверу (см. README, раздел «Музыка»).
-- ============================================================================

SET NAMES utf8mb4;

-- ----------------------------------------------------------------------------
-- 1) Справочник "Музыка"
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
-- 2) Настройки очереди / группы обзвона (1 к 1 с extensions)
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
-- 3) Состав очереди / группы обзвона
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
