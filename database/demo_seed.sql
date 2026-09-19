-- ============================================================================
-- PBX Doc — демонстрационные данные (НЕОБЯЗАТЕЛЬНО)
-- Загружайте только на тестовом стенде, чтобы посмотреть, как выглядит
-- заполненное приложение. Для продакшена достаточно schema.sql + seed.sql.
-- ============================================================================

SET NAMES utf8mb4;

INSERT INTO trunks (name, type, provider, host, context, status, description, created_by, updated_by)
VALUES
  ('Билайн SIP', 'PJSIP', 'Билайн Бизнес', 'sip.beeline.ru:5060', 'from-trunk-beeline', 'active',
   'Основной городской транк. 10 одновременных каналов.', 1, 1),
  ('МТС Резерв', 'PJSIP', 'МТС', 'sip.mts.ru:5060', 'from-trunk-mts', 'active',
   'Резервный транк на случай падения основного оператора.', 1, 1);

INSERT INTO scenarios (name, context, description, dialplan_code, created_by, updated_by)
VALUES
  ('IVR Приветствие', 'ivr-main',
   'Голосовое меню: 1 — отдел продаж, 2 — поддержка, 0 — оператор.',
   'exten => s,1,Answer()\nexten => s,n,Background(welcome)\nexten => s,n,WaitExten(5)\nexten => 1,1,Goto(sales,s,1)\nexten => 2,1,Goto(support,s,1)\nexten => 0,1,Dial(PJSIP/100,20)',
   1, 1);

INSERT INTO routes (name, direction, pattern, trunk_id, context, description, created_by, updated_by)
VALUES
  ('Исходящие городские', 'outbound', '_8ZZZZZZZZZZ', 1, 'outbound-office',
   'Все исходящие городские звонки уходят через Билайн, при недоступности — через МТС.', 1, 1),
  ('Входящие на IVR', 'inbound', '78121234567', 1, 'from-trunk-beeline',
   'Входящий DID направляется на сценарий IVR Приветствие.', 1, 1);

INSERT INTO extensions (number, type, owner, context, scenario_id, status, description, created_by, updated_by)
VALUES
  ('100', 'internal', 'Стойка ресепшн', 'internal', NULL, 'active', 'Основной номер администратора офиса.', 1, 1),
  ('101', 'internal', 'Иванов И.И. (продажи)', 'internal', NULL, 'active', NULL, 1, 1),
  ('78121234567', 'did', 'Компания — общий номер', 'from-trunk-beeline', 1, 'active',
   'Городской номер, входящие идут на IVR.', 1, 1);
