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
            View::render('routes/form', array_merge([
                'title' => 'Новый маршрут',
                'route' => $_POST,
                'errors' => $validator->errors(),
            ], $this->formOptions()));
            return;
        }

        $data['created_by'] = Auth::id();

        $conn = Db::connection();
        $conn->beginTransaction();
        try {
            $id = RouteEntity::create($data);
            RouteEntity::replaceRouteMembers($id, $memberIds);
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

        [$data, $validator, $memberIds] = $this->validate($_POST, $id);
        if ($validator->fails()) {
            View::render('routes/form', array_merge([
                'title' => 'Редактирование маршрута',
                'route' => array_merge($old, $_POST, ['id' => $id]),
                'errors' => $validator->errors(),
            ], $this->formOptions($id)));
            return;
        }

        $data['updated_by'] = Auth::id();

        $conn = Db::connection();
        $conn->beginTransaction();
        try {
            RouteEntity::update($id, $data);
            RouteEntity::replaceRouteMembers($id, $memberIds);
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollBack();
            throw $e;
        }

        $oldForDiff = $old;
        $oldForDiff['members'] = $oldMembersSummary;

        $newForDiff = $data;
        $newForDiff['members'] = ExtensionNumber::summarizeMembers(RouteEntity::routeMembers($id));

        AuditLogger::logUpdate('route', $id, $oldForDiff, $newForDiff);

        Flash::success('Изменения сохранены');
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
            $old['members'] = ExtensionNumber::summarizeMembers(RouteEntity::routeMembers($id));
            AuditLogger::logDelete('route', $id, $old);
            RouteEntity::delete($id);
            Flash::success('Маршрут удалён');
        }
        header('Location: /routes');
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
        $results = array_map(fn($r) => [
            'id' => (int)$r['id'],
            'label' => $r['number'] . ($r['owner'] ? ' — ' . $r['owner'] : ''),
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
