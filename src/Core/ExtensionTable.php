<?php

namespace App\Core;

/**
 * Наборы колонок для таблиц номеров — общие для карточки подразделения и
 * выгрузки в Excel/PDF, чтобы колонки не расходились между экраном и файлом.
 *
 * Строки номеров — в формате ExtensionNumber::all()/find() (с джойнами:
 * provider_name, trunk_name, department_name, calltracking_target_name,
 * caller_id_number и т.д.).
 */
final class ExtensionTable
{
    /** Вкладки страницы /extensions */
    public const TAB_LABELS = [
        'external' => 'Внешние',
        'internal' => 'Внутренние',
        'queue'    => 'Очередь',
        'ivr'      => 'Групповые',
        'other'    => 'Служебные',
    ];

    private const LABELS = [
        'number' => 'Номер',
        'type' => 'Тип',
        'provider' => 'Провайдер',
        'trunk' => 'Транк',
        'owner' => 'Владелец',
        'department' => 'Подразделение',
        'division' => 'Отдел',
        'calltracking_goal' => 'Цель',
        'caller_id' => 'АОН',
        'status' => 'Статус',
        'description' => 'Описание',
    ];

    /** Колонки выгрузки вкладки /extensions — как на экране (без «Изменён») */
    public static function columnsForTab(string $tab): array
    {
        return match ($tab) {
            'external' => ['number', 'type', 'provider', 'trunk', 'owner', 'department', 'calltracking_goal', 'status'],
            'internal' => ['number', 'owner', 'department', 'division', 'caller_id', 'status'],
            default => ['number', 'owner', 'department', 'status'],
        };
    }

    /**
     * Колонки группы номеров одного типа на карточке подразделения/отдела.
     * $omit — колонки, которые на этой карточке не нужны (например,
     * «Подразделение» на карточке самого подразделения).
     */
    public static function columnsForGroup(string $category, string $type, array $omit = []): array
    {
        if ($category === 'internal') {
            $columns = $type === 'internal'
                ? ['number', 'owner', 'department', 'division', 'caller_id', 'status', 'description']
                : ['number', 'owner', 'department', 'status', 'description'];
        } else {
            $columns = $type === 'calltracking'
                ? ['number', 'provider', 'trunk', 'calltracking_goal', 'owner', 'department', 'status', 'description']
                : ['number', 'provider', 'trunk', 'owner', 'department', 'status', 'description'];
        }
        return array_values(array_diff($columns, $omit));
    }

    public static function label(string $column): string
    {
        return self::LABELS[$column] ?? $column;
    }

    /** Текстовое значение ячейки ('' для пустых — в таблице выводится «—») */
    public static function value(array $row, string $column): string
    {
        $v = match ($column) {
            'number' => $row['number'] ?? '',
            'type' => Labels::extensionTypeLabel((string)($row['type'] ?? ''), $row['category'] ?? null),
            'provider' => $row['provider_name'] ?? '',
            'trunk' => $row['trunk_name'] ?? '',
            'owner' => $row['owner'] ?? '',
            'department' => $row['department_name'] ?? '',
            'division' => $row['division_name'] ?? '',
            'calltracking_goal' => $row['calltracking_target_name'] ?? '',
            'caller_id' => $row['caller_id_number'] ?? '',
            'status' => Labels::statusLabel((string)($row['status'] ?? '')),
            'description' => $row['description'] ?? '',
            default => '',
        };
        return trim((string)$v);
    }

    /**
     * Секция для выгрузки: заголовок (или null), подписи колонок и строки-значения.
     *
     * @return array{title: ?string, headers: string[], rows: string[][]}
     */
    public static function section(?string $title, array $columns, array $rows): array
    {
        return [
            'title' => $title,
            'headers' => array_map([self::class, 'label'], $columns),
            'rows' => array_map(
                fn($r) => array_map(fn($c) => self::value($r, $c), $columns),
                $rows
            ),
        ];
    }

    /**
     * Разбивает номера на группы по категории и типу, сохраняя порядок строк.
     *
     * @return array<int, array{category: string, type: string, label: string, rows: array}>
     */
    public static function groupByType(array $rows): array
    {
        $groups = [];
        foreach ($rows as $r) {
            $key = $r['category'] . '/' . $r['type'];
            if (!isset($groups[$key])) {
                $categoryLabel = $r['category'] === 'external' ? 'Внешние' : 'Внутренние';
                $groups[$key] = [
                    'category' => $r['category'],
                    'type' => $r['type'],
                    'label' => $categoryLabel . ': ' . Labels::extensionTypeLabel($r['type'], $r['category']),
                    'rows' => [],
                ];
            }
            $groups[$key]['rows'][] = $r;
        }
        return array_values($groups);
    }
}
