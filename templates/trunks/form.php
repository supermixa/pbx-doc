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
                <select name="provider_id" class="form-select <?= isset($errors['provider_id']) ? 'is-invalid' : '' ?>">
                    <option value="">— не выбран —</option>
                    <?php foreach ($providers as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" <?= (string)($trunk['provider_id'] ?? '') === (string)$p['id'] ? 'selected' : '' ?>><?= View::e($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['provider_id'])): ?><div class="invalid-feedback"><?= View::e($errors['provider_id']) ?></div><?php endif; ?>
                <?php if (empty($providers)): ?>
                    <div class="form-text">Справочник пуст — сначала добавьте провайдера в разделе <a href="/providers">«Провайдеры»</a>.</div>
                <?php endif; ?>
                <?php if ($isEdit && !empty($trunk['provider']) && empty($trunk['provider_id'])): ?>
                    <div class="alert alert-warning py-2 px-3 mt-2 mb-0 small">
                        Ранее провайдер был введён вручную: <strong><?= View::e($trunk['provider']) ?></strong>.
                        Выберите его из справочника — после сохранения старое значение удалится.
                        Если такого провайдера нет, добавьте его в <a href="/providers/create" target="_blank">«Провайдеры»</a>.
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="checkbox" name="clear_legacy_provider" value="1" id="clear-legacy-provider" <?= !empty($trunk['clear_legacy_provider']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="clear-legacy-provider">Удалить старое значение, не выбирая провайдера</label>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <label class="form-label">Хост / адрес</label>
                <input type="text" name="host" class="form-control" value="<?= View::e($trunk['host'] ?? '') ?>" placeholder="sip.provider.ru:5060">
            </div>

            <div class="col-md-6">
                <label class="form-label">Контекст диалплана</label>
                <input type="text" name="context" class="form-control" value="<?= View::e($trunk['context'] ?? '') ?>" placeholder="from-trunk">
            </div>
            <div class="col-md-3">
                <label class="form-label">Емкость СЛ</label>
                <input type="number" name="line_capacity" min="0" max="999999" step="1" inputmode="numeric"
                       class="form-control <?= isset($errors['line_capacity']) ? 'is-invalid' : '' ?>"
                       value="<?= View::e((string)($trunk['line_capacity'] ?? '')) ?>"
                       onkeydown="if (['e', 'E', '+', '-', '.', ','].includes(event.key)) event.preventDefault();">
                <?php if (isset($errors['line_capacity'])): ?><div class="invalid-feedback"><?= View::e($errors['line_capacity']) ?></div><?php else: ?><div class="form-text">Число соединительных линий</div><?php endif; ?>
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
