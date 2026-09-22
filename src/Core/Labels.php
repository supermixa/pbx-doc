<?php

namespace App\Core;

/**
 * Человекочитаемые подписи для ролей, сущностей, действий и полей —
 * используются в интерфейсе и в журнале аудита.
 */
class Labels
{
    public static function role(?string $role): string
    {
        return match ($role) {
            'admin' => 'Администратор',
            'user' => 'Пользователь',
            'observer' => 'Наблюдатель',
            default => (string)$role,
        };
    }

    public static function entity(string $type): string
    {
        return match ($type) {
            'trunk' => 'Транк',
            'route' => 'Маршрут',
            'scenario' => 'Сценарий',
            'extension' => 'Номер',
            'user' => 'Пользователь',
            'department' => 'Подразделение',
            'equipment' => 'Оборудование',
            'equipment_type' => 'Тип оборудования',
            'legal_entity' => 'Юрлицо',
            'provider' => 'Провайдер',
            'calltracking_target' => 'Цель Calltracking',
            'music_file' => 'Файл музыки',
            default => $type,
        };
    }

    public static function entityUrl(string $type, int $id): string
    {
        return match ($type) {
            'trunk' => '/trunks/' . $id,
            'route' => '/routes/' . $id,
            'scenario' => '/scenarios/' . $id,
            'extension' => '/extensions/' . $id,
            'user' => '/users/' . $id . '/edit',
            'department' => '/departments/' . $id,
            'equipment' => '/equipment/' . $id,
            'equipment_type' => '/equipment-types/' . $id,
            'legal_entity' => '/legal-entities/' . $id,
            'provider' => '/providers/' . $id,
            'calltracking_target' => '/calltracking/' . $id,
            'music_file' => '/music/' . $id,
            default => '#',
        };
    }

    public static function action(string $action): string
    {
        return match ($action) {
            'create' => 'Создание',
            'update' => 'Изменение',
            'delete' => 'Удаление',
            default => $action,
        };
    }

    public static function actionBadgeClass(string $action): string
    {
        return match ($action) {
            'create' => 'text-bg-success',
            'update' => 'text-bg-primary',
            'delete' => 'text-bg-danger',
            default => 'text-bg-secondary',
        };
    }

    private static array $fields = [
        'name' => 'Название',
        'type' => 'Тип',
        'provider' => 'Провайдер',
        'host' => 'Хост',
        'context' => 'Контекст',
        'status' => 'Статус',
        'description' => 'Описание',
        'direction' => 'Направление',
        'pattern' => 'Шаблон номера',
        'trunk_id' => 'Транк',
        'dialplan_code' => 'Код диалплана',
        'number' => 'Номер',
        'scenario_id' => 'Сценарий',
        'email' => 'Email',
        'role' => 'Роль',
        'is_active' => 'Активен',
        'password_hash' => 'Пароль',
        'category' => 'Категория',
        'department_id' => 'Подразделение',
        'equipment_type_id' => 'Тип оборудования',
        'location' => 'Расположение',
        'ip_address' => 'IP-адрес',
        'model' => 'Модель',
        'brand' => 'Бренд',
        'inn' => 'ИНН',
        'contract_number' => '№ договора',
        'support_phone' => 'Телефон ТП',
        'note' => 'Примечание',
        'legal_entity_id' => 'Юрлицо',
        'provider_id' => 'Провайдер',
        'managers' => 'Менеджеры',
        'legal_entities' => 'Обслуживаемые юрлица',
        'calltracking_target_id' => 'Цель Calltracking',
        'target' => 'Цель',
        'title' => 'Название',
        'original_filename' => 'Имя файла',
        'mime_type' => 'Формат',
        'size_bytes' => 'Размер',
        'members' => 'Состав',
        'queue_settings' => 'Настройки очереди/группы',
        'ring_strategy' => 'Метод обзвона',
        'agent_answer_timeout_sec' => 'Время дозвона оператору, сек',
        'agent_wrapup_time_sec' => 'Время отдыха оператора, сек',
        'agent_ring_in_use' => 'Получать звонки во время разговора',
        'caller_wait_sound' => 'Что слышит абонент при ожидании',
        'moh_music_id' => 'Фоновая музыка (MOH)',
        'announce_position' => 'Оповещать о позиции в очереди',
        'announce_hold_time' => 'Оповещать о расчётном времени ожидания',
        'announcement_music_id' => 'Рекламная фраза',
        'announcement_repeat_sec' => 'Повтор оповещений, сек',
        'failover1_timeout_sec' => 'Сценарий №1: таймаут ожидания, сек',
        'failover1_target' => 'Сценарий №1: перевести на',
        'failover2_target' => 'Сценарий №2: перевести на',
    ];

