-- ============================================================================
-- Миграция 0008: справочник «Цели» для Calltracking
--
-- Применять на УЖЕ РАЗВЁРНУТОЙ базе (там, где schema.sql уже был накатан
-- раньше и есть данные). Для новой установки миграция не нужна — всё уже
-- входит в schema.sql.
--
-- Запуск:
--   mysql --default-character-set=utf8mb4 -u <пользователь> -p <база> \
--       < database/migrations/0008_calltracking_goals.sql
--
-- Миграция идемпотентна: повторный запуск не приведёт к ошибке благодаря
-- проверкам через information_schema.
--
-- Что делает:
--   1) создаёт подчинённый справочник calltracking_goals («Цели»);
--   2) переносит в него все цели, которые раньше вписывались вручную в
--      calltracking_targets.target — одинаковые тексты (без учёта регистра
--      и пробелов по краям) становятся одной целью;
--   3) calltracking_targets превращается в связь «подразделение — цель»
--      (новая колонка goal_id). Номера CallTracking продолжают ссылаться на
--      те же строки calltracking_targets, поэтому у них ничего не меняется;
--   4) если у подразделения одна и та же цель была записана несколько раз,
--      дубли сливаются в одну строку (номера перепривязываются к ней).
--
-- Старая колонка calltracking_targets.target НЕ удаляется (данные
-- сохраняются), но приложение её больше не использует.
-- ============================================================================

SET NAMES utf8mb4;

-- ----------------------------------------------------------------------------
-- 1. Справочник целей
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS calltracking_goals (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(190)        NOT NULL,
    created_by      INT UNSIGNED        NULL,
    updated_by      INT UNSIGNED        NULL,
    created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_calltracking_goals_name (name),
    CONSTRAINT fk_calltracking_goals_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_calltracking_goals_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2. calltracking_targets.goal_id (пока NULL — заполним ниже)
-- ----------------------------------------------------------------------------
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'calltracking_targets' AND COLUMN_NAME = 'goal_id'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE calltracking_targets ADD COLUMN goal_id INT UNSIGNED NULL AFTER department_id',
    'SELECT ''column goal_id already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Старый текст цели больше не обязателен
ALTER TABLE calltracking_targets MODIFY target VARCHAR(190) NULL;

-- ----------------------------------------------------------------------------
-- 3. Перенос целей, введённых вручную, в справочник
-- ----------------------------------------------------------------------------
-- Одинаковые тексты (без учёта регистра — так работает сравнение в
-- utf8mb4_unicode_ci — и пробелов по краям) дают одну цель.
INSERT IGNORE INTO calltracking_goals (name, created_by, updated_by)
SELECT TRIM(target), MIN(created_by), MIN(created_by)
FROM calltracking_targets
WHERE goal_id IS NULL AND target IS NOT NULL AND TRIM(target) <> ''
GROUP BY TRIM(target);

UPDATE calltracking_targets ct
JOIN calltracking_goals g ON g.name = TRIM(ct.target)
SET ct.goal_id = g.id
WHERE ct.goal_id IS NULL;

-- Строки без текста цели (на всякий случай) — в цель «Без названия»
INSERT IGNORE INTO calltracking_goals (name)
SELECT 'Без названия' FROM DUAL
WHERE EXISTS (SELECT 1 FROM calltracking_targets WHERE goal_id IS NULL);

UPDATE calltracking_targets
SET goal_id = (SELECT id FROM calltracking_goals WHERE name = 'Без названия')
WHERE goal_id IS NULL;

-- ----------------------------------------------------------------------------
-- 4. Дубли «подразделение + цель» сливаются в одну строку
-- ----------------------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS ct_keep;
CREATE TEMPORARY TABLE ct_keep AS
SELECT department_id, goal_id, MIN(id) AS keep_id
FROM calltracking_targets
GROUP BY department_id, goal_id
HAVING COUNT(*) > 1;

UPDATE extensions e
JOIN calltracking_targets ct ON ct.id = e.calltracking_target_id
JOIN ct_keep k ON k.department_id = ct.department_id AND k.goal_id = ct.goal_id
SET e.calltracking_target_id = k.keep_id
WHERE ct.id <> k.keep_id;

DELETE ct FROM calltracking_targets ct
JOIN ct_keep k ON k.department_id = ct.department_id AND k.goal_id = ct.goal_id
WHERE ct.id <> k.keep_id;

DROP TEMPORARY TABLE ct_keep;

-- ----------------------------------------------------------------------------
-- 5. Ограничения: goal_id обязателен, ссылается на справочник, пара
--    «подразделение + цель» уникальна
-- ----------------------------------------------------------------------------
ALTER TABLE calltracking_targets MODIFY goal_id INT UNSIGNED NOT NULL;

SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'calltracking_targets' AND CONSTRAINT_NAME = 'fk_calltracking_targets_goal'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE calltracking_targets ADD CONSTRAINT fk_calltracking_targets_goal FOREIGN KEY (goal_id) REFERENCES calltracking_goals(id)',
    'SELECT ''fk fk_calltracking_targets_goal already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'calltracking_targets' AND INDEX_NAME = 'uq_calltracking_targets_department_goal'
);
SET @sql := IF(@idx_exists = 0,
    'ALTER TABLE calltracking_targets ADD UNIQUE KEY uq_calltracking_targets_department_goal (department_id, goal_id)',
    'SELECT ''index uq_calltracking_targets_department_goal already exists'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Для контроля: получившийся справочник целей и где они используются
SELECT g.id, g.name, GROUP_CONCAT(d.name ORDER BY d.name SEPARATOR ', ') AS departments
FROM calltracking_goals g
LEFT JOIN calltracking_targets ct ON ct.goal_id = g.id
LEFT JOIN departments d ON d.id = ct.department_id
GROUP BY g.id, g.name
ORDER BY g.name;
