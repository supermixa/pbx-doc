<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Export\XlsxWriter;
use App\Core\Labels;
use App\Core\View;
use App\Models\AuditLog;

/**
 * Раздел «История» (/history): весь журнал изменений с отборами по периоду
 * (дата и время), типу объекта, действию, пользователю, объекту и тексту
 * изменений; постраничный вывод и выгрузка в Excel. Доступен всем ролям;
 * изменения пользователей (раздел только для администратора) видит только
 * администратор.
 */
class HistoryController
{
    private const PER_PAGE = 50;
    private const EXPORT_LIMIT = 10000;
    private const ACTIONS = ['create', 'update', 'delete'];

    public function index(): void
    {
        if (!Auth::requireLogin()) {
            return;
        }
        $f = $this->filters($_GET);
        $total = AuditLog::searchCount($f['query']);
        $pages = max(1, (int)ceil($total / self::PER_PAGE));
        $page = min(max(1, (int)($_GET['page'] ?? 1)), $pages);
        $entries = AuditLog::search($f['query'], self::PER_PAGE, ($page - 1) * self::PER_PAGE);

        View::render('history/index', [
            'title' => 'История изменений',
            'entries' => $entries,
            'names' => AuditLog::entityNames($entries),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'f' => $f['form'],
            'entityTypes' => array_values(array_diff(AuditLog::entityTypes(), $f['query']['hide_types'])),
            'authors' => AuditLog::authors(),
            'actions' => self::ACTIONS,
            'queryString' => $f['queryString'],
        ]);
    }

