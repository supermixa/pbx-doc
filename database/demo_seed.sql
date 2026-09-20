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

INSERT INTO departments (name, description, created_by, updated_by)
VALUES
  ('Отдел продаж', 'Менеджеры по продажам и работе с клиентами.', 1, 1),
  ('Техподдержка', 'Первая и вторая линия технической поддержки.', 1, 1);

INSERT INTO equipment_types (category, name, model, brand, created_by, updated_by)
VALUES
  ('ip_phone', 'Офисный IP-телефон', 'Yealink T31P', 'Yealink', 1, 1),
  ('softphone', 'Софтфон для ноутбука', 'Zoiper 5', 'Zoiper', 1, 1),
  ('dect_base', 'Базовая станция IP DECT', 'W60B', 'Yealink', 1, 1),
  ('gateway', 'FXO-шлюз на 4 порта', 'GXW4104', 'Grandstream', 1, 1);

INSERT INTO equipment (equipment_type_id, location, ip_address, description, created_by, updated_by)
VALUES
  (1, 'Офис, 2 этаж, стойка ресепшн', '192.168.10.11', 'Телефон администратора офиса.', 1, 1),
  (3, 'Офис, серверная', '192.168.10.5', 'Базовая станция для беспроводных DECT-трубок отдела продаж.', 1, 1),
  (4, 'Серверная, стойка А', '192.168.10.2', 'Шлюз для подключения городских линий.', 1, 1);

INSERT INTO legal_entities (name, inn, created_by, updated_by)
VALUES
  ('ООО "Ромашка"', '7701234567', 1, 1),
  ('ИП Иванов И.И.', '772812345678', 1, 1);

INSERT INTO providers (name, support_phone, note, created_by, updated_by)
VALUES
  ('Билайн Бизнес', '8-800-700-8000', 'Основной поставщик городских номеров.', 1, 1);

INSERT INTO provider_managers (provider_id, name, phone, sort_order)
VALUES
  (1, 'Смирнова А.А.', '+7 900 111-22-33', 0),
  (1, 'Кузнецов П.П.', '+7 900 444-55-66', 1);

INSERT INTO provider_legal_entities (provider_id, legal_entity_id, manager_id, contract_number)
VALUES
  (1, 1, 1, '№ БИЗ-2024-118');

INSERT INTO calltracking_targets (department_id, target, created_by, updated_by)
VALUES
  (1, 'Реклама Яндекс.Директ', 1, 1),
  (1, 'Реклама ВКонтакте', 1, 1);

INSERT INTO extensions (number, category, type, owner, context, scenario_id, department_id, legal_entity_id, provider_id, trunk_id, calltracking_target_id, status, description, created_by, updated_by)
VALUES
  ('100', 'internal', 'internal', 'Стойка ресепшн', 'internal', NULL, NULL, NULL, NULL, NULL, NULL, 'active', 'Основной номер администратора офиса.', 1, 1),
  ('101', 'internal', 'internal', 'Иванов И.И. (продажи)', 'internal', NULL, 1, NULL, NULL, NULL, NULL, 'active', NULL, 1, 1),
  ('78121234567', 'external', 'did_city', 'Компания — общий номер', 'from-trunk-beeline', 1, NULL, 1, 1, 1, NULL, 'active',
   'Городской номер, входящие идут на IVR.', 1, 1),
  ('78121234599', 'external', 'calltracking', NULL, 'from-trunk-beeline', NULL, NULL, 1, 1, 1, 1, 'active',
   'Отслеживающий номер для рекламной кампании в Яндекс.Директ.', 1, 1);
