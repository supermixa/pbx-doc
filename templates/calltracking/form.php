<?php
use App\Core\Csrf;
use App\Core\View;

$isEdit = !empty($target['id']);
$action = $isEdit ? '/calltracking/' . (int)$target['id'] . '/edit' : '/calltracking';
?>
<h3 class="mb-3"><?= View::e($title) ?></h3>

<form method="post" action="<?= $action ?>" class="card">
    <div class="card-body">
        <?= Csrf::field() ?>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Подразделение *</label>
                <select name="department_id" class="form-select <?= isset($errors['department_id']) ? 'is-invalid' : '' ?>">
                    <option value="">— не выбрано —</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= (int)$d['id'] ?>" <?= (int)($target['department_id'] ?? 0) === (int)$d['id'] ? 'selected' : '' ?>><?= View::e($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['department_id'])): ?><div class="invalid-feedback"><?= View::e($errors['department_id']) ?></div><?php endif; ?>
            </div>
            <div class="col-md-6">
                <label class="form-label">Цель *</label>
                <input type="text" name="target" class="form-control <?= isset($errors['target']) ? 'is-invalid' : '' ?>" value="<?= View::e($target['target'] ?? '') ?>" required>
                <?php if (isset($errors['target'])): ?><div class="invalid-feedback"><?= View::e($errors['target']) ?></div><?php endif; ?>
            </div>
        </div>
    </div>
    <div class="card-footer bg-white d-flex gap-2">
        <button type="submit" class="btn btn-primary">Сохранить</button>
        <a href="<?= $isEdit ? '/calltracking/' . (int)$target['id'] : '/calltracking' ?>" class="btn btn-outline-secondary">Отмена</a>
    </div>
</form>
