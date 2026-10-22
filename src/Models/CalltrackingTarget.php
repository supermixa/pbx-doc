<?php

namespace App\Models;

use App\Core\Db;

/**
 * Справочник "Calltracking": связь «подразделение — цель». У подразделения
 * может быть несколько целей; сами цели — в подчинённом справочнике
 * calltracking_goals (см. CalltrackingGoal).
 *
 * Внешние номера с типом CallTracking ссылаются на строку этой связи
 * (extensions.calltracking_target_id): в форме номера выбирается
 * подразделение и предлагается список его целей.
 */
class CalltrackingTarget
{
    /**
     * Подразделения, у которых есть цели, с их списком целей — для /calltracking.
     * Поиск — по названию подразделения или цели.
     *
     * @return array<int, array{department_id: int, department_name: string, goals: array<int, array{id: int, name: string}>}>
     */
    public static function groupedByDepartment(string $search = ''): array
    {
        $sql = 'SELECT d.id AS department_id, d.name AS department_name, g.id AS goal_id, g.name AS goal_name
                FROM calltracking_targets ct
                JOIN departments d ON d.id = ct.department_id
                JOIN calltracking_goals g ON g.id = ct.goal_id';
        $params = [];
        if ($search !== '') {
            // Подразделение показывается целиком (со всеми целями), если
            // совпало его название или название хотя бы одной из его целей.
            $sql .= ' WHERE ct.department_id IN (
                        SELECT ct2.department_id FROM calltracking_targets ct2
                        JOIN departments d2 ON d2.id = ct2.department_id
                        JOIN calltracking_goals g2 ON g2.id = ct2.goal_id
                        WHERE d2.name LIKE :search1 OR g2.name LIKE :search2)';
            $params['search1'] = "%{$search}%";
            $params['search2'] = "%{$search}%";
        }
        $sql .= ' ORDER BY d.name, g.name';
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute($params);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $depId = (int)$row['department_id'];
            if (!isset($result[$depId])) {
                $result[$depId] = [
                    'department_id' => $depId,
                    'department_name' => $row['department_name'],
                    'goals' => [],
                ];
            }
            $result[$depId]['goals'][] = ['id' => (int)$row['goal_id'], 'name' => $row['goal_name']];
        }
        return array_values($result);
    }

    /**
     * Цели подразделения (строки связи) с числом номеров, которые на них ссылаются.
     *
     * @return array<int, array{id: int, goal_id: int, goal_name: string, extensions_count: int}>
     */
    public static function forDepartment(int $departmentId): array
    {
        $sql = 'SELECT ct.id, ct.goal_id, g.name AS goal_name, COUNT(e.id) AS extensions_count
                FROM calltracking_targets ct
                JOIN calltracking_goals g ON g.id = ct.goal_id
                LEFT JOIN extensions e ON e.calltracking_target_id = ct.id
                WHERE ct.department_id = :id
                GROUP BY ct.id, ct.goal_id, g.name
                ORDER BY g.name';
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute(['id' => $departmentId]);
        return $stmt->fetchAll();
    }

    /** Номера CallTracking подразделения (с выбранной целью) */
    public static function extensionsForDepartment(int $departmentId): array
    {
        $sql = 'SELECT e.id, e.number, e.owner, g.id AS goal_id, g.name AS goal_name
                FROM extensions e
                JOIN calltracking_targets ct ON ct.id = e.calltracking_target_id
                JOIN calltracking_goals g ON g.id = ct.goal_id
                WHERE ct.department_id = :id
                ORDER BY e.number';
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute(['id' => $departmentId]);
        return $stmt->fetchAll();
    }

    public static function findRaw(int $id): ?array
    {
        $stmt = Db::connection()->prepare('SELECT * FROM calltracking_targets WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Приводит набор целей подразделения к $goalIds: недостающие добавляет,
     * лишние удаляет. Строки для целей, которые остаются, НЕ пересоздаются —
     * на них ссылаются номера CallTracking. Контроллер заранее проверяет,
     * что удаляемые цели не используются номерами.
     */
    public static function syncDepartmentGoals(int $departmentId, array $goalIds, ?int $userId): void
    {
        $goalIds = array_values(array_unique(array_map('intval', $goalIds)));
        $conn = Db::connection();

        $existing = [];
        foreach (self::forDepartment($departmentId) as $row) {
            $existing[(int)$row['goal_id']] = (int)$row['id'];
        }

        $del = $conn->prepare('DELETE FROM calltracking_targets WHERE id = :id');
        foreach ($existing as $goalId => $linkId) {
            if (!in_array($goalId, $goalIds, true)) {
                $del->execute(['id' => $linkId]);
            }
        }

        $ins = $conn->prepare(
            'INSERT INTO calltracking_targets (department_id, goal_id, created_by, updated_by)
             VALUES (:department_id, :goal_id, :created_by, :updated_by)'
        );
        foreach ($goalIds as $goalId) {
            if (!isset($existing[$goalId])) {
                $ins->execute([
                    'department_id' => $departmentId,
                    'goal_id' => $goalId,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
            }
        }
    }

    public static function count(): int
    {
        return (int)Db::connection()->query('SELECT COUNT(DISTINCT department_id) FROM calltracking_targets')->fetchColumn();
    }

    /**
     * Список целей с ID подразделения (для клиентской фильтрации в форме
     * внешнего номера: "выбрали подразделение -> показали его цели").
     */
    public static function optionsWithDepartments(): array
    {
        $sql = 'SELECT ct.id, ct.department_id, g.name AS goal_name
                FROM calltracking_targets ct
                JOIN calltracking_goals g ON g.id = ct.goal_id
                ORDER BY g.name';
        return Db::connection()->query($sql)->fetchAll();
    }

    /** "Цель 1, Цель 2" — для журнала изменений */
    public static function summarizeGoals(array $goalNames): string
    {
        sort($goalNames, SORT_NATURAL | SORT_FLAG_CASE);
        return implode(', ', $goalNames);
    }
}
