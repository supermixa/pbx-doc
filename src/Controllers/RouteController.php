<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\AuditLogger;
use App\Core\Csrf;
use App\Core\Db;
use App\Core\Flash;
use App\Core\Labels;
use App\Core\Validator;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\ExtensionNumber;
use App\Models\RouteEntity;
use App\Models\Scenario;
use App\Models\Trunk;
use Throwable;

class RouteController
{
    public function index(): void
    {
        if (!Auth::requireLogin()) {
            return;
        }
        $search = trim($_GET['q'] ?? '');
        View::render('routes/index', [
            'title' => 'Маршруты',
            'routes' => RouteEntity::all($search),
            'search' => $search,
        ]);
    }

    public function show(array $params): void
    {
        if (!Auth::requireLogin()) {
            return;
        }
        $route = RouteEntity::find((int)$params['id']);
        if (!$route) {
            http_response_code(404);
            View::render('errors/404', []);
            return;
        }
        View::render('routes/show', [
            'title' => $route['name'],
            'route' => $route,
            'routeMembers' => $route['selection_type'] === 'internal_numbers' ? RouteEntity::routeMembers((int)$route['id']) : [],
            'departmentMembers' => (!empty($route['department_id']) && $route['selection_type'] === 'departments')
                ? ExtensionNumber::departmentInternalNumbers((int)$route['department_id'])
                : [],
            'excludedIds' => RouteEntity::excludedIds((int)$route['id']),
            'aonNumbers' => self::numbersWithRouteCallerId((int)$route['id']),
            'history' => AuditLog::forEntity('route', (int)$route['id']),
        ]);
    }

