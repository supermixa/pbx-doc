-- ============================================================================
-- Миграция 0010: поле «Отдел» у внутренних номеров
--
-- Применять на УЖЕ РАЗВЁРНУТОЙ базе (там, где schema.sql уже был накатан
-- раньше и есть данные). Для новой установки миграция не нужна — всё уже
-- входит в schema.sql. Требует миграцию 0009 (таблица divisions).
--
-- Запуск:
--   mysql --default-character-set=utf8mb4 -u <пользователь> -p <база> \
--       < database/migrations/0010_extension_division.sql
--
-- Миграция идемпотентна: повторный запуск не приведёт к ошибке благодаря
-- проверкам через information_schema.
--
-- Добавляет extensions.division_id — «Отдел» (справочник «Отделы») у
-- внутренних номеров с типом «Внутренний»; при удалении отдела поле
-- у номеров очищается.
-- ============================================================================

SET NAMES utf8mb4;

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'extensions' AND COLUMN_NAME = 'division_id'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE extensions ADD COLUMN division_id INT UNSIGNED NULL AFTER caller_id_extension_id',
    'SELECT ''column division_id already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'extensions' AND CONSTRAINT_NAME = 'fk_extensions_division'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE extensions ADD CONSTRAINT fk_extensions_division FOREIGN KEY (division_id) REFERENCES divisions(id) ON DELETE SET NULL',
    'SELECT ''fk fk_extensions_division already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
