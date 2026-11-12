<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\AuditLogger;
use App\Core\Csrf;
use App\Core\Db;
use App\Core\Flash;
use App\Core\Import\ExtensionCsvImport;
use App\Core\View;
use App\Models\ExtensionNumber;
use Throwable;

/**
 * Импорт внутренних номеров (тип «Внутренний») из CSV:
 *   GET  /extensions/import           — страница загрузки + описание колонок
 *   GET  /extensions/import/template  — файл-шаблон CSV
 *   POST /extensions/import           — разбор файла и экран проверки (дубли, ошибки)
 *   POST /extensions/import/confirm   — выполнение импорта с выбранными действиями
 * Между проверкой и подтверждением разобранные строки хранятся в сессии.
 */
class ExtensionImportController
{
    private const SESSION_KEY = 'extension_import';

    public function form(): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        View::render('extensions/import', [
            'title' => 'Импорт внутренних номеров',
            'error' => null,
        ]);
    }

    public function template(): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        $content = ExtensionCsvImport::templateCsv();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="pbxdoc-import-template.csv"; filename*=UTF-8\'\''
            . rawurlencode('Шаблон импорта внутренних номеров.csv'));
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: private, no-store');
        echo $content;
    }

    public function upload(): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        Csrf::verifyRequestOrFail();

        $file = $_FILES['csv'] ?? null;
        $error = null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $error = 'Выберите CSV-файл.';
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            $error = 'Файл не загрузился (код ошибки ' . (int)$file['error'] . ').';
        } elseif ($file['size'] > ExtensionCsvImport::MAX_FILE_BYTES) {
            $error = 'Файл больше 2 МБ — разбейте его на несколько файлов.';
        } elseif (!in_array(strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION)), ['csv', 'txt'], true)) {
            $error = 'Нужен файл в формате CSV (.csv). В Excel: «Файл → Сохранить как → CSV (разделители — точка с запятой)».';
        }
        if ($error) {
            View::render('extensions/import', ['title' => 'Импорт внутренних номеров', 'error' => $error]);
            return;
        }

        $parsed = ExtensionCsvImport::parse((string)file_get_contents($file['tmp_name']));
        if ($parsed['fatal'] !== null) {
            View::render('extensions/import', ['title' => 'Импорт внутренних номеров', 'error' => $parsed['fatal']]);
            return;
        }
        if (!$parsed['rows']) {
            View::render('extensions/import', ['title' => 'Импорт внутренних номеров', 'error' => 'В файле нет строк с номерами (строки с «#» в начале номера пропускаются).']);
            return;
        }

        // Для экрана проверки: текущие данные дублей и что изменится в каждом режиме
        $existingRows = [];
        foreach ($parsed['rows'] as &$row) {
            if ($row['status'] === 'duplicate') {
                $existing = ExtensionNumber::findRaw($row['existing_id']);
                $existingRows[$row['existing_id']] = $existing;
                $row['changes'] = [
                    ExtensionCsvImport::MODE_FILL => ExtensionCsvImport::changes($existing, $row, ExtensionCsvImport::MODE_FILL),
                    ExtensionCsvImport::MODE_REPLACE => ExtensionCsvImport::changes($existing, $row, ExtensionCsvImport::MODE_REPLACE),
                ];
            }
        }
        unset($row);

        $token = bin2hex(random_bytes(16));
        $_SESSION[self::SESSION_KEY] = [
            'token' => $token,
            'file' => (string)$file['name'],
            // в сессию — только то, что можно импортировать
            'rows' => array_values(array_filter($parsed['rows'], fn($r) => $r['status'] !== 'error')),
        ];

        View::render('extensions/import_preview', [
            'title' => 'Импорт: проверка файла',
            'token' => $token,
            'fileName' => (string)$file['name'],
            'parsed' => $parsed,
            'existingRows' => $existingRows,
        ]);
    }

    public function confirm(): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        Csrf::verifyRequestOrFail();

        $stored = $_SESSION[self::SESSION_KEY] ?? null;
        if (!$stored || !hash_equals($stored['token'], (string)($_POST['token'] ?? ''))) {
            Flash::error('Данные проверки устарели — загрузите файл ещё раз.');
            header('Location: /extensions/import');
            return;
        }
        unset($_SESSION[self::SESSION_KEY]); // повторная отправка формы не импортирует дважды

        $actions = is_array($_POST['action'] ?? null) ? $_POST['action'] : [];
        $results = [];
        $counts = ['created' => 0, 'filled' => 0, 'replaced' => 0, 'unchanged' => 0, 'skipped' => 0];
        $userId = Auth::id();
        $audit = [];

        $conn = Db::connection();
        $conn->beginTransaction();
        try {
            foreach ($stored['rows'] as $row) {
                $wasDuplicate = $row['status'] === 'duplicate';
                $row['errors'] = [];
                ExtensionCsvImport::classify($row); // база могла измениться после проверки

                if ($row['status'] === 'error') {
                    $results[] = $this->result($row, 'skipped', implode('; ', $row['errors']));
                    $counts['skipped']++;
                    continue;
                }
                if ($row['status'] === 'new') {
                    if ($wasDuplicate) {
                        $results[] = $this->result($row, 'skipped', 'номер был удалён после проверки файла');
                        $counts['skipped']++;
                        continue;
                    }
                    $data = ExtensionCsvImport::newExtensionData($row);
                    $data['created_by'] = $userId;
                    $id = ExtensionNumber::create($data);
                    $audit[] = ['create', $id, $data];
                    $results[] = $this->result($row, 'created', 'создан', $id);
                    $counts['created']++;
                    continue;
                }

                // duplicate
                if (!$wasDuplicate) {
                    $results[] = $this->result($row, 'skipped', 'номер появился в базе после проверки файла — загрузите файл ещё раз', $row['existing_id']);
                    $counts['skipped']++;
                    continue;
                }
                $mode = (string)($actions[$row['line']] ?? ExtensionCsvImport::MODE_FILL);
                if (!in_array($mode, [ExtensionCsvImport::MODE_FILL, ExtensionCsvImport::MODE_REPLACE], true)) {
                    $results[] = $this->result($row, 'skipped', 'пропущен', $row['existing_id']);
                    $counts['skipped']++;
                    continue;
                }
                $old = ExtensionNumber::findRaw($row['existing_id']);
                $changes = ExtensionCsvImport::changes($old, $row, $mode);
                if (!$changes) {
                    $results[] = $this->result($row, 'unchanged', 'изменений нет', $row['existing_id']);
                    $counts['unchanged']++;
                    continue;
                }
                $new = array_merge($old, $changes, ['updated_by' => $userId]);
                ExtensionNumber::update($row['existing_id'], $new);
                $audit[] = ['update', $row['existing_id'], $new, $old];
                $label = $mode === ExtensionCsvImport::MODE_FILL ? 'filled' : 'replaced';
                $results[] = $this->result($row, $label,
                    ($mode === ExtensionCsvImport::MODE_FILL ? 'дополнен: ' : 'заменены данные: ')
                    . implode(', ', array_map([ExtensionCsvImport::class, 'fieldLabel'], array_keys($changes))),
                    $row['existing_id']);
                $counts[$label]++;
            }
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollBack();
            Flash::error('Импорт прерван, ни одна запись не сохранена: ' . $e->getMessage());
            header('Location: /extensions/import');
            return;
        }

        foreach ($audit as $entry) {
            if ($entry[0] === 'create') {
                AuditLogger::logCreate('extension', $entry[1], ExtensionController::forAudit($entry[2]));
            } else {
                AuditLogger::logUpdate('extension', $entry[1], ExtensionController::forAudit($entry[3]), ExtensionController::forAudit($entry[2]));
            }
        }

        View::render('extensions/import_result', [
            'title' => 'Импорт завершён',
            'fileName' => $stored['file'],
            'results' => $results,
            'counts' => $counts,
        ]);
    }

    private function result(array $row, string $kind, string $message, ?int $id = null): array
    {
        return ['line' => $row['line'], 'number' => $row['number'], 'kind' => $kind, 'message' => $message, 'id' => $id];
    }
}