    public function create(): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        View::render('routes/form', array_merge([
            'title' => 'Новый маршрут',
            'route' => null,
            'errors' => [],
        ], $this->formOptions()));
    }

    public function store(): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        Csrf::verifyRequestOrFail();

        [$data, $validator, $memberIds] = $this->validate($_POST);
        if ($validator->fails()) {
            $this->renderForm('Новый маршрут', $_POST, $validator->errors(), null, $memberIds);
            return;
        }

        // АОН маршрута ↔ АОН внутренних номеров: конфликты с другими маршрутами
        $plan = $this->callerIdPlan(null, $data, $memberIds, []);
        if ($warnings = $this->unconfirmedWarnings($plan)) {
            $this->renderForm('Новый маршрут', $_POST, [], null, $memberIds, $warnings);
            return;
        }

        $data['created_by'] = Auth::id();

        $conn = Db::connection();
        $conn->beginTransaction();
        try {
            $id = RouteEntity::create($data);
            RouteEntity::replaceRouteMembers($id, $memberIds);
            RouteEntity::syncExclusions($id, []);
            $detached = $this->detachConflicts($plan);
            $callerIdResult = $this->applyCallerIdToInternalNumbers($id, $data);
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollBack();
            throw $e;
        }

        $auditData = $data;
        if (!empty($memberIds)) {
            $auditData['members'] = ExtensionNumber::summarizeMembers(RouteEntity::routeMembers($id));
        }
        AuditLogger::logCreate('route', $id, $auditData);

        Flash::success('Маршрут создан');
        $this->reportDetached($detached);
        $this->reportCallerIdResult($callerIdResult);
        header('Location: /routes/' . $id);
    }

    public function edit(array $params): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        $route = RouteEntity::find((int)$params['id']);
        if (!$route) {
            http_response_code(404);
            View::render('errors/404', []);
            return;
        }
        View::render('routes/form', array_merge([
            'title' => 'Редактирование маршрута',
            'route' => $route,
            'errors' => [],
        ], $this->formOptions((int)$route['id'])));
    }

    public function update(array $params): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        Csrf::verifyRequestOrFail();

        $id = (int)$params['id'];
        $old = RouteEntity::findRaw($id);
        if (!$old) {
            http_response_code(404);
            View::render('errors/404', []);
            return;
        }

        $oldMembersSummary = ExtensionNumber::summarizeMembers(RouteEntity::routeMembers($id));
        $oldExclusionsSummary = RouteEntity::summarizeExclusions($id);
        $restoreIds = $this->postedIds('restore_exclusion');

        [$data, $validator, $memberIds] = $this->validate($_POST, $id);
        if ($validator->fails()) {
            $this->renderForm('Редактирование маршрута', array_merge($old, $_POST, ['id' => $id]), $validator->errors(), $id, $memberIds, [], $restoreIds);
            return;
        }

        // АОН маршрута ↔ АОН внутренних номеров: конфликты с другими маршрутами
        // и номера, которые перестают получать АОН от этого маршрута
        $plan = $this->callerIdPlan($id, $data, $memberIds, $restoreIds);
        if ($warnings = $this->unconfirmedWarnings($plan)) {
            $this->renderForm('Редактирование маршрута', array_merge($old, $_POST, ['id' => $id]), [], $id, $memberIds, $warnings, $restoreIds);
            return;
        }

        $data['updated_by'] = Auth::id();

        $conn = Db::connection();
        $conn->beginTransaction();
        try {
            RouteEntity::update($id, $data);
            RouteEntity::replaceRouteMembers($id, $memberIds);
            RouteEntity::syncExclusions($id, $restoreIds);
            $detached = $this->detachConflicts($plan);
            $cleared = ($_POST['lost_aon_action'] ?? '') === 'clear' ? $this->clearCallerId($plan['lost']) : [];
            $callerIdResult = $this->applyCallerIdToInternalNumbers($id, $data);
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollBack();
            throw $e;
        }

        $oldForDiff = $old;
        $oldForDiff['members'] = $oldMembersSummary;
        $oldForDiff['excluded'] = $oldExclusionsSummary;

        $newForDiff = $data;
        $newForDiff['members'] = ExtensionNumber::summarizeMembers(RouteEntity::routeMembers($id));
        $newForDiff['excluded'] = RouteEntity::summarizeExclusions($id);

        AuditLogger::logUpdate('route', $id, $oldForDiff, $newForDiff);

        Flash::success('Изменения сохранены');
        $this->reportDetached($detached);
        $this->reportLost($plan['lost'], $cleared);
        $this->reportCallerIdResult($callerIdResult);
        header('Location: /routes/' . $id);
    }

    public function destroy(array $params): void
    {
        if (!Auth::requireAdmin()) {
            return;
        }
        Csrf::verifyRequestOrFail();

        $id = (int)$params['id'];
        $old = RouteEntity::findRaw($id);
        if ($old) {
            // Номера, получившие АОН от этого маршрута: по выбору пользователя
            // АОН у них очищается или остаётся
            $lost = self::numbersWithRouteCallerId($id);
            $cleared = ($_POST['lost_aon_action'] ?? 'keep') === 'clear' ? $this->clearCallerId($lost) : [];
            $old['members'] = ExtensionNumber::summarizeMembers(RouteEntity::routeMembers($id));
            AuditLogger::logDelete('route', $id, $old);
            RouteEntity::delete($id);
            Flash::success('Маршрут удалён');
            $this->reportLost($lost, $cleared);
        }
        header('Location: /routes');
    }

    // ------------------------------------------------------------------
    // АОН маршрута ↔ АОН внутренних номеров
    // ------------------------------------------------------------------

    /**
     * Внутренние номера маршрута, у которых сейчас стоит АОН этого маршрута
     * (то есть полученный от него). Только для маршрутов, реально задающих
     * АОН (АОН — «Городской»/«Сотовый»).
     */
    public static function numbersWithRouteCallerId(int $routeId): array
    {
        $route = RouteEntity::findRaw($routeId);
        $aonId = (int)($route['outbound_caller_id_id'] ?? 0);
        if (!$route || $aonId <= 0) {
            return [];
        }
        $aon = ExtensionNumber::findRaw($aonId);
        if (!$aon || !in_array($aon['type'], ExtensionNumber::CALLER_ID_TYPES, true)) {
            return [];
        }
        return array_values(array_filter(
            RouteEntity::coveredInternalNumbers($routeId),
            fn($e) => (int)($e['caller_id_extension_id'] ?? 0) === $aonId
        ));
    }

    /**
     * Убирает номер из маршрута: из состава (тип «Внутренние номера») или
     * добавляет в исключения (тип «Подразделения»). Пишет журнал маршрута.
     * Возвращает название маршрута (или null, если маршрута нет).
     */
    public static function detachNumberFromRoute(int $routeId, int $extensionId): ?string
    {
        $route = RouteEntity::findRaw($routeId);
        if (!$route) {
            return null;
        }
        if ($route['selection_type'] === 'internal_numbers') {
            $before = ExtensionNumber::summarizeMembers(RouteEntity::routeMembers($routeId));
            RouteEntity::removeMember($routeId, $extensionId);
            AuditLogger::logUpdate('route', $routeId, ['members' => $before],
                ['members' => ExtensionNumber::summarizeMembers(RouteEntity::routeMembers($routeId))]);
        } elseif ($route['selection_type'] === 'departments') {
            $before = RouteEntity::summarizeExclusions($routeId);
            RouteEntity::addExclusion($routeId, $extensionId);
            AuditLogger::logUpdate('route', $routeId, ['excluded' => $before],
                ['excluded' => RouteEntity::summarizeExclusions($routeId)]);
        }
        return $route['name'];
    }

    /**
     * Что произойдёт с АОН внутренних номеров при сохранении маршрута:
     *   conflicts — номера маршрута, которые входят в ДРУГОЙ маршрут с другим
     *               АОН (при сохранении их нужно убрать из того маршрута);
     *   lost      — номера, у которых стоит АОН этого маршрута, но после
     *               сохранения маршрут перестанет его задавать (АОН убран
     *               или номер исключён из маршрута) — очистить АОН или оставить.
     */
    private function callerIdPlan(?int $routeId, array $data, array $memberIds, array $restoreIds): array
    {
        $plan = ['newAon' => null, 'conflicts' => [], 'lost' => []];
        $newCovered = [];
        $covers = $data['direction'] === 'outbound'
            && in_array($data['selection_type'], ['internal_numbers', 'departments'], true);
        if ($covers) {
            if ($data['selection_type'] === 'internal_numbers') {
                $newCovered = array_map('intval', $memberIds);
            } elseif (!empty($data['department_id'])) {
                $excluded = [];
                if ($routeId) {
                    $old = RouteEntity::findRaw($routeId);
                    if ($old && $old['selection_type'] === 'departments' && (int)$old['department_id'] === (int)$data['department_id']) {
                        $excluded = array_diff(RouteEntity::excludedIds($routeId), $restoreIds);
                    }
                }
                foreach (ExtensionNumber::departmentInternalNumbers((int)$data['department_id']) as $e) {
                    if (!in_array((int)$e['id'], $excluded, true)) {
                        $newCovered[] = (int)$e['id'];
                    }
                }
            }
            $aon = !empty($data['outbound_caller_id_id']) ? ExtensionNumber::findRaw((int)$data['outbound_caller_id_id']) : null;
            if ($aon && in_array($aon['type'], ExtensionNumber::CALLER_ID_TYPES, true)) {
                $plan['newAon'] = (int)$aon['id'];
            }
        }

        if ($plan['newAon']) {
            foreach ($newCovered as $extId) {
                $ext = ExtensionNumber::findRaw($extId);
                if (!$ext) {
                    continue;
                }
                $deptId = $ext['department_id'] !== null ? (int)$ext['department_id'] : null;
                foreach (RouteEntity::callerIdRoutesFor($extId, $deptId) as $r) {
                    if ((int)$r['id'] !== (int)$routeId && (int)$r['outbound_caller_id_id'] !== $plan['newAon']) {
                        $plan['conflicts'][] = ['extension_id' => $extId, 'number' => $ext['number'], 'route' => $r];
                    }
                }
            }
        }

        if ($routeId) {
            foreach (self::numbersWithRouteCallerId($routeId) as $e) {
                if ($plan['newAon'] === null || !in_array((int)$e['id'], $newCovered, true)) {
                    $plan['lost'][] = $e;
                }
            }
        }
        return $plan;
    }

    /** Предупреждения, которые пользователь ещё не подтвердил в форме */
    private function unconfirmedWarnings(array $plan): array
    {
        $warnings = [];
        if ($plan['conflicts'] && empty($_POST['confirm_route_conflicts'])) {
            $warnings['conflicts'] = $plan['conflicts'];
        }
        if ($plan['lost'] && !in_array($_POST['lost_aon_action'] ?? '', ['clear', 'keep'], true)) {
            $warnings['lost'] = $plan['lost'];
        }
        return $warnings;
    }

    /** Убирает номера из других маршрутов (подтверждённые конфликты) */
    private function detachConflicts(array $plan): array
    {
        $detached = [];
        foreach ($plan['conflicts'] as $c) {
            $name = self::detachNumberFromRoute((int)$c['route']['id'], (int)$c['extension_id']);
            if ($name !== null) {
                $detached[] = ['number' => $c['number'], 'route' => $name];
            }
        }
        return $detached;
    }

    /** Очищает АОН у номеров (с журналом изменений). Возвращает номера, у которых очищен. */
    private function clearCallerId(array $numbers): array
    {
        $stmt = Db::connection()->prepare('UPDATE extensions SET caller_id_extension_id = NULL, updated_by = :user WHERE id = :id');
        $cleared = [];
        foreach ($numbers as $e) {
            $stmt->execute(['user' => Auth::id(), 'id' => (int)$e['id']]);
            AuditLogger::logUpdate('extension', (int)$e['id'],
                ExtensionController::forAudit(['caller_id_extension_id' => $e['caller_id_extension_id']]),
                ['caller_id_extension_id' => null]);
            $cleared[] = $e['number'];
        }
        return $cleared;
    }

    private function reportDetached(array $detached): void
    {
        if ($detached) {
            $items = array_map(fn($d) => $d['number'] . ' — из «' . $d['route'] . '»', $detached);
            Flash::warning('Номера убраны из других исходящих маршрутов (там был другой АОН): ' . implode('; ', $items) . '.');
        }
    }

    private function reportLost(array $lost, array $cleared): void
    {
        if (!$lost) {
            return;
        }
        $numbers = implode(', ', array_column($lost, 'number'));
        if ($cleared) {
            Flash::warning('АОН очищен у внутренних номеров, которые больше не получают его от маршрута: ' . $numbers . '.');
        } else {
            Flash::warning('У внутренних номеров ' . $numbers . ' оставлен прежний АОН, хотя маршрут его больше не задаёт.');
        }
    }

    /** ID из POST-массива (restore_exclusion[] и т.п.) */
    private function postedIds(string $key): array
    {
        $raw = $_POST[$key] ?? [];
        return array_values(array_filter(array_map('intval', is_array($raw) ? $raw : []), fn($v) => $v > 0));
    }

    /**
     * Форма маршрута при ошибке/предупреждении: состав и отмеченные
     * «вернуть в маршрут» берутся из присланной формы, а не из базы.
     */
    private function renderForm(string $title, array $route, array $errors, ?int $routeId, array $memberIds,
                                array $warnings = [], array $restoreIds = []): void
    {
        $members = [];
        foreach ($memberIds as $mid) {
            $m = ExtensionNumber::findRaw((int)$mid);
            if ($m) {
                $members[] = $m;
            }
        }
        View::render('routes/form', array_merge($this->formOptions($routeId), [
            'title' => $title,
            'route' => $route,
            'errors' => $errors,
            'routeMembers' => $members,
            'routeWarnings' => $warnings,
            'restoreIds' => $restoreIds,
        ]));
    }

    /**
     * JSON-эндпоинт: внутренние номера (type=internal) заданного подразделения —
     * для живого отображения состава при выборе "Подразделения" в форме маршрута.
     */
    public function departmentMembers(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (!Auth::check()) {
            http_response_code(403);
            echo json_encode([]);
            return;
        }

        $departmentId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
        if ($departmentId <= 0) {
            echo json_encode([]);
            return;
        }

        $rows = ExtensionNumber::departmentInternalNumbers($departmentId);
        // Номера, исключённые из редактируемого маршрута (route_id) — помечаются
        $routeId = isset($_GET['route_id']) ? (int)$_GET['route_id'] : 0;
        $route = $routeId > 0 ? RouteEntity::findRaw($routeId) : null;
        $excluded = ($route && $route['selection_type'] === 'departments' && (int)$route['department_id'] === $departmentId)
            ? RouteEntity::excludedIds($routeId) : [];
        $results = array_map(fn($r) => [
            'id' => (int)$r['id'],
            'label' => $r['number'] . ($r['owner'] ? ' — ' . $r['owner'] : ''),
            'excluded' => in_array((int)$r['id'], $excluded, true),
        ], $rows);
        echo json_encode($results);
    }

    /**
     * Справочники, нужные форме создания/редактирования маршрута.
     * Состав внутренних номеров подразделения (для selection_type=departments)
     * в форму не передаётся — он только для чтения и подгружается через AJAX
     * (departmentMembers()), чтобы корректно отражать текущий выбор в select'е
     * подразделения даже после неудачной валидации.
     */
    /**
     * АОН исходящего маршрута присваивается полю «АОН» внутренних номеров
     * маршрута (состав для «Внутренние номера», номера подразделения для
     * «Подразделения»). Вызывается внутри транзакции сохранения маршрута;
     * журнал изменений номеров пишется в reportCallerIdResult() после commit.
     *
     * @return array{aon: ?array, changed: array, unchanged: int, skippedReason: ?string}
     */
    private function applyCallerIdToInternalNumbers(int $routeId, array $data): array
    {
        $result = ['aon' => null, 'changed' => [], 'unchanged' => 0, 'skippedReason' => null];
        $aonId = (int)($data['outbound_caller_id_id'] ?? 0);
        if ($data['direction'] !== 'outbound' || $aonId <= 0
            || !in_array($data['selection_type'], ['internal_numbers', 'departments'], true)) {
            return $result;
        }
        $aon = ExtensionNumber::findRaw($aonId);
        $result['aon'] = $aon;
        $covered = RouteEntity::coveredInternalNumbers($routeId);
        if (!$covered) {
            return $result;
        }
        if (!$aon || !in_array($aon['type'], ExtensionNumber::CALLER_ID_TYPES, true)) {
            // Поле «АОН» внутреннего номера допускает только «Городской»/«Сотовый»
            $result['skippedReason'] = 'АОН маршрута не присвоен внутренним номерам: у внутреннего номера АОН может быть только внешним номером с типом «Городской» или «Сотовый».';
            return $result;
        }
        $stmt = Db::connection()->prepare(
            'UPDATE extensions SET caller_id_extension_id = :aon, updated_by = :user WHERE id = :id'
        );
        foreach ($covered as $ext) {
            if ((int)($ext['caller_id_extension_id'] ?? 0) === $aonId) {
                $result['unchanged']++;
                continue;
            }
            $stmt->execute(['aon' => $aonId, 'user' => Auth::id(), 'id' => (int)$ext['id']]);
            $result['changed'][] = [
                'id' => (int)$ext['id'],
                'number' => $ext['number'],
                'old' => $ext['caller_id_extension_id'] !== null ? (int)$ext['caller_id_extension_id'] : null,
            ];
        }
        return $result;
    }

    private function reportCallerIdResult(array $result): void
    {
        foreach ($result['changed'] as $c) {
            AuditLogger::logUpdate('extension', $c['id'],
                ExtensionController::forAudit(['caller_id_extension_id' => $c['old']]),
                ExtensionController::forAudit(['caller_id_extension_id' => $result['aon']['id']]));
        }
        if ($result['skippedReason']) {
            Flash::warning($result['skippedReason']);
            return;
        }
        if ($result['changed']) {
            $numbers = array_column($result['changed'], 'number');
            $list = implode(', ', array_slice($numbers, 0, 20)) . (count($numbers) > 20 ? ' и ещё ' . (count($numbers) - 20) : '');
            Flash::success('АОН ' . $result['aon']['number'] . ' присвоен внутренним номерам маршрута (' . count($numbers) . '): ' . $list . '.');
        } elseif ($result['unchanged'] > 0) {
            Flash::success('У всех внутренних номеров маршрута уже стоит АОН ' . $result['aon']['number'] . '.');
        }
    }

    private function formOptions(?int $routeId = null): array
    {
        return [
            'scenarios' => Scenario::options(),
            'departments' => Department::options(),
            'trunks' => Trunk::options(),
            'externalNumbers' => ExtensionNumber::externalNumberOptions(),
            'routeMembers' => $routeId ? RouteEntity::routeMembers($routeId) : [],
        ];
    }

    /**
     * @return array{0: array, 1: Validator, 2: array}
     *   [данные маршрута, валидатор, ID внутренних номеров состава (только для selection_type=internal_numbers)]
     */
    private function validate(array $input, ?int $currentId = null): array
    {
        $v = new Validator($input);
        $v->required('name', 'Название')
            ->maxLength('name', 150, 'Название')
            ->required('direction', 'Направление')
            ->in('direction', RouteEntity::DIRECTIONS, 'Направление')
            ->maxLength('context', 100, 'Контекст');

        $direction = $v->get('direction', 'outbound') ?: 'outbound';

        $scenarioId = $input['scenario_id'] ?? '';
        $scenarioId = ($scenarioId === '' || $scenarioId === null) ? null : (int)$scenarioId;

        $selectionType = trim((string)($input['selection_type'] ?? ''));

        $departmentId = $input['department_id'] ?? '';
        $departmentId = ($departmentId === '' || $departmentId === null) ? null : (int)$departmentId;

        $trunkId = $input['trunk_id'] ?? '';
        $trunkId = ($trunkId === '' || $trunkId === null) ? null : (int)$trunkId;

        $outboundCallerIdId = $input['outbound_caller_id_id'] ?? '';
        $outboundCallerIdId = ($outboundCallerIdId === '' || $outboundCallerIdId === null) ? null : (int)$outboundCallerIdId;

        $pattern = $v->get('pattern');

        $memberIds = [];

        if ($direction === 'inbound') {
            // Входящий: значим только Сценарий и Шаблон номера звонящего.
            $selectionType = null;
            $departmentId = null;
            $trunkId = null;
            $outboundCallerIdId = null;

            if ($scenarioId !== null && !Scenario::find($scenarioId)) {
                $v->addError('scenario_id', 'Выбранный сценарий не найден');
                $scenarioId = null;
            }
        } else {
            // Исходящий: значимость остальных полей зависит от Типа отбора.
            $scenarioId = null;

            if (!in_array($selectionType, RouteEntity::SELECTION_TYPES, true)) {
                $v->addError('selection_type', 'Выберите тип отбора');
            }

            if ($selectionType === 'internal_numbers') {
                $pattern = null;
                $departmentId = null;
                $memberIds = $this->parseMemberIds($input, $currentId, $v);
            } elseif ($selectionType === 'departments') {
                $pattern = null;
                if ($departmentId === null) {
                    $v->addError('department_id', 'Выберите подразделение');
                } elseif (!Department::find($departmentId)) {
                    $v->addError('department_id', 'Выбранное подразделение не найдено');
                    $departmentId = null;
                }
            } elseif ($selectionType === 'dialed_number') {
                $departmentId = null;
            } elseif ($selectionType === 'complex') {
                // Для сложного отбора пока ничего не настраивается.
                $pattern = null;
                $departmentId = null;
                $trunkId = null;
                $outboundCallerIdId = null;
            } else {
                $departmentId = null;
                $trunkId = null;
                $outboundCallerIdId = null;
                $pattern = null;
            }

            if ($selectionType !== 'complex' && in_array($selectionType, RouteEntity::SELECTION_TYPES, true)) {
                if ($trunkId !== null && !Trunk::find($trunkId)) {
                    $v->addError('trunk_id', 'Выбранный транк не найден');
                    $trunkId = null;
                }
                if ($outboundCallerIdId !== null) {
                    $aon = ExtensionNumber::findRaw($outboundCallerIdId);
                    if (!$aon || $aon['category'] !== 'external') {
                        $v->addError('outbound_caller_id_id', 'Выбранный исходящий АОН не найден');
                        $outboundCallerIdId = null;
                    }
                }
            }
        }

        $v->maxLength('pattern', 190, $direction === 'inbound' ? 'Шаблон номера звонящего' : 'Шаблон набираемого номера');

        $data = [
            'name' => $v->get('name'),
            'direction' => $direction,
            'scenario_id' => $scenarioId,
            'pattern' => $pattern,
            'selection_type' => $selectionType !== '' ? $selectionType : null,
            'department_id' => $departmentId,
            'trunk_id' => $trunkId,
            'outbound_caller_id_id' => $outboundCallerIdId,
            'context' => $v->get('context'),
            'description' => $v->get('description'),
        ];

        return [$data, $v, $memberIds];
    }

    /**
     * Состав внутренних номеров для selection_type=internal_numbers — та же
     * логика, что и в ExtensionController::validate() для состава очереди/группы:
     * допустимы только внутренние номера (category=internal, type=internal).
     */
    private function parseMemberIds(array $input, ?int $currentId, Validator $v): array
    {
        $rawIds = $input['member_extension_id'] ?? [];
        if (!is_array($rawIds)) {
            $rawIds = [];
        }
        $memberIds = [];
        $seen = [];
        foreach ($rawIds as $rawId) {
            $memberId = (int)$rawId;
            if ($memberId <= 0 || isset($seen[$memberId])) {
                continue;
            }
            $seen[$memberId] = true;
            $member = ExtensionNumber::findRaw($memberId);
            if (!$member || $member['category'] !== 'internal' || $member['type'] !== 'internal') {
                $v->addError('member_extension_id', 'В составе может быть только внутренний номер (тип «Внутренний»)');
                continue;
            }
            $memberIds[] = $memberId;
        }
        return $memberIds;
    }
}
