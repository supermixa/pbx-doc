<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\AuditLogger;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Validator;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\EquipmentType;

class EquipmentTypeController
{
    public function index(): void
    {
        if (!Auth::requireLogin()) {
            return;
        }
        $search = trim($_GET['q'] ?? '');
        View::render('equipment_types/index', [
            'title' => 'Типы оборудования',
            'equipmentTypes' => EquipmentType::all($search),
            'search' => $search,
        ]);
    }

    public function show(array $params): void
    {
        if (!Auth::requireLogin()) {
            return;
        }
        $equipmentType = EquipmentType::find((int)$params['id']);
        if (!$equipmentType) {
            http_response_code(404);
            View::render('errors/404', []);
            return;
        }
        View::render('equipment_types/show', [
            'title' => $equipmentType['name'],
            'equipmentType' => $equipmentType,
            'history' => AuditLog::forEntity('equipment_type', (int)$equipmentType['id']),
        ]);
    }

    public function create(): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        View::render('equipment_types/form', [
            'title' => 'Новый тип оборудования',
            'equipmentType' => null,
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
            View::render('equipment_types/form', [
                'title' => 'Новый тип оборудования',
                'equipmentType' => $_POST,
                'errors' => $validator->errors(),
            ]);
            return;
        }

        $data['created_by'] = Auth::id();
        $id = EquipmentType::create($data);
        AuditLogger::logCreate('equipment_type', $id, $data);

        Flash::success('Тип оборудования создан');
        header('Location: /equipment-types/' . $id);
    }

    public function edit(array $params): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        $equipmentType = EquipmentType::find((int)$params['id']);
        if (!$equipmentType) {
            http_response_code(404);
            View::render('errors/404', []);
            return;
        }
        View::render('equipment_types/form', [
            'title' => 'Редактирование типа оборудования',
            'equipmentType' => $equipmentType,
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
        $old = EquipmentType::findRaw($id);
        if (!$old) {
            http_response_code(404);
            View::render('errors/404', []);
            return;
        }

        [$data, $validator] = $this->validate($_POST);
        if ($validator->fails()) {
            View::render('equipment_types/form', [
                'title' => 'Редактирование типа оборудования',
                'equipmentType' => array_merge($old, $_POST, ['id' => $id]),
                'errors' => $validator->errors(),
            ]);
            return;
        }

        $data['updated_by'] = Auth::id();
        EquipmentType::update($id, $data);
        AuditLogger::logUpdate('equipment_type', $id, $old, $data);

        Flash::success('Изменения сохранены');
        header('Location: /equipment-types/' . $id);
    }

    public function destroy(array $params): void
    {
        if (!Auth::requireAdmin()) {
            return;
        }
        Csrf::verifyRequestOrFail();

        $id = (int)$params['id'];
        $old = EquipmentType::findRaw($id);
        if ($old) {
            AuditLogger::logDelete('equipment_type', $id, $old);
            EquipmentType::delete($id);
            Flash::success('Тип оборудования удалён');
        }
        header('Location: /equipment-types');
    }

    private function validate(array $input): array
    {
        $v = new Validator($input);
        $v->required('name', 'Название')->maxLength('name', 150, 'Название')
            ->required('category', 'Категория')->in('category', EquipmentType::CATEGORIES, 'Категория')
            ->maxLength('model', 150, 'Модель')
            ->maxLength('brand', 150, 'Бренд');

        $data = [
            'category' => $v->get('category', 'ip_phone'),
            'name' => $v->get('name'),
            'model' => $v->get('model'),
            'brand' => $v->get('brand'),
        ];

        return [$data, $v];
    }
}
