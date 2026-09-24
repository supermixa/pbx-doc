<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\AuditLogger;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Validator;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\ExtensionNumber;
use App\Models\MusicFile;
use App\Models\RouteEntity;
use App\Models\Scenario;

class ScenarioController
{
    public function index(): void
    {
        if (!Auth::requireLogin()) {
            return;
        }
        $search = trim($_GET['q'] ?? '');
        View::render('scenarios/index', [
            'title' => 'Сценарии',
            'scenarios' => Scenario::all($search),
            'search' => $search,
        ]);
    }

    public function show(array $params): void
    {
        if (!Auth::requireLogin()) {
            return;
        }
        $scenario = Scenario::find((int)$params['id']);
        if (!$scenario) {
            http_response_code(404);
            View::render('errors/404', []);
            return;
        }
        $blocks = Scenario::decodeFlow($scenario['flow_json'] ?? null);
        View::render('scenarios/show', [
            'title' => $scenario['name'],
            'scenario' => $scenario,
            'flow' => Scenario::describeFlow($blocks),
            'history' => AuditLog::forEntity('scenario', (int)$scenario['id']),
        ]);
    }

    public function create(): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        View::render('scenarios/form', array_merge([
            'title' => 'Новый сценарий',
            'scenario' => null,
            'errors' => [],
        ], $this->formOptions()));
    }

    public function store(): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        Csrf::verifyRequestOrFail();

        [$data, $validator] = $this->validate($_POST);
        if ($validator->fails()) {
            View::render('scenarios/form', array_merge([
                'title' => 'Новый сценарий',
                'scenario' => $_POST,
                'errors' => $validator->errors(),
            ], $this->formOptions()));
            return;
        }

        $data['created_by'] = Auth::id();
        $id = Scenario::create($data);
        AuditLogger::logCreate('scenario', $id, $data);

        Flash::success('Сценарий создан');
        header('Location: /scenarios/' . $id);
    }

    public function edit(array $params): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        $scenario = Scenario::find((int)$params['id']);
        if (!$scenario) {
            http_response_code(404);
            View::render('errors/404', []);
            return;
        }
        View::render('scenarios/form', array_merge([
            'title' => 'Редактирование сценария',
            'scenario' => $scenario,
            'errors' => [],
        ], $this->formOptions()));
    }

    public function update(array $params): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        Csrf::verifyRequestOrFail();

        $id = (int)$params['id'];
        $old = Scenario::findRaw($id);
        if (!$old) {
            http_response_code(404);
            View::render('errors/404', []);
            return;
        }

        [$data, $validator] = $this->validate($_POST);
        if ($validator->fails()) {
            View::render('scenarios/form', array_merge([
                'title' => 'Редактирование сценария',
                'scenario' => array_merge($old, $_POST, ['id' => $id]),
                'errors' => $validator->errors(),
            ], $this->formOptions()));
            return;
        }

        $data['updated_by'] = Auth::id();
        Scenario::update($id, $data);
        AuditLogger::logUpdate('scenario', $id, $old, $data);

        Flash::success('Изменения сохранены');
        header('Location: /scenarios/' . $id);
    }

    public function destroy(array $params): void
    {
        if (!Auth::requireAdmin()) {
            return;
        }
        Csrf::verifyRequestOrFail();

        $id = (int)$params['id'];
        $old = Scenario::findRaw($id);
        if ($old) {
            AuditLogger::logDelete('scenario', $id, $old);
            Scenario::delete($id);
            Flash::success('Сценарий удалён');
        }
        header('Location: /scenarios');
    }

    /**
     * Быстрая загрузка своего файла музыки прямо из блока "Проиграть файл"
     * (тот же эндпоинт /music/quick-upload, что и в форме номера — см.
     * MusicController::quickUpload()); отдельный маршрут здесь не нужен.
     */

    /** Справочники, нужные конструктору блоков сценария */
    private function formOptions(): array
    {
        return [
            'musicFiles' => MusicFile::options(),
            'queueOptions' => ExtensionNumber::optionsByType('queue'),
            'internalOptions' => ExtensionNumber::optionsByType('internal'),
            'failoverTargets' => ExtensionNumber::scenarioFailureTargetCandidates(),
            'routeOptions' => RouteEntity::outboundOptions(),
        ];
    }

    /**
     * @return array{0: array, 1: Validator}
     */
    private function validate(array $input): array
    {
        $v = new Validator($input);
        $v->required('name', 'Название')->maxLength('name', 150, 'Название');

        $rawFlow = $input['flow_json'] ?? '[]';
        $decoded = json_decode(is_string($rawFlow) ? $rawFlow : '[]', true);
        if (!is_array($decoded)) {
            $decoded = [];
        }

        $sanitized = [];
        $seenIds = [];
        foreach ($decoded as $b) {
            if (!is_array($b)) {
                continue;
            }
            $type = (string)($b['type'] ?? '');
            if (!in_array($type, Scenario::BLOCK_TYPES, true)) {
                // Неизвестный/повреждённый тип блока — пропускаем, не роняем сохранение всего сценария.
                continue;
            }
            $id = (string)($b['id'] ?? '');
            if ($id === '' || isset($seenIds[$id])) {
                $id = 'b' . (count($sanitized) + 1) . substr(md5(uniqid((string)mt_rand(), true)), 0, 6);
            }
            $seenIds[$id] = true;
            $fields = is_array($b['fields'] ?? null) ? $b['fields'] : [];
            $sanitized[] = ['id' => $id, 'type' => $type, 'fields' => $this->sanitizeBlockFields($type, $fields)];
        }

        // Ссылки "При неудаче -> блок оповещения" валидны только если реально
        // указывают на блок notify_tg/notify_email, оставшийся в сценарии.
        $validRefIds = [];
        foreach ($sanitized as $b) {
            if (in_array($b['type'], ['notify_tg', 'notify_email'], true)) {
                $validRefIds[$b['id']] = true;
            }
        }
        foreach ($sanitized as &$b) {
            if (($b['fields']['on_failure']['kind'] ?? '') === 'block') {
                $ref = (string)($b['fields']['on_failure']['block_ref'] ?? '');
                if ($ref === '' || !isset($validRefIds[$ref])) {
                    $b['fields']['on_failure'] = ['kind' => '', 'target_id' => null, 'block_ref' => null];
                }
            }
        }
        unset($b);

        $data = [
            'name' => $v->get('name'),
            'description' => $v->get('description'),
            'flow_json' => json_encode($sanitized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        return [$data, $v];
    }

    private function sanitizeBlockFields(string $type, array $f): array
    {
        switch ($type) {
            case 'play_file':
                return ['music_id' => $this->nullableId($f['music_id'] ?? null, fn($id) => MusicFile::findRaw($id) !== null)];

            case 'ai_agent':
                return [
                    'server' => $this->str($f['server'] ?? ''),
                    'query' => $this->str($f['query'] ?? '', 2000),
                    'description' => $this->str($f['description'] ?? '', 2000),
                ];

            case 'queue_transfer':
                return [
                    'queue_id' => $this->nullableId($f['queue_id'] ?? null, function ($id) {
                        $e = ExtensionNumber::findRaw($id);
                        return $e && $e['category'] === 'internal' && $e['type'] === 'queue';
                    }),
                    'wait_sec' => $this->nullableIntRange($f['wait_sec'] ?? null, 0, 3600),
                    'on_failure' => $this->sanitizeFailover($f['on_failure'] ?? null),
                ];

            case 'number_transfer':
                return [
                    'internal_id' => $this->nullableId($f['internal_id'] ?? null, function ($id) {
                        $e = ExtensionNumber::findRaw($id);
                        return $e && $e['category'] === 'internal' && $e['type'] === 'internal';
                    }),
                    'wait_sec' => $this->nullableIntRange($f['wait_sec'] ?? null, 0, 3600),
                    'on_failure' => $this->sanitizeFailover($f['on_failure'] ?? null),
                ];

            case 'script':
                return [
                    'name' => $this->str($f['name'] ?? ''),
                    'script' => $this->str($f['script'] ?? '', 20000),
                ];

            case 'notify_tg':
                return [
                    'group_id' => $this->str($f['group_id'] ?? ''),
                    'example_text' => $this->str($f['example_text'] ?? '', 2000),
                ];

            case 'notify_email':
                return [
                    'email' => $this->str($f['email'] ?? ''),
                    'from_email' => $this->str($f['from_email'] ?? ''),
                    'example_text' => $this->str($f['example_text'] ?? '', 2000),
                ];

            case 'hangup':
                return [];

            case 'external_transfer':
                return [
                    'number' => $this->str($f['number'] ?? '', 50),
                    'route_id' => $this->nullableId($f['route_id'] ?? null, function ($id) {
                        $r = RouteEntity::findRaw($id);
                        return $r && $r['direction'] === 'outbound';
                    }),
                ];

            case 'business_hours':
                $days = is_array($f['days'] ?? null) ? $f['days'] : [];
                $days = array_values(array_intersect($days, ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun']));
                return [
                    'from_time' => $this->timeOrEmpty($f['from_time'] ?? ''),
                    'to_time' => $this->timeOrEmpty($f['to_time'] ?? ''),
                    'days' => $days,
                ];
        }
        return [];
    }

    private function sanitizeFailover(?array $raw): array
    {
        $kind = is_array($raw) ? (string)($raw['kind'] ?? '') : '';
        if (!in_array($kind, Scenario::FAILOVER_KINDS, true)) {
            return ['kind' => '', 'target_id' => null, 'block_ref' => null];
        }
        if ($kind === 'extension') {
            $id = (int)($raw['target_id'] ?? 0);
            $e = $id > 0 ? ExtensionNumber::findRaw($id) : null;
            $valid = $e && $e['category'] === 'internal' && in_array($e['type'], ['queue', 'ivr', 'internal'], true);
            return ['kind' => $valid ? 'extension' : '', 'target_id' => $valid ? $id : null, 'block_ref' => null];
        }
        if ($kind === 'block') {
            $ref = (string)($raw['block_ref'] ?? '');
            return ['kind' => $ref !== '' ? 'block' : '', 'target_id' => null, 'block_ref' => $ref !== '' ? $ref : null];
        }
        return ['kind' => 'hangup', 'target_id' => null, 'block_ref' => null];
    }

    private function str($value, int $max = 190): string
    {
        $value = trim((string)($value ?? ''));
        return mb_substr($value, 0, $max);
    }

    private function nullableId($value, callable $exists): ?int
    {
        $id = (int)($value ?? 0);
        if ($id <= 0) {
            return null;
        }
        return $exists($id) ? $id : null;
    }

    private function nullableIntRange($value, int $min, int $max): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $n = (int)$value;
        if ($n < $min || $n > $max) {
            return null;
        }
        return $n;
    }

    private function timeOrEmpty($value): string
    {
        $value = trim((string)($value ?? ''));
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) ? $value : '';
    }
}
