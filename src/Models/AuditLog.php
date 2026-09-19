<?php

namespace App\Models;

use App\Core\Db;

class AuditLog
{
    public static function create(array $data): int
    {
        $sql = 'INSERT INTO audit_logs (entity_type, entity_id, action, user_id, changes)
                VALUES (:entity_type, :entity_id, :action, :user_id, :changes)';
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute([
            'entity_type' => $data['entity_type'],
            'entity_id' => $data['entity_id'],
            'action' => $data['action'],
            'user_id' => $data['user_id'],
            'changes' => $data['changes'],
        ]);
        return (int)Db::connection()->lastInsertId();
    }

    /** История изменений конкретной сущности (новые сверху) */
    public static function forEntity(string $entityType, int $entityId): array
    {
        $sql = 'SELECT a.*, u.name AS user_name, u.email AS user_email
                FROM audit_logs a
                LEFT JOIN users u ON u.id = a.user_id
                WHERE a.entity_type = :entity_type AND a.entity_id = :entity_id
                ORDER BY a.created_at DESC, a.id DESC';
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute(['entity_type' => $entityType, 'entity_id' => $entityId]);
        return $stmt->fetchAll();
    }

    /** Общая лента последних изменений по всем сущностям */
    public static function recent(int $limit = 30): array
    {
        $limit = max(1, min(200, $limit));
        $sql = "SELECT a.*, u.name AS user_name, u.email AS user_email
                FROM audit_logs a
                LEFT JOIN users u ON u.id = a.user_id
                ORDER BY a.created_at DESC, a.id DESC
                LIMIT {$limit}";
        $stmt = Db::connection()->query($sql);
        return $stmt->fetchAll();
    }
}
