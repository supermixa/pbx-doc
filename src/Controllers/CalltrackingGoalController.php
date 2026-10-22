<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\AuditLogger;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Validator;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\CalltrackingGoal;

/**
 * Подчинённый справочник Calltracking — «Цели» (/calltracking/goals).
 * Карточка цели показывает, в каких подразделениях она используется.
 */
class CalltrackingGoalController
{
    public function index(): void
    {
        if (!Auth::requireLogin()) {
            return;
        }
        $search = trim($_GET['q'] ?? '');
        View::render('calltracking_goals/index', [
            'title' => 'Цели Calltracking',
            'goals' => CalltrackingGoal::all($search),
            'search' => $search,
        ]);
    }

    public function show(array $params): void
    {
        if (!Auth::requireLogin()) {
            return;
        }
        $goal = CalltrackingGoal::find((int)$params['id']);
        if (!$goal) {
            http_response_code(404);
            View::render('errors/404', []);
            return;
        }
        $id = (int)$goal['id'];
        View::render('calltracking_goals/show', [
            'title' => $goal['name'],
            'goal' => $goal,
            'departments' => CalltrackingGoal::departments($id),
            'extensions' => CalltrackingGoal::extensions($id),
            'history' => AuditLog::forEntity('calltracking_goal', $id),
        ]);
    }

    public function create(): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        View::render('calltracking_goals/form', ['title' => 'Новая цель', 'goal' => null, 'errors' => []]);
    }

    public function store(): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        Csrf::verifyRequestOrFail();

        [$data, $v] = $this->validate($_POST, null);
        if ($v->fails()) {
            View::render('calltracking_goals/form', ['title' => 'Новая цель', 'goal' => $_POST, 'errors' => $v->errors()]);
            return;
        }
        $data['created_by'] = Auth::id();
        $id = CalltrackingGoal::create($data);
        AuditLogger::logCreate('calltracking_goal', $id, ['name' => $data['name']]);

        Flash::success('Цель добавлена');
        header('Location: /calltracking/goals/' . $id);
    }

    /**
     * Быстрое добавление цели прямо из формы подразделения (AJAX).
     * Возвращает JSON {id, name, existed} или {error}. Если цель с таким
     * названием уже есть — возвращается она (existed = true), дубль не создаётся.
     */
    public function quickCreate(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (!Auth::check() || !Auth::canEdit()) {
            http_response_code(403);
            echo json_encode(['error' => 'Недостаточно прав']);
            return;
        }
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            echo json_encode(['error' => 'Сессия истекла, обновите страницу']);
            return;
        }

        $name = trim((string)($_POST['name'] ?? ''));
        $existing = $name !== '' ? CalltrackingGoal::findByName($name) : null;
        if ($existing) {
            echo json_encode(['id' => (int)$existing['id'], 'name' => $existing['name'], 'existed' => true], JSON_UNESCAPED_UNICODE);
            return;
        }

        [$data, $v] = $this->validate(['name' => $name], null);
        if ($v->fails()) {
            http_response_code(422);
            echo json_encode(['error' => $v->firstError()], JSON_UNESCAPED_UNICODE);
            return;
        }
        $data['created_by'] = Auth::id();
        $id = CalltrackingGoal::create($data);
        AuditLogger::logCreate('calltracking_goal', $id, ['name' => $data['name']]);

        echo json_encode(['id' => $id, 'name' => $data['name'], 'existed' => false], JSON_UNESCAPED_UNICODE);
    }

    public function edit(array $params): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        $goal = CalltrackingGoal::find((int)$params['id']);
        if (!$goal) {
            http_response_code(404);
            View::render('errors/404', []);
            return;
        }
        View::render('calltracking_goals/form', ['title' => 'Редактирование цели', 'goal' => $goal, 'errors' => []]);
    }

    public function update(array $params): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        Csrf::verifyRequestOrFail();

        $id = (int)$params['id'];
        $old = CalltrackingGoal::findRaw($id);
        if (!$old) {
            http_response_code(404);
            View::render('errors/404', []);
            return;
        }
        [$data, $v] = $this->validate($_POST, $id);
        if ($v->fails()) {
            View::render('calltracking_goals/form', [
                'title' => 'Редактирование цели',
                'goal' => array_merge($old, $_POST, ['id' => $id]),
                'errors' => $v->errors(),
            ]);
            return;
        }
        $data['updated_by'] = Auth::id();
        CalltrackingGoal::update($id, $data);
        AuditLogger::logUpdate('calltracking_goal', $id, ['name' => $old['name']], ['name' => $data['name']]);

        Flash::success('Изменения сохранены');
        header('Location: /calltracking/goals/' . $id);
    }

    public function destroy(array $params): void
    {
        if (!Auth::requireAdmin()) {
            return;
        }
        Csrf::verifyRequestOrFail();

        $id = (int)$params['id'];
        $old = CalltrackingGoal::findRaw($id);
        if (!$old) {
            header('Location: /calltracking/goals');
            return;
        }
        $departments = CalltrackingGoal::departments($id);
        if ($departments) {
            Flash::error('Нельзя удалить цель: она используется в подразделениях (' . count($departments) . '). Сначала уберите её у подразделений в справочнике Calltracking.');
            header('Location: /calltracking/goals/' . $id);
            return;
        }
        AuditLogger::logDelete('calltracking_goal', $id, ['name' => $old['name']]);
        CalltrackingGoal::delete($id);
        Flash::success('Цель удалена');
        header('Location: /calltracking/goals');
    }

    /**
     * @return array{0: array, 1: Validator}
     */
    private function validate(array $input, ?int $selfId): array
    {
        $v = new Validator($input);
        $v->required('name', 'Цель')->maxLength('name', 190, 'Цель');
        $name = trim((string)$v->get('name', ''));
        if ($name !== '') {
            $existing = CalltrackingGoal::findByName($name);
            if ($existing && (int)$existing['id'] !== (int)$selfId) {
                $v->addError('name', 'Цель «' . $existing['name'] . '» уже есть в справочнике');
            }
        }
        return [['name' => $name], $v];
    }
}