    /** Выгрузка отобранной истории в Excel: одна строка — одно изменённое поле */
    public function export(): void
    {
        if (!Auth::requireLogin()) {
            return;
        }
        $f = $this->filters($_GET);
        $entries = AuditLog::search($f['query'], self::EXPORT_LIMIT);
        $names = AuditLog::entityNames($entries);

        $rows = [];
        foreach ($entries as $e) {
            $et = $e['entity_type'];
            $name = $names[$et][(int)$e['entity_id']]['name'] ?? '';
            $base = [
                date('d.m.Y H:i:s', strtotime($e['created_at'])),
                $e['user_name'] ?? '—',
                Labels::action($e['action']),
                Labels::entity($et),
                $name !== '' ? $name : '#' . $e['entity_id'],
            ];
            $changes = self::visibleChanges($e);
            if (!$changes) {
                $rows[] = array_merge($base, ['', '', '']);
            }
            foreach ($changes as $field => $vals) {
                $rows[] = array_merge($base, [
                    Labels::field($field),
                    Labels::formatFieldValue($et, $field, $vals['old'] ?? null),
                    Labels::formatFieldValue($et, $field, $vals['new'] ?? null),
                ]);
            }
        }

        $subtitle = 'Записей журнала: ' . count($entries)
            . (count($entries) >= self::EXPORT_LIMIT ? ' (выгружены последние ' . self::EXPORT_LIMIT . ')' : '')
            . ($f['description'] !== '' ? ' · ' . $f['description'] : '');
        $content = XlsxWriter::build('История изменений', $subtitle, [[
            'title' => null,
            'headers' => ['Дата и время', 'Пользователь', 'Действие', 'Тип объекта', 'Объект', 'Поле', 'Было', 'Стало'],
            'rows' => $rows,
        ]], 'История');

        $date = date('Y-m-d');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="pbxdoc-history-' . $date . '.xlsx"; filename*=UTF-8\'\''
            . rawurlencode('История изменений ' . $date . '.xlsx'));
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: private, no-store');
        echo $content;
    }

    /**
     * Изменённые поля записи журнала, которые есть смысл показывать: при
     * создании/удалении пустые поля («— → —») пропускаются.
     */
    public static function visibleChanges(array $entry): array
    {
        $changes = json_decode($entry['changes'] ?? '[]', true) ?: [];
        $out = [];
        foreach ($changes as $field => $vals) {
            if (in_array($field, ['created_by', 'updated_by', 'created_at', 'updated_at'], true)) {
                continue;
            }
            $old = $vals['old'] ?? null;
            $new = $vals['new'] ?? null;
            if (($old === null || $old === '') && ($new === null || $new === '')) {
                continue;
            }
            $out[$field] = ['old' => $old, 'new' => $new];
        }
        return $out;
    }

    /**
     * Отборы из GET. Период: period = today | yesterday | 7d | 30d | all,
     * либо from / to (дата и время, формат поля datetime-local).
     *
     * @return array{query: array, form: array, queryString: string, description: string}
     */
    private function filters(array $get): array
    {
        $form = [
            'period' => (string)($get['period'] ?? ''),
            'from' => trim((string)($get['from'] ?? '')),
            'to' => trim((string)($get['to'] ?? '')),
            'type' => trim((string)($get['type'] ?? '')),
            'action' => trim((string)($get['action'] ?? '')),
            'user' => (int)($get['user'] ?? 0),
            'entity_id' => (int)($get['entity_id'] ?? 0),
            'q' => trim((string)($get['q'] ?? '')),
        ];

        $today = date('Y-m-d');
        switch ($form['period']) {
            case 'today':
                $form['from'] = $today . 'T00:00';
                $form['to'] = '';
                break;
            case 'yesterday':
                $y = date('Y-m-d', strtotime('-1 day'));
                $form['from'] = $y . 'T00:00';
                $form['to'] = $y . 'T23:59';
                break;
            case '7d':
                $form['from'] = date('Y-m-d', strtotime('-6 days')) . 'T00:00';
                $form['to'] = '';
                break;
            case '30d':
                $form['from'] = date('Y-m-d', strtotime('-29 days')) . 'T00:00';
                $form['to'] = '';
                break;
            case 'all':
                $form['from'] = '';
                $form['to'] = '';
                break;
            default:
                $form['period'] = '';
        }

        $from = self::parseDateTime($form['from'], false);
        $to = self::parseDateTime($form['to'], true);
        if ($from === null) {
            $form['from'] = '';
        }
        if ($to === null) {
            $form['to'] = '';
        }
        if (!in_array($form['action'], self::ACTIONS, true)) {
            $form['action'] = '';
        }
        $hide = Auth::isAdmin() ? [] : ['user'];
        if (in_array($form['type'], $hide, true)) {
            $form['type'] = '';
        }
        if ($form['type'] === '') {
            $form['entity_id'] = 0;
        }

        $query = [
            'from' => $from,
            'to' => $to,
            'entity_type' => $form['type'],
            'action' => $form['action'],
            'user_id' => $form['user'],
            'entity_id' => $form['entity_id'],
            'q' => $form['q'],
            'hide_types' => $hide,
        ];

        // Параметры для ссылок пагинации и выгрузки
        $qs = array_filter([
            'period' => $form['period'],
            'from' => $form['period'] === '' ? $form['from'] : '',
            'to' => $form['period'] === '' ? $form['to'] : '',
            'type' => $form['type'],
            'entity_id' => $form['entity_id'] ?: '',
            'action' => $form['action'],
            'user' => $form['user'] ?: '',
            'q' => $form['q'],
        ], fn($v) => $v !== '' && $v !== null);

        $desc = [];
        if ($from || $to) {
            $desc[] = 'период: ' . ($from ? 'с ' . date('d.m.Y H:i', strtotime($from)) : '')
                . ($to ? ' по ' . date('d.m.Y H:i', strtotime($to)) : '');
        }
        if ($form['type'] !== '') {
            $desc[] = 'объекты: ' . Labels::entity($form['type']);
        }
        if ($form['action'] !== '') {
            $desc[] = 'действие: ' . Labels::action($form['action']);
        }
        if ($form['q'] !== '') {
            $desc[] = 'текст: «' . $form['q'] . '»';
        }

        return [
            'query' => $query,
            'form' => $form,
            'queryString' => http_build_query($qs),
            'description' => implode(' · ', $desc),
        ];
    }

    /** '2026-11-14T09:30' / '2026-11-14' -> '2026-11-14 09:30:00' (для «по» — до конца минуты/дня) */
    private static function parseDateTime(string $value, bool $endOf): ?string
    {
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $value .= $endOf ? 'T23:59' : 'T00:00';
        }
        $dt = \DateTime::createFromFormat('Y-m-d\TH:i', substr($value, 0, 16));
        if (!$dt) {
            return null;
        }
        return $dt->format('Y-m-d H:i') . ($endOf ? ':59' : ':00');
    }
}
