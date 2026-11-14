<?php

namespace App\Models;

use App\Core\Db;

class AuditLog
{
    public static function create(array $data): int
    {
        $sql = 'INSERT INTO audit_logs (entity_type, entity_id, action, user_id, changes)
                VALUES (:entity_type, :entity_id, :action, :user_id, :changes)';
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute([
            'entity_type' => $data['entity_type'],
            'entity_id' => $data['entity_id'],
            'action' => $data['action'],
            'user_id' => $data['user_id'],
            'changes' => $data['changes'],
        ]);
        return (int)Db::connection()->lastInsertId();
    }

    /** История изменений конкретной сущности (новые сверху) */
    public static function forEntity(string $entityType, int $entityId): array
    {
        $sql = 'SELECT a.*, u.name AS user_name, u.email AS user_email
                FROM audit_logs a
                LEFT JOIN users u ON u.id = a.user_id
                WHERE a.entity_type = :entity_type AND a.entity_id = :entity_id
                ORDER BY a.created_at DESC, a.id DESC';
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute(['entity_type' => $entityType, 'entity_id' => $entityId]);
        return $stmt->fetchAll();
    }

    /** Общая лента последних изменений по всем сущностям */
    public static function recent(int $limit = 30): array
    {
        $limit = max(1, min(200, $limit));
        $sql = "SELECT a.*, u.name AS user_name, u.email AS user_email
                FROM audit_logs a
                LEFT JOIN users u ON u.id = a.user_id
                ORDER BY a.created_at DESC, a.id DESC
                LIMIT {$limit}";
        $stmt = Db::connection()->query($sql);
        return $stmt->fetchAll();
    }

    // ------------------------------------------------------------------
    // Раздел «История» — поиск по всему журналу
    // ------------------------------------------------------------------

    /** Таблица и колонка с названием записи — для подписи объекта в истории */
    private const NAME_SOURCES = [
        'trunk' => ['trunks', 'name'],
        'route' => ['routes', 'name'],
        'scenario' => ['scenarios', 'name'],
        'extension' => ['extensions', 'number'],
        'user' => ['users', 'name'],
        'department' => ['departments', 'name'],
        'division' => ['divisions', 'name'],
        'equipment' => ['equipment', "COALESCE(NULLIF(location, ''), ip_address)"],
        'equipment_type' => ['equipment_types', 'name'],
        'legal_entity' => ['legal_entities', 'name'],
        'provider' => ['providers', 'name'],
        'calltracking' => ['departments', 'name'],
        'calltracking_goal' => ['calltracking_goals', 'name'],
        'music_file' => ['music_files', 'title'],
    ];

    /**
     * Условия отбора истории. $f: from, to ('Y-m-d H:i:s'), entity_type,
     * action, user_id (0 = системные/без пользователя не выделяются), q
     * (текст в изменениях), hide_types (типы, скрытые от текущей роли).
     *
     * @return array{0: string, 1: array}
     */
    private static function searchWhere(array $f): array
    {
        $where = [];
        $params = [];
        if (!empty($f['from'])) {
            $where[] = 'a.created_at >= :from';
            $params['from'] = $f['from'];
        }
        if (!empty($f['to'])) {
            $where[] = 'a.created_at <= :to';
            $params['to'] = $f['to'];
        }
        if (!empty($f['entity_type'])) {
            $where[] = 'a.entity_type = :entity_type';
            $params['entity_type'] = $f['entity_type'];
        }
        if (!empty($f['action'])) {
            $where[] = 'a.action = :action';
            $params['action'] = $f['action'];
        }
        if (!empty($f['user_id'])) {
            $where[] = 'a.user_id = :user_id';
            $params['user_id'] = (int)$f['user_id'];
        }
        if (!empty($f['entity_id'])) {
            $where[] = 'a.entity_id = :entity_id';
            $params['entity_id'] = (int)$f['entity_id'];
        }
        if (isset($f['q']) && $f['q'] !== '') {
            $where[] = 'a.changes LIKE :q';
            $params['q'] = '%' . $f['q'] . '%';
        }
        foreach (array_values($f['hide_types'] ?? []) as $i => $type) {
            $where[] = "a.entity_type <> :hide{$i}";
            $params['hide' . $i] = $type;
        }
        return [$where ? ' WHERE ' . implode(' AND ', $where) : '', $params];
    }

    public static function search(array $filters, int $limit, int $offset = 0): array
    {
        [$where, $params] = self::searchWhere($filters);
        $limit = max(1, min(20000, $limit));
        $offset = max(0, $offset);
        $sql = "SELECT a.*, u.name AS user_name, u.email AS user_email
                FROM audit_logs a
                LEFT JOIN users u ON u.id = a.user_id
                {$where}
                ORDER BY a.created_at DESC, a.id DESC
                LIMIT {$limit} OFFSET {$offset}";
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function searchCount(array $filters): int
    {
        [$where, $params] = self::searchWhere($filters);
        $stmt = Db::connection()->prepare("SELECT COUNT(*) FROM audit_logs a {$where}");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /** Типы объектов, встречающиеся в журнале */
    public static function entityTypes(): array
    {
        return Db::connection()->query('SELECT DISTINCT entity_type FROM audit_logs ORDER BY entity_type')->fetchAll(\PDO::FETCH_COLUMN);
    }

    /** Пользователи, оставившие записи в журнале */
    public static function authors(): array
    {
        return Db::connection()->query(
            'SELECT DISTINCT u.id, u.name FROM audit_logs a JOIN users u ON u.id = a.user_id ORDER BY u.name'
        )->fetchAll();
    }

    /**
     * Подписи объектов для записей журнала: [entity_type][entity_id] =>
     * ['name' => ..., 'exists' => bool]. Если объект удалён — название
     * берётся из самой записи журнала (поля name/number/title).
     */
    public static function entityNames(array $entries): array
    {
        $ids = [];
        foreach ($entries as $e) {
            $ids[$e['entity_type']][(int)$e['entity_id']] = true;
        }
        $names = [];
        foreach ($ids as $type => $set) {
            if (!isset(self::NAME_SOURCES[$type])) {
                continue;
            }
            [$table, $column] = self::NAME_SOURCES[$type];
            $list = implode(',', array_map('intval', array_keys($set)));
            $rows = Db::connection()->query("SELECT id, {$column} AS label FROM {$table} WHERE id IN ({$list})")->fetchAll();
            foreach ($rows as $r) {
                $names[$type][(int)$r['id']] = ['name' => (string)($r['label'] ?? ''), 'exists' => true];
            }
        }
        foreach ($entries as $e) {
            $type = $e['entity_type'];
            $id = (int)$e['entity_id'];
            if (isset($names[$type][$id]) && ($names[$type][$id]['exists'] || $names[$type][$id]['name'] !== '')) {
                continue;
            }
            $changes = json_decode($e['changes'] ?? '[]', true) ?: [];
            $label = '';
            foreach (['name', 'number', 'title', 'target'] as $field) {
                $v = $changes[$field]['new'] ?? $changes[$field]['old'] ?? null;
                if ($v !== null && $v !== '') {
                    $label = (string)$v;
                    break;
                }
            }
            $names[$type][$id] = ['name' => $label, 'exists' => false];
        }
        return $names;
    }
}
