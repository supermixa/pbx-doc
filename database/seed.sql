-- ============================================================================
-- PBX Doc — начальные данные
-- Учётная запись администратора по умолчанию:
--   email:    admin@example.com
--   password: admin123
-- ОБЯЗАТЕЛЬНО смените пароль сразу после первого входа!
-- ============================================================================

SET NAMES utf8mb4;

INSERT INTO users (name, email, password_hash, role, is_active)
VALUES ('Администратор', 'admin@example.com', '$2y$12$vV7IDcwzYWbf.5Xtp7QEdu3AA05vMDjWHxU1Ub5TQA62Jg9BSmK9q', 'admin', 1)
ON DUPLICATE KEY UPDATE name = name;
