-- ============================================================================
-- Миграция 0007: транки — провайдер из справочника и «Емкость СЛ»
--
-- Применять на УЖЕ РАЗВЁРНУТОЙ базе (там, где schema.sql уже был накатан
-- раньше и есть данные). Для новой установки миграция не нужна — всё уже
-- входит в schema.sql.
--
-- Запуск:
--   mysql --default-character-set=utf8mb4 -u <пользователь> -p <база> \
--       < database/migrations/0007_trunk_provider_and_capacity.sql
--
-- Миграция идемпотентна: повторный запуск не приведёт к ошибке благодаря
-- проверкам через information_schema.
--
-- Что делает:
--   1) добавляет trunks.provider_id (ссылка на справочник «Провайдеры»);
--   2) добавляет trunks.line_capacity («Емкость СЛ» — число соединительных
--      линий, целое неотрицательное);
--   3) автоматически связывает существующие транки с провайдерами, если
--      старое текстовое значение trunks.provider совпадает с названием
--      провайдера из справочника (без учёта регистра и пробелов по краям);
--      у таких транков старый текст очищается — он больше не нужен.
--
-- Старая текстовая колонка trunks.provider НЕ удаляется: в ней остаются
-- значения, которые не удалось сопоставить со справочником (например,
-- «Билайн» при провайдере «Билайн Бизнес»). Интерфейс показывает их с
-- пометкой «введено вручную», чтобы их можно было найти и выбрать
-- правильного провайдера из справочника.
-- ============================================================================

SET NAMES utf8mb4;

-- ----------------------------------------------------------------------------
-- trunks.provider_id
-- ----------------------------------------------------------------------------
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'trunks' AND COLUMN_NAME = 'provider_id'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE trunks ADD COLUMN provider_id INT UNSIGNED NULL AFTER type',
    'SELECT ''column provider_id already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'trunks' AND CONSTRAINT_NAME = 'fk_trunks_provider'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE trunks ADD CONSTRAINT fk_trunks_provider FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE SET NULL',
    'SELECT ''fk fk_trunks_provider already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ----------------------------------------------------------------------------
-- trunks.line_capacity («Емкость СЛ»)
-- ----------------------------------------------------------------------------
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'trunks' AND COLUMN_NAME = 'line_capacity'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE trunks ADD COLUMN line_capacity INT UNSIGNED NULL AFTER provider',
    'SELECT ''column line_capacity already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ----------------------------------------------------------------------------
-- Перенос старых текстовых значений провайдера в ссылку на справочник
-- ----------------------------------------------------------------------------
-- Пустые строки/пробелы — это просто «не указано».
UPDATE trunks SET provider = NULL
WHERE provider IS NOT NULL AND TRIM(provider) = '';

-- Точное совпадение с названием провайдера из справочника. Если в
-- справочнике несколько провайдеров с одинаковым названием, берётся
-- провайдер с наименьшим id.
UPDATE trunks t
JOIN (
    SELECT TRIM(name) AS pname, MIN(id) AS pid
    FROM providers
    GROUP BY TRIM(name)
) p ON p.pname = TRIM(t.provider)
SET t.provider_id = p.pid,
    t.provider = NULL
WHERE t.provider_id IS NULL
  AND t.provider IS NOT NULL;

-- Для контроля: транки, у которых осталось значение, введённое вручную
-- (их нужно открыть и выбрать провайдера из справочника).
SELECT id, name, provider AS provider_entered_manually
FROM trunks
WHERE provider_id IS NULL AND provider IS NOT NULL
ORDER BY name;
