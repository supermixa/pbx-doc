<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\AuditLogger;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Validator;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\LegalEntity;
use App\Models\Provider;

class LegalEntityController
{
    public function index(): void
    {
        if (!Auth::requireLogin()) {
            return;
        }
        $search = trim($_GET['q'] ?? '');
        View::render('legal_entities/index', [
            'title' => 'Юрлица',
            'legalEntities' => LegalEntity::all($search),
            'search' => $search,
        ]);
    }

    public function show(array $params): void
    {
        if (!Auth::requireLogin()) {
            return;
        }
        $legalEntity = LegalEntity::find((int)$params['id']);
        if (!$legalEntity) {
            http_response_code(404);
            View::render('errors/404', []);
            return;
        }
        View::render('legal_entities/show', [
            'title' => $legalEntity['name'],
            'legalEntity' => $legalEntity,
            'providers' => Provider::forLegalEntity((int)$legalEntity['id']),
            'history' => AuditLog::forEntity('legal_entity', (int)$legalEntity['id']),
        ]);
    }

    public function create(): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        View::render('legal_entities/form', [
            'title' => 'Новое юрлицо',
            'legalEntity' => null,
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
            View::render('legal_entities/form', [
                'title' => 'Новое юрлицо',
                'legalEntity' => $_POST,
                'errors' => $validator->errors(),
            ]);
            return;
        }

        $data['created_by'] = Auth::id();
        $id = LegalEntity::create($data);
        AuditLogger::logCreate('legal_entity', $id, $data);

        Flash::success('Юрлицо создано');
        header('Location: /legal-entities/' . $id);
    }

    public function edit(array $params): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        $legalEntity = LegalEntity::find((int)$params['id']);
        if (!$legalEntity) {
            http_response_code(404);
            View::render('errors/404', []);
            return;
        }
        View::render('legal_entities/form', [
            'title' => 'Редактирование юрлица',
            'legalEntity' => $legalEntity,
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
        $old = LegalEntity::findRaw($id);
        if (!$old) {
            http_response_code(404);
            View::render('errors/404', []);
            return;
        }

        [$data, $validator] = $this->validate($_POST);
        if ($validator->fails()) {
            View::render('legal_entities/form', [
                'title' => 'Редактирование юрлица',
                'legalEntity' => array_merge($old, $_POST, ['id' => $id]),
                'errors' => $validator->errors(),
            ]);
            return;
        }

        $data['updated_by'] = Auth::id();
        LegalEntity::update($id, $data);
        AuditLogger::logUpdate('legal_entity', $id, $old, $data);

        Flash::success('Изменения сохранены');
        header('Location: /legal-entities/' . $id);
    }

    public function destroy(array $params): void
    {
        if (!Auth::requireAdmin()) {
            return;
        }
        Csrf::verifyRequestOrFail();

        $id = (int)$params['id'];
        $old = LegalEntity::findRaw($id);
        if ($old) {
            AuditLogger::logDelete('legal_entity', $id, $old);
            LegalEntity::delete($id);
            Flash::success('Юрлицо удалено');
        }
        header('Location: /legal-entities');
    }

    private function validate(array $input): array
    {
        $v = new Validator($input);
        $v->required('name', 'Наименование')->maxLength('name', 190, 'Наименование')
            ->maxLength('inn', 20, 'ИНН');

        $data = [
            'name' => $v->get('name'),
            'inn' => $v->get('inn'),
        ];

        return [$data, $v];
    }
}
