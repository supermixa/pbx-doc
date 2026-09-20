<?php
use App\Core\Csrf;
use App\Core\View;

$isEdit = !empty($provider['id']);
$action = $isEdit ? '/providers/' . (int)$provider['id'] . '/edit' : '/providers';
?>
<h3 class="mb-3"><?= View::e($title) ?></h3>

<?php if (isset($errors['legal_entities'])): ?>
    <div class="alert alert-danger"><?= View::e($errors['legal_entities']) ?></div>
<?php endif; ?>

<form method="post" action="<?= $action ?>" class="card">
    <div class="card-body">
        <?= Csrf::field() ?>

        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label">Наименование *</label>
                <input type="text" name="name" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" value="<?= View::e($provider['name'] ?? '') ?>" required>
                <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= View::e($errors['name']) ?></div><?php endif; ?>
            </div>
            <div class="col-md-4">
                <label class="form-label">Телефон ТП</label>
                <input type="text" name="support_phone" class="form-control <?= isset($errors['support_phone']) ? 'is-invalid' : '' ?>" value="<?= View::e($provider['support_phone'] ?? '') ?>">
                <?php if (isset($errors['support_phone'])): ?><div class="invalid-feedback"><?= View::e($errors['support_phone']) ?></div><?php endif; ?>
            </div>

            <div class="col-12">
                <label class="form-label">Примечание</label>
                <textarea name="note" class="form-control" rows="3"><?= View::e($provider['note'] ?? '') ?></textarea>
            </div>
        </div>

        <hr class="my-4">
        <h5 class="mb-3">Менеджеры <span class="text-muted small fw-normal">(до 3, необязательно)</span></h5>
        <div class="row g-3 mb-2">
            <?php foreach ($managerSlots as $i => $slot): ?>
                <div class="col-md-6">
                    <div class="row g-2">
                        <div class="col-7">
                            <label class="form-label small text-muted">Менеджер <?= $i + 1 ?> — имя</label>
                            <input type="text" name="manager_name[]" class="form-control manager-name-input <?= isset($errors['manager_name_' . $i]) ? 'is-invalid' : '' ?>" value="<?= View::e($slot['name']) ?>">
                            <?php if (isset($errors['manager_name_' . $i])): ?><div class="invalid-feedback"><?= View::e($errors['manager_name_' . $i]) ?></div><?php endif; ?>
                        </div>
                        <div class="col-5">
                            <label class="form-label small text-muted">Телефон</label>
                            <input type="text" name="manager_phone[]" class="form-control <?= isset($errors['manager_phone_' . $i]) ? 'is-invalid' : '' ?>" value="<?= View::e($slot['phone']) ?>">
                            <?php if (isset($errors['manager_phone_' . $i])): ?><div class="invalid-feedback"><?= View::e($errors['manager_phone_' . $i]) ?></div><?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <hr class="my-4">
        <h5 class="mb-3">Обслуживаемые юрлица</h5>
        <p class="text-muted small">Привяжите юрлица, которые обслуживает этот провайдер, и при необходимости укажите ответственного менеджера и № договора с этим юрлицом.</p>
        <div id="legal-entity-rows"></div>
        <button type="button" id="add-legal-entity-row" class="btn btn-outline-secondary btn-sm">+ Привязать юрлицо</button>
    </div>
    <div class="card-footer bg-white d-flex gap-2">
        <button type="submit" class="btn btn-primary">Сохранить</button>
        <a href="<?= $isEdit ? '/providers/' . (int)$provider['id'] : '/providers' ?>" class="btn btn-outline-secondary">Отмена</a>
    </div>
</form>

<script>
(function () {
    var LEGAL_ENTITIES = <?= json_encode(array_map(fn($le) => [
        'id' => (int)$le['id'],
        'label' => $le['name'] . (!empty($le['inn']) ? ' (ИНН ' . $le['inn'] . ')' : ''),
    ], $legalEntities), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
    var INITIAL_ROWS = <?= json_encode(array_map(fn($r) => [
        'legalEntityId' => $r['legal_entity_id'],
        'managerPosition' => $r['manager_position'],
        'contractNumber' => $r['contract_number'] ?? '',
    ], $legalEntityRows), JSON_UNESCAPED_UNICODE) ?>;

    var container = document.getElementById('legal-entity-rows');
    var addBtn = document.getElementById('add-legal-entity-row');
    var managerNameInputs = document.querySelectorAll('.manager-name-input');

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = String(str || '');
        return div.innerHTML;
    }

    function managerLabel(index) {
        var input = managerNameInputs[index];
        var name = input ? input.value.trim() : '';
        return 'Менеджер ' + (index + 1) + (name ? ' — ' + name : '');
    }

    function buildManagerOptions(selected) {
        var html = '<option value="">— без менеджера —</option>';
        for (var i = 0; i < managerNameInputs.length; i++) {
            var sel = (String(selected) === String(i)) ? ' selected' : '';
            html += '<option value="' + i + '"' + sel + '>' + escapeHtml(managerLabel(i)) + '</option>';
        }
        return html;
    }

    function buildLegalEntityOptions(selectedId) {
        var html = '<option value="">— выберите юрлицо —</option>';
        for (var i = 0; i < LEGAL_ENTITIES.length; i++) {
            var le = LEGAL_ENTITIES[i];
            var sel = (String(selectedId) === String(le.id)) ? ' selected' : '';
            html += '<option value="' + le.id + '"' + sel + '>' + escapeHtml(le.label) + '</option>';
        }
        return html;
    }

    function addRow(legalEntityId, managerPosition, contractNumber) {
        var row = document.createElement('div');
        row.className = 'row g-2 mb-2 align-items-end legal-entity-row';
        row.innerHTML =
            '<div class="col-md-4">' +
                '<label class="form-label small text-muted">Юрлицо</label>' +
                '<select name="legal_entity_id[]" class="form-select legal-entity-select">' + buildLegalEntityOptions(legalEntityId) + '</select>' +
            '</div>' +
            '<div class="col-md-3">' +
                '<label class="form-label small text-muted">Менеджер</label>' +
                '<select name="legal_entity_manager[]" class="form-select manager-position-select">' + buildManagerOptions(managerPosition) + '</select>' +
            '</div>' +
            '<div class="col-md-3">' +
                '<label class="form-label small text-muted">№ договора</label>' +
                '<input type="text" name="legal_entity_contract_number[]" class="form-control" value="' + escapeHtml(contractNumber || '') + '">' +
            '</div>' +
            '<div class="col-md-2">' +
                '<button type="button" class="btn btn-outline-danger btn-sm w-100 remove-row-btn">Удалить</button>' +
            '</div>';
        row.querySelector('.remove-row-btn').addEventListener('click', function () {
            row.remove();
        });
        container.appendChild(row);
    }

    addBtn.addEventListener('click', function () {
        addRow('', '', '');
    });

    // При изменении имени менеджера обновляем подписи во всех уже добавленных строках
    managerNameInputs.forEach(function (input) {
        input.addEventListener('input', function () {
            document.querySelectorAll('.manager-position-select').forEach(function (select) {
                var current = select.value;
                select.innerHTML = buildManagerOptions(current);
            });
        });
    });

    if (INITIAL_ROWS.length === 0) {
        addRow('', '', '');
    } else {
        INITIAL_ROWS.forEach(function (r) {
            addRow(r.legalEntityId, r.managerPosition, r.contractNumber);
        });
    }
})();
</script>
