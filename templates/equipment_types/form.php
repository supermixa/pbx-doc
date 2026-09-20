<?php
use App\Core\Csrf;
use App\Core\Labels;
use App\Core\View;
use App\Models\EquipmentType;

$isEdit = !empty($equipmentType['id']);
$action = $isEdit ? '/equipment-types/' . (int)$equipmentType['id'] . '/edit' : '/equipment-types';
?>
<h3 class="mb-3"><?= View::e($title) ?></h3>

<form method="post" action="<?= $action ?>" class="card">
    <div class="card-body">
        <?= Csrf::field() ?>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Название *</label>
                <input type="text" name="name" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" value="<?= View::e($equipmentType['name'] ?? '') ?>" required>
                <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= View::e($errors['name']) ?></div><?php endif; ?>
            </div>
            <div class="col-md-6">
                <label class="form-label">Категория *</label>
                <select name="category" class="form-select <?= isset($errors['category']) ? 'is-invalid' : '' ?>">
                    <?php foreach (EquipmentType::CATEGORIES as $c): ?>
                        <option value="<?= $c ?>" <?= ($equipmentType['category'] ?? 'ip_phone') === $c ? 'selected' : '' ?>><?= View::e(Labels::equipmentCategoryLabel($c)) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['category'])): ?><div class="invalid-feedback"><?= View::e($errors['category']) ?></div><?php endif; ?>
            </div>

            <div class="col-md-6">
                <label class="form-label">Модель</label>
                <input type="text" name="model" class="form-control" value="<?= View::e($equipmentType['model'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Бренд</label>
                <input type="text" name="brand" class="form-control" value="<?= View::e($equipmentType['brand'] ?? '') ?>">
            </div>
        </div>
    </div>
    <div class="card-footer bg-white d-flex gap-2">
        <button type="submit" class="btn btn-primary">Сохранить</button>
        <a href="<?= $isEdit ? '/equipment-types/' . (int)$equipmentType['id'] : '/equipment-types' ?>" class="btn btn-outline-secondary">Отмена</a>
    </div>
</form>
