<?php
use App\Core\Csrf;
use App\Core\Labels;
use App\Core\View;
use App\Models\ExtensionNumber;

$isEdit = !empty($extension['id']);
$action = $isEdit ? '/extensions/' . (int)$extension['id'] . '/edit' : '/extensions';
?>
<h3 class="mb-3"><?= View::e($title) ?></h3>

<form method="post" action="<?= $action ?>" class="card">
    <div class="card-body">
        <?= Csrf::field() ?>

        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Номер *</label>
                <input type="text" name="number" class="form-control <?= isset($errors['number']) ? 'is-invalid' : '' ?>" value="<?= View::e($extension['number'] ?? '') ?>" required>
                <?php if (isset($errors['number'])): ?><div class="invalid-feedback"><?= View::e($errors['number']) ?></div><?php endif; ?>
            </div>
            <div class="col-md-4">
                <label class="form-label">Категория *</label>
                <select name="category" id="category-select" class="form-select <?= isset($errors['category']) ? 'is-invalid' : '' ?>">
                    <?php foreach (ExtensionNumber::CATEGORIES as $c): ?>
                        <option value="<?= $c ?>" <?= ($extension['category'] ?? 'internal') === $c ? 'selected' : '' ?>><?= View::e(Labels::extensionCategoryLabel($c)) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['category'])): ?><div class="invalid-feedback"><?= View::e($errors['category']) ?></div><?php endif; ?>
            </div>

            <div class="col-md-4" id="type-internal-field">
                <label class="form-label">Тип *</label>
                <select name="type" id="type-internal-select" class="form-select <?= isset($errors['type']) ? 'is-invalid' : '' ?>">
                    <?php foreach (ExtensionNumber::INTERNAL_TYPES as $t): ?>
                        <option value="<?= $t ?>" <?= ($extension['type'] ?? 'internal') === $t ? 'selected' : '' ?>><?= View::e(Labels::extensionTypeLabel($t)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4" id="type-external-field">
                <label class="form-label">Тип *</label>
                <select name="type" id="type-external-select" class="form-select <?= isset($errors['type']) ? 'is-invalid' : '' ?>" disabled>
                    <?php foreach (ExtensionNumber::EXTERNAL_TYPES as $t): ?>
                        <option value="<?= $t ?>" <?= ($extension['type'] ?? '') === $t ? 'selected' : '' ?>><?= View::e(Labels::extensionTypeLabel($t)) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['type'])): ?><div class="invalid-feedback d-block"><?= View::e($errors['type']) ?></div><?php endif; ?>
            </div>

            <div class="col-md-6">
                <label class="form-label">Владелец</label>
                <input type="text" name="owner" class="form-control" value="<?= View::e($extension['owner'] ?? '') ?>" placeholder="Иванов И.И. / Отдел продаж">
            </div>
            <div class="col-md-6">
                <label class="form-label">Статус</label>
                <select name="status" class="form-select">
                    <?php foreach (ExtensionNumber::STATUSES as $s): ?>
                        <option value="<?= $s ?>" <?= ($extension['status'] ?? 'active') === $s ? 'selected' : '' ?>><?= View::e(Labels::statusLabel($s)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6 internal-field" id="department-field">
                <label class="form-label">Подразделение</label>
                <select name="department_id" class="form-select">
                    <option value="">— не выбрано —</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= (int)$d['id'] ?>" <?= (int)($extension['department_id'] ?? 0) === (int)$d['id'] ? 'selected' : '' ?>><?= View::e($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4 external-field" id="legal-entity-field">
                <label class="form-label">Юрлицо</label>
                <select name="legal_entity_id" id="legal-entity-select" class="form-select">
                    <option value="">— не выбрано —</option>
                    <?php foreach ($legalEntities as $le): ?>
                        <option value="<?= (int)$le['id'] ?>" <?= (int)($extension['legal_entity_id'] ?? 0) === (int)$le['id'] ? 'selected' : '' ?>><?= View::e($le['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 external-field" id="provider-field">
                <label class="form-label">Провайдер</label>
                <select name="provider_id" id="provider-select" class="form-select">
                    <option value="">— сначала выберите юрлицо —</option>
                    <?php foreach ($providers as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" data-legal-entities="<?= View::e($p['legal_entity_ids'] ?? '') ?>" <?= (int)($extension['provider_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>><?= View::e($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 external-field" id="trunk-field">
                <label class="form-label">Транк</label>
                <select name="trunk_id" class="form-select">
                    <option value="">— не выбран —</option>
                    <?php foreach ($trunks as $t): ?>
                        <option value="<?= (int)$t['id'] ?>" <?= (int)($extension['trunk_id'] ?? 0) === (int)$t['id'] ? 'selected' : '' ?>><?= View::e($t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label">Связанный сценарий</label>
                <select name="scenario_id" class="form-select">
                    <option value="">— не выбран —</option>
                    <?php foreach ($scenarios as $s): ?>
                        <option value="<?= (int)$s['id'] ?>" <?= (int)($extension['scenario_id'] ?? 0) === (int)$s['id'] ? 'selected' : '' ?>><?= View::e($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Контекст диалплана</label>
                <input type="text" name="context" class="form-control" value="<?= View::e($extension['context'] ?? '') ?>" placeholder="internal">
            </div>

            <div class="col-md-6 external-field calltracking-field" id="calltracking-target-field">
                <label class="form-label">Цель</label>
                <select name="calltracking_target_id" id="calltracking-target-select" class="form-select">
                    <option value="">— не выбрана —</option>
                    <?php foreach ($calltrackingTargets as $ct): ?>
                        <option value="<?= (int)$ct['id'] ?>" <?= (int)($extension['calltracking_target_id'] ?? 0) === (int)$ct['id'] ? 'selected' : '' ?>><?= View::e(($ct['department_name'] ?? '') !== '' ? $ct['department_name'] . ' — ' . $ct['target'] : $ct['target']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12">
                <label class="form-label">Описание</label>
                <textarea name="description" class="form-control" rows="4"><?= View::e($extension['description'] ?? '') ?></textarea>
            </div>
        </div>
    </div>
    <div class="card-footer bg-white d-flex gap-2">
        <button type="submit" class="btn btn-primary">Сохранить</button>
        <a href="<?= $isEdit ? '/extensions/' . (int)$extension['id'] : '/extensions' ?>" class="btn btn-outline-secondary">Отмена</a>
    </div>
</form>
<script>
(function () {
    var categorySelect = document.getElementById('category-select');
    var departmentField = document.getElementById('department-field');
    var internalFields = document.querySelectorAll('.internal-field');
    var externalFields = document.querySelectorAll('.external-field');
    var typeInternalField = document.getElementById('type-internal-field');
    var typeExternalField = document.getElementById('type-external-field');
    var typeInternalSelect = document.getElementById('type-internal-select');
    var typeExternalSelect = document.getElementById('type-external-select');

    var calltrackingField = document.getElementById('calltracking-target-field');

    function toggleCalltrackingField() {
        var isExternal = categorySelect.value === 'external';
        var isCalltracking = isExternal && typeExternalSelect.value === 'calltracking';
        calltrackingField.style.display = isCalltracking ? '' : 'none';
    }

    function toggleByCategory() {
        var isExternal = categorySelect.value === 'external';

        internalFields.forEach(function (el) { el.style.display = isExternal ? 'none' : ''; });
        externalFields.forEach(function (el) { el.style.display = isExternal ? '' : 'none'; });

        typeInternalField.style.display = isExternal ? 'none' : '';
        typeExternalField.style.display = isExternal ? '' : 'none';
        typeInternalSelect.disabled = isExternal;
        typeExternalSelect.disabled = !isExternal;

        toggleCalltrackingField();
    }

    categorySelect.addEventListener('change', toggleByCategory);
    typeExternalSelect.addEventListener('change', toggleCalltrackingField);
    toggleByCategory();

    var legalEntitySelect = document.getElementById('legal-entity-select');
    var providerSelect = document.getElementById('provider-select');
    var providerOptions = Array.prototype.slice.call(providerSelect.querySelectorAll('option[value]:not([value=""])'));
    var providerPlaceholder = providerSelect.querySelector('option[value=""]');

    function filterProviders() {
        var legalEntityId = legalEntitySelect.value;
        var anyVisible = false;
        providerOptions.forEach(function (opt) {
            var ids = (opt.getAttribute('data-legal-entities') || '').split(',');
            var visible = legalEntityId !== '' && ids.indexOf(legalEntityId) !== -1;
            opt.style.display = visible ? '' : 'none';
            opt.disabled = !visible;
            if (visible) {
                anyVisible = true;
            }
        });
        if (legalEntityId === '') {
            providerPlaceholder.textContent = '— сначала выберите юрлицо —';
        } else if (!anyVisible) {
            providerPlaceholder.textContent = '— нет привязанных провайдеров —';
        } else {
            providerPlaceholder.textContent = '— не выбран —';
        }
        var currentOpt = providerSelect.options[providerSelect.selectedIndex];
        if (currentOpt && currentOpt.value !== '' && currentOpt.style.display === 'none') {
            providerSelect.value = '';
        }
    }

    legalEntitySelect.addEventListener('change', filterProviders);
    filterProviders();
})();
</script>
