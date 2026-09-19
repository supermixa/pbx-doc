<?php

namespace App\Core;

use App\Models\AuditLog;

/**
 * Журналирование действий пользователей над сущностями (транки, маршруты,
 * сценарии, номера). При просмотре карточки сущности видно, кто и что менял.
 */
class AuditLogger
{
    /**
     * Записать создание сущности.
     */
    public static function logCreate(string $entityType, int $entityId, array $data): void
    {
        $changes = [];
        foreach ($data as $field => $value) {
            if ($field === 'id') {
                continue;
            }
            $changes[$field] = ['old' => null, 'new' => $value];
        }
        self::write($entityType, $entityId, 'create', $changes);
    }

    /**
     * Записать изменение сущности — сравнивает старые и новые значения полей
     * и сохраняет только реально изменившиеся.
     */
    public static function logUpdate(string $entityType, int $entityId, array $old, array $new): void
    {
        $changes = [];
        foreach ($new as $field => $newValue) {
            if (!array_key_exists($field, $old)) {
                continue;
            }
            if (in_array($field, ['id', 'created_at', 'updated_at', 'created_by', 'updated_by'], true)) {
                continue;
            }
            $oldValue = $old[$field];
            // Сравниваем как строки, чтобы не путать "1" и 1
            if ((string)$oldValue !== (string)$newValue) {
                $changes[$field] = ['old' => $oldValue, 'new' => $newValue];
            }
        }

        if (empty($changes)) {
            return;
        }

        self::write($entityType, $entityId, 'update', $changes);
    }

    public static function logDelete(string $entityType, int $entityId, array $data): void
    {
        $changes = [];
        foreach ($data as $field => $value) {
            if ($field === 'id') {
                continue;
            }
            $changes[$field] = ['old' => $value, 'new' => null];
        }
        self::write($entityType, $entityId, 'delete', $changes);
    }

    private static function write(string $entityType, int $entityId, string $action, array $changes): void
    {
        AuditLog::create([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'user_id' => Auth::id(),
            'changes' => json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }
}
