<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\AuditLogger;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Validator;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\ExtensionNumber;
use App\Models\LegalEntity;
use App\Models\Provider;
use App\Models\Scenario;
use App\Models\Trunk;

class ExtensionController
{
    public function index(): void
    {
        if (!Auth::requireLogin()) {
            return;
        }
        $search = trim($_GET['q'] ?? '');
        $category = trim($_GET['category'] ?? '');
        View::render('extensions/index', [
            'title' => 'Номера',
            'extensions' => ExtensionNumber::all($search, $category),
            'search' => $search,
            'category' => $category,
            'countInternal' => ExtensionNumber::countByCategory('internal'),
            'countExternal' => ExtensionNumber::countByCategory('external'),
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
        View::render('extensions/form', array_merge([
            'title' => 'Новый номер',
            'extension' => null,
            'errors' => [],
        ], $this->formOptions()));
    }

    public function store(): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        Csrf::verifyRequestOrFail();

        [$data, $validator] = $this->validate($_POST);
        if ($validator->fails()) {
            View::render('extensions/form', array_merge([
                'title' => 'Новый номер',
                'extension' => $_POST,
                'errors' => $validator->errors(),
            ], $this->formOptions()));
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
        View::render('extensions/form', array_merge([
            'title' => 'Редактирование номера',
            'extension' => $extension,
            'errors' => [],
        ], $this->formOptions()));
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
            View::render('extensions/form', array_merge([
                'title' => 'Редактирование номера',
                'extension' => array_merge($old, $_POST, ['id' => $id]),
                'errors' => $validator->errors(),
            ], $this->formOptions()));
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

    /** Справочники, нужные форме создания/редактирования номера */
    private function formOptions(): array
    {
        return [
            'scenarios' => Scenario::options(),
            'departments' => Department::options(),
            'legalEntities' => LegalEntity::options(),
            'providers' => Provider::optionsWithLegalEntities(),
            'trunks' => Trunk::options(),
        ];
    }

    private function validate(array $input): array
    {
        $v = new Validator($input);
        $v->required('number', 'Номер')
            ->maxLength('number', 50, 'Номер')
            ->required('category', 'Категория')
            ->in('category', ExtensionNumber::CATEGORIES, 'Категория');

        $category = $v->get('category', 'internal');
        $allowedTypes = $category === 'external' ? ExtensionNumber::EXTERNAL_TYPES : ExtensionNumber::INTERNAL_TYPES;
        $defaultType = $category === 'external' ? 'did_city' : 'internal';

        $v->required('type', 'Тип')
            ->in('type', $allowedTypes, 'Тип')
            ->maxLength('owner', 150, 'Владелец')
            ->maxLength('context', 100, 'Контекст')
            ->in('status', ExtensionNumber::STATUSES, 'Статус');

        $scenarioId = $input['scenario_id'] ?? '';
        $scenarioId = ($scenarioId === '' || $scenarioId === null) ? null : (int)$scenarioId;

        $departmentId = $input['department_id'] ?? '';
        $departmentId = ($departmentId === '' || $departmentId === null) ? null : (int)$departmentId;

        $legalEntityId = $input['legal_entity_id'] ?? '';
        $legalEntityId = ($legalEntityId === '' || $legalEntityId === null) ? null : (int)$legalEntityId;

        $providerId = $input['provider_id'] ?? '';
        $providerId = ($providerId === '' || $providerId === null) ? null : (int)$providerId;

        $trunkId = $input['trunk_id'] ?? '';
        $trunkId = ($trunkId === '' || $trunkId === null) ? null : (int)$trunkId;

        // Поля привязаны к соответствующей категории — если категория не та, значение не сохраняем
        if ($category !== 'internal') {
            $departmentId = null;
        }
        if ($category !== 'external') {
            $legalEntityId = null;
            $providerId = null;
            $trunkId = null;
        }

        $data = [
            'number' => $v->get('number'),
            'category' => $category,
            'type' => $v->get('type', $defaultType) ?: $defaultType,
            'owner' => $v->get('owner'),
            'context' => $v->get('context'),
            'scenario_id' => $scenarioId,
            'department_id' => $departmentId,
            'legal_entity_id' => $legalEntityId,
            'provider_id' => $providerId,
            'trunk_id' => $trunkId,
            'status' => $v->get('status', 'active') ?: 'active',
            'description' => $v->get('description'),
        ];

        return [$data, $v];
    }
}
