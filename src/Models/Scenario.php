<?php

namespace App\Models;

use App\Core\Db;

/**
 * Модель "Сценарий". Логика сценария — визуальный конструктор блоков
 * (flow_json: упорядоченный JSON-массив блоков прохождения звонка).
 * Поля context/dialplan_code оставлены в БД для совместимости со старыми
 * записями, но больше не редактируются и не показываются в интерфейсе.
 */
class Scenario
{
    /** Типы блоков сценария, в порядке появления в меню "Добавить блок" */
    public const BLOCK_TYPES = [
        'play_file',
        'ai_agent',
        'queue_transfer',
        'number_transfer',
        'script',
        'notify_tg',
        'notify_email',
        'hangup',
        'external_transfer',
        'business_hours',
    ];

    /** Типы целей "При неудачи" / ссылки на блок оповещения */
    public const FAILOVER_KINDS = ['extension', 'block', 'hangup'];

    private static array $fillable = ['name', 'description', 'flow_json'];

    public static function all(string $search = ''): array
    {
        $sql = 'SELECT s.*, cu.name AS created_by_name, uu.name AS updated_by_name
                FROM scenarios s
                LEFT JOIN users cu ON cu.id = s.created_by
                LEFT JOIN users uu ON uu.id = s.updated_by';
        $params = [];
        if ($search !== '') {
            $sql .= ' WHERE s.name LIKE :search';
            $params['search'] = "%{$search}%";
        }
        $sql .= ' ORDER BY s.name';
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $sql = 'SELECT s.*, cu.name AS created_by_name, uu.name AS updated_by_name
                FROM scenarios s
                LEFT JOIN users cu ON cu.id = s.created_by
                LEFT JOIN users uu ON uu.id = s.updated_by
                WHERE s.id = :id';
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findRaw(int $id): ?array
    {
        $stmt = Db::connection()->prepare('SELECT * FROM scenarios WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $fields = self::$fillable;
        $columns = implode(', ', $fields);
        $placeholders = ':' . implode(', :', $fields);
        $sql = "INSERT INTO scenarios ({$columns}, created_by, updated_by) VALUES ({$placeholders}, :created_by, :updated_by)";

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
        $sql = "UPDATE scenarios SET {$set}, updated_by = :updated_by WHERE id = :id";

        $params = ['id' => $id, 'updated_by' => $data['updated_by'] ?? null];
        foreach ($fields as $f) {
            $params[$f] = $data[$f] ?? null;
        }

        Db::connection()->prepare($sql)->execute($params);
    }

    public static function delete(int $id): void
    {
        Db::connection()->prepare('DELETE FROM scenarios WHERE id = :id')->execute(['id' => $id]);
    }

    public static function count(): int
    {
        return (int)Db::connection()->query('SELECT COUNT(*) FROM scenarios')->fetchColumn();
    }

    public static function options(): array
    {
        $stmt = Db::connection()->query('SELECT id, name FROM scenarios ORDER BY name');
        return $stmt->fetchAll();
    }

    /** Декодирует flow_json в массив блоков (пустой массив, если пусто/битый JSON) */
    public static function decodeFlow(?string $json): array
    {
        if (!$json) {
            return [];
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Готовит блоки сценария к графическому отображению на карточке
     * сценария: подписи типов и человекочитаемые значения полей (имена
     * музыки/номеров/маршрутов вместо голых ID), включая цель "При
     * неудачи"/"Оповещение" — либо ссылку на другой блок этого же сценария,
     * либо на внешнюю сущность (номер), либо "Положить трубку".
     *
     * @return array<int, array{id: string, type: string, typeLabel: string, fields: array<int, array{label: string, value: string}>}>
     */
    public static function describeFlow(array $blocks): array
    {
        // Индекс блоков по id — нужен, чтобы описать ссылку "на блок оповещения".
        $byId = [];
        foreach ($blocks as $b) {
            if (!empty($b['id'])) {
                $byId[(string)$b['id']] = $b;
            }
        }

        $result = [];
        foreach ($blocks as $block) {
            $result[] = [
                'id' => (string)($block['id'] ?? ''),
                'type' => (string)($block['type'] ?? ''),
                'typeLabel' => \App\Core\Labels::scenarioBlockTypeLabel((string)($block['type'] ?? '')),
                'fields' => self::describeBlockFields($block, $byId),
            ];
        }
        return $result;
    }

    /** @return array<int, array{label: string, value: string}> */
    private static function describeBlockFields(array $block, array $byId): array
    {
        $type = (string)($block['type'] ?? '');
        $f = $block['fields'] ?? [];
        $out = [];

        switch ($type) {
            case 'play_file':
                $out[] = ['label' => 'Файл', 'value' => self::describeMusic($f['music_id'] ?? null)];
                break;
            case 'ai_agent':
                $out[] = ['label' => 'Сервер', 'value' => (string)($f['server'] ?? '') ?: '—'];
                $out[] = ['label' => 'Запрос', 'value' => (string)($f['query'] ?? '') ?: '—'];
                $out[] = ['label' => 'Описание', 'value' => (string)($f['description'] ?? '') ?: '—'];
                break;
            case 'queue_transfer':
                $out[] = ['label' => 'Номер очереди', 'value' => self::describeExtension($f['queue_id'] ?? null)];
                $out[] = ['label' => 'Время ожидания', 'value' => !empty($f['wait_sec']) ? $f['wait_sec'] . ' сек' : '—'];
                $out[] = ['label' => 'При неудаче', 'value' => self::describeFailover($f['on_failure'] ?? null, $byId)];
                break;
            case 'number_transfer':
                $out[] = ['label' => 'Внутренний номер', 'value' => self::describeExtension($f['internal_id'] ?? null)];
                $out[] = ['label' => 'Время ожидания', 'value' => !empty($f['wait_sec']) ? $f['wait_sec'] . ' сек' : '—'];
                $out[] = ['label' => 'При неудаче', 'value' => self::describeFailover($f['on_failure'] ?? null, $byId)];
                break;
            case 'script':
                $out[] = ['label' => 'Название', 'value' => (string)($f['name'] ?? '') ?: '—'];
                $out[] = ['label' => 'Скрипт', 'value' => (string)($f['script'] ?? '') ?: '—'];
                break;
            case 'notify_tg':
                $out[] = ['label' => 'Id группы', 'value' => (string)($f['group_id'] ?? '') ?: '—'];
                $out[] = ['label' => 'Пример текста', 'value' => (string)($f['example_text'] ?? '') ?: '—'];
                break;
            case 'notify_email':
                $out[] = ['label' => 'E-mail получателя', 'value' => (string)($f['email'] ?? '') ?: '—'];
                $out[] = ['label' => 'Почта отправителя', 'value' => (string)($f['from_email'] ?? '') ?: '—'];
                $out[] = ['label' => 'Пример текста', 'value' => (string)($f['example_text'] ?? '') ?: '—'];
                break;
            case 'hangup':
                break;
            case 'external_transfer':
                $out[] = ['label' => 'Номер', 'value' => (string)($f['number'] ?? '') ?: '—'];
                $out[] = ['label' => 'Маршрут на выход', 'value' => self::describeRoute($f['route_id'] ?? null)];
                break;
            case 'business_hours':
                $from = (string)($f['from_time'] ?? '');
                $to = (string)($f['to_time'] ?? '');
                $out[] = ['label' => 'Время работы', 'value' => ($from || $to) ? ($from ?: '—') . '–' . ($to ?: '—') : '—'];
                $days = is_array($f['days'] ?? null) ? $f['days'] : [];
                $out[] = ['label' => 'Дни недели', 'value' => $days ? \App\Core\Labels::weekdaysShortList($days) : '—'];
                break;
        }

        return $out;
    }

    private static function describeMusic($id): string
    {
        $id = (int)($id ?? 0);
        if ($id <= 0) {
            return '—';
        }
        $m = MusicFile::findRaw($id);
        return $m ? $m['title'] : '— файл удалён —';
    }

    private static function describeExtension($id): string
    {
        $id = (int)($id ?? 0);
        if ($id <= 0) {
            return '—';
        }
        $e = ExtensionNumber::findRaw($id);
        if (!$e) {
            return '— номер удалён —';
        }
        return $e['number'] . ($e['owner'] ? ' — ' . $e['owner'] : '');
    }

    private static function describeRoute($id): string
    {
        $id = (int)($id ?? 0);
        if ($id <= 0) {
            return '—';
        }
        $r = RouteEntity::findRaw($id);
        return $r ? $r['name'] : '— маршрут удалён —';
    }

    private static function describeFailover(?array $onFailure, array $byId): string
    {
        if (!$onFailure || empty($onFailure['kind'])) {
            return '—';
        }
        $kind = $onFailure['kind'];
        if ($kind === 'hangup') {
            return 'Положить трубку';
        }
        if ($kind === 'extension') {
            return self::describeExtension($onFailure['target_id'] ?? null);
        }
        if ($kind === 'block') {
            $refId = (string)($onFailure['block_ref'] ?? '');
            if ($refId === '' || !isset($byId[$refId])) {
                return '— блок удалён —';
            }
            $ref = $byId[$refId];
            $refType = (string)($ref['type'] ?? '');
            $label = \App\Core\Labels::scenarioBlockTypeLabel($refType);
            if ($refType === 'notify_tg') {
                $label .= ' (' . ((string)($ref['fields']['group_id'] ?? '') ?: '—') . ')';
            } elseif ($refType === 'notify_email') {
                $label .= ' (' . ((string)($ref['fields']['email'] ?? '') ?: '—') . ')';
            }
            return $label;
        }
        return '—';
    }
}
