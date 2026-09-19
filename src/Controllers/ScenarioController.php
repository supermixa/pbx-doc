<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\AuditLogger;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Validator;
use App\Core\View;
use App\Models\AuditLog;
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
        View::render('scenarios/show', [
            'title' => $scenario['name'],
            'scenario' => $scenario,
            'history' => AuditLog::forEntity('scenario', (int)$scenario['id']),
        ]);
    }

    public function create(): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        View::render('scenarios/form', [
            'title' => 'Новый сценарий',
            'scenario' => null,
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
            View::render('scenarios/form', [
                'title' => 'Новый сценарий',
                'scenario' => $_POST,
                'errors' => $validator->errors(),
            ]);
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
        View::render('scenarios/form', [
            'title' => 'Редактирование сценария',
            'scenario' => $scenario,
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
        $old = Scenario::findRaw($id);
        if (!$old) {
            http_response_code(404);
            View::render('errors/404', []);
            return;
        }

        [$data, $validator] = $this->validate($_POST);
        if ($validator->fails()) {
            View::render('scenarios/form', [
                'title' => 'Редактирование сценария',
                'scenario' => array_merge($old, $_POST, ['id' => $id]),
                'errors' => $validator->errors(),
            ]);
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

    private function validate(array $input): array
    {
        $v = new Validator($input);
        $v->required('name', 'Название')
            ->maxLength('name', 150, 'Название')
            ->maxLength('context', 100, 'Контекст');

        $data = [
            'name' => $v->get('name'),
            'context' => $v->get('context'),
            'description' => $v->get('description'),
            'dialplan_code' => $v->get('dialplan_code'),
        ];

        return [$data, $v];
    }
}
