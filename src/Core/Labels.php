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
        'owner' => 'Владелец',
        'scenario_id' => 'Сценарий',
        'email' => 'Email',
        'role' => 'Роль',
        'is_active' => 'Активен',
        'password_hash' => 'Пароль',
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

    public static function extensionTypeLabel(?string $type): string
    {
        return match ($type) {
            'internal' => 'Внутренний',
            'did' => 'Городской (DID)',
            'queue' => 'Очередь',
            'ivr' => 'IVR',
            'other' => 'Другое',
            default => (string)$type,
        };
    }

    /** Читаемое представление значения поля в истории изменений */
    public static function formatFieldValue(string $entityType, string $field, $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        if ($field === 'password_hash') {
            return '(изменён)';
        }
        if ($field === 'is_active') {
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
            return self::extensionTypeLabel((string)$value);
        }
        return (string)$value;
    }
}
