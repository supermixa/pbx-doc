<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\AuditLogger;
use App\Core\Csrf;
use App\Core\Db;
use App\Core\Flash;
use App\Core\Validator;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\LegalEntity;
use App\Models\Provider;
use Throwable;

class ProviderController
{
    public function index(): void
    {
        if (!Auth::requireLogin()) {
            return;
        }
        $search = trim($_GET['q'] ?? '');
        View::render('providers/index', [
            'title' => 'Провайдеры',
            'providers' => Provider::all($search),
            'search' => $search,
        ]);
    }

    public function show(array $params): void
    {
        if (!Auth::requireLogin()) {
            return;
        }
        $id = (int)$params['id'];
        $provider = Provider::find($id);
        if (!$provider) {
            http_response_code(404);
            View::render('errors/404', []);
            return;
        }
        View::render('providers/show', [
            'title' => $provider['name'],
            'provider' => $provider,
            'managers' => Provider::managers($id),
            'legalEntityLinks' => Provider::legalEntityLinks($id),
            'history' => AuditLog::forEntity('provider', $id),
        ]);
    }

    public function create(): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        View::render('providers/form', [
            'title' => 'Новый провайдер',
            'provider' => null,
            'managerSlots' => $this->emptyManagerSlots(),
            'legalEntityRows' => [],
            'legalEntities' => LegalEntity::options(),
            'errors' => [],
        ]);
    }

    public function store(): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        Csrf::verifyRequestOrFail();

        [$data, $validator, $managerSlots, $legalEntityRowsRaw] = $this->validate($_POST);
        if ($validator->fails()) {
            View::render('providers/form', [
                'title' => 'Новый провайдер',
                'provider' => $_POST,
                'managerSlots' => $managerSlots,
                'legalEntityRows' => $legalEntityRowsRaw,
                'legalEntities' => LegalEntity::options(),
                'errors' => $validator->errors(),
            ]);
            return;
        }

        $data['created_by'] = Auth::id();

        $conn = Db::connection();
        $conn->beginTransaction();
        try {
            $id = Provider::create($data);
            $managerRows = $this->nonEmptyManagerRows($managerSlots);
            $managerIdBySortOrder = Provider::replaceManagers($id, $managerRows);
            $links = $this->resolveLinks($legalEntityRowsRaw, $managerIdBySortOrder);
            Provider::replaceLegalEntityLinks($id, $links);
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollBack();
            throw $e;
        }

        $auditData = $data;
        $auditData['managers'] = Provider::summarizeManagers($managerRows);
        $auditData['legal_entities'] = Provider::summarizeLegalEntityLinks(Provider::legalEntityLinks($id));
        AuditLogger::logCreate('provider', $id, $auditData);

        Flash::success('Провайдер создан');
        header('Location: /providers/' . $id);
    }

    public function edit(array $params): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        $id = (int)$params['id'];
        $provider = Provider::find($id);
        if (!$provider) {
            http_response_code(404);
            View::render('errors/404', []);
            return;
        }

        $managers = Provider::managers($id);
        $links = Provider::legalEntityLinks($id);

        View::render('providers/form', [
            'title' => 'Редактирование провайдера',
            'provider' => $provider,
            'managerSlots' => $this->managerSlotsFromDb($managers),
            'legalEntityRows' => $this->legalEntityRowsFromDb($links, $managers),
            'legalEntities' => LegalEntity::options(),
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
        $old = Provider::findRaw($id);
        if (!$old) {
            http_response_code(404);
            View::render('errors/404', []);
            return;
        }

        $oldManagersSummary = Provider::summarizeManagers(Provider::managers($id));
        $oldLinksSummary = Provider::summarizeLegalEntityLinks(Provider::legalEntityLinks($id));

        [$data, $validator, $managerSlots, $legalEntityRowsRaw] = $this->validate($_POST);
        if ($validator->fails()) {
            View::render('providers/form', [
                'title' => 'Редактирование провайдера',
                'provider' => array_merge($old, $_POST, ['id' => $id]),
                'managerSlots' => $managerSlots,
                'legalEntityRows' => $legalEntityRowsRaw,
                'legalEntities' => LegalEntity::options(),
                'errors' => $validator->errors(),
            ]);
            return;
        }

        $data['updated_by'] = Auth::id();

        $conn = Db::connection();
        $conn->beginTransaction();
        try {
            Provider::update($id, $data);
            $managerRows = $this->nonEmptyManagerRows($managerSlots);
            $managerIdBySortOrder = Provider::replaceManagers($id, $managerRows);
            $links = $this->resolveLinks($legalEntityRowsRaw, $managerIdBySortOrder);
            Provider::replaceLegalEntityLinks($id, $links);
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollBack();
            throw $e;
        }

        $oldForDiff = $old;
        $oldForDiff['managers'] = $oldManagersSummary;
        $oldForDiff['legal_entities'] = $oldLinksSummary;

        $newForDiff = $data;
        $newForDiff['managers'] = Provider::summarizeManagers($managerRows);
        $newForDiff['legal_entities'] = Provider::summarizeLegalEntityLinks(Provider::legalEntityLinks($id));

        AuditLogger::logUpdate('provider', $id, $oldForDiff, $newForDiff);

        Flash::success('Изменения сохранены');
        header('Location: /providers/' . $id);
    }

    public function destroy(array $params): void
    {
        if (!Auth::requireAdmin()) {
            return;
        }
        Csrf::verifyRequestOrFail();

        $id = (int)$params['id'];
        $old = Provider::findRaw($id);
        if ($old) {
            $old['managers'] = Provider::summarizeManagers(Provider::managers($id));
            $old['legal_entities'] = Provider::summarizeLegalEntityLinks(Provider::legalEntityLinks($id));
            AuditLogger::logDelete('provider', $id, $old);
            Provider::delete($id);
            Flash::success('Провайдер удалён');
        }
        header('Location: /providers');
    }

    // ------------------------------------------------------------------
    // Вспомогательные методы
    // ------------------------------------------------------------------

    /**
     * @return array{0: array, 1: Validator, 2: array, 3: array}
     */
    private function validate(array $input): array
    {
        $v = new Validator($input);
        $v->required('name', 'Наименование')->maxLength('name', 190, 'Наименование')
            ->maxLength('contract_number', 100, '№ договора')
            ->maxLength('support_phone', 50, 'Телефон ТП');

        $managerSlots = [];
        $names = $input['manager_name'] ?? [];
        $phones = $input['manager_phone'] ?? [];
        for ($i = 0; $i < Provider::MAX_MANAGERS; $i++) {
            $name = trim((string)($names[$i] ?? ''));
            $phone = trim((string)($phones[$i] ?? ''));
            if (mb_strlen($name) > 150) {
                $v->addError('manager_name_' . $i, 'Имя менеджера ' . ($i + 1) . ' не должно превышать 150 символов');
            }
            if (mb_strlen($phone) > 50) {
                $v->addError('manager_phone_' . $i, 'Телефон менеджера ' . ($i + 1) . ' не должен превышать 50 символов');
            }
            $managerSlots[$i] = ['name' => $name, 'phone' => $phone];
        }

        $legalEntityIdsRaw = $input['legal_entity_id'] ?? [];
        $managerPositionsRaw = $input['legal_entity_manager'] ?? [];
        $legalEntityRowsRaw = [];
        $seen = [];
        foreach ($legalEntityIdsRaw as $i => $rawId) {
            $legalEntityId = trim((string)$rawId);
            $managerPosition = trim((string)($managerPositionsRaw[$i] ?? ''));
            if ($legalEntityId === '') {
                continue;
            }
            $legalEntityRowsRaw[] = ['legal_entity_id' => $legalEntityId, 'manager_position' => $managerPosition];
            if (isset($seen[$legalEntityId])) {
                $v->addError('legal_entities', 'Одно и то же юрлицо привязано к провайдеру более одного раза');
            }
            $seen[$legalEntityId] = true;
        }

        $data = [
            'name' => $v->get('name'),
            'contract_number' => $v->get('contract_number'),
            'support_phone' => $v->get('support_phone'),
            'note' => $v->get('note'),
        ];

        return [$data, $v, $managerSlots, $legalEntityRowsRaw];
    }

    /** Убирает полностью пустые слоты менеджеров, сохраняя позицию (sort_order) */
    private function nonEmptyManagerRows(array $managerSlots): array
    {
        $rows = [];
        foreach ($managerSlots as $i => $slot) {
            $name = $slot['name'] ?? '';
            $phone = $slot['phone'] ?? '';
            if ($name === '' && $phone === '') {
                continue;
            }
            $rows[] = ['sort_order' => $i, 'name' => $name !== '' ? $name : null, 'phone' => $phone !== '' ? $phone : null];
        }
        return $rows;
    }

    /** Превращает "сырые" строки привязки юрлиц в данные для Provider::replaceLegalEntityLinks() */
    private function resolveLinks(array $legalEntityRowsRaw, array $managerIdBySortOrder): array
    {
        $links = [];
        $seen = [];
        foreach ($legalEntityRowsRaw as $row) {
            $legalEntityId = (int)$row['legal_entity_id'];
            if ($legalEntityId <= 0 || isset($seen[$legalEntityId])) {
                continue;
            }
            $seen[$legalEntityId] = true;
            $pos = $row['manager_position'];
            $managerId = ($pos !== '' && isset($managerIdBySortOrder[(int)$pos])) ? $managerIdBySortOrder[(int)$pos] : null;
            $links[] = ['legal_entity_id' => $legalEntityId, 'manager_id' => $managerId];
        }
        return $links;
    }

    private function emptyManagerSlots(): array
    {
        $slots = [];
        for ($i = 0; $i < Provider::MAX_MANAGERS; $i++) {
            $slots[$i] = ['name' => '', 'phone' => ''];
        }
        return $slots;
    }

    /** @param array $managers строки из Provider::managers() */
    private function managerSlotsFromDb(array $managers): array
    {
        $slots = $this->emptyManagerSlots();
        foreach ($managers as $m) {
            $i = (int)$m['sort_order'];
            if ($i >= 0 && $i < Provider::MAX_MANAGERS) {
                $slots[$i] = ['name' => (string)($m['name'] ?? ''), 'phone' => (string)($m['phone'] ?? '')];
            }
        }
        return $slots;
    }

    /**
     * @param array $links    строки из Provider::legalEntityLinks()
     * @param array $managers строки из Provider::managers()
     */
    private function legalEntityRowsFromDb(array $links, array $managers): array
    {
        $rows = [];
        foreach ($links as $l) {
            $position = '';
            foreach ($managers as $m) {
                if ((int)$m['id'] === (int)($l['manager_id'] ?? 0)) {
                    $position = (string)$m['sort_order'];
                    break;
                }
            }
            $rows[] = ['legal_entity_id' => (string)$l['legal_entity_id'], 'manager_position' => $position];
        }
        return $rows;
    }
}
