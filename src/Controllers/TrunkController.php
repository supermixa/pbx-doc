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
use App\Models\Provider;
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
            'extensions' => ExtensionNumber::forTrunk((int)$trunk['id']),
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
            'providers' => Provider::options(),
        ]);
    }

    public function store(): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        Csrf::verifyRequestOrFail();

        [$data, $validator] = $this->validate($_POST, null);
        if ($validator->fails()) {
            View::render('trunks/form', [
                'title' => 'Новый транк',
                'trunk' => $_POST,
                'errors' => $validator->errors(),
                'providers' => Provider::options(),
            ]);
            return;
        }

        $data['created_by'] = Auth::id();
        $id = Trunk::create($data);
        AuditLogger::logCreate('trunk', $id, $this->forAudit($data, false));

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
            'providers' => Provider::options(),
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

        [$data, $validator] = $this->validate($_POST, $old);
        if ($validator->fails()) {
            View::render('trunks/form', [
                'title' => 'Редактирование транка',
                // provider (текст, введённый вручную) в форме не редактируется —
                // берём из БД, чтобы подсказка о нём не пропала после ошибки.
                'trunk' => array_merge($old, $_POST, ['id' => $id, 'provider' => $old['provider']]),
                'errors' => $validator->errors(),
                'providers' => Provider::options(),
            ]);
            return;
        }

        $data['updated_by'] = Auth::id();
        Trunk::update($id, $data);
        $keepLegacy = !empty($old['provider']) || !empty($data['provider']);
        AuditLogger::logUpdate('trunk', $id, $this->forAudit($old, $keepLegacy), $this->forAudit($data, $keepLegacy));

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
            AuditLogger::logDelete('trunk', $id, $this->forAudit($old, !empty($old['provider'])));
            Trunk::delete($id);
            Flash::success('Транк удалён');
        }
        header('Location: /trunks');
    }

    /**
     * @param array|null $old текущая запись (при редактировании) — нужна, чтобы
     *                        сохранить/очистить старый текст провайдера.
     * @return array{0: array, 1: Validator}
     */
    private function validate(array $input, ?array $old): array
    {
        $v = new Validator($input);
        $v->required('name', 'Название')
            ->maxLength('name', 150, 'Название')
            ->required('type', 'Тип')
            ->in('type', Trunk::TYPES, 'Тип')
            ->maxLength('host', 190, 'Хост')
            ->maxLength('context', 100, 'Контекст')
            ->in('status', Trunk::STATUSES, 'Статус');

        // Провайдер — только из справочника
        $providerId = null;
        $rawProvider = trim((string)($input['provider_id'] ?? ''));
        if ($rawProvider !== '') {
            if (ctype_digit($rawProvider) && Provider::findRaw((int)$rawProvider)) {
                $providerId = (int)$rawProvider;
            } else {
                $v->addError('provider_id', 'Выберите провайдера из справочника');
            }
        }

        // «Емкость СЛ» — только целое неотрицательное число (необязательное)
        $lineCapacity = null;
        $rawCapacity = trim((string)($input['line_capacity'] ?? ''));
        if ($rawCapacity !== '') {
            if (preg_match('/^\d{1,6}$/', $rawCapacity)) {
                $lineCapacity = (int)$rawCapacity;
            } else {
                $v->addError('line_capacity', 'Поле «Емкость СЛ» — только целое число (от 0 до 999999)');
            }
        }

        // Старый текст провайдера, введённый вручную, больше не редактируется:
        // очищается, как только выбран провайдер из справочника (или по
        // галочке «удалить»), иначе сохраняется как есть, чтобы не потерять.
        $legacyProvider = null;
        if ($old !== null && $providerId === null && empty($input['clear_legacy_provider'])) {
            $legacyProvider = $old['provider'] ?? null;
        }

        $data = [
            'name' => $v->get('name'),
            'type' => $v->get('type', 'PJSIP'),
            'provider_id' => $providerId,
            'provider' => $legacyProvider,
            'line_capacity' => $lineCapacity,
            'host' => $v->get('host'),
            'context' => $v->get('context'),
            'status' => $v->get('status', 'active') ?: 'active',
            'description' => $v->get('description'),
        ];

        return [$data, $v];
    }

    /**
     * Для журнала изменений провайдер пишется названием, а не ID — так
     * история остаётся читаемой, даже если провайдера потом переименуют
     * или удалят. Устаревшее поле provider (текст, введённый вручную)
     * попадает в журнал, только когда оно реально было/стало заполнено —
     * иначе в истории каждого нового транка висела бы пустая строка «— → —».
     */
    private function forAudit(array $row, bool $keepLegacy): array
    {
        if (!$keepLegacy) {
            unset($row['provider']);
        }
        if (array_key_exists('provider_id', $row)) {
            $id = (int)($row['provider_id'] ?? 0);
            $provider = $id > 0 ? Provider::findRaw($id) : null;
            $row['provider_id'] = $provider ? $provider['name'] : ($id > 0 ? "#{$id}" : null);
        }
        return $row;
    }
}
