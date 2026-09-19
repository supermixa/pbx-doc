<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\AuditLogger;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Validator;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\Trunk;

class TrunkController
{
    public function index(): void
    {
        if (!Auth::requireLogin()) {
            return;
        }
        $search = trim($_GET['q'] ?? '');
        View::render('trunks/index', [
            'title' => 'Транки',
            'trunks' => Trunk::all($search),
            'search' => $search,
        ]);
    }

    public function show(array $params): void
    {
        if (!Auth::requireLogin()) {
            return;
        }
        $trunk = Trunk::find((int)$params['id']);
        if (!$trunk) {
            http_response_code(404);
            View::render('errors/404', []);
            return;
        }
        View::render('trunks/show', [
            'title' => $trunk['name'],
            'trunk' => $trunk,
            'history' => AuditLog::forEntity('trunk', (int)$trunk['id']),
        ]);
    }

    public function create(): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        View::render('trunks/form', [
            'title' => 'Новый транк',
            'trunk' => null,
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
            View::render('trunks/form', [
                'title' => 'Новый транк',
                'trunk' => $_POST,
                'errors' => $validator->errors(),
            ]);
            return;
        }

        $data['created_by'] = Auth::id();
        $id = Trunk::create($data);
        AuditLogger::logCreate('trunk', $id, $data);

        Flash::success('Транк создан');
        header('Location: /trunks/' . $id);
    }

    public function edit(array $params): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        $trunk = Trunk::find((int)$params['id']);
        if (!$trunk) {
            http_response_code(404);
            View::render('errors/404', []);
            return;
        }
        View::render('trunks/form', [
            'title' => 'Редактирование транка',
            'trunk' => $trunk,
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
        $old = Trunk::findRaw($id);
        if (!$old) {
            http_response_code(404);
            View::render('errors/404', []);
            return;
        }

        [$data, $validator] = $this->validate($_POST);
        if ($validator->fails()) {
            View::render('trunks/form', [
                'title' => 'Редактирование транка',
                'trunk' => array_merge($old, $_POST, ['id' => $id]),
                'errors' => $validator->errors(),
            ]);
            return;
        }

        $data['updated_by'] = Auth::id();
        Trunk::update($id, $data);
        AuditLogger::logUpdate('trunk', $id, $old, $data);

        Flash::success('Изменения сохранены');
        header('Location: /trunks/' . $id);
    }

    public function destroy(array $params): void
    {
        if (!Auth::requireAdmin()) {
            return;
        }
        Csrf::verifyRequestOrFail();

        $id = (int)$params['id'];
        $old = Trunk::findRaw($id);
        if ($old) {
            AuditLogger::logDelete('trunk', $id, $old);
            Trunk::delete($id);
            Flash::success('Транк удалён');
        }
        header('Location: /trunks');
    }

    private function validate(array $input): array
    {
        $v = new Validator($input);
        $v->required('name', 'Название')
            ->maxLength('name', 150, 'Название')
            ->required('type', 'Тип')
            ->in('type', Trunk::TYPES, 'Тип')
            ->maxLength('host', 190, 'Хост')
            ->maxLength('context', 100, 'Контекст')
            ->in('status', Trunk::STATUSES, 'Статус');

        $data = [
            'name' => $v->get('name'),
            'type' => $v->get('type', 'PJSIP'),
            'provider' => $v->get('provider'),
            'host' => $v->get('host'),
            'context' => $v->get('context'),
            'status' => $v->get('status', 'active') ?: 'active',
            'description' => $v->get('description'),
        ];

        return [$data, $v];
    }
}
