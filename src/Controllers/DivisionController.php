<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\AuditLogger;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Validator;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\Division;

class DivisionController
{
    public function index(): void
    {
        if (!Auth::requireLogin()) {
            return;
        }
        $search = trim($_GET['q'] ?? '');
        View::render('divisions/index', [
            'title' => 'Отделы',
            'divisions' => Division::all($search),
            'search' => $search,
        ]);
    }

    public function show(array $params): void
    {
        if (!Auth::requireLogin()) {
            return;
        }
        $division = Division::find((int)$params['id']);
        if (!$division) {
            http_response_code(404);
            View::render('errors/404', []);
            return;
        }
        View::render('divisions/show', [
            'title' => $division['name'],
            'division' => $division,
            'history' => AuditLog::forEntity('division', (int)$division['id']),
        ]);
    }

    public function create(): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        View::render('divisions/form', [
            'title' => 'Новый отдел',
            'division' => null,
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
            View::render('divisions/form', [
                'title' => 'Новый отдел',
                'division' => $_POST,
                'errors' => $validator->errors(),
            ]);
            return;
        }

        $data['created_by'] = Auth::id();
        $id = Division::create($data);
        AuditLogger::logCreate('division', $id, $data);

        Flash::success('Отдел создан');
        header('Location: /divisions/' . $id);
    }

    public function edit(array $params): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        $division = Division::find((int)$params['id']);
        if (!$division) {
            http_response_code(404);
            View::render('errors/404', []);
            return;
        }
        View::render('divisions/form', [
            'title' => 'Редактирование отдела',
            'division' => $division,
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
        $old = Division::findRaw($id);
        if (!$old) {
            http_response_code(404);
            View::render('errors/404', []);
            return;
        }

        [$data, $validator] = $this->validate($_POST);
        if ($validator->fails()) {
            View::render('divisions/form', [
                'title' => 'Редактирование отдела',
                'division' => array_merge($old, $_POST, ['id' => $id]),
                'errors' => $validator->errors(),
            ]);
            return;
        }

        $data['updated_by'] = Auth::id();
        Division::update($id, $data);
        AuditLogger::logUpdate('division', $id, $old, $data);

        Flash::success('Изменения сохранены');
        header('Location: /divisions/' . $id);
    }

    public function destroy(array $params): void
    {
        if (!Auth::requireAdmin()) {
            return;
        }
        Csrf::verifyRequestOrFail();

        $id = (int)$params['id'];
        $old = Division::findRaw($id);
        if ($old) {
            AuditLogger::logDelete('division', $id, $old);
            Division::delete($id);
            Flash::success('Отдел удалён');
        }
        header('Location: /divisions');
    }

    private function validate(array $input): array
    {
        $v = new Validator($input);
        $v->required('name', 'Наименование')->maxLength('name', 150, 'Наименование');

        $data = [
            'name' => $v->get('name'),
            'description' => $v->get('description'),
        ];

        return [$data, $v];
    }
}
