-- ============================================================================
-- Миграция 0013: переадресация внешних номеров
--
-- Применять на УЖЕ РАЗВЁРНУТОЙ базе (там, где schema.sql уже был накатан
-- раньше и есть данные). Для новой установки миграция не нужна — всё уже
-- входит в schema.sql.
--
-- Запуск:
--   mysql --default-character-set=utf8mb4 -u <пользователь> -p <база> \
--       < database/migrations/0013_extension_forwarding.sql
--
-- Миграция идемпотентна: повторный запуск не приведёт к ошибке благодаря
-- проверкам через information_schema.
--
-- Добавляет extensions.forward_to_extension_id — внешний номер, на который
-- переадресован внешний номер со статусом «Переадресован» (forwarded).
-- Новые статусы внешних номеров («Переадресован» = forwarded, «Резерв» =
-- reserve) хранятся в той же колонке status — менять её не нужно.
-- ============================================================================

SET NAMES utf8mb4;

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'extensions' AND COLUMN_NAME = 'forward_to_extension_id'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE extensions ADD COLUMN forward_to_extension_id INT UNSIGNED NULL AFTER division_id',
    'SELECT ''column forward_to_extension_id already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'extensions' AND CONSTRAINT_NAME = 'fk_extensions_forward_to'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE extensions ADD CONSTRAINT fk_extensions_forward_to FOREIGN KEY (forward_to_extension_id) REFERENCES extensions(id) ON DELETE SET NULL',
    'SELECT ''fk fk_extensions_forward_to already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
