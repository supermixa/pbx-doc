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
use App\Models\Scenario;

class ExtensionController
{
    public function index(): void
    {
        if (!Auth::requireLogin()) {
            return;
        }
        $search = trim($_GET['q'] ?? '');
        View::render('extensions/index', [
            'title' => 'Номера',
            'extensions' => ExtensionNumber::all($search),
            'search' => $search,
        ]);
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
            'history' => AuditLog::forEntity('extension', (int)$extension['id']),
        ]);
    }

    public function create(): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        View::render('extensions/form', [
            'title' => 'Новый номер',
            'extension' => null,
            'scenarios' => Scenario::options(),
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
            View::render('extensions/form', [
                'title' => 'Новый номер',
                'extension' => $_POST,
                'scenarios' => Scenario::options(),
                'errors' => $validator->errors(),
            ]);
            return;
        }

        $data['created_by'] = Auth::id();
        $id = ExtensionNumber::create($data);
        AuditLogger::logCreate('extension', $id, $data);

        Flash::success('Номер создан');
        header('Location: /extensions/' . $id);
    }

    public function edit(array $params): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        $extension = ExtensionNumber::find((int)$params['id']);
        if (!$extension) {
            http_response_code(404);
            View::render('errors/404', []);
            return;
        }
        View::render('extensions/form', [
            'title' => 'Редактирование номера',
            'extension' => $extension,
            'scenarios' => Scenario::options(),
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
        $old = ExtensionNumber::findRaw($id);
        if (!$old) {
            http_response_code(404);
            View::render('errors/404', []);
            return;
        }

        [$data, $validator] = $this->validate($_POST);
        if ($validator->fails()) {
            View::render('extensions/form', [
                'title' => 'Редактирование номера',
                'extension' => array_merge($old, $_POST, ['id' => $id]),
                'scenarios' => Scenario::options(),
                'errors' => $validator->errors(),
            ]);
            return;
        }

        $data['updated_by'] = Auth::id();
        ExtensionNumber::update($id, $data);
        AuditLogger::logUpdate('extension', $id, $old, $data);

        Flash::success('Изменения сохранены');
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
            AuditLogger::logDelete('extension', $id, $old);
            ExtensionNumber::delete($id);
            Flash::success('Номер удалён');
        }
        header('Location: /extensions');
    }

    private function validate(array $input): array
    {
        $v = new Validator($input);
        $v->required('number', 'Номер')
            ->maxLength('number', 50, 'Номер')
            ->required('type', 'Тип')
            ->in('type', ExtensionNumber::TYPES, 'Тип')
            ->maxLength('owner', 150, 'Владелец')
            ->maxLength('context', 100, 'Контекст')
            ->in('status', ExtensionNumber::STATUSES, 'Статус');

        $scenarioId = $input['scenario_id'] ?? '';
        $scenarioId = ($scenarioId === '' || $scenarioId === null) ? null : (int)$scenarioId;

        $data = [
            'number' => $v->get('number'),
            'type' => $v->get('type', 'internal'),
            'owner' => $v->get('owner'),
            'context' => $v->get('context'),
            'scenario_id' => $scenarioId,
            'status' => $v->get('status', 'active') ?: 'active',
            'description' => $v->get('description'),
        ];

        return [$data, $v];
    }
}
