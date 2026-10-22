<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\AuditLogger;
use App\Core\Csrf;
use App\Core\Db;
use App\Core\Flash;
use App\Core\Validator;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\CalltrackingGoal;
use App\Models\CalltrackingTarget;
use App\Models\Department;
use Throwable;

/**
 * Справочник "Calltracking": у каждого подразделения — список целей из
 * подчинённого справочника «Цели» (CalltrackingGoalController).
 * Запись справочника = подразделение, поэтому карточка/редактирование
 * адресуются по ID подразделения: /calltracking/departments/{id}.
 * В журнале изменений — сущность 'calltracking' с entity_id = ID подразделения.
 */
class CalltrackingTargetController
{
    public function index(): void
    {
        if (!Auth::requireLogin()) {
            return;
        }
        $search = trim($_GET['q'] ?? '');
        View::render('calltracking/index', [
            'title' => 'Calltracking',
            'rows' => CalltrackingTarget::groupedByDepartment($search),
            'search' => $search,
        ]);
    }

    public function show(array $params): void
    {
        if (!Auth::requireLogin()) {
            return;
        }
        $department = Department::find((int)$params['id']);
        if (!$department) {
            http_response_code(404);
            View::render('errors/404', []);
            return;
        }
        $depId = (int)$department['id'];
        View::render('calltracking/show', [
            'title' => 'Calltracking: ' . $department['name'],
            'department' => $department,
            'links' => CalltrackingTarget::forDepartment($depId),
            'extensions' => CalltrackingTarget::extensionsForDepartment($depId),
            'history' => AuditLog::forEntity('calltracking', $depId),
        ]);
    }

    /**
     * Старые ссылки вида /calltracking/{id} (ID строки «подразделение — цель»,
     * встречаются в журнале изменений) — ведут на карточку цели.
     */
    public function legacyRedirect(array $params): void
    {
        if (!Auth::requireLogin()) {
            return;
        }
        $link = CalltrackingTarget::findRaw((int)$params['id']);
        header('Location: ' . ($link ? '/calltracking/goals/' . (int)$link['goal_id'] : '/calltracking'));
    }

