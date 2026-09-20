<?php
use App\Core\Csrf;
use App\Core\View;

$isEdit = !empty($legalEntity['id']);
$action = $isEdit ? '/legal-entities/' . (int)$legalEntity['id'] . '/edit' : '/legal-entities';
?>
<h3 class="mb-3"><?= View::e($title) ?></h3>

<form method="post" action="<?= $action ?>" class="card">
    <div class="card-body">
        <?= Csrf::field() ?>

        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label">Наименование *</label>
                <input type="text" name="name" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" value="<?= View::e($legalEntity['name'] ?? '') ?>" required>
                <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= View::e($errors['name']) ?></div><?php endif; ?>
            </div>
            <div class="col-md-4">
                <label class="form-label">ИНН</label>
                <input type="text" name="inn" class="form-control <?= isset($errors['inn']) ? 'is-invalid' : '' ?>" value="<?= View::e($legalEntity['inn'] ?? '') ?>">
                <?php if (isset($errors['inn'])): ?><div class="invalid-feedback"><?= View::e($errors['inn']) ?></div><?php endif; ?>
            </div>
        </div>
    </div>
    <div class="card-footer bg-white d-flex gap-2">
        <button type="submit" class="btn btn-primary">Сохранить</button>
        <a href="<?= $isEdit ? '/legal-entities/' . (int)$legalEntity['id'] : '/legal-entities' ?>" class="btn btn-outline-secondary">Отмена</a>
    </div>
</form>
