<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\AuditLogger;
use App\Core\Csrf;
use App\Core\Export\Exporter;
use App\Core\ExtensionTable;
use App\Core\Flash;
use App\Core\Validator;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\ExtensionNumber;

class DepartmentController
{
    public function index(): void
    {
        if (!Auth::requireLogin()) {
            return;
        }
        $search = trim($_GET['q'] ?? '');
        View::render('departments/index', [
            'title' => 'Подразделения',
            'departments' => Department::all($search),
            'search' => $search,
        ]);
    }

    public function show(array $params): void
    {
        if (!Auth::requireLogin()) {
            return;
        }
        $department = Department::find((int)$params['id']);
        if (!$department) {
            http_response_code(404);
            View::render('errors/404', []);
            return;
        }
        View::render('departments/show', [
            'title' => $department['name'],
            'department' => $department,
            'numberGroups' => ExtensionTable::groupByType(ExtensionNumber::forDepartment((int)$department['id'])),
            'history' => AuditLog::forEntity('department', (int)$department['id']),
        ]);
    }

    /**
     * Выгрузка номеров подразделения (по типам) в Excel или PDF:
     * /departments/{id}/export?format=xlsx|pdf
     */
    public function export(array $params): void
    {
        if (!Auth::requireLogin()) {
            return;
        }
        $department = Department::find((int)$params['id']);
        if (!$department) {
            http_response_code(404);
            View::render('errors/404', []);
            return;
        }
        $format = ($_GET['format'] ?? 'xlsx') === 'pdf' ? 'pdf' : 'xlsx';
        $rows = ExtensionNumber::forDepartment((int)$department['id']);

        $sections = [];
        foreach (ExtensionTable::groupByType($rows) as $g) {
            $sections[] = ExtensionTable::section(
                $g['label'] . ' (' . count($g['rows']) . ')',
                ExtensionTable::columnsForGroup($g['category'], $g['type'], ['department']),
                $g['rows']
            );
        }
        $title = 'Подразделение «' . $department['name'] . '» — номера';
        Exporter::send($format, 'Номера — ' . $department['name'], $title, 'Всего номеров: ' . count($rows), $sections);
    }

    public function create(): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        View::render('departments/form', [
            'title' => 'Новое подразделение',
            'department' => null,
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
            View::render('departments/form', [
                'title' => 'Новое подразделение',
                'department' => $_POST,
                'errors' => $validator->errors(),
            ]);
            return;
        }

        $data['created_by'] = Auth::id();
        $id = Department::create($data);
        AuditLogger::logCreate('department', $id, $data);

        Flash::success('Подразделение создано');
        header('Location: /departments/' . $id);
    }

    public function edit(array $params): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        $department = Department::find((int)$params['id']);
        if (!$department) {
            http_response_code(404);
            View::render('errors/404', []);
            return;
        }
        View::render('departments/form', [
            'title' => 'Редактирование подразделения',
            'department' => $department,
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
        $old = Department::findRaw($id);
        if (!$old) {
            http_response_code(404);
            View::render('errors/404', []);
            return;
        }

        [$data, $validator] = $this->validate($_POST);
        if ($validator->fails()) {
            View::render('departments/form', [
                'title' => 'Редактирование подразделения',
                'department' => array_merge($old, $_POST, ['id' => $id]),
                'errors' => $validator->errors(),
            ]);
            return;
        }

        $data['updated_by'] = Auth::id();
        Department::update($id, $data);
        AuditLogger::logUpdate('department', $id, $old, $data);

        Flash::success('Изменения сохранены');
        header('Location: /departments/' . $id);
    }

    public function destroy(array $params): void
    {
        if (!Auth::requireAdmin()) {
            return;
        }
        Csrf::verifyRequestOrFail();

        $id = (int)$params['id'];
        $old = Department::findRaw($id);
        if ($old) {
            AuditLogger::logDelete('department', $id, $old);
            Department::delete($id);
            Flash::success('Подразделение удалено');
        }
        header('Location: /departments');
    }

    private function validate(array $input): array
    {
        $v = new Validator($input);
        $v->required('name', 'Название')->maxLength('name', 150, 'Название');

        $data = [
            'name' => $v->get('name'),
            'description' => $v->get('description'),
        ];

        return [$data, $v];
    }
}
