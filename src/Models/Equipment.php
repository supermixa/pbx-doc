<?php

namespace App\Models;

use App\Core\Db;

class Equipment
{
    private static array $fillable = ['equipment_type_id', 'location', 'ip_address', 'description'];

    public static function all(string $search = ''): array
    {
        $sql = 'SELECT e.*,
                       et.name AS equipment_type_name, et.model AS equipment_type_model,
                       et.brand AS equipment_type_brand, et.category AS equipment_type_category,
                       cu.name AS created_by_name, uu.name AS updated_by_name
                FROM equipment e
                LEFT JOIN equipment_types et ON et.id = e.equipment_type_id
                LEFT JOIN users cu ON cu.id = e.created_by
                LEFT JOIN users uu ON uu.id = e.updated_by';
        $params = [];
        if ($search !== '') {
            $sql .= ' WHERE e.location LIKE :search1 OR e.ip_address LIKE :search2
                       OR et.name LIKE :search3 OR et.model LIKE :search4 OR et.brand LIKE :search5';
            $params['search1'] = "%{$search}%";
            $params['search2'] = "%{$search}%";
            $params['search3'] = "%{$search}%";
            $params['search4'] = "%{$search}%";
            $params['search5'] = "%{$search}%";
        }
        $sql .= ' ORDER BY e.location, e.id';
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $sql = 'SELECT e.*,
                       et.name AS equipment_type_name, et.model AS equipment_type_model,
                       et.brand AS equipment_type_brand, et.category AS equipment_type_category,
                       cu.name AS created_by_name, uu.name AS updated_by_name
                FROM equipment e
                LEFT JOIN equipment_types et ON et.id = e.equipment_type_id
                LEFT JOIN users cu ON cu.id = e.created_by
                LEFT JOIN users uu ON uu.id = e.updated_by
                WHERE e.id = :id';
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findRaw(int $id): ?array
    {
        $stmt = Db::connection()->prepare('SELECT * FROM equipment WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $fields = self::$fillable;
        $columns = implode(', ', $fields);
        $placeholders = ':' . implode(', :', $fields);
        $sql = "INSERT INTO equipment ({$columns}, created_by, updated_by) VALUES ({$placeholders}, :created_by, :updated_by)";

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
        $sql = "UPDATE equipment SET {$set}, updated_by = :updated_by WHERE id = :id";

        $params = ['id' => $id, 'updated_by' => $data['updated_by'] ?? null];
        foreach ($fields as $f) {
            $params[$f] = $data[$f] ?? null;
        }

        Db::connection()->prepare($sql)->execute($params);
    }

    public static function delete(int $id): void
    {
        Db::connection()->prepare('DELETE FROM equipment WHERE id = :id')->execute(['id' => $id]);
    }

    public static function count(): int
    {
        return (int)Db::connection()->query('SELECT COUNT(*) FROM equipment')->fetchColumn();
    }
}
