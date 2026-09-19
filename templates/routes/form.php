<?php
use App\Core\Csrf;
use App\Core\Labels;
use App\Core\View;
use App\Models\RouteEntity;

$isEdit = !empty($route['id']);
$action = $isEdit ? '/routes/' . (int)$route['id'] . '/edit' : '/routes';
?>
<h3 class="mb-3"><?= View::e($title) ?></h3>

<form method="post" action="<?= $action ?>" class="card">
    <div class="card-body">
        <?= Csrf::field() ?>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Название *</label>
                <input type="text" name="name" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" value="<?= View::e($route['name'] ?? '') ?>" required>
                <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= View::e($errors['name']) ?></div><?php endif; ?>
            </div>
            <div class="col-md-3">
                <label class="form-label">Направление *</label>
                <select name="direction" class="form-select">
                    <?php foreach (RouteEntity::DIRECTIONS as $d): ?>
                        <option value="<?= $d ?>" <?= ($route['direction'] ?? 'outbound') === $d ? 'selected' : '' ?>><?= View::e(Labels::directionLabel($d)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Транк</label>
                <select name="trunk_id" class="form-select">
                    <option value="">— не выбран —</option>
                    <?php foreach ($trunks as $t): ?>
                        <option value="<?= (int)$t['id'] ?>" <?= (int)($route['trunk_id'] ?? 0) === (int)$t['id'] ? 'selected' : '' ?>><?= View::e($t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label">Шаблон номера</label>
                <input type="text" name="pattern" class="form-control" value="<?= View::e($route['pattern'] ?? '') ?>" placeholder="_8ZZZZZZZZZZ или _XXX">
            </div>
            <div class="col-md-6">
                <label class="form-label">Контекст диалплана</label>
                <input type="text" name="context" class="form-control" value="<?= View::e($route['context'] ?? '') ?>" placeholder="outbound-office">
            </div>

            <div class="col-12">
                <label class="form-label">Описание</label>
                <textarea name="description" class="form-control" rows="5" placeholder="Логика маршрутизации, приоритеты, условия..."><?= View::e($route['description'] ?? '') ?></textarea>
            </div>
        </div>
    </div>
    <div class="card-footer bg-white d-flex gap-2">
        <button type="submit" class="btn btn-primary">Сохранить</button>
        <a href="<?= $isEdit ? '/routes/' . (int)$route['id'] : '/routes' ?>" class="btn btn-outline-secondary">Отмена</a>
    </div>
</form>
