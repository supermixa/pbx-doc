<?php

namespace App\Models;

use App\Core\Db;

/**
 * Подчинённый справочник Calltracking — «Цели». Каждая цель хранится один
 * раз; к подразделениям цели привязываются через calltracking_targets
 * (см. CalltrackingTarget).
 */
class CalltrackingGoal
{
    public static function all(string $search = ''): array
    {
        $sql = 'SELECT g.*, uu.name AS updated_by_name,
                       GROUP_CONCAT(DISTINCT CONCAT(d.id, \':\', d.name) ORDER BY d.name SEPARATOR \'\n\') AS departments_raw,
                       COUNT(DISTINCT e.id) AS extensions_count
                FROM calltracking_goals g
                LEFT JOIN calltracking_targets ct ON ct.goal_id = g.id
                LEFT JOIN departments d ON d.id = ct.department_id
                LEFT JOIN extensions e ON e.calltracking_target_id = ct.id
                LEFT JOIN users uu ON uu.id = g.updated_by';
        $params = [];
        if ($search !== '') {
            $sql .= ' WHERE g.name LIKE :search';
            $params['search'] = "%{$search}%";
        }
        $sql .= ' GROUP BY g.id ORDER BY g.name';
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['departments'] = self::parsePairs($row['departments_raw'] ?? null);
        }
        unset($row);
        return $rows;
    }

    public static function find(int $id): ?array
    {
        $sql = 'SELECT g.*, cu.name AS created_by_name, uu.name AS updated_by_name
                FROM calltracking_goals g
                LEFT JOIN users cu ON cu.id = g.created_by
                LEFT JOIN users uu ON uu.id = g.updated_by
                WHERE g.id = :id';
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findRaw(int $id): ?array
    {
        $stmt = Db::connection()->prepare('SELECT * FROM calltracking_goals WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Поиск по названию без учёта регистра и пробелов по краям (для проверки дублей) */
    public static function findByName(string $name): ?array
    {
        $stmt = Db::connection()->prepare('SELECT * FROM calltracking_goals WHERE name = :name');
        $stmt->execute(['name' => trim($name)]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = Db::connection()->prepare(
            'INSERT INTO calltracking_goals (name, created_by, updated_by) VALUES (:name, :created_by, :updated_by)'
        );
        $stmt->execute([
            'name' => $data['name'],
            'created_by' => $data['created_by'] ?? null,
            'updated_by' => $data['created_by'] ?? null,
        ]);
        return (int)Db::connection()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        Db::connection()->prepare('UPDATE calltracking_goals SET name = :name, updated_by = :updated_by WHERE id = :id')
            ->execute(['id' => $id, 'name' => $data['name'], 'updated_by' => $data['updated_by'] ?? null]);
    }

    public static function delete(int $id): void
    {
        Db::connection()->prepare('DELETE FROM calltracking_goals WHERE id = :id')->execute(['id' => $id]);
    }

    public static function options(): array
    {
        return Db::connection()->query('SELECT id, name FROM calltracking_goals ORDER BY name')->fetchAll();
    }

    /** Подразделения, в которых используется цель (с числом номеров по каждому) */
    public static function departments(int $goalId): array
    {
        $sql = 'SELECT d.id, d.name, ct.id AS link_id, COUNT(e.id) AS extensions_count
                FROM calltracking_targets ct
                JOIN departments d ON d.id = ct.department_id
                LEFT JOIN extensions e ON e.calltracking_target_id = ct.id
                WHERE ct.goal_id = :id
                GROUP BY d.id, d.name, ct.id
                ORDER BY d.name';
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute(['id' => $goalId]);
        return $stmt->fetchAll();
    }

    /** Номера CallTracking, у которых выбрана эта цель */
    public static function extensions(int $goalId): array
    {
        $sql = 'SELECT e.id, e.number, e.owner, d.name AS department_name
                FROM extensions e
                JOIN calltracking_targets ct ON ct.id = e.calltracking_target_id
                LEFT JOIN departments d ON d.id = ct.department_id
                WHERE ct.goal_id = :id
                ORDER BY e.number';
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute(['id' => $goalId]);
        return $stmt->fetchAll();
    }

    /** "12:Продажи\n15:Сервис" -> [['id' => 12, 'name' => 'Продажи'], ...] */
    public static function parsePairs(?string $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        $out = [];
        foreach (explode("\n", $raw) as $pair) {
            $pos = strpos($pair, ':');
            if ($pos === false) {
                continue;
            }
            $out[] = ['id' => (int)substr($pair, 0, $pos), 'name' => substr($pair, $pos + 1)];
        }
        return $out;
    }
}
