<?php

namespace App\Models;

use App\Core\Db;

/**
 * Модель "Номер/экстеншен". Названа ExtensionNumber, чтобы не конфликтовать
 * с зарезервированным для PHP словом extension.
 */
class ExtensionNumber
{
    public const STATUSES = ['active', 'inactive'];
    public const CATEGORIES = ['internal', 'external'];

    /** Типы для внутренних номеров (category = internal) */
    public const INTERNAL_TYPES = ['internal', 'queue', 'ivr', 'other'];
    /** Типы для внешних номеров (category = external); did_city — по умолчанию */
    public const EXTERNAL_TYPES = ['did_city', 'did_mobile', 'calltracking', 'other'];

    private static array $fillable = [
        'number', 'category', 'type', 'owner', 'context', 'scenario_id', 'department_id',
        'legal_entity_id', 'provider_id', 'trunk_id', 'calltracking_target_id', 'status', 'description',
    ];

    private const JOINS = '
                FROM extensions e
                LEFT JOIN scenarios s ON s.id = e.scenario_id
                LEFT JOIN departments d ON d.id = e.department_id
                LEFT JOIN legal_entities le ON le.id = e.legal_entity_id
                LEFT JOIN providers pr ON pr.id = e.provider_id
                LEFT JOIN trunks tr ON tr.id = e.trunk_id
                LEFT JOIN calltracking_targets ct ON ct.id = e.calltracking_target_id
                LEFT JOIN users cu ON cu.id = e.created_by
                LEFT JOIN users uu ON uu.id = e.updated_by';

    private const SELECT_COLUMNS = 'e.*, s.name AS scenario_name, d.name AS department_name,
                       le.name AS legal_entity_name, pr.name AS provider_name, tr.name AS trunk_name,
                       ct.target AS calltracking_target_name,
                       cu.name AS created_by_name, uu.name AS updated_by_name';

    public static function all(string $search = '', string $category = ''): array
    {
        $sql = 'SELECT ' . self::SELECT_COLUMNS . self::JOINS;
        $where = [];
        $params = [];
        if ($search !== '') {
            $where[] = '(e.number LIKE :search1 OR e.owner LIKE :search2)';
            $params['search1'] = "%{$search}%";
            $params['search2'] = "%{$search}%";
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
        $sql = 'SELECT ' . self::SELECT_COLUMNS . self::JOINS . ' WHERE e.id = :id';
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Номера, привязанные к данному транку (для карточки транка) */
    public static function forTrunk(int $trunkId): array
    {
        $sql = 'SELECT ' . self::SELECT_COLUMNS . self::JOINS . ' WHERE e.trunk_id = :trunk_id ORDER BY e.number';
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute(['trunk_id' => $trunkId]);
        return $stmt->fetchAll();
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
