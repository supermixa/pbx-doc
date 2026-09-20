<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\AuditLogger;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Validator;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\Equipment;
use App\Models\EquipmentType;

class EquipmentController
{
    public function index(): void
    {
        if (!Auth::requireLogin()) {
            return;
        }
        $search = trim($_GET['q'] ?? '');
        View::render('equipment/index', [
            'title' => 'Оборудование',
            'equipment' => Equipment::all($search),
            'search' => $search,
        ]);
    }

    public function show(array $params): void
    {
        if (!Auth::requireLogin()) {
            return;
        }
        $item = Equipment::find((int)$params['id']);
        if (!$item) {
            http_response_code(404);
            View::render('errors/404', []);
            return;
        }
        View::render('equipment/show', [
            'title' => $item['equipment_type_name'] ?? ('Оборудование #' . $item['id']),
            'item' => $item,
            'history' => AuditLog::forEntity('equipment', (int)$item['id']),
        ]);
    }

    public function create(): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        View::render('equipment/form', [
            'title' => 'Новое оборудование',
            'item' => null,
            'equipmentTypes' => EquipmentType::options(),
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
            View::render('equipment/form', [
                'title' => 'Новое оборудование',
                'item' => $_POST,
                'equipmentTypes' => EquipmentType::options(),
                'errors' => $validator->errors(),
            ]);
            return;
        }

        $data['created_by'] = Auth::id();
        $id = Equipment::create($data);
        AuditLogger::logCreate('equipment', $id, $data);

        Flash::success('Оборудование добавлено');
        header('Location: /equipment/' . $id);
    }

    public function edit(array $params): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        $item = Equipment::find((int)$params['id']);
        if (!$item) {
            http_response_code(404);
            View::render('errors/404', []);
            return;
        }
        View::render('equipment/form', [
            'title' => 'Редактирование оборудования',
            'item' => $item,
            'equipmentTypes' => EquipmentType::options(),
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
        $old = Equipment::findRaw($id);
        if (!$old) {
            http_response_code(404);
            View::render('errors/404', []);
            return;
        }

        [$data, $validator] = $this->validate($_POST);
        if ($validator->fails()) {
            View::render('equipment/form', [
                'title' => 'Редактирование оборудования',
                'item' => array_merge($old, $_POST, ['id' => $id]),
                'equipmentTypes' => EquipmentType::options(),
                'errors' => $validator->errors(),
            ]);
            return;
        }

        $data['updated_by'] = Auth::id();
        Equipment::update($id, $data);
        AuditLogger::logUpdate('equipment', $id, $old, $data);

        Flash::success('Изменения сохранены');
        header('Location: /equipment/' . $id);
    }

    public function destroy(array $params): void
    {
        if (!Auth::requireAdmin()) {
            return;
        }
        Csrf::verifyRequestOrFail();

        $id = (int)$params['id'];
        $old = Equipment::findRaw($id);
        if ($old) {
            AuditLogger::logDelete('equipment', $id, $old);
            Equipment::delete($id);
            Flash::success('Оборудование удалено');
        }
        header('Location: /equipment');
    }

    private function validate(array $input): array
    {
        $v = new Validator($input);
        $v->maxLength('location', 190, 'Расположение')
            ->maxLength('ip_address', 50, 'IP-адрес');

        $typeId = $input['equipment_type_id'] ?? '';
        $typeId = ($typeId === '' || $typeId === null) ? null : (int)$typeId;

        $data = [
            'equipment_type_id' => $typeId,
            'location' => $v->get('location'),
            'ip_address' => $v->get('ip_address'),
            'description' => $v->get('description'),
        ];

        return [$data, $v];
    }
}
