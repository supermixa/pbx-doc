-- ============================================================================
-- Миграция 0011: «Приветствие оператора» у подразделений
--
-- Применять на УЖЕ РАЗВЁРНУТОЙ базе (там, где schema.sql уже был накатан
-- раньше и есть данные). Для новой установки миграция не нужна — всё уже
-- входит в schema.sql.
--
-- Запуск:
--   mysql --default-character-set=utf8mb4 -u <пользователь> -p <база> \
--       < database/migrations/0011_department_operator_greeting.sql
--
-- Миграция идемпотентна: повторный запуск не приведёт к ошибке благодаря
-- проверкам через information_schema.
--
-- Добавляет departments.operator_greeting — текст приветствия оператора.
-- ============================================================================

SET NAMES utf8mb4;

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'departments' AND COLUMN_NAME = 'operator_greeting'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE departments ADD COLUMN operator_greeting TEXT NULL AFTER name',
    'SELECT ''column operator_greeting already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
