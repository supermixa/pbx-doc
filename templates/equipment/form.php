<?php
use App\Core\Csrf;
use App\Core\Labels;
use App\Core\View;

$isEdit = !empty($item['id']);
$action = $isEdit ? '/equipment/' . (int)$item['id'] . '/edit' : '/equipment';
?>
<h3 class="mb-3"><?= View::e($title) ?></h3>

<form method="post" action="<?= $action ?>" class="card">
    <div class="card-body">
        <?= Csrf::field() ?>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Расположение</label>
                <input type="text" name="location" class="form-control <?= isset($errors['location']) ? 'is-invalid' : '' ?>" value="<?= View::e($item['location'] ?? '') ?>" placeholder="Офис, этаж, стойка...">
                <?php if (isset($errors['location'])): ?><div class="invalid-feedback"><?= View::e($errors['location']) ?></div><?php endif; ?>
            </div>
            <div class="col-md-6">
                <label class="form-label">Тип</label>
                <select name="equipment_type_id" class="form-select">
                    <option value="">— не выбран —</option>
                    <?php foreach ($equipmentTypes as $et): ?>
                        <?php
                        $label = $et['name'];
                        $extra = trim(($et['model'] ?? '') . ' ' . ($et['brand'] ?? ''));
                        if ($extra !== '') {
                            $label .= ' (' . $extra . ')';
                        }
                        $label .= ' — ' . Labels::equipmentCategoryLabel($et['category']);
                        ?>
                        <option value="<?= (int)$et['id'] ?>" <?= (int)($item['equipment_type_id'] ?? 0) === (int)$et['id'] ? 'selected' : '' ?>><?= View::e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label">IP</label>
                <input type="text" name="ip_address" class="form-control <?= isset($errors['ip_address']) ? 'is-invalid' : '' ?>" value="<?= View::e($item['ip_address'] ?? '') ?>" placeholder="192.168.1.10">
                <?php if (isset($errors['ip_address'])): ?><div class="invalid-feedback"><?= View::e($errors['ip_address']) ?></div><?php endif; ?>
            </div>

            <div class="col-12">
                <label class="form-label">Описание</label>
                <textarea name="description" class="form-control" rows="4"><?= View::e($item['description'] ?? '') ?></textarea>
            </div>
        </div>
    </div>
    <div class="card-footer bg-white d-flex gap-2">
        <button type="submit" class="btn btn-primary">Сохранить</button>
        <a href="<?= $isEdit ? '/equipment/' . (int)$item['id'] : '/equipment' ?>" class="btn btn-outline-secondary">Отмена</a>
    </div>
</form>
