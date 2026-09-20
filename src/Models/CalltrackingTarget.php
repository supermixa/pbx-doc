<?php

namespace App\Models;

use App\Core\Db;

/**
 * Справочник "Calltracking": цель звонка, привязанная к подразделению.
 * Используется во внешних номерах с типом CallTracking — там выбирается
 * подразделение (см. ExtensionNumber::department_id) и предлагается список
 * целей, относящихся именно к этому подразделению.
 */
class CalltrackingTarget
{
    private static array $fillable = ['department_id', 'target'];

    public static function all(string $search = ''): array
    {
        $sql = 'SELECT ct.*, d.name AS department_name, cu.name AS created_by_name, uu.name AS updated_by_name
                FROM calltracking_targets ct
                LEFT JOIN departments d ON d.id = ct.department_id
                LEFT JOIN users cu ON cu.id = ct.created_by
                LEFT JOIN users uu ON uu.id = ct.updated_by';
        $params = [];
        if ($search !== '') {
            $sql .= ' WHERE ct.target LIKE :search1 OR d.name LIKE :search2';
            $params['search1'] = "%{$search}%";
            $params['search2'] = "%{$search}%";
        }
        $sql .= ' ORDER BY d.name, ct.target';
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $sql = 'SELECT ct.*, d.name AS department_name, cu.name AS created_by_name, uu.name AS updated_by_name
                FROM calltracking_targets ct
                LEFT JOIN departments d ON d.id = ct.department_id
                LEFT JOIN users cu ON cu.id = ct.created_by
                LEFT JOIN users uu ON uu.id = ct.updated_by
                WHERE ct.id = :id';
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findRaw(int $id): ?array
    {
        $stmt = Db::connection()->prepare('SELECT * FROM calltracking_targets WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $fields = self::$fillable;
        $columns = implode(', ', $fields);
        $placeholders = ':' . implode(', :', $fields);
        $sql = "INSERT INTO calltracking_targets ({$columns}, created_by, updated_by) VALUES ({$placeholders}, :created_by, :updated_by)";

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
        $sql = "UPDATE calltracking_targets SET {$set}, updated_by = :updated_by WHERE id = :id";

        $params = ['id' => $id, 'updated_by' => $data['updated_by'] ?? null];
        foreach ($fields as $f) {
            $params[$f] = $data[$f] ?? null;
        }

        Db::connection()->prepare($sql)->execute($params);
    }

    public static function delete(int $id): void
    {
        Db::connection()->prepare('DELETE FROM calltracking_targets WHERE id = :id')->execute(['id' => $id]);
    }

    public static function count(): int
    {
        return (int)Db::connection()->query('SELECT COUNT(*) FROM calltracking_targets')->fetchColumn();
    }

    /**
     * Список целей с ID подразделения (для клиентской фильтрации в форме
     * внешнего номера: "выбрали подразделение -> показали его цели").
     */
    public static function optionsWithDepartments(): array
    {
        $sql = 'SELECT ct.id, ct.target, ct.department_id
                FROM calltracking_targets ct
                ORDER BY ct.target';
        $stmt = Db::connection()->query($sql);
        return $stmt->fetchAll();
    }
}
