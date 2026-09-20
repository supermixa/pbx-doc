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
            <div class="col-md-4">
                <label class="form-label">Тип *</label>
                <select name="type" class="form-select">
                    <?php foreach (ExtensionNumber::TYPES as $t): ?>
                        <option value="<?= $t ?>" <?= ($extension['type'] ?? 'internal') === $t ? 'selected' : '' ?>><?= View::e(Labels::extensionTypeLabel($t)) ?></option>
                    <?php endforeach; ?>
                </select>
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

            <div class="col-md-6" id="department-field">
                <label class="form-label">Подразделение</label>
                <select name="department_id" class="form-select">
                    <option value="">— не выбрано —</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= (int)$d['id'] ?>" <?= (int)($extension['department_id'] ?? 0) === (int)$d['id'] ? 'selected' : '' ?>><?= View::e($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Контекст диалплана</label>
                <input type="text" name="context" class="form-control" value="<?= View::e($extension['context'] ?? '') ?>" placeholder="internal">
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
    function toggleDepartmentField() {
        departmentField.style.display = categorySelect.value === 'internal' ? '' : 'none';
    }
    categorySelect.addEventListener('change', toggleDepartmentField);
    toggleDepartmentField();
})();
</script>
