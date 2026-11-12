<?php

namespace App\Core\Import;

use App\Core\Db;
use App\Models\Department;
use App\Models\Division;
use App\Models\ExtensionNumber;
use App\Models\Scenario;

/**
 * Импорт внутренних номеров (категория «Внутренний», тип «Внутренний») из CSV.
 *
 * Разбор файла: кодировка UTF-8 (с BOM или без) или Windows-1251 (так
 * сохраняет CSV русский Excel), разделитель «;», «,» или табуляция —
 * определяется автоматически. Колонки ищутся по заголовку (порядок любой,
 * лишние колонки игнорируются), обязательна только «Номер». Строки, где
 * номер начинается с «#», считаются примером/комментарием и пропускаются.
 *
 * Каждая строка получает статус:
 *   new       — такого внутреннего номера нет, будет создан;
 *   duplicate — внутренний номер с типом «Внутренний» уже есть — пользователь
 *               выбирает: дополнить недостающие данные / заменить все / пропустить;
 *   error     — строку импортировать нельзя (причины в errors).
 */
final class ExtensionCsvImport
{
    public const MAX_FILE_BYTES = 2 * 1024 * 1024;
    public const MAX_ROWS = 5000;

    public const MODE_FILL = 'fill';
    public const MODE_REPLACE = 'replace';
    public const MODE_SKIP = 'skip';

    /** Колонки шаблона (поле => заголовок) — в этом порядке */
    public const COLUMNS = [
        'number' => 'Номер',
        'owner' => 'Владелец',
        'department' => 'Подразделение',
        'division' => 'Отдел',
        'caller_id' => 'АОН',
        'scenario' => 'Сценарий',
        'context' => 'Контекст',
        'status' => 'Статус',
        'description' => 'Описание',
    ];

    /** Поле импорта => колонка таблицы extensions */
    public const DB_FIELDS = [
        'owner' => 'owner',
        'department' => 'department_id',
        'division' => 'division_id',
        'caller_id' => 'caller_id_extension_id',
        'scenario' => 'scenario_id',
        'context' => 'context',
        'status' => 'status',
        'description' => 'description',
    ];

    /** Допустимые варианты заголовков (в нижнем регистре, «ё» → «е») */
    private const HEADER_ALIASES = [
        'number' => ['номер', 'внутренний номер', 'number', 'extension'],
        'owner' => ['владелец', 'фио', 'owner'],
        'department' => ['подразделение', 'department'],
        'division' => ['отдел', 'division'],
        'caller_id' => ['аон', 'caller id', 'callerid', 'caller_id'],
        'scenario' => ['сценарий', 'связанный сценарий', 'scenario'],
        'context' => ['контекст', 'контекст диалплана', 'context'],
        'status' => ['статус', 'status'],
        'description' => ['описание', 'комментарий', 'примечание', 'description'],
    ];

    private const STATUS_VALUES = [
        'активен' => 'active', 'активный' => 'active', 'active' => 'active', 'да' => 'active', '1' => 'active', 'вкл' => 'active',
        'отключен' => 'inactive', 'отключён' => 'inactive', 'неактивен' => 'inactive', 'inactive' => 'inactive',
        'нет' => 'inactive', '0' => 'inactive', 'выкл' => 'inactive',
    ];

    /** Содержимое файла-шаблона (UTF-8 с BOM, разделитель «;» — открывается в Excel без настроек) */
    public static function templateCsv(): string
    {
        $rows = [
            array_values(self::COLUMNS),
            ['#101', 'Иванов Иван Иванович', 'Отдел продаж', 'Отдел логистики', '+7 (383) 200-11-22', '',
                'from-internal', 'Активен', 'Пример: строки, где номер начинается с #, не импортируются — удалите или замените её'],
        ];
        $out = "\xEF\xBB\xBF";
        foreach ($rows as $r) {
            $out .= implode(';', array_map(fn($v) => '"' . str_replace('"', '""', $v) . '"', $r)) . "\r\n";
        }
        return $out;
    }

    /**
     * Разбирает и проверяет файл.
     *
     * @return array{fatal: ?string, rows: array, columns: string[], delimiter: string, encoding: string}
     */
    public static function parse(string $content): array
    {
        $result = ['fatal' => null, 'rows' => [], 'columns' => [], 'delimiter' => ';', 'encoding' => 'UTF-8'];

        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        } elseif (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1251');
            $result['encoding'] = 'Windows-1251';
        }
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        if (trim($content) === '') {
            $result['fatal'] = 'Файл пустой.';
            return $result;
        }

        $firstLine = strtok($content, "\n");
        $delimiter = ';';
        $best = -1;
        foreach ([';', ',', "\t"] as $d) {
            $count = substr_count($firstLine, $d);
            if ($count > $best) {
                $best = $count;
                $delimiter = $d;
            }
        }
        $result['delimiter'] = $delimiter;

