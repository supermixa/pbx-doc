<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\AuditLogger;
use App\Core\Csrf;
use App\Core\Db;
use App\Core\Export\Exporter;
use App\Core\Export\MikoPbxCsv;
use App\Core\ExtensionTable;
use App\Core\Flash;
use App\Core\Labels;
use App\Core\Validator;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\CalltrackingTarget;
use App\Models\Department;
use App\Models\Division;
use App\Models\ExtensionNumber;
use App\Models\LegalEntity;
use App\Models\MusicFile;
use App\Models\Provider;
use App\Models\RouteEntity;
use App\Models\Scenario;
use App\Models\Trunk;
use Throwable;

class ExtensionController
{
    /** Маршруты, из которых убрать номер после сохранения (подтверждённая смена АОН) */
    private array $routesToDetach = [];

    /** Вкладка -> [категория, тип (null = любой тип этой категории)] */
    private const INDEX_TABS = [
        'external' => ['external', null],
        'internal' => ['internal', 'internal'],
        'queue'    => ['internal', 'queue'],
        'ivr'      => ['internal', 'ivr'],
        'other'    => ['internal', 'other'],
    ];

    /** Отборы списка по вкладкам (GET-параметры f_<ключ>) */
    private const TAB_FILTERS = [
        'external' => ['type', 'provider', 'trunk', 'department'],
        'internal' => ['department', 'division'],
    ];

    /**
     * Читает отборы вкладки из GET (f_type, f_provider, f_trunk,
     * f_department, f_division). Пусто — без отбора, 'none' — «не указано»,
     * иначе ID справочника (для «Тип» — код типа внешнего номера).
     */
    private static function listFilters(string $tab, array $query): array
    {
        $filters = [];
        foreach (self::TAB_FILTERS[$tab] ?? [] as $key) {
            $value = trim((string)($query['f_' . $key] ?? ''));
            if ($value === '') {
                continue;
            }
            if ($key === 'type') {
                if (in_array($value, ExtensionNumber::EXTERNAL_TYPES, true)) {
                    $filters[$key] = $value;
                }
            } elseif ($value === 'none' || ctype_digit($value)) {
                $filters[$key] = $value === 'none' ? 'none' : (int)$value;
            }
        }
        return $filters;
    }

    /** Варианты для выпадающих списков отборов вкладки */
    private static function filterOptions(string $tab): array
    {
        $options = [];
        foreach (self::TAB_FILTERS[$tab] ?? [] as $key) {
            $options[$key] = match ($key) {
                'type' => array_map(fn($t) => ['id' => $t, 'name' => Labels::extensionTypeLabel($t, 'external')], ExtensionNumber::EXTERNAL_TYPES),
                'provider' => Provider::options(),
                'trunk' => Trunk::options(),
                'department' => Department::options(),
                'division' => Division::options(),
            };
        }
        return $options;
    }

    /** «Тип: Городской (DID) · Провайдер: …» — для подзаголовка выгрузки */
    private static function describeFilters(string $tab, array $filters): string
    {
        $labels = ['type' => 'Тип', 'provider' => 'Провайдер', 'trunk' => 'Транк', 'department' => 'Подразделение', 'division' => 'Отдел'];
        $options = self::filterOptions($tab);
        $parts = [];
        foreach ($filters as $key => $value) {
            $name = 'не указан';
            if ($value !== 'none') {
                foreach ($options[$key] as $o) {
                    if ((string)$o['id'] === (string)$value) {
                        $name = $o['name'];
                    }
                }
            }
            $parts[] = $labels[$key] . ': ' . $name;
        }
        return implode(' · ', $parts);
    }

