<?php
use App\Core\Csrf;
use App\Core\View;

$isEdit = !empty($goal['id']);
$action = $isEdit ? '/calltracking/goals/' . (int)$goal['id'] . '/edit' : '/calltracking/goals';
?>
<h3 class="mb-3"><?= View::e($title) ?></h3>

<form method="post" action="<?= $action ?>" class="card">
    <div class="card-body">
        <?= Csrf::field() ?>
        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label">Цель *</label>
                <input type="text" name="name" maxlength="190" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" value="<?= View::e($goal['name'] ?? '') ?>" required>
                <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= View::e($errors['name']) ?></div><?php endif; ?>
                <?php if ($isEdit): ?><div class="form-text">Новое название сразу отобразится во всех подразделениях и номерах, где используется цель.</div><?php endif; ?>
            </div>
        </div>
    </div>
    <div class="card-footer bg-white d-flex gap-2">
        <button type="submit" class="btn btn-primary">Сохранить</button>
        <a href="<?= $isEdit ? '/calltracking/goals/' . (int)$goal['id'] : '/calltracking/goals' ?>" class="btn btn-outline-secondary">Отмена</a>
    </div>
</form>
