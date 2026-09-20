<?php

namespace App\Models;

use App\Core\Db;

/**
 * Вложенный справочник "Типы оборудования" — каталог конкретных
 * моделей/брендов, сгруппированных по категории устройства.
 */
class EquipmentType
{
    public const CATEGORIES = ['ip_phone', 'softphone', 'dect_base', 'gateway'];

    private static array $fillable = ['category', 'name', 'model', 'brand'];

    public static function all(string $search = ''): array
    {
        $sql = 'SELECT et.*, cu.name AS created_by_name, uu.name AS updated_by_name
                FROM equipment_types et
                LEFT JOIN users cu ON cu.id = et.created_by
                LEFT JOIN users uu ON uu.id = et.updated_by';
        $params = [];
        if ($search !== '') {
            $sql .= ' WHERE et.name LIKE :search OR et.model LIKE :search OR et.brand LIKE :search';
            $params['search'] = "%{$search}%";
        }
        $sql .= ' ORDER BY et.name';
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $sql = 'SELECT et.*, cu.name AS created_by_name, uu.name AS updated_by_name
                FROM equipment_types et
                LEFT JOIN users cu ON cu.id = et.created_by
                LEFT JOIN users uu ON uu.id = et.updated_by
                WHERE et.id = :id';
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findRaw(int $id): ?array
    {
        $stmt = Db::connection()->prepare('SELECT * FROM equipment_types WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $fields = self::$fillable;
        $columns = implode(', ', $fields);
        $placeholders = ':' . implode(', :', $fields);
        $sql = "INSERT INTO equipment_types ({$columns}, created_by, updated_by) VALUES ({$placeholders}, :created_by, :updated_by)";

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
        $sql = "UPDATE equipment_types SET {$set}, updated_by = :updated_by WHERE id = :id";

        $params = ['id' => $id, 'updated_by' => $data['updated_by'] ?? null];
        foreach ($fields as $f) {
            $params[$f] = $data[$f] ?? null;
        }

        Db::connection()->prepare($sql)->execute($params);
    }

    public static function delete(int $id): void
    {
        Db::connection()->prepare('DELETE FROM equipment_types WHERE id = :id')->execute(['id' => $id]);
    }

    public static function count(): int
    {
        return (int)Db::connection()->query('SELECT COUNT(*) FROM equipment_types')->fetchColumn();
    }

    public static function options(): array
    {
        $stmt = Db::connection()->query('SELECT id, category, name, model, brand FROM equipment_types ORDER BY name');
        return $stmt->fetchAll();
    }
}