    public function index(): void
    {
        if (!Auth::requireLogin()) {
            return;
        }
        $search = trim($_GET['q'] ?? '');
        $tab = $_GET['tab'] ?? 'external';
        if (!array_key_exists($tab, self::INDEX_TABS)) {
            $tab = 'external';
        }
        [$category, $type] = self::INDEX_TABS[$tab];

        $counts = [];
        foreach (self::INDEX_TABS as $key => [$c, $t]) {
            $counts[$key] = ExtensionNumber::countByCategoryAndType($c, $t);
        }

        // Поиск начинает фильтровать только от 2 символов (то же правило,
        // что и в живом поиске search-rows) — короче просто показываем весь список.
        $effectiveSearch = mb_strlen($search) >= 2 ? $search : '';

        $filters = self::listFilters($tab, $_GET);

        View::render('extensions/index', [
            'title' => 'Номера',
            'extensions' => ExtensionNumber::all($effectiveSearch, $category, $type ?? '', $filters),
            'filters' => $filters,
            'filterOptions' => self::filterOptions($tab),
            'search' => $search,
            'tab' => $tab,
            'counts' => $counts,
            'isExternalTab' => $tab === 'external',
            'withoutOwnerCount' => $tab === 'internal' ? ExtensionNumber::countInternalWithoutOwner() : 0,
        ]);
    }

    /**
     * AJAX-эндпоинт живого поиска для страницы /extensions: возвращает только
     * строки таблицы (partial), без layout — фильтрация начинается от 2
     * символов, короче — показывается весь список вкладки.
     */
    public function searchRows(): void
    {
        if (!Auth::check()) {
            http_response_code(403);
            return;
        }
        $search = trim($_GET['q'] ?? '');
        $tab = $_GET['tab'] ?? 'external';
        if (!array_key_exists($tab, self::INDEX_TABS)) {
            $tab = 'external';
        }
        [$category, $type] = self::INDEX_TABS[$tab];
        $effectiveSearch = mb_strlen($search) >= 2 ? $search : '';

        View::render('extensions/_rows', [
            'extensions' => ExtensionNumber::all($effectiveSearch, $category, $type ?? '', self::listFilters($tab, $_GET)),
            'isExternalTab' => $tab === 'external',
            'isInternalTab' => $tab === 'internal',
        ], null);
    }

    public function show(array $params): void
    {
        if (!Auth::requireLogin()) {
            return;
        }
        $extension = ExtensionNumber::find((int)$params['id']);
        if (!$extension) {
            http_response_code(404);
            View::render('errors/404', []);
            return;
        }
        View::render('extensions/show', [
            'title' => $extension['number'],
            'extension' => $extension,
            'groupMembers' => ExtensionNumber::groupMembers((int)$extension['id']),
            'queueSettings' => ExtensionNumber::queueSettings((int)$extension['id']),
            'memberships' => ExtensionNumber::groupMembershipsFor((int)$extension['id']),
            'callerIdUsers' => ExtensionNumber::callerIdUsers((int)$extension['id']),
            'history' => AuditLog::forEntity('extension', (int)$extension['id']),
        ]);
    }

