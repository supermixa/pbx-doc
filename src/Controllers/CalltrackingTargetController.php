<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\AuditLogger;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Validator;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\CalltrackingTarget;
use App\Models\Department;

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
            'targets' => CalltrackingTarget::all($search),
            'search' => $search,
        ]);
    }

    public function show(array $params): void
    {
        if (!Auth::requireLogin()) {
            return;
        }
        $target = CalltrackingTarget::find((int)$params['id']);
        if (!$target) {
            http_response_code(404);
            View::render('errors/404', []);
            return;
        }
        View::render('calltracking/show', [
            'title' => $target['target'],
            'target' => $target,
            'history' => AuditLog::forEntity('calltracking_target', (int)$target['id']),
        ]);
    }

    public function create(): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        View::render('calltracking/form', [
            'title' => 'Новая цель Calltracking',
            'target' => null,
            'departments' => Department::options(),
            'errors' => [],
        ]);
    }

    public function store(): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        Csrf::verifyRequestOrFail();

        [$data, $validator] = $this->validate($_POST);
        if ($validator->fails()) {
            View::render('calltracking/form', [
                'title' => 'Новая цель Calltracking',
                'target' => $_POST,
                'departments' => Department::options(),
                'errors' => $validator->errors(),
            ]);
            return;
        }

        $data['created_by'] = Auth::id();
        $id = CalltrackingTarget::create($data);
        AuditLogger::logCreate('calltracking_target', $id, $data);

        Flash::success('Цель Calltracking создана');
        header('Location: /calltracking/' . $id);
    }

    public function edit(array $params): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        $target = CalltrackingTarget::find((int)$params['id']);
        if (!$target) {
            http_response_code(404);
            View::render('errors/404', []);
            return;
        }
        View::render('calltracking/form', [
            'title' => 'Редактирование цели Calltracking',
            'target' => $target,
            'departments' => Department::options(),
            'errors' => [],
        ]);
    }

    public function update(array $params): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        Csrf::verifyRequestOrFail();

        $id = (int)$params['id'];
        $old = CalltrackingTarget::findRaw($id);
        if (!$old) {
            http_response_code(404);
            View::render('errors/404', []);
            return;
        }

        [$data, $validator] = $this->validate($_POST);
        if ($validator->fails()) {
            View::render('calltracking/form', [
                'title' => 'Редактирование цели Calltracking',
                'target' => array_merge($old, $_POST, ['id' => $id]),
                'departments' => Department::options(),
                'errors' => $validator->errors(),
            ]);
            return;
        }

        $data['updated_by'] = Auth::id();
        CalltrackingTarget::update($id, $data);
        AuditLogger::logUpdate('calltracking_target', $id, $old, $data);

        Flash::success('Изменения сохранены');
        header('Location: /calltracking/' . $id);
    }

    public function destroy(array $params): void
    {
        if (!Auth::requireAdmin()) {
            return;
        }
        Csrf::verifyRequestOrFail();

        $id = (int)$params['id'];
        $old = CalltrackingTarget::findRaw($id);
        if ($old) {
            AuditLogger::logDelete('calltracking_target', $id, $old);
            CalltrackingTarget::delete($id);
            Flash::success('Цель Calltracking удалена');
        }
        header('Location: /calltracking');
    }

    private function validate(array $input): array
    {
        $v = new Validator($input);
        $v->required('department_id', 'Подразделение')
            ->required('target', 'Цель')
            ->maxLength('target', 190, 'Цель');

        $departmentId = trim((string)($input['department_id'] ?? ''));
        if ($departmentId !== '' && !Department::find((int)$departmentId)) {
            $v->addError('department_id', 'Выбранное подразделение не найдено');
        }

        $data = [
            'department_id' => $departmentId !== '' ? (int)$departmentId : null,
            'target' => $v->get('target'),
        ];

        return [$data, $v];
    }
}