    public static function field(string $field): string
    {
        return self::$fields[$field] ?? $field;
    }

    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            'active' => 'Активен',
            'inactive' => 'Отключён',
            default => (string)$status,
        };
    }

    public static function directionLabel(?string $direction): string
    {
        return match ($direction) {
            'inbound' => 'Входящий',
            'outbound' => 'Исходящий',
            default => (string)$direction,
        };
    }

    /**
     * Подпись типа номера. $category нужна только чтобы различить значение
     * 'other', которое означает разное для внутренних (Служебный номер) и
     * внешних (Другое) номеров — остальные значения категорию не путают.
     */
    public static function extensionTypeLabel(?string $type, ?string $category = null): string
    {
        return match ($type) {
            'internal' => 'Внутренний',
            'queue' => 'Очередь (Queue)',
            'ivr' => 'Группа обзвона (Ring Group)',
            'did_city' => 'Городской (DID)',
            'did_mobile' => 'Сотовый (DID)',
            'calltracking' => 'CallTracking',
            'other' => $category === 'internal' ? 'Служебный номер' : 'Другое',
            'did' => 'Городской (DID)', // устаревшее значение до миграции 0002
            default => (string)$type,
        };
    }

    /** Подпись метода обзвона участников очереди (ring_strategy) — полное описание из ТЗ */
    public static function queueRingStrategyLabel(?string $strategy): string
    {
        return match ($strategy) {
            'ringall' => 'Вызов поступает одновременно на всех участников очереди, включая тех, кто занят (по умолчанию).',
            'leastrecent' => 'Вызов поступает на участника очереди, который дольше всех не принимал звонки.',
            'fewestcalls' => 'Вызов поступает на участника очереди, который обработал меньше всего звонков.',
            'random' => 'Вызов поступает на любого свободного участника очереди.',
            'rrmemory' => 'Вызов поступает на любого свободного участника очереди кроме участника, обработавшего последний вызов.',
            'linear' => 'Вызов поступает на каждого участника очереди по порядку.',
            default => (string)$strategy,
        };
    }

    /** Подпись метода обзвона участников группы обзвона (ring group) */
    public static function ringGroupRingStrategyLabel(?string $strategy): string
    {
        return match ($strategy) {
            'ringall' => 'Вызов поступает одновременно на всех участников группы, включая тех, кто занят (по умолчанию).',
            'linear' => 'Вызов поступает на каждого участника группы по порядку.',
            default => (string)$strategy,
        };
    }

    /** Короткая подпись ring_strategy — для карточки номера и списков */
    public static function ringStrategyShortLabel(?string $strategy): string
    {
        return match ($strategy) {
            'ringall' => 'Все одновременно',
            'leastrecent' => 'Дольше всех не принимал',
            'fewestcalls' => 'Меньше всего звонков',
            'random' => 'Любой свободный',
            'rrmemory' => 'Любой свободный, кроме принявшего последний звонок',
            'linear' => 'По порядку',
            default => (string)$strategy,
        };
    }

    public static function callerWaitSoundLabel(?string $value): string
    {
        return match ($value) {
            'music' => 'Музыка',
            'ringing' => 'Сигнал вызова',
            default => (string)$value,
        };
    }

    public static function failoverTargetTypeLabel(?string $value): string
    {
        return match ($value) {
            'scenario' => 'Сценарий',
            'extension' => 'Номер',
            default => (string)$value,
        };
    }

    /** Человекочитаемый размер файла (Б/КБ/МБ) */
    public static function formatBytes(?int $bytes): string
    {
        $bytes = (int)$bytes;
        if ($bytes < 1024) {
            return $bytes . ' Б';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' КБ';
        }
        return round($bytes / (1024 * 1024), 2) . ' МБ';
    }

    public static function extensionCategoryLabel(?string $category): string
    {
        return match ($category) {
            'internal' => 'Внутренний',
            'external' => 'Внешний',
            default => (string)$category,
        };
    }

    public static function equipmentCategoryLabel(?string $category): string
    {
        return match ($category) {
            'ip_phone' => 'IP-телефон',
            'softphone' => 'SoftPhone',
            'dect_base' => 'IP Dect БС',
            'gateway' => 'Шлюз',
            default => (string)$category,
        };
    }

    /**
     * Читаемое представление значения поля в истории изменений.
     * $category — категория номера (internal/external), если известна на
     * момент отображения — нужна только чтобы верно подписать type='other'
     * у номеров (см. extensionTypeLabel). Если не передана, используется
     * подпись по умолчанию (как для внешнего номера).
     */
    public static function formatFieldValue(string $entityType, string $field, $value, ?string $category = null): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        if ($field === 'password_hash') {
            return '(изменён)';
        }
        if (in_array($field, ['is_active', 'agent_ring_in_use', 'announce_position', 'announce_hold_time'], true)) {
            return ((string)$value === '1') ? 'Да' : 'Нет';
        }
        if ($field === 'status') {
            return self::statusLabel((string)$value);
        }
        if ($field === 'direction') {
            return self::directionLabel((string)$value);
        }
        if ($field === 'role') {
            return self::role((string)$value);
        }
        if ($field === 'type' && $entityType === 'extension') {
            return self::extensionTypeLabel((string)$value, $category);
        }
        if ($field === 'category' && $entityType === 'extension') {
            return self::extensionCategoryLabel((string)$value);
        }
        if ($field === 'category' && $entityType === 'equipment_type') {
            return self::equipmentCategoryLabel((string)$value);
        }
        if ($field === 'ring_strategy') {
            return self::ringStrategyShortLabel((string)$value);
        }
        if ($field === 'caller_wait_sound') {
            return self::callerWaitSoundLabel((string)$value);
        }
        return (string)$value;
    }
}
