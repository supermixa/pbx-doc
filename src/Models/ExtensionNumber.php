<?php

namespace App\Models;

use App\Core\Db;
use App\Core\Labels;

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

    /** Методы обзвона участников очереди (type = queue) */
    public const QUEUE_RING_STRATEGIES = ['ringall', 'leastrecent', 'fewestcalls', 'random', 'rrmemory', 'linear'];
    /** Методы обзвона участников группы обзвона (type = ivr) — подмножество методов очереди */
    public const RING_GROUP_RING_STRATEGIES = ['ringall', 'linear'];
    /** Что слышит абонент во время ожидания в очереди */
    public const CALLER_WAIT_SOUNDS = ['music', 'ringing'];
    /** Типы цели маршрутизации при сбоях очереди (сценарий или номер — очередь/группа/служебный/внутренний) */
    public const FAILOVER_TARGET_TYPES = ['scenario', 'extension'];

    private static array $fillable = [
        'number', 'category', 'type', 'owner', 'context', 'scenario_id', 'department_id',
        'legal_entity_id', 'provider_id', 'trunk_id', 'calltracking_target_id', 'caller_id_extension_id', 'division_id',
        'status', 'description',
    ];

    /** Типы внешних номеров, которые можно выбрать АОН-ом внутреннего номера */
    public const CALLER_ID_TYPES = ['did_city', 'did_mobile'];

    private const JOINS = '
                FROM extensions e
                LEFT JOIN scenarios s ON s.id = e.scenario_id
                LEFT JOIN departments d ON d.id = e.department_id
                LEFT JOIN legal_entities le ON le.id = e.legal_entity_id
                LEFT JOIN providers pr ON pr.id = e.provider_id
                LEFT JOIN trunks tr ON tr.id = e.trunk_id
                LEFT JOIN calltracking_targets ct ON ct.id = e.calltracking_target_id
                LEFT JOIN calltracking_goals ctg ON ctg.id = ct.goal_id
                LEFT JOIN extensions cid ON cid.id = e.caller_id_extension_id
                LEFT JOIN divisions dv ON dv.id = e.division_id
                LEFT JOIN users cu ON cu.id = e.created_by
                LEFT JOIN users uu ON uu.id = e.updated_by';

    private const SELECT_COLUMNS = 'e.*, s.name AS scenario_name, d.name AS department_name,
                       le.name AS legal_entity_name, pr.name AS provider_name, tr.name AS trunk_name,
                       ctg.name AS calltracking_target_name, ct.goal_id AS calltracking_goal_id,
                       cid.number AS caller_id_number, cid.type AS caller_id_type,
                       dv.name AS division_name,
                       cu.name AS created_by_name, uu.name AS updated_by_name';

    /**
     * Колонки, по которым работают отборы списка номеров:
     * ключ отбора => колонка. Значение отбора 'none' — «не указано» (IS NULL).
     */
    public const LIST_FILTER_COLUMNS = [
        'type' => 'e.type',
        'provider' => 'e.provider_id',
        'trunk' => 'e.trunk_id',
        'department' => 'e.department_id',
        'division' => 'e.division_id',
    ];

    /** @param array $filters уже проверенные отборы: ключ LIST_FILTER_COLUMNS => значение/'none' */
    public static function all(string $search = '', string $category = '', string $type = '', array $filters = []): array
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
        if ($type !== '') {
            $where[] = 'e.type = :type';
            $params['type'] = $type;
        }
        foreach ($filters as $key => $value) {
            $column = self::LIST_FILTER_COLUMNS[$key] ?? null;
            if ($column === null) {
                continue;
            }
            if ($value === 'none') {
                $where[] = "{$column} IS NULL";
            } else {
                $where[] = "{$column} = :f_{$key}";
                $params['f_' . $key] = $value;
            }
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

    // ------------------------------------------------------------------
    // Очередь / группа обзвона (type = queue | ivr)
    // ------------------------------------------------------------------

    /** Настройки очереди/группы обзвона номера (с расшифровкой музыки/целей маршрутизации) */
    public static function queueSettings(int $extensionId): ?array
    {
        $sql = 'SELECT eqs.*,
                       mm.title AS moh_music_title,
                       am.title AS announcement_music_title
                FROM extension_queue_settings eqs
                LEFT JOIN music_files mm ON mm.id = eqs.moh_music_id
                LEFT JOIN music_files am ON am.id = eqs.announcement_music_id
                WHERE eqs.extension_id = :id';
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute(['id' => $extensionId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $row['failover1_target_label'] = self::describeTarget($row['failover1_target_type'] ?? null, $row['failover1_target_id'] ?? null);
        $row['failover2_target_label'] = self::describeTarget($row['failover2_target_type'] ?? null, $row['failover2_target_id'] ?? null);
        return $row;
    }

    private static function describeTarget(?string $type, $id): ?string
    {
        if (!$type || !$id) {
            return null;
        }
        if ($type === 'scenario') {
            $stmt = Db::connection()->prepare('SELECT name FROM scenarios WHERE id = :id');
            $stmt->execute(['id' => $id]);
            $name = $stmt->fetchColumn();
            return $name !== false ? "Сценарий «{$name}»" : null;
        }
        if ($type === 'extension') {
            $stmt = Db::connection()->prepare('SELECT number, category, type FROM extensions WHERE id = :id');
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch();
            if (!$row) {
                return null;
            }
            return 'Номер ' . $row['number'];
        }
        return null;
    }

    /**
     * Полностью заменяет настройки очереди/группы обзвона номера.
     * $data === null — удаляет настройки (номер больше не queue/ivr).
     */
    public static function replaceQueueSettings(int $extensionId, ?array $data): void
    {
        $conn = Db::connection();
        if ($data === null) {
            $conn->prepare('DELETE FROM extension_queue_settings WHERE extension_id = :id')->execute(['id' => $extensionId]);
            return;
        }

        $sql = 'INSERT INTO extension_queue_settings (
                    extension_id, ring_strategy, agent_answer_timeout_sec, agent_wrapup_time_sec, agent_ring_in_use,
                    caller_wait_sound, moh_music_id, announce_position, announce_hold_time, announcement_music_id,
                    announcement_repeat_sec, failover1_timeout_sec, failover1_target_type, failover1_target_id,
                    failover2_target_type, failover2_target_id
                ) VALUES (
                    :extension_id, :ring_strategy, :agent_answer_timeout_sec, :agent_wrapup_time_sec, :agent_ring_in_use,
                    :caller_wait_sound, :moh_music_id, :announce_position, :announce_hold_time, :announcement_music_id,
                    :announcement_repeat_sec, :failover1_timeout_sec, :failover1_target_type, :failover1_target_id,
                    :failover2_target_type, :failover2_target_id
                )
                ON DUPLICATE KEY UPDATE
                    ring_strategy = VALUES(ring_strategy),
                    agent_answer_timeout_sec = VALUES(agent_answer_timeout_sec),
                    agent_wrapup_time_sec = VALUES(agent_wrapup_time_sec),
                    agent_ring_in_use = VALUES(agent_ring_in_use),
                    caller_wait_sound = VALUES(caller_wait_sound),
                    moh_music_id = VALUES(moh_music_id),
                    announce_position = VALUES(announce_position),
                    announce_hold_time = VALUES(announce_hold_time),
                    announcement_music_id = VALUES(announcement_music_id),
                    announcement_repeat_sec = VALUES(announcement_repeat_sec),
                    failover1_timeout_sec = VALUES(failover1_timeout_sec),
                    failover1_target_type = VALUES(failover1_target_type),
                    failover1_target_id = VALUES(failover1_target_id),
                    failover2_target_type = VALUES(failover2_target_type),
                    failover2_target_id = VALUES(failover2_target_id)';

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'extension_id' => $extensionId,
            'ring_strategy' => $data['ring_strategy'] ?? 'ringall',
            'agent_answer_timeout_sec' => $data['agent_answer_timeout_sec'] ?? null,
            'agent_wrapup_time_sec' => $data['agent_wrapup_time_sec'] ?? null,
            'agent_ring_in_use' => !empty($data['agent_ring_in_use']) ? 1 : 0,
            'caller_wait_sound' => $data['caller_wait_sound'] ?? 'music',
            'moh_music_id' => $data['moh_music_id'] ?? null,
            'announce_position' => !empty($data['announce_position']) ? 1 : 0,
            'announce_hold_time' => !empty($data['announce_hold_time']) ? 1 : 0,
            'announcement_music_id' => $data['announcement_music_id'] ?? null,
            'announcement_repeat_sec' => $data['announcement_repeat_sec'] ?? null,
            'failover1_timeout_sec' => $data['failover1_timeout_sec'] ?? null,
            'failover1_target_type' => $data['failover1_target_type'] ?? null,
            'failover1_target_id' => $data['failover1_target_id'] ?? null,
            'failover2_target_type' => $data['failover2_target_type'] ?? null,
            'failover2_target_id' => $data['failover2_target_id'] ?? null,
        ]);
    }

    /** Упорядоченный состав участников очереди/группы обзвона */
    public static function groupMembers(int $extensionId): array
    {
        $sql = 'SELECT egm.position, m.id, m.number, m.owner
                FROM extension_group_members egm
                JOIN extensions m ON m.id = egm.member_extension_id
                WHERE egm.extension_id = :id
                ORDER BY egm.position';
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute(['id' => $extensionId]);
        return $stmt->fetchAll();
    }

    /**
     * Полностью заменяет состав участников (порядок = порядок в массиве).
     * $memberExtensionIds — уже провалидированные ID (category=internal, type=internal).
     */
    public static function replaceGroupMembers(int $extensionId, array $memberExtensionIds): void
    {
        $conn = Db::connection();
        $conn->prepare('DELETE FROM extension_group_members WHERE extension_id = :id')->execute(['id' => $extensionId]);

        if (empty($memberExtensionIds)) {
            return;
        }
        $stmt = $conn->prepare(
            'INSERT INTO extension_group_members (extension_id, member_extension_id, position) VALUES (:extension_id, :member_id, :position)'
        );
        foreach (array_values($memberExtensionIds) as $position => $memberId) {
            $stmt->execute(['extension_id' => $extensionId, 'member_id' => $memberId, 'position' => $position]);
        }
    }

    /**
     * Номера заданного типа (category=internal) — для полей конструктора
     * сценария "Номер очереди" (type=queue) и "Внутренний номер" (type=internal).
     */
    public static function optionsByType(string $type): array
    {
        $sql = "SELECT id, number, owner FROM extensions WHERE category = 'internal' AND type = :type ORDER BY number";
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute(['type' => $type]);
        return $stmt->fetchAll();
    }

    /**
     * Очереди, группы обзвона и внутренние номера — кандидаты для цели
     * "При неудаче" в блоках "Переключить на очередь"/"Переключить на номер"
     * конструктора сценария.
     */
    public static function scenarioFailureTargetCandidates(): array
    {
        $sql = "SELECT id, number, owner, type FROM extensions
                WHERE category = 'internal' AND type IN ('queue', 'ivr', 'internal')
                ORDER BY number";
        $stmt = Db::connection()->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Внешние номера (category=external) со своим trunk_id — для выбора
     * "Исходящего АОН" в исходящих маршрутах (каждый вариант несёт
     * data-trunk, чтобы JS мог отфильтровать список по выбранному транку).
     */
    public static function externalNumberOptions(): array
    {
        $sql = "SELECT id, number, owner, trunk_id FROM extensions WHERE category = 'external' ORDER BY number";
        $stmt = Db::connection()->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Внутренние номера (category=internal, type=internal) заданного
     * подразделения — для отображения состава при выборе "Подразделения"
     * в качестве типа отбора исходящего маршрута.
     */
    public static function departmentInternalNumbers(int $departmentId): array
    {
        $sql = "SELECT id, number, owner FROM extensions
                WHERE category = 'internal' AND type = 'internal' AND department_id = :department_id
                ORDER BY number";
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute(['department_id' => $departmentId]);
        return $stmt->fetchAll();
    }

    /** Внутренние номера (category=internal, type=internal) — единственно допустимые участники очереди/группы */
    /**
     * Кандидаты в АОН внутреннего номера: внешние номера с типом «Городской»
     * или «Сотовый», в которых встречается введённая последовательность цифр
     * (форматирование номера — пробелы, скобки, «+» — не мешает поиску).
     */
    public static function callerIdCandidates(string $digits, int $limit = 20): array
    {
        $digits = preg_replace('/\D+/', '', $digits);
        if (strlen($digits) < 3) {
            return [];
        }
        $sql = "SELECT id, number, type, owner FROM extensions
                WHERE category = 'external' AND type IN ('did_city', 'did_mobile')
                  AND REGEXP_REPLACE(number, '[^0-9]', '') LIKE :digits
                ORDER BY number LIMIT " . max(1, min(50, $limit));
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute(['digits' => '%' . $digits . '%']);
        return $stmt->fetchAll();
    }

    /** Сколько внутренних номеров с типом «Внутренний» без владельца (для экспорта в MikoPBX) */
    public static function countInternalWithoutOwner(): int
    {
        return (int)Db::connection()->query(
            "SELECT COUNT(*) FROM extensions WHERE category = 'internal' AND type = 'internal' AND (owner IS NULL OR TRIM(owner) = '')"
        )->fetchColumn();
    }

    /** Внутренние номера, у которых этот внешний номер выбран АОН-ом */
    public static function callerIdUsers(int $externalId): array
    {
        $stmt = Db::connection()->prepare('SELECT id, number, owner FROM extensions WHERE caller_id_extension_id = :id ORDER BY number');
        $stmt->execute(['id' => $externalId]);
        return $stmt->fetchAll();
    }

    /** Номера отдела (для карточки отдела) */
    public static function forDivision(int $divisionId): array
    {
        $sql = 'SELECT ' . self::SELECT_COLUMNS . self::JOINS . "
                WHERE e.division_id = :division_id
                ORDER BY e.category = 'external', FIELD(e.type, 'internal', 'queue', 'ivr', 'other', 'did_city', 'did_mobile', 'calltracking'), e.number";
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute(['division_id' => $divisionId]);
        return $stmt->fetchAll();
    }

    /** Все номера подразделения (для карточки подразделения), по категории/типу/номеру */
    public static function forDepartment(int $departmentId): array
    {
        $sql = 'SELECT ' . self::SELECT_COLUMNS . self::JOINS . "
                WHERE e.department_id = :department_id
                ORDER BY e.category = 'external', FIELD(e.type, 'internal', 'queue', 'ivr', 'other', 'did_city', 'did_mobile', 'calltracking'), e.number";
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute(['department_id' => $departmentId]);
        return $stmt->fetchAll();
    }

    public static function internalMemberCandidates(string $search = '', int $limit = 20): array
    {
        $sql = "SELECT id, number, owner FROM extensions WHERE category = 'internal' AND type = 'internal'";
        $params = [];
        if ($search !== '') {
            $sql .= ' AND (number LIKE :search1 OR owner LIKE :search2)';
            $params['search1'] = "%{$search}%";
            $params['search2'] = "%{$search}%";
        }
        $sql .= ' ORDER BY number LIMIT ' . max(1, min(50, $limit));
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Поиск целей для маршрутизации при сбоях очереди: сценарии + любые
     * внутренние номера (очереди/группы/служебные/внутренние), кроме самого
     * редактируемого номера (нельзя перевести звонок на самого себя).
     */
    public static function failoverTargetCandidates(string $search, ?int $excludeExtensionId = null, int $limit = 20): array
    {
        $limit = max(1, min(50, $limit));
        $results = [];

        $sql = 'SELECT id, name FROM scenarios';
        $params = [];
        if ($search !== '') {
            $sql .= ' WHERE name LIKE :search';
            $params['search'] = "%{$search}%";
        }
        $sql .= ' ORDER BY name LIMIT ' . $limit;
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $s) {
            $results[] = ['type' => 'scenario', 'id' => (int)$s['id'], 'label' => 'Сценарий: ' . $s['name']];
        }

        $sql = "SELECT id, number, owner, type FROM extensions WHERE category = 'internal'";
        $params = [];
        if ($excludeExtensionId) {
            $sql .= ' AND id != :exclude_id';
            $params['exclude_id'] = $excludeExtensionId;
        }
        if ($search !== '') {
            $sql .= ' AND (number LIKE :search1 OR owner LIKE :search2)';
            $params['search1'] = "%{$search}%";
            $params['search2'] = "%{$search}%";
        }
        $sql .= ' ORDER BY number LIMIT ' . $limit;
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $e) {
            $results[] = [
                'type' => 'extension',
                'id' => (int)$e['id'],
                'label' => 'Номер ' . $e['number'] . ' (' . Labels::extensionTypeLabel($e['type'], 'internal') . ')' . ($e['owner'] ? ' — ' . $e['owner'] : ''),
            ];
        }

        return $results;
    }

    /** Очереди/группы обзвона, в состав которых входит данный номер (для защиты от удаления/смены типа) */
    public static function groupMembershipsFor(int $memberExtensionId): array
    {
        $sql = 'SELECT q.id, q.number, q.type
                FROM extension_group_members egm
                JOIN extensions q ON q.id = egm.extension_id
                WHERE egm.member_extension_id = :id
                ORDER BY q.number';
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute(['id' => $memberExtensionId]);
        return $stmt->fetchAll();
    }

    /** Короткое читаемое резюме состава — для журнала аудита */
    public static function summarizeMembers(array $memberRows): string
    {
        if (empty($memberRows)) {
            return '';
        }
        $parts = array_map(fn($m) => $m['number'] . ($m['owner'] ? " ({$m['owner']})" : ''), $memberRows);
        return implode(', ', $parts);
    }

    /** Короткое читаемое резюме настроек очереди/группы обзвона — для журнала аудита */
    public static function summarizeQueueSettings(?array $settings): string
    {
        if (!$settings) {
            return '';
        }
        $parts = ['метод: ' . Labels::ringStrategyShortLabel($settings['ring_strategy'] ?? null)];
        if (!empty($settings['agent_answer_timeout_sec'])) {
            $parts[] = 'дозвон ' . $settings['agent_answer_timeout_sec'] . ' сек';
        }
        if (!empty($settings['agent_wrapup_time_sec'])) {
            $parts[] = 'отдых ' . $settings['agent_wrapup_time_sec'] . ' сек';
        }
        return implode('; ', $parts);
    }

    /**
     * Есть ли уже номер с таким значением в этой категории (дубли внутренних
     * номеров запрещены — иначе диалплан не сможет однозначно определить,
     * куда направлять вызов). $excludeId — при редактировании, чтобы номер
     * не конфликтовал сам с собой.
     */
    public static function findDuplicate(string $number, string $category, ?int $excludeId = null): ?array
    {
        $sql = 'SELECT id, number, type FROM extensions WHERE number = :number AND category = :category';
        $params = ['number' => $number, 'category' => $category];
        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
            $params['exclude_id'] = $excludeId;
        }
        $sql .= ' LIMIT 1';
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Счётчик для вкладок страницы /extensions. $type === null — не фильтровать по типу. */
    public static function countByCategoryAndType(string $category, ?string $type = null): int
    {
        $sql = 'SELECT COUNT(*) FROM extensions WHERE category = :category';
        $params = ['category' => $category];
        if ($type !== null) {
            $sql .= ' AND type = :type';
            $params['type'] = $type;
        }
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute($params);
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
