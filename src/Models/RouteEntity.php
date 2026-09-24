<?php

namespace App\Models;

use App\Core\Db;

/**
 * Модель "Маршрут" (правило маршрутизации вызовов).
 * Названа RouteEntity, чтобы не путать с App\Core\Router.
 *
 * direction=inbound: используются scenario_id и pattern ("Шаблон номера звонящего").
 * direction=outbound: используется selection_type, который определяет,
 * какие ещё поля значимы (см. константу SELECTION_TYPES и RouteController::validate()).
 */
class RouteEntity
{
    public const DIRECTIONS = ['inbound', 'outbound'];

    /** Тип отбора для исходящих маршрутов */
    public const SELECTION_TYPES = ['internal_numbers', 'departments', 'dialed_number', 'complex'];

    private static array $fillable = [
        'name', 'direction', 'scenario_id', 'pattern', 'selection_type', 'department_id',
        'trunk_id', 'outbound_caller_id_id', 'context', 'description',
    ];

    private const JOINS = '
                FROM routes r
                LEFT JOIN scenarios s ON s.id = r.scenario_id
                LEFT JOIN departments d ON d.id = r.department_id
                LEFT JOIN trunks t ON t.id = r.trunk_id
                LEFT JOIN extensions aon ON aon.id = r.outbound_caller_id_id
                LEFT JOIN users cu ON cu.id = r.created_by
                LEFT JOIN users uu ON uu.id = r.updated_by';

    private const SELECT_COLUMNS = 'r.*, s.name AS scenario_name, d.name AS department_name,
                       t.name AS trunk_name, aon.number AS outbound_caller_id_number,
                       cu.name AS created_by_name, uu.name AS updated_by_name';

    public static function all(string $search = ''): array
    {
        $sql = 'SELECT ' . self::SELECT_COLUMNS . self::JOINS;
        $params = [];
        if ($search !== '') {
            $sql .= ' WHERE r.name LIKE :search1 OR r.pattern LIKE :search2';
            $params['search1'] = "%{$search}%";
            $params['search2'] = "%{$search}%";
        }
        $sql .= ' ORDER BY r.name';
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $sql = 'SELECT ' . self::SELECT_COLUMNS . self::JOINS . ' WHERE r.id = :id';
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findRaw(int $id): ?array
    {
        $stmt = Db::connection()->prepare('SELECT * FROM routes WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $fields = self::$fillable;
        $columns = implode(', ', $fields);
        $placeholders = ':' . implode(', :', $fields);
        $sql = "INSERT INTO routes ({$columns}, created_by, updated_by) VALUES ({$placeholders}, :created_by, :updated_by)";

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
        $sql = "UPDATE routes SET {$set}, updated_by = :updated_by WHERE id = :id";

        $params = ['id' => $id, 'updated_by' => $data['updated_by'] ?? null];
        foreach ($fields as $f) {
            $params[$f] = $data[$f] ?? null;
        }

        Db::connection()->prepare($sql)->execute($params);
    }

    public static function delete(int $id): void
    {
        Db::connection()->prepare('DELETE FROM routes WHERE id = :id')->execute(['id' => $id]);
    }

    public static function count(): int
    {
        return (int)Db::connection()->query('SELECT COUNT(*) FROM routes')->fetchColumn();
    }

    // ------------------------------------------------------------------
    // Состав внутренних номеров (selection_type = internal_numbers)
    // ------------------------------------------------------------------

    /** Упорядоченный состав внутренних номеров маршрута */
    public static function routeMembers(int $routeId): array
    {
        $sql = 'SELECT rm.position, m.id, m.number, m.owner
                FROM route_members rm
                JOIN extensions m ON m.id = rm.member_extension_id
                WHERE rm.route_id = :id
                ORDER BY rm.position';
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute(['id' => $routeId]);
        return $stmt->fetchAll();
    }

    /**
     * Полностью заменяет состав внутренних номеров маршрута (порядок = порядок в массиве).
     * $memberExtensionIds — уже провалидированные ID (category=internal, type=internal).
     */
    public static function replaceRouteMembers(int $routeId, array $memberExtensionIds): void
    {
        $conn = Db::connection();
        $conn->prepare('DELETE FROM route_members WHERE route_id = :id')->execute(['id' => $routeId]);

        if (empty($memberExtensionIds)) {
            return;
        }
        $stmt = $conn->prepare(
            'INSERT INTO route_members (route_id, member_extension_id, position) VALUES (:route_id, :member_id, :position)'
        );
        foreach (array_values($memberExtensionIds) as $position => $memberId) {
            $stmt->execute(['route_id' => $routeId, 'member_id' => $memberId, 'position' => $position]);
        }
    }
}
