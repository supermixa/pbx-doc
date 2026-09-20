<?php

namespace App\Models;

use App\Core\Db;

/**
 * Модель "Номер/экстеншен". Названа ExtensionNumber, чтобы не конфликтовать
 * с зарезервированным для PHP словом extension.
 */
class ExtensionNumber
{
    public const TYPES = ['internal', 'did', 'queue', 'ivr', 'other'];
    public const STATUSES = ['active', 'inactive'];
    public const CATEGORIES = ['internal', 'external'];

    private static array $fillable = ['number', 'category', 'type', 'owner', 'context', 'scenario_id', 'department_id', 'status', 'description'];

    public static function all(string $search = '', string $category = ''): array
    {
        $sql = 'SELECT e.*, s.name AS scenario_name, d.name AS department_name,
                       cu.name AS created_by_name, uu.name AS updated_by_name
                FROM extensions e
                LEFT JOIN scenarios s ON s.id = e.scenario_id
                LEFT JOIN departments d ON d.id = e.department_id
                LEFT JOIN users cu ON cu.id = e.created_by
                LEFT JOIN users uu ON uu.id = e.updated_by';
        $where = [];
        $params = [];
        if ($search !== '') {
            $where[] = '(e.number LIKE :search OR e.owner LIKE :search)';
            $params['search'] = "%{$search}%";
        }
        if (in_array($category, self::CATEGORIES, true)) {
            $where[] = 'e.category = :category';
            $params['category'] = $category;
        }
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY e.number';
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $sql = 'SELECT e.*, s.name AS scenario_name, d.name AS department_name,
                       cu.name AS created_by_name, uu.name AS updated_by_name
                FROM extensions e
                LEFT JOIN scenarios s ON s.id = e.scenario_id
                LEFT JOIN departments d ON d.id = e.department_id
                LEFT JOIN users cu ON cu.id = e.created_by
                LEFT JOIN users uu ON uu.id = e.updated_by
                WHERE e.id = :id';
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function countByCategory(string $category): int
    {
        $stmt = Db::connection()->prepare('SELECT COUNT(*) FROM extensions WHERE category = :category');
        $stmt->execute(['category' => $category]);
        return (int)$stmt->fetchColumn();
    }

    public static function findRaw(int $id): ?array
    {
        $stmt = Db::connection()->prepare('SELECT * FROM extensions WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $fields = self::$fillable;
        $columns = implode(', ', $fields);
        $placeholders = ':' . implode(', :', $fields);
        $sql = "INSERT INTO extensions ({$columns}, created_by, updated_by) VALUES ({$placeholders}, :created_by, :updated_by)";

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
        $sql = "UPDATE extensions SET {$set}, updated_by = :updated_by WHERE id = :id";

        $params = ['id' => $id, 'updated_by' => $data['updated_by'] ?? null];
        foreach ($fields as $f) {
            $params[$f] = $data[$f] ?? null;
        }

        Db::connection()->prepare($sql)->execute($params);
    }

    public static function delete(int $id): void
    {
        Db::connection()->prepare('DELETE FROM extensions WHERE id = :id')->execute(['id' => $id]);
    }

    public static function count(): int
    {
        return (int)Db::connection()->query('SELECT COUNT(*) FROM extensions')->fetchColumn();
    }
}