        $fh = fopen('php://temp', 'r+');
        fwrite($fh, $content);
        rewind($fh);

        $header = fgetcsv($fh, null, $delimiter, '"', '');
        $map = self::mapHeader(is_array($header) ? $header : []);
        if (!isset($map['number'])) {
            fclose($fh);
            $result['fatal'] = 'В первой строке файла не найдена колонка «Номер». Возьмите за основу шаблон — заголовки колонок должны остаться как в нём.';
            return $result;
        }
        $result['columns'] = array_keys($map);

        $refs = self::loadReferences();
        $line = 1;
        $seenNumbers = [];
        while (($cells = fgetcsv($fh, null, $delimiter, '"', '')) !== false) {
            $line++;
            if ($cells === [null] || implode('', array_map('trim', array_map('strval', $cells))) === '') {
                continue; // пустая строка
            }
            $raw = [];
            foreach ($map as $field => $index) {
                $raw[$field] = trim((string)($cells[$index] ?? ''));
            }
            if (str_starts_with($raw['number'], '#')) {
                continue; // пример/комментарий
            }
            if (count($result['rows']) >= self::MAX_ROWS) {
                $result['fatal'] = 'В файле больше ' . self::MAX_ROWS . ' строк — разбейте его на несколько файлов.';
                break;
            }
            $row = self::resolveRow($line, $raw, $refs);

            if ($row['number'] !== '') {
                $key = mb_strtolower($row['number']);
                if (isset($seenNumbers[$key])) {
                    $row['errors'][] = 'Номер уже встречается в файле (строка ' . $seenNumbers[$key] . ')';
                } else {
                    $seenNumbers[$key] = $line;
                }
            }
            self::classify($row);
            $result['rows'][] = $row;
        }
        fclose($fh);
        return $result;
    }

    /** Заголовок -> [поле => индекс колонки] */
    private static function mapHeader(array $header): array
    {
        $map = [];
        foreach ($header as $index => $title) {
            $norm = self::norm(str_replace('*', '', (string)$title));
            foreach (self::HEADER_ALIASES as $field => $aliases) {
                if (!isset($map[$field]) && in_array($norm, $aliases, true)) {
                    $map[$field] = $index;
                    break;
                }
            }
        }
        return $map;
    }

    private static function norm(string $s): string
    {
        $s = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $s) ?? ''));
        return str_replace('ё', 'е', $s);
    }

    /** Справочники для сопоставления названий с ID */
    private static function loadReferences(): array
    {
        $byName = function (array $options): array {
            $out = [];
            foreach ($options as $o) {
                $out[self::norm($o['name'])][] = (int)$o['id'];
            }
            return $out;
        };
        $callerIds = [];
        $stmt = Db::connection()->query("SELECT id, number FROM extensions WHERE category = 'external' AND type IN ('did_city', 'did_mobile')");
        foreach ($stmt->fetchAll() as $r) {
            $callerIds[preg_replace('/\D+/', '', $r['number'])][] = (int)$r['id'];
        }
        return [
            'department' => $byName(Department::options()),
            'division' => $byName(Division::options()),
            'scenario' => $byName(Scenario::options()),
            'caller_id' => $callerIds,
        ];
    }

    private static function resolveRow(int $line, array $raw, array $refs): array
    {
        $row = [
            'line' => $line,
            'number' => $raw['number'],
            'raw' => $raw,
            'present' => array_values(array_diff(array_keys($raw), ['number'])),
            'values' => [],    // колонка БД => значение (только для колонок, которые есть в файле)
            'errors' => [],
            'status' => 'error',
            'existing_id' => null,
        ];

        if ($raw['number'] === '') {
            $row['errors'][] = 'Не указан номер';
        } elseif (mb_strlen($raw['number']) > 50) {
            $row['errors'][] = 'Номер длиннее 50 символов';
        }

        foreach ($row['present'] as $field) {
            $value = $raw[$field];
            $dbField = self::DB_FIELDS[$field];
            switch ($field) {
                case 'owner':
                    if (mb_strlen($value) > 150) {
                        $row['errors'][] = 'Владелец длиннее 150 символов';
                    }
                    $row['values'][$dbField] = $value !== '' ? $value : null;
                    break;
                case 'context':
                    if (mb_strlen($value) > 100) {
                        $row['errors'][] = 'Контекст длиннее 100 символов';
                    }
                    $row['values'][$dbField] = $value !== '' ? $value : null;
                    break;
                case 'description':
                    $row['values'][$dbField] = $value !== '' ? $value : null;
                    break;
                case 'department':
                case 'division':
                case 'scenario':
                    $label = ['department' => 'Подразделение', 'division' => 'Отдел', 'scenario' => 'Сценарий'][$field];
                    $directory = ['department' => '«Подразделения»', 'division' => '«Отделы»', 'scenario' => '«Сценарии»'][$field];
                    if ($value === '') {
                        $row['values'][$dbField] = null;
                        break;
                    }
                    $ids = $refs[$field][self::norm($value)] ?? [];
                    if (count($ids) === 1) {
                        $row['values'][$dbField] = $ids[0];
                    } elseif (count($ids) > 1) {
                        $row['errors'][] = "{$label} «{$value}»: в справочнике {$directory} несколько записей с таким названием";
                    } else {
                        $row['errors'][] = "{$label} «{$value}» не найден(о) в справочнике {$directory}";
                    }
                    break;
                case 'caller_id':
                    if ($value === '') {
                        $row['values'][$dbField] = null;
                        break;
                    }
                    $digits = preg_replace('/\D+/', '', $value);
                    $ids = $digits !== '' ? ($refs['caller_id'][$digits] ?? []) : [];
                    if (count($ids) === 1) {
                        $row['values'][$dbField] = $ids[0];
                    } elseif (count($ids) > 1) {
                        $row['errors'][] = "АОН «{$value}»: найдено несколько внешних номеров с такими цифрами";
                    } else {
                        $row['errors'][] = "АОН «{$value}»: нет внешнего номера с типом «Городской» или «Сотовый»";
                    }
                    break;
                case 'status':
                    if ($value === '') {
                        break; // пусто — у нового номера «Активен», у существующего не меняется
                    }
                    $status = self::STATUS_VALUES[self::norm($value)] ?? null;
                    if ($status === null) {
                        $row['errors'][] = "Статус «{$value}» не распознан (допустимо: Активен / Отключён)";
                    } else {
                        $row['values'][$dbField] = $status;
                    }
                    break;
            }
        }
        return $row;
    }

    /** Определяет статус строки (new / duplicate / error) по текущему состоянию базы */
    public static function classify(array &$row): void
    {
        $row['existing_id'] = null;
        if ($row['errors']) {
            $row['status'] = 'error';
            return;
        }
        $existing = ExtensionNumber::findDuplicate($row['number'], 'internal');
        if (!$existing) {
            $row['status'] = 'new';
            return;
        }
        if ($existing['type'] !== 'internal') {
            $row['status'] = 'error';
            $row['errors'][] = 'Номер уже существует с типом «' . \App\Core\Labels::extensionTypeLabel($existing['type'], 'internal')
                . '» — импорт обновляет только номера с типом «Внутренний»';
            return;
        }
        $row['status'] = 'duplicate';
        $row['existing_id'] = (int)$existing['id'];
    }

    /**
     * Что изменится у существующего номера в режиме fill/replace.
     * fill — заполняются только пустые поля номера; replace — все колонки,
     * которые есть в файле (пустая ячейка очищает поле; пустой «Статус» не меняется).
     *
     * @return array<string, mixed> колонка БД => новое значение
     */
    public static function changes(array $existing, array $row, string $mode): array
    {
        $changes = [];
        foreach ($row['values'] as $dbField => $newValue) {
            $old = $existing[$dbField] ?? null;
            $oldEmpty = $old === null || $old === '';
            if ($mode === self::MODE_FILL) {
                if ($oldEmpty && $newValue !== null && $newValue !== '') {
                    $changes[$dbField] = $newValue;
                }
            } elseif ($mode === self::MODE_REPLACE) {
                if ((string)($old ?? '') !== (string)($newValue ?? '')) {
                    $changes[$dbField] = $newValue;
                }
            }
        }
        return $changes;
    }

    /** Данные нового номера для ExtensionNumber::create() */
    public static function newExtensionData(array $row): array
    {
        $data = [
            'number' => $row['number'],
            'category' => 'internal',
            'type' => 'internal',
            'status' => 'active',
        ];
        foreach ($row['values'] as $dbField => $value) {
            $data[$dbField] = $value;
        }
        return $data;
    }

    /** Человекочитаемое значение поля для экрана проверки */
    public static function display(string $dbField, $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        switch ($dbField) {
            case 'department_id':
                $r = Department::findRaw((int)$value);
                return $r ? $r['name'] : '#' . $value;
            case 'division_id':
                $r = Division::findRaw((int)$value);
                return $r ? $r['name'] : '#' . $value;
            case 'scenario_id':
                $r = Scenario::findRaw((int)$value);
                return $r ? $r['name'] : '#' . $value;
            case 'caller_id_extension_id':
                $r = ExtensionNumber::findRaw((int)$value);
                return $r ? $r['number'] : '#' . $value;
            case 'status':
                return \App\Core\Labels::statusLabel((string)$value);
        }
        return (string)$value;
    }

    public static function fieldLabel(string $dbField): string
    {
        $field = array_search($dbField, self::DB_FIELDS, true);
        return $field !== false ? self::COLUMNS[$field] : $dbField;
    }
}
