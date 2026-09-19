<?php

namespace App\Models;

use App\Core\Db;

class Trunk
{
    public const STATUSES = ['active', 'inactive'];
    public const TYPES = ['PJSIP', 'SIP', 'IAX2', 'DAHDI', 'Other'];

    private static array $fillable = ['name', 'type', 'provider', 'host', 'context', 'status', 'description'];

    public static function all(string $search = ''): array
    {
        $sql = 'SELECT t.*, cu.name AS created_by_name, uu.name AS updated_by_name
                FROM trunks t
                LEFT JOIN users cu ON cu.id = t.created_by
                LEFT JOIN users uu ON uu.id = t.updated_by';
        $params = [];
        if ($search !== '') {
            $sql .= ' WHERE t.name LIKE :search OR t.provider LIKE :search OR t.host LIKE :search';
            $params['search'] = "%{$search}%";
        }
        $sql .= ' ORDER BY t.name';
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $sql = 'SELECT t.*, cu.name AS created_by_name, uu.name AS updated_by_name
                FROM trunks t
                LEFT JOIN users cu ON cu.id = t.created_by
                LEFT JOIN users uu ON uu.id = t.updated_by
                WHERE t.id = :id';
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Сырые данные без джойнов — для сравнения при аудите изменений */
    public static function findRaw(int $id): ?array
    {
        $stmt = Db::connection()->prepare('SELECT * FROM trunks WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $fields = self::$fillable;
        $columns = implode(', ', $fields);
        $placeholders = ':' . implode(', :', $fields);
        $sql = "INSERT INTO trunks ({$columns}, created_by, updated_by) VALUES ({$placeholders}, :created_by, :updated_by)";

        $params = [];
        foreach ($fields as $f) {
            $params[$f] = $data[$f] ?? null;
        }
        $params['created_by'] = $data['created_by'] ?? null;
        $params['updated_by'] = $data['created_by'] ?? null;

        $stmt = Db::connection()->prepare($sql);
        $stmt->execute($params);
        return (int)Db::connection()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $fields = self::$fillable;
        $set = implode(', ', array_map(fn($f) => "{$f} = :{$f}", $fields));
        $sql = "UPDATE trunks SET {$set}, updated_by = :updated_by WHERE id = :id";

        $params = ['id' => $id, 'updated_by' => $data['updated_by'] ?? null];
        foreach ($fields as $f) {
            $params[$f] = $data[$f] ?? null;
        }

        Db::connection()->prepare($sql)->execute($params);
    }

    public static function delete(int $id): void
    {
        Db::connection()->prepare('DELETE FROM trunks WHERE id = :id')->execute(['id' => $id]);
    }

    public static function count(): int
    {
        return (int)Db::connection()->query('SELECT COUNT(*) FROM trunks')->fetchColumn();
    }

    public static function options(): array
    {
        $stmt = Db::connection()->query('SELECT id, name FROM trunks ORDER BY name');
        return $stmt->fetchAll();
    }
}
