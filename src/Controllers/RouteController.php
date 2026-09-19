<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\AuditLogger;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Validator;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\RouteEntity;
use App\Models\Trunk;

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
            'history' => AuditLog::forEntity('route', (int)$route['id']),
        ]);
    }

    public function create(): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        View::render('routes/form', [
            'title' => 'Новый маршрут',
            'route' => null,
            'trunks' => Trunk::options(),
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
            View::render('routes/form', [
                'title' => 'Новый маршрут',
                'route' => $_POST,
                'trunks' => Trunk::options(),
                'errors' => $validator->errors(),
            ]);
            return;
        }

        $data['created_by'] = Auth::id();
        $id = RouteEntity::create($data);
        AuditLogger::logCreate('route', $id, $data);

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
        View::render('routes/form', [
            'title' => 'Редактирование маршрута',
            'route' => $route,
            'trunks' => Trunk::options(),
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
        $old = RouteEntity::findRaw($id);
        if (!$old) {
            http_response_code(404);
            View::render('errors/404', []);
            return;
        }

        [$data, $validator] = $this->validate($_POST);
        if ($validator->fails()) {
            View::render('routes/form', [
                'title' => 'Редактирование маршрута',
                'route' => array_merge($old, $_POST, ['id' => $id]),
                'trunks' => Trunk::options(),
                'errors' => $validator->errors(),
            ]);
            return;
        }

        $data['updated_by'] = Auth::id();
        RouteEntity::update($id, $data);
        AuditLogger::logUpdate('route', $id, $old, $data);

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
            AuditLogger::logDelete('route', $id, $old);
            RouteEntity::delete($id);
            Flash::success('Маршрут удалён');
        }
        header('Location: /routes');
    }

    private function validate(array $input): array
    {
        $v = new Validator($input);
        $v->required('name', 'Название')
            ->maxLength('name', 150, 'Название')
            ->required('direction', 'Направление')
            ->in('direction', RouteEntity::DIRECTIONS, 'Направление')
            ->maxLength('pattern', 190, 'Шаблон номера')
            ->maxLength('context', 100, 'Контекст');

        $trunkId = $input['trunk_id'] ?? '';
        $trunkId = ($trunkId === '' || $trunkId === null) ? null : (int)$trunkId;

        $data = [
            'name' => $v->get('name'),
            'direction' => $v->get('direction', 'outbound'),
            'pattern' => $v->get('pattern'),
            'trunk_id' => $trunkId,
            'context' => $v->get('context'),
            'description' => $v->get('description'),
        ];

        return [$data, $v];
    }
}