    public function create(): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        $this->renderForm('Новая запись Calltracking', null, [], []);
    }

    public function store(): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        Csrf::verifyRequestOrFail();

        $v = new Validator($_POST);
        $departmentId = (int)($_POST['department_id'] ?? 0);
        $department = $departmentId > 0 ? Department::find($departmentId) : null;
        if (!$department) {
            $v->addError('department_id', 'Выберите подразделение');
        } elseif (CalltrackingTarget::forDepartment($departmentId)) {
            $v->addError('department_id', 'У этого подразделения уже есть цели — откройте его в списке и отредактируйте');
        }
        $goalIds = $this->validateGoals($_POST, $v, []);

        if ($v->fails()) {
            $this->renderForm('Новая запись Calltracking', null, $this->rowsFromPost($goalIds, []), $v->errors(), $departmentId);
            return;
        }

        $this->inTransaction(fn() => CalltrackingTarget::syncDepartmentGoals($departmentId, $goalIds, Auth::id()));
        AuditLogger::logCreate('calltracking', $departmentId, ['goals' => $this->goalsSummary($departmentId)]);

        Flash::success('Цели подразделения сохранены');
        header('Location: /calltracking/departments/' . $departmentId);
    }

    public function edit(array $params): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        $department = Department::find((int)$params['id']);
        if (!$department) {
            http_response_code(404);
            View::render('errors/404', []);
            return;
        }
        $links = CalltrackingTarget::forDepartment((int)$department['id']);
        $rows = array_map(fn($l) => [
            'goal_id' => (int)$l['goal_id'],
            'extensions_count' => (int)$l['extensions_count'],
        ], $links);
        $this->renderForm('Цели подразделения «' . $department['name'] . '»', $department, $rows, []);
    }

    public function update(array $params): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        Csrf::verifyRequestOrFail();

        $department = Department::find((int)$params['id']);
        if (!$department) {
            http_response_code(404);
            View::render('errors/404', []);
            return;
        }
        $depId = (int)$department['id'];
        $links = CalltrackingTarget::forDepartment($depId);

        $v = new Validator($_POST);
        $goalIds = $this->validateGoals($_POST, $v, $links);

        if ($v->fails()) {
            $this->renderForm('Цели подразделения «' . $department['name'] . '»', $department, $this->rowsFromPost($goalIds, $links), $v->errors());
            return;
        }

        $oldSummary = $this->goalsSummary($depId);
        $this->inTransaction(fn() => CalltrackingTarget::syncDepartmentGoals($depId, $goalIds, Auth::id()));
        AuditLogger::logUpdate('calltracking', $depId, ['goals' => $oldSummary], ['goals' => $this->goalsSummary($depId)]);

        Flash::success('Изменения сохранены');
        header('Location: /calltracking/departments/' . $depId);
    }

    public function destroy(array $params): void
    {
        if (!Auth::requireAdmin()) {
            return;
        }
        Csrf::verifyRequestOrFail();

        $depId = (int)$params['id'];
        $links = CalltrackingTarget::forDepartment($depId);
        $used = array_filter($links, fn($l) => (int)$l['extensions_count'] > 0);
        if ($used) {
            Flash::error('Нельзя удалить: цели этого подразделения используются номерами CallTracking — сначала смените у них подразделение/цель.');
            header('Location: /calltracking/departments/' . $depId);
            return;
        }
        if ($links) {
            $oldSummary = $this->goalsSummary($depId);
            $this->inTransaction(fn() => CalltrackingTarget::syncDepartmentGoals($depId, [], Auth::id()));
            AuditLogger::logDelete('calltracking', $depId, ['goals' => $oldSummary]);
            Flash::success('Цели подразделения удалены из справочника Calltracking');
        }
        header('Location: /calltracking');
    }

    // ------------------------------------------------------------------

    /**
     * Проверяет присланный список целей (goal_ids[]): только существующие
     * цели, без повторов, хотя бы одна. Цели, на которые ссылаются номера
     * CallTracking ($links с extensions_count > 0), убирать нельзя — номера
     * берут цель именно из этой связи «подразделение — цель».
     *
     * @return int[] ID целей в порядке формы, без повторов
     */
    private function validateGoals(array $input, Validator $v, array $links): array
    {
        $raw = $input['goal_ids'] ?? [];
        $raw = is_array($raw) ? $raw : [];

        $goalIds = [];
        $unknown = false;
        foreach ($raw as $value) {
            $value = trim((string)$value);
            if ($value === '') {
                continue;
            }
            $id = ctype_digit($value) ? (int)$value : 0;
            if ($id <= 0 || !CalltrackingGoal::findRaw($id)) {
                $unknown = true;
                continue;
            }
            if (!in_array($id, $goalIds, true)) {
                $goalIds[] = $id;
            }
        }

        if ($unknown) {
            $v->addError('goal_ids', 'Одна из выбранных целей не найдена в справочнике — обновите страницу');
        } elseif (!$goalIds) {
            $v->addError('goal_ids', 'Добавьте хотя бы одну цель');
        }

        $blocked = [];
        foreach ($links as $l) {
            if ((int)$l['extensions_count'] > 0 && !in_array((int)$l['goal_id'], $goalIds, true)) {
                $blocked[] = '«' . $l['goal_name'] . '» (номеров: ' . (int)$l['extensions_count'] . ')';
            }
        }
        if ($blocked) {
            $v->addError('goal_ids', 'Нельзя убрать цели, которые используются номерами CallTracking: ' . implode(', ', $blocked) . '.');
            // Возвращаем их обратно в форму, чтобы не пропали
            foreach ($links as $l) {
                if ((int)$l['extensions_count'] > 0 && !in_array((int)$l['goal_id'], $goalIds, true)) {
                    $goalIds[] = (int)$l['goal_id'];
                }
            }
        }

        return $goalIds;
    }

    /** Строки формы после ошибки валидации: выбранные цели + признак «используется номерами» */
    private function rowsFromPost(array $goalIds, array $links): array
    {
        $counts = [];
        foreach ($links as $l) {
            $counts[(int)$l['goal_id']] = (int)$l['extensions_count'];
        }
        return array_map(fn($id) => ['goal_id' => $id, 'extensions_count' => $counts[$id] ?? 0], $goalIds);
    }

    private function renderForm(string $title, ?array $department, array $rows, array $errors, int $selectedDepartmentId = 0): void
    {
        $taken = [];
        foreach (CalltrackingTarget::groupedByDepartment() as $r) {
            $taken[(int)$r['department_id']] = true;
        }
        // При создании предлагаем только подразделения, у которых ещё нет целей
        $departments = array_values(array_filter(
            Department::options(),
            fn($d) => !isset($taken[(int)$d['id']]) || (int)$d['id'] === $selectedDepartmentId
        ));

        View::render('calltracking/form', [
            'title' => $title,
            'department' => $department,
            'departments' => $departments,
            'selectedDepartmentId' => $selectedDepartmentId,
            'rows' => $rows,
            'goals' => CalltrackingGoal::options(),
            'errors' => $errors,
        ]);
    }

    private function goalsSummary(int $departmentId): string
    {
        return CalltrackingTarget::summarizeGoals(array_column(CalltrackingTarget::forDepartment($departmentId), 'goal_name'));
    }

    private function inTransaction(callable $fn): void
    {
        $conn = Db::connection();
        $conn->beginTransaction();
        try {
            $fn();
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollBack();
            throw $e;
        }
    }
}
