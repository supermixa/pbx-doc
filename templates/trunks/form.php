<?php
use App\Core\Csrf;
use App\Core\Labels;
use App\Core\View;
use App\Models\Trunk;

$isEdit = !empty($trunk['id']);
$action = $isEdit ? '/trunks/' . (int)$trunk['id'] . '/edit' : '/trunks';
?>
<h3 class="mb-3"><?= View::e($title) ?></h3>

<form method="post" action="<?= $action ?>" class="card">
    <div class="card-body">
        <?= Csrf::field() ?>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Название *</label>
                <input type="text" name="name" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" value="<?= View::e($trunk['name'] ?? '') ?>" required>
                <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= View::e($errors['name']) ?></div><?php endif; ?>
            </div>
            <div class="col-md-3">
                <label class="form-label">Тип *</label>
                <select name="type" class="form-select <?= isset($errors['type']) ? 'is-invalid' : '' ?>">
                    <?php foreach (Trunk::TYPES as $t): ?>
                        <option value="<?= $t ?>" <?= ($trunk['type'] ?? 'PJSIP') === $t ? 'selected' : '' ?>><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Статус</label>
                <select name="status" class="form-select">
                    <?php foreach (Trunk::STATUSES as $s): ?>
                        <option value="<?= $s ?>" <?= ($trunk['status'] ?? 'active') === $s ? 'selected' : '' ?>><?= View::e(Labels::statusLabel($s)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label">Провайдер</label>
                <input type="text" name="provider" class="form-control" value="<?= View::e($trunk['provider'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Хост / адрес</label>
                <input type="text" name="host" class="form-control" value="<?= View::e($trunk['host'] ?? '') ?>" placeholder="sip.provider.ru:5060">
            </div>

            <div class="col-md-6">
                <label class="form-label">Контекст диалплана</label>
                <input type="text" name="context" class="form-control" value="<?= View::e($trunk['context'] ?? '') ?>" placeholder="from-trunk">
            </div>

            <div class="col-12">
                <label class="form-label">Описание</label>
                <textarea name="description" class="form-control" rows="5" placeholder="Назначение транка, особенности настройки, контакты провайдера..."><?= View::e($trunk['description'] ?? '') ?></textarea>
            </div>
        </div>
    </div>
    <div class="card-footer bg-white d-flex gap-2">
        <button type="submit" class="btn btn-primary">Сохранить</button>
        <a href="<?= $isEdit ? '/trunks/' . (int)$trunk['id'] : '/trunks' ?>" class="btn btn-outline-secondary">Отмена</a>
    </div>
</form>
