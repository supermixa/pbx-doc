-- ============================================================================
-- Миграция 0012: исключения номеров из исходящих маршрутов «Подразделения»
--
-- Применять на УЖЕ РАЗВЁРНУТОЙ базе (там, где schema.sql уже был накатан
-- раньше и есть данные). Для новой установки миграция не нужна — всё уже
-- входит в schema.sql.
--
-- Запуск:
--   mysql --default-character-set=utf8mb4 -u <пользователь> -p <база> \
--       < database/migrations/0012_route_excluded_extensions.sql
--
-- Миграция идемпотентна (CREATE TABLE IF NOT EXISTS).
--
-- Создаёт таблицу route_excluded_extensions — внутренние номера, исключённые
-- из исходящего маршрута с типом отбора «Подразделения» (маршрут действует на
-- все внутренние номера подразделения, кроме исключённых).
-- ============================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS route_excluded_extensions (
    route_id        INT UNSIGNED        NOT NULL,
    extension_id    INT UNSIGNED        NOT NULL,
    created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (route_id, extension_id),
    KEY idx_route_excluded_extension (extension_id),
    CONSTRAINT fk_route_excluded_route FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE CASCADE,
    CONSTRAINT fk_route_excluded_extension FOREIGN KEY (extension_id) REFERENCES extensions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