    public function create(): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        View::render('extensions/form', array_merge([
            'title' => 'Новый номер',
            'extension' => null,
            'errors' => [],
        ], $this->formOptions()));
    }

    public function store(): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        Csrf::verifyRequestOrFail();

        [$data, $validator, $memberIds, $queueSettingsData] = $this->validate($_POST);
        if ($validator->fails()) {
            View::render('extensions/form', array_merge([
                'title' => 'Новый номер',
                'extension' => $_POST,
                'errors' => $validator->errors(),
            ], $this->formOptions()));
            return;
        }

        $data['created_by'] = Auth::id();

        $conn = Db::connection();
        $conn->beginTransaction();
        try {
            $id = ExtensionNumber::create($data);
            ExtensionNumber::replaceGroupMembers($id, $memberIds);
            ExtensionNumber::replaceQueueSettings($id, $queueSettingsData);
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollBack();
            throw $e;
        }

        $auditData = $data;
        if ($queueSettingsData !== null) {
            $auditData['members'] = ExtensionNumber::summarizeMembers(ExtensionNumber::groupMembers($id));
            $auditData['queue_settings'] = ExtensionNumber::summarizeQueueSettings(ExtensionNumber::queueSettings($id));
        }
        AuditLogger::logCreate('extension', $id, self::forAudit($auditData));

        Flash::success('Номер создан');
        $this->detachFromRoutes($id, $data['number']);
        header('Location: /extensions/' . $id);
    }

    public function edit(array $params): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        $id = (int)$params['id'];
        $extension = ExtensionNumber::find($id);
        if (!$extension) {
            http_response_code(404);
            View::render('errors/404', []);
            return;
        }
        View::render('extensions/form', array_merge([
            'title' => 'Редактирование номера',
            'extension' => $extension,
            'errors' => [],
        ], $this->formOptions($id)));
    }

    public function update(array $params): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        Csrf::verifyRequestOrFail();

        $id = (int)$params['id'];
        $old = ExtensionNumber::findRaw($id);
        if (!$old) {
            http_response_code(404);
            View::render('errors/404', []);
            return;
        }

        $oldMembersSummary = ExtensionNumber::summarizeMembers(ExtensionNumber::groupMembers($id));
        $oldQueueSettingsSummary = ExtensionNumber::summarizeQueueSettings(ExtensionNumber::queueSettings($id));

        [$data, $validator, $memberIds, $queueSettingsData] = $this->validate($_POST, $id);
        if ($validator->fails()) {
            View::render('extensions/form', array_merge([
                'title' => 'Редактирование номера',
                'extension' => array_merge($old, $_POST, ['id' => $id]),
                'errors' => $validator->errors(),
            ], $this->formOptions($id)));
            return;
        }

        $data['updated_by'] = Auth::id();

        $conn = Db::connection();
        $conn->beginTransaction();
        try {
            ExtensionNumber::update($id, $data);
            ExtensionNumber::replaceGroupMembers($id, $memberIds);
            ExtensionNumber::replaceQueueSettings($id, $queueSettingsData);
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollBack();
            throw $e;
        }

        $oldForDiff = $old;
        $oldForDiff['members'] = $oldMembersSummary;
        $oldForDiff['queue_settings'] = $oldQueueSettingsSummary;

        $newForDiff = $data;
        $newForDiff['members'] = ExtensionNumber::summarizeMembers(ExtensionNumber::groupMembers($id));
        $newForDiff['queue_settings'] = ExtensionNumber::summarizeQueueSettings(ExtensionNumber::queueSettings($id));

        AuditLogger::logUpdate('extension', $id, self::forAudit($oldForDiff), self::forAudit($newForDiff));

        Flash::success('Изменения сохранены');
        $this->detachFromRoutes($id, $data['number']);
        header('Location: /extensions/' . $id);
    }

    public function destroy(array $params): void
    {
        if (!Auth::requireAdmin()) {
            return;
        }
        Csrf::verifyRequestOrFail();

        $id = (int)$params['id'];
        $old = ExtensionNumber::findRaw($id);
        if ($old) {
            $memberships = ExtensionNumber::groupMembershipsFor($id);
            if (!empty($memberships)) {
                $names = implode(', ', array_map(fn($m) => $m['number'], $memberships));
                Flash::error("Нельзя удалить номер «{$old['number']}» — он входит в состав очереди/группы обзвона: {$names}. Сначала уберите его из состава.");
                header('Location: /extensions/' . $id);
                return;
            }
            $old['members'] = ExtensionNumber::summarizeMembers(ExtensionNumber::groupMembers($id));
            $old['queue_settings'] = ExtensionNumber::summarizeQueueSettings(ExtensionNumber::queueSettings($id));
            AuditLogger::logDelete('extension', $id, self::forAudit($old));
            ExtensionNumber::delete($id);
            Flash::success('Номер удалён');
        }
        header('Location: /extensions');
    }

    /**
     * JSON-эндпоинт живой проверки дубля номера прямо при вводе в форме
     * (окончательная проверка всё равно происходит на сервере в validate()
     * при сохранении — это только подсказка в UI).
     */
    public function checkNumber(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (!Auth::check()) {
            http_response_code(403);
            echo json_encode(['exists' => false]);
            return;
        }

        $number = trim($_GET['number'] ?? '');
        $category = trim($_GET['category'] ?? 'internal');
        $excludeId = isset($_GET['exclude']) && $_GET['exclude'] !== '' ? (int)$_GET['exclude'] : null;

        if ($number === '' || !in_array($category, ExtensionNumber::CATEGORIES, true)) {
            echo json_encode(['exists' => false]);
            return;
        }

        $duplicate = ExtensionNumber::findDuplicate($number, $category, $excludeId);
        if (!$duplicate) {
            echo json_encode(['exists' => false]);
            return;
        }

        echo json_encode([
            'exists' => true,
            'message' => 'Такой номер уже существует (№' . $duplicate['id'] . ', ' . Labels::extensionTypeLabel($duplicate['type'], $category) . ')',
        ]);
    }

    /**
     * JSON-эндпоинт интерактивного поиска для формы номера:
     * mode=member — внутренние номера (только category=internal, type=internal) для состава очереди/группы;
     * mode=failover — сценарии + любые внутренние номера, для маршрутизации при сбоях очереди.
     */
    public function searchTargets(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (!Auth::check()) {
            http_response_code(403);
            echo json_encode([]);
            return;
        }

        $mode = $_GET['mode'] ?? 'member';
        $q = trim($_GET['q'] ?? '');
        $excludeId = isset($_GET['exclude']) ? (int)$_GET['exclude'] : null;

        if ($mode === 'caller_id') {
            // АОН внутреннего номера: внешние «Городской»/«Сотовый», поиск от 3 цифр
            $results = array_map(fn($r) => [
                'type' => 'extension',
                'id' => (int)$r['id'],
                'label' => $r['number'] . ' — ' . Labels::extensionTypeLabel($r['type'], 'external') . ($r['owner'] ? ' (' . $r['owner'] . ')' : ''),
            ], ExtensionNumber::callerIdCandidates($q, 20));
        } elseif ($mode === 'failover') {
            $results = ExtensionNumber::failoverTargetCandidates($q, $excludeId, 20);
        } else {
            $rows = ExtensionNumber::internalMemberCandidates($q, 20);
            $results = array_map(fn($r) => [
                'type' => 'extension',
                'id' => (int)$r['id'],
                'label' => $r['number'] . ($r['owner'] ? ' — ' . $r['owner'] : ''),
            ], $rows);
            if ($excludeId) {
                $results = array_values(array_filter($results, fn($r) => $r['id'] !== $excludeId));
            }
        }

        echo json_encode($results);
    }

    /**
     * Выгрузка списка номеров текущей вкладки /extensions (с учётом поиска)
     * в Excel (.xlsx) или PDF: /extensions/export?format=xlsx|pdf&tab=...&q=...
     */
    public function export(): void
    {
        if (!Auth::requireLogin()) {
            return;
        }
        $requested = (string)($_GET['format'] ?? 'xlsx');
        $search = trim($_GET['q'] ?? '');
        $tab = $_GET['tab'] ?? 'external';

        // CSV для импорта сотрудников в MikoPBX — только внутренние номера
        // с типом «Внутренний» (вкладка «Внутренние»), с учётом поиска
        if ($requested === 'mikopbx') {
            $effectiveSearch = mb_strlen($search) >= 2 ? $search : '';
            MikoPbxCsv::send(ExtensionNumber::all($effectiveSearch, 'internal', 'internal', self::listFilters('internal', $_GET)));
            return;
        }

        $format = $requested === 'pdf' ? 'pdf' : 'xlsx';
        if (!array_key_exists($tab, self::INDEX_TABS)) {
            $tab = 'external';
        }
        [$category, $type] = self::INDEX_TABS[$tab];
        $effectiveSearch = mb_strlen($search) >= 2 ? $search : '';
        $filters = self::listFilters($tab, $_GET);
        $rows = ExtensionNumber::all($effectiveSearch, $category, $type ?? '', $filters);

        $tabLabel = ExtensionTable::TAB_LABELS[$tab];
        $columns = ExtensionTable::columnsForTab($tab);
        $subtitle = 'Записей: ' . count($rows) . ($effectiveSearch !== '' ? ' · поиск: «' . $effectiveSearch . '»' : '');
        if ($filters) {
            $subtitle .= ' · ' . self::describeFilters($tab, $filters);
        }

        Exporter::send($format, 'Номера — ' . $tabLabel, 'Номера — ' . $tabLabel, $subtitle, [
            ExtensionTable::section(null, $columns, $rows),
        ]);
    }

    /** Убирает номер из маршрутов, АОН которых он больше не получает (см. validate()) */
    private function detachFromRoutes(int $extensionId, string $number): void
    {
        $names = [];
        foreach ($this->routesToDetach as $r) {
            $name = RouteController::detachNumberFromRoute((int)$r['id'], $extensionId);
            if ($name !== null) {
                $names[] = '«' . $name . '»';
            }
        }
        if ($names) {
            Flash::warning('Номеру ' . $number . ' задан АОН вручную — номер убран из исходящего маршрута: ' . implode(', ', $names) . '.');
        }
    }

    /**
     * Для журнала изменений АОН пишется номером, а не ID — история остаётся
     * читаемой, даже если внешний номер потом удалят.
     */
    public static function forAudit(array $row): array
    {
        if (array_key_exists('caller_id_extension_id', $row)) {
            $id = (int)($row['caller_id_extension_id'] ?? 0);
            $cid = $id > 0 ? ExtensionNumber::findRaw($id) : null;
            $row['caller_id_extension_id'] = $cid ? $cid['number'] : ($id > 0 ? "#{$id}" : null);
        }
        if (array_key_exists('division_id', $row)) {
            $id = (int)($row['division_id'] ?? 0);
            $division = $id > 0 ? Division::findRaw($id) : null;
            $row['division_id'] = $division ? $division['name'] : ($id > 0 ? "#{$id}" : null);
        }
        return $row;
    }

    /** Справочники, нужные форме создания/редактирования номера */
    private function formOptions(?int $extensionId = null): array
    {
        return [
            'scenarios' => Scenario::options(),
            'departments' => Department::options(),
            'divisions' => Division::options(),
            'legalEntities' => LegalEntity::options(),
            'providers' => Provider::optionsWithLegalEntities(),
            'trunks' => Trunk::options(),
            'calltrackingTargets' => CalltrackingTarget::optionsWithDepartments(),
            'musicFiles' => MusicFile::options(),
            'groupMembers' => $extensionId ? ExtensionNumber::groupMembers($extensionId) : [],
            'queueSettings' => $extensionId ? ExtensionNumber::queueSettings($extensionId) : null,
        ];
    }

    /**
     * @return array{0: array, 1: Validator, 2: array, 3: ?array}
     *   [данные номера, валидатор, ID участников очереди/группы, данные настроек очереди/группы (или null)]
     */
    private function validate(array $input, ?int $currentId = null): array
    {
        $v = new Validator($input);
        $v->required('number', 'Номер')
            ->maxLength('number', 50, 'Номер')
            ->required('category', 'Категория')
            ->in('category', ExtensionNumber::CATEGORIES, 'Категория');

        $category = $v->get('category', 'internal');
        $allowedTypes = $category === 'external' ? ExtensionNumber::EXTERNAL_TYPES : ExtensionNumber::INTERNAL_TYPES;
        $defaultType = $category === 'external' ? 'did_city' : 'internal';

        $v->required('type', 'Тип')
            ->in('type', $allowedTypes, 'Тип')
            ->maxLength('owner', 150, 'Владелец')
            ->maxLength('context', 100, 'Контекст')
            ->in('status', ExtensionNumber::STATUSES, 'Статус');

        // Дубли внутренних номеров запрещены — иначе диалплан не сможет
        // однозначно определить, куда направлять вызов на такой номер.
        $numberValue = $v->get('number');
        if ($category === 'internal' && $numberValue !== null && $numberValue !== '') {
            $duplicate = ExtensionNumber::findDuplicate($numberValue, 'internal', $currentId);
            if ($duplicate) {
                $v->addError('number', 'Такой внутренний номер уже существует (№' . $duplicate['id'] . ', ' . Labels::extensionTypeLabel($duplicate['type'], 'internal') . ')');
            }
        }

        $scenarioId = $input['scenario_id'] ?? '';
        $scenarioId = ($scenarioId === '' || $scenarioId === null) ? null : (int)$scenarioId;

        $departmentId = $input['department_id'] ?? '';
        $departmentId = ($departmentId === '' || $departmentId === null) ? null : (int)$departmentId;

        $legalEntityId = $input['legal_entity_id'] ?? '';
        $legalEntityId = ($legalEntityId === '' || $legalEntityId === null) ? null : (int)$legalEntityId;

        $providerId = $input['provider_id'] ?? '';
        $providerId = ($providerId === '' || $providerId === null) ? null : (int)$providerId;

        $trunkId = $input['trunk_id'] ?? '';
        $trunkId = ($trunkId === '' || $trunkId === null) ? null : (int)$trunkId;

        $calltrackingTargetId = $input['calltracking_target_id'] ?? '';
        $calltrackingTargetId = ($calltrackingTargetId === '' || $calltrackingTargetId === null) ? null : (int)$calltrackingTargetId;

        $type = $v->get('type', $defaultType) ?: $defaultType;
        $isQueue = $category === 'internal' && $type === 'queue';
        $isRingGroup = $category === 'internal' && $type === 'ivr';

        // Подразделение теперь доступно и внутренним, и внешним номерам (для
        // внешних — кроме Calltracking, у которого своё поле "Подразделение",
        // связанное с выбором Цели; оно передаётся тем же полем department_id).
        if ($category !== 'external') {
            $legalEntityId = null;
            $providerId = null;
            $trunkId = null;
        }
        // Цель Calltracking имеет смысл только для внешних номеров с типом calltracking
        if ($category !== 'external' || $type !== 'calltracking') {
            $calltrackingTargetId = null;
        }
        // АОН — только у внутренних номеров с типом «Внутренний»: внешний номер
        // с типом «Городской» или «Сотовый» (или пусто)
        $callerIdExtensionId = null;
        if ($category === 'internal' && $type === 'internal') {
            $rawCallerId = trim((string)($input['caller_id_extension_id'] ?? ''));
            if ($rawCallerId !== '') {
                $callerId = ctype_digit($rawCallerId) ? ExtensionNumber::findRaw((int)$rawCallerId) : null;
                if ($callerId && $callerId['category'] === 'external' && in_array($callerId['type'], ExtensionNumber::CALLER_ID_TYPES, true)) {
                    $callerIdExtensionId = (int)$callerId['id'];
                } else {
                    $v->addError('caller_id_extension_id', 'АОН — выберите внешний номер с типом «Городской» или «Сотовый»');
                }
            }
        }
        // АОН задаётся исходящим маршрутом (номер в его составе или в его
        // подразделении)? Тогда смена АОН вручную требует подтверждения, а
        // после сохранения номер убирается из этих маршрутов (иначе маршрут
        // при следующем сохранении вернул бы свой АОН).
        $this->routesToDetach = [];
        if ($category === 'internal' && $type === 'internal' && !isset($v->errors()['caller_id_extension_id'])) {
            $oldCallerId = $currentId ? (ExtensionNumber::findRaw($currentId)['caller_id_extension_id'] ?? null) : null;
            $changed = (int)($oldCallerId ?? 0) !== (int)($callerIdExtensionId ?? 0);
            $conflicts = $changed ? array_values(array_filter(
                RouteEntity::callerIdRoutesFor($currentId, $departmentId),
                fn($r) => (int)$r['outbound_caller_id_id'] !== (int)($callerIdExtensionId ?? 0)
            )) : [];
            if ($conflicts && empty($input['confirm_caller_id_override'])) {
                $names = array_map(fn($r) => '«' . $r['name'] . '» (АОН ' . $r['aon_number'] . ')', $conflicts);
                $v->addError('caller_id_route_conflict', 'АОН этого номера задаётся исходящим маршрутом: '
                    . implode(', ', $names) . '. Если сохранить другой АОН, номер будет убран из '
                    . (count($conflicts) > 1 ? 'этих маршрутов' : 'этого маршрута') . '.');
            } elseif ($conflicts) {
                $this->routesToDetach = $conflicts;
            }
        }

        // Отдел (справочник «Отделы») — тоже только у внутренних номеров с типом «Внутренний»
        $divisionId = null;
        if ($category === 'internal' && $type === 'internal') {
            $rawDivision = trim((string)($input['division_id'] ?? ''));
            if ($rawDivision !== '') {
                if (ctype_digit($rawDivision) && Division::findRaw((int)$rawDivision)) {
                    $divisionId = (int)$rawDivision;
                } else {
                    $v->addError('division_id', 'Выберите отдел из справочника «Отделы»');
                }
            }
        }
        // Сценарий/Контекст скрыты в форме для типа "Очередь" — не сохраняем устаревшее значение
        if ($isQueue) {
            $scenarioId = null;
            $scenarioContext = null;
        } else {
            $scenarioContext = $v->get('context');
        }

        $data = [
            'number' => $v->get('number'),
            'category' => $category,
            'type' => $type,
            'owner' => $v->get('owner'),
            'context' => $scenarioContext,
            'scenario_id' => $scenarioId,
            'department_id' => $departmentId,
            'legal_entity_id' => $legalEntityId,
            'provider_id' => $providerId,
            'trunk_id' => $trunkId,
            'calltracking_target_id' => $calltrackingTargetId,
            'caller_id_extension_id' => $callerIdExtensionId,
            'division_id' => $divisionId,
            'status' => $v->get('status', 'active') ?: 'active',
            'description' => $v->get('description'),
        ];

        // ------------------------------------------------------------------
        // Состав очереди/группы обзвона — только для type queue|ivr
        // ------------------------------------------------------------------
        $memberIds = [];
        if ($isQueue || $isRingGroup) {
            $rawIds = $input['member_extension_id'] ?? [];
            if (!is_array($rawIds)) {
                $rawIds = [];
            }
            $seen = [];
            foreach ($rawIds as $rawId) {
                $memberId = (int)$rawId;
                if ($memberId <= 0 || isset($seen[$memberId]) || $memberId === $currentId) {
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
        }
        // Если номер раньше был допустимым участником (internal/internal) и мог входить
        // в состав какой-то очереди/группы, не даём сменить категорию/тип так, чтобы он
        // перестал подходить, пока он всё ещё в чьём-то составе.
        $this->guardAgainstBreakingMembership($currentId, $category, $type, $v);

        // ------------------------------------------------------------------
        // Настройки очереди/группы обзвона
        // ------------------------------------------------------------------
        $queueSettingsData = null;
        if ($isQueue || $isRingGroup) {
            $allowedStrategies = $isQueue ? ExtensionNumber::QUEUE_RING_STRATEGIES : ExtensionNumber::RING_GROUP_RING_STRATEGIES;
            $ringStrategy = trim((string)($input['ring_strategy'] ?? 'ringall'));
            if (!in_array($ringStrategy, $allowedStrategies, true)) {
                $v->addError('ring_strategy', 'Недопустимый метод обзвона для этого типа номера');
                $ringStrategy = 'ringall';
            }

            $agentAnswerTimeout = $this->parseNullableInt($input['agent_answer_timeout_sec'] ?? '', 'agent_answer_timeout_sec', 'Время попытки дозвона операторам', $v);
            $agentWrapupTime = $this->parseNullableInt($input['agent_wrapup_time_sec'] ?? '', 'agent_wrapup_time_sec', 'Время отдыха оператора', $v);

            $queueSettingsData = [
                'ring_strategy' => $ringStrategy,
                'agent_answer_timeout_sec' => $agentAnswerTimeout,
                'agent_wrapup_time_sec' => $agentWrapupTime,
                'agent_ring_in_use' => $isQueue && !empty($input['agent_ring_in_use']),
            ];

            if ($isQueue) {
                $callerWaitSound = trim((string)($input['caller_wait_sound'] ?? 'music'));
                if (!in_array($callerWaitSound, ExtensionNumber::CALLER_WAIT_SOUNDS, true)) {
                    $v->addError('caller_wait_sound', 'Недопустимое значение поля «Что слышит абонент при ожидании»');
                    $callerWaitSound = 'music';
                }

                $mohMusicId = $this->parseNullableMusicId($input['moh_music_id'] ?? '', 'moh_music_id', 'Фоновая музыка (MOH)', $v);
                $announcementMusicId = $this->parseNullableMusicId($input['announcement_music_id'] ?? '', 'announcement_music_id', 'Рекламная фраза', $v);
                $announcementRepeatSec = $this->parseNullableInt($input['announcement_repeat_sec'] ?? '', 'announcement_repeat_sec', 'Повтор оповещений', $v);
                $failover1Timeout = $this->parseNullableInt($input['failover1_timeout_sec'] ?? '', 'failover1_timeout_sec', 'Сценарий №1: таймаут ожидания', $v);

                [$failover1Type, $failover1Id] = $this->parseFailoverTarget($input, 'failover1', $currentId, $v);
                [$failover2Type, $failover2Id] = $this->parseFailoverTarget($input, 'failover2', $currentId, $v);

                $queueSettingsData += [
                    'caller_wait_sound' => $callerWaitSound,
                    'moh_music_id' => $mohMusicId,
                    'announce_position' => !empty($input['announce_position']),
                    'announce_hold_time' => !empty($input['announce_hold_time']),
                    'announcement_music_id' => $announcementMusicId,
                    'announcement_repeat_sec' => $announcementRepeatSec,
                    'failover1_timeout_sec' => $failover1Timeout,
                    'failover1_target_type' => $failover1Type,
                    'failover1_target_id' => $failover1Id,
                    'failover2_target_type' => $failover2Type,
                    'failover2_target_id' => $failover2Id,
                ];
            }
        }

        return [$data, $v, $memberIds, $queueSettingsData];
    }

    /** Число из формы: пусто -> null, иначе целое в диапазоне [0, 86400] секунд */
    private function parseNullableInt($raw, string $key, string $label, Validator $v): ?int
    {
        $raw = trim((string)($raw ?? ''));
        if ($raw === '') {
            return null;
        }
        if (!ctype_digit($raw)) {
            $v->addError($key, "Поле «{$label}» должно быть целым числом");
            return null;
        }
        $val = (int)$raw;
        if ($val > 86400) {
            $v->addError($key, "Поле «{$label}» слишком велико (максимум 86400 сек)");
            return null;
        }
        return $val;
    }

    private function parseNullableMusicId($raw, string $key, string $label, Validator $v): ?int
    {
        $raw = trim((string)($raw ?? ''));
        if ($raw === '') {
            return null;
        }
        $id = (int)$raw;
        if ($id <= 0 || !MusicFile::findRaw($id)) {
            $v->addError($key, "Поле «{$label}»: выбранный файл не найден в справочнике Музыка");
            return null;
        }
        return $id;
    }

    /** @return array{0: ?string, 1: ?int} [target_type, target_id] */
    private function parseFailoverTarget(array $input, string $prefix, ?int $currentId, Validator $v): array
    {
        $type = trim((string)($input[$prefix . '_target_type'] ?? ''));
        $rawId = trim((string)($input[$prefix . '_target_id'] ?? ''));
        if ($type === '' && $rawId === '') {
            return [null, null];
        }
        if (!in_array($type, ExtensionNumber::FAILOVER_TARGET_TYPES, true) || $rawId === '' || !ctype_digit($rawId)) {
            $v->addError($prefix . '_target', 'Укажите корректную цель маршрутизации');
            return [null, null];
        }
        $id = (int)$rawId;
        if ($type === 'extension' && $id === $currentId) {
            $v->addError($prefix . '_target', 'Нельзя перевести вызов на этот же номер');
            return [null, null];
        }
        if ($type === 'scenario') {
            if (!Scenario::find($id)) {
                $v->addError($prefix . '_target', 'Выбранный сценарий не найден');
                return [null, null];
            }
        } else {
            $target = ExtensionNumber::findRaw($id);
            if (!$target || $target['category'] !== 'internal') {
                $v->addError($prefix . '_target', 'Выбранный номер не найден');
                return [null, null];
            }
        }
        return [$type, $id];
    }

    /**
     * Если номер сейчас входит в состав какой-то очереди/группы (был
     * type=internal, category=internal), не даём поменять его тип/категорию
     * так, чтобы он перестал подходить как участник — иначе состав очереди
     * останется ссылаться на номер, который больше не "внутренний".
     */
    private function guardAgainstBreakingMembership(?int $currentId, string $newCategory, string $newType, Validator $v): void
    {
        if ($currentId === null) {
            return;
        }
        $stillValidMember = $newCategory === 'internal' && $newType === 'internal';
        if ($stillValidMember) {
            return;
        }
        $memberships = ExtensionNumber::groupMembershipsFor($currentId);
        if (!empty($memberships)) {
            $names = implode(', ', array_map(fn($m) => $m['number'], $memberships));
            $v->addError('type', "Нельзя изменить категорию/тип — номер входит в состав очереди/группы обзвона: {$names}. Сначала уберите его из состава.");
        }
    }
}
