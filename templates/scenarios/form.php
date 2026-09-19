<?php
use App\Core\Csrf;
use App\Core\View;

$isEdit = !empty($scenario['id']);
$action = $isEdit ? '/scenarios/' . (int)$scenario['id'] . '/edit' : '/scenarios';
?>
<h3 class="mb-3"><?= View::e($title) ?></h3>

<form method="post" action="<?= $action ?>" class="card">
    <div class="card-body">
        <?= Csrf::field() ?>

        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label">Название *</label>
                <input type="text" name="name" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" value="<?= View::e($scenario['name'] ?? '') ?>" required>
                <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= View::e($errors['name']) ?></div><?php endif; ?>
            </div>
            <div class="col-md-4">
                <label class="form-label">Контекст диалплана</label>
                <input type="text" name="context" class="form-control" value="<?= View::e($scenario['context'] ?? '') ?>" placeholder="ivr-main">
            </div>

            <div class="col-12">
                <label class="form-label">Описание</label>
                <textarea name="description" class="form-control" rows="4" placeholder="Что делает сценарий, для какого отдела/линии..."><?= View::e($scenario['description'] ?? '') ?></textarea>
            </div>

            <div class="col-12">
                <label class="form-label">Код диалплана (extensions.conf)</label>
                <textarea name="dialplan_code" class="form-control font-monospace" rows="10" placeholder="exten => 100,1,Dial(PJSIP/100,20)&#10;exten => 100,n,Hangup()"><?= View::e($scenario['dialplan_code'] ?? '') ?></textarea>
            </div>
        </div>
    </div>
    <div class="card-footer bg-white d-flex gap-2">
        <button type="submit" class="btn btn-primary">Сохранить</button>
        <a href="<?= $isEdit ? '/scenarios/' . (int)$scenario['id'] : '/scenarios' ?>" class="btn btn-outline-secondary">Отмена</a>
    </div>
</form>
