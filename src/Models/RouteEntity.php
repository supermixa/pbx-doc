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

    /** Исходящие маршруты — для поля "Маршрут на выход" блока "Перевести на внешний номер" */
    public static function outboundOptions(): array
    {
        $stmt = Db::connection()->query("SELECT id, name FROM routes WHERE direction = 'outbound' ORDER BY name");
        return $stmt->fetchAll();
    }

    // ------------------------------------------------------------------
    // АОН маршрута ↔ АОН внутренних номеров
    // ------------------------------------------------------------------

    /**
     * Внутренние номера (тип «Внутренний»), к которым относится исходящий
     * маршрут: состав маршрута (selection_type = internal_numbers) или все
     * внутренние номера подразделения (selection_type = departments).
     * Для остальных типов отбора — пусто.
     *
     * @return array<int, array{id: int, number: string, caller_id_extension_id: ?int}>
     */
    public static function coveredInternalNumbers(int $routeId): array
    {
        $route = self::findRaw($routeId);
        if (!$route || $route['direction'] !== 'outbound') {
            return [];
        }
        if ($route['selection_type'] === 'internal_numbers') {
            $sql = "SELECT e.id, e.number, e.caller_id_extension_id
                    FROM route_members rm JOIN extensions e ON e.id = rm.member_extension_id
                    WHERE rm.route_id = :id AND e.category = 'internal' AND e.type = 'internal'
                    ORDER BY e.number";
            $params = ['id' => $routeId];
        } elseif ($route['selection_type'] === 'departments' && $route['department_id']) {
            $sql = "SELECT id, number, caller_id_extension_id FROM extensions
                    WHERE department_id = :dep AND category = 'internal' AND type = 'internal'
                    ORDER BY number";
            $params = ['dep' => (int)$route['department_id']];
        } else {
            return [];
        }
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Исходящие маршруты с заполненным АОН, которые задают АОН этому
     * внутреннему номеру: номер в составе маршрута или в подразделении
     * маршрута ($departmentId — подразделение номера, в т.ч. только что
     * выбранное в форме). $extensionId = null — номер ещё не создан.
     *
     * @return array<int, array{id: int, name: string, outbound_caller_id_id: int, aon_number: string, via: string}>
     */
    public static function callerIdRoutesFor(?int $extensionId, ?int $departmentId): array
    {
        $sql = "SELECT r.id, r.name, r.outbound_caller_id_id, aon.number AS aon_number,
                       CASE WHEN r.selection_type = 'internal_numbers' THEN 'members' ELSE 'department' END AS via
                FROM routes r
                JOIN extensions aon ON aon.id = r.outbound_caller_id_id
                WHERE r.direction = 'outbound' AND (
                    (r.selection_type = 'internal_numbers' AND r.id IN (
                        SELECT route_id FROM route_members WHERE member_extension_id = :ext))
                    OR (r.selection_type = 'departments' AND r.department_id = :dep)
                )
                ORDER BY r.name";
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute(['ext' => $extensionId ?? 0, 'dep' => $departmentId ?? 0]);
        return $stmt->fetchAll();
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
