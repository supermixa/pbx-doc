-- ============================================================================
-- Миграция 0006: визуальный конструктор сценария (граф блоков)
--
-- Применять на УЖЕ РАЗВЁРНУТОЙ базе (там, где schema.sql уже был накатан
-- раньше и есть данные). Для новой установки миграция не нужна — всё уже
-- входит в schema.sql.
--
-- Запуск:
--   mysql --default-character-set=utf8mb4 -u <пользователь> -p <база> \
--       < database/migrations/0006_scenario_flow_builder.sql
--
-- Миграция идемпотентна: повторный запуск не приведёт к ошибке благодаря
-- проверкам через information_schema.
--
-- Поля scenarios.context и scenarios.dialplan_code НЕ удаляются (данные
-- сохраняются), но приложение больше их не показывает и не редактирует —
-- их заменил визуальный конструктор (flow_json).
-- ============================================================================

SET NAMES utf8mb4;

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'scenarios' AND COLUMN_NAME = 'flow_json'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE scenarios ADD COLUMN flow_json LONGTEXT NULL AFTER description',
    'SELECT ''column flow_json already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
