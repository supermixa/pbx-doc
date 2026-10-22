<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;

$isEdit = !empty($department['id']);
$action = $isEdit ? '/calltracking/departments/' . (int)$department['id'] . '/edit' : '/calltracking';

/** <option> для всех целей справочника; $selected — ID выбранной цели */
$goalOptions = function (?int $selected) use ($goals): string {
    $html = '<option value="">— выберите цель —</option>';
    foreach ($goals as $g) {
        $html .= '<option value="' . (int)$g['id'] . '"' . ((int)$g['id'] === $selected ? ' selected' : '') . '>' . View::e($g['name']) . '</option>';
    }
    return $html;
};
if (!$rows) {
    $rows = [['goal_id' => null, 'extensions_count' => 0]];
}
?>
<h3 class="mb-3"><?= View::e($title) ?></h3>

<form method="post" action="<?= $action ?>" class="card" id="calltracking-form">
    <div class="card-body">
        <?= Csrf::field() ?>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Подразделение *</label>
                <?php if ($isEdit): ?>
                    <input type="text" class="form-control" value="<?= View::e($department['name']) ?>" disabled>
                <?php else: ?>
                    <select name="department_id" class="form-select <?= isset($errors['department_id']) ? 'is-invalid' : '' ?>">
                        <option value="">— не выбрано —</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= (int)$d['id'] ?>" <?= (int)$selectedDepartmentId === (int)$d['id'] ? 'selected' : '' ?>><?= View::e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['department_id'])): ?><div class="invalid-feedback"><?= View::e($errors['department_id']) ?></div><?php endif; ?>
                    <div class="form-text">В списке только подразделения, у которых ещё нет целей.</div>
                <?php endif; ?>
            </div>

            <div class="col-md-8">
                <label class="form-label">Цели *</label>
                <div id="goal-rows">
                    <?php foreach ($rows as $row): ?>
                        <?php $locked = (int)$row['extensions_count'] > 0; ?>
                        <div class="input-group mb-2 goal-row">
                            <?php if ($locked): ?>
                                <select class="form-select goal-select" disabled><?= $goalOptions((int)$row['goal_id']) ?></select>
                                <input type="hidden" name="goal_ids[]" value="<?= (int)$row['goal_id'] ?>">
                                <span class="input-group-text" title="Цель выбрана у номеров CallTracking — убрать её нельзя, пока у номеров не сменят цель">номеров: <?= (int)$row['extensions_count'] ?></span>
                            <?php else: ?>
                                <select name="goal_ids[]" class="form-select goal-select"><?= $goalOptions($row['goal_id'] !== null ? (int)$row['goal_id'] : null) ?></select>
                                <button type="button" class="btn btn-outline-danger goal-remove" title="Убрать цель">✕</button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (isset($errors['goal_ids'])): ?><div class="text-danger small mb-2"><?= View::e($errors['goal_ids']) ?></div><?php endif; ?>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="goal-add-row">+ Добавить цель</button>

                <?php if (Auth::canEdit()): ?>
                    <div class="mt-3">
                        <label class="form-label small text-muted mb-1" for="new-goal-name">Нет нужной цели в списке? Добавьте её в справочник:</label>
                        <div class="input-group input-group-sm" style="max-width: 480px">
                            <input type="text" class="form-control" id="new-goal-name" maxlength="190" placeholder="Название новой цели">
                            <button type="button" class="btn btn-outline-primary" id="new-goal-create">Создать цель</button>
                        </div>
                        <div class="small mt-1" id="new-goal-status"></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="card-footer bg-white d-flex gap-2">
        <button type="submit" class="btn btn-primary">Сохранить</button>
        <a href="<?= $isEdit ? '/calltracking/departments/' . (int)$department['id'] : '/calltracking' ?>" class="btn btn-outline-secondary">Отмена</a>
    </div>
</form>

<template id="goal-row-template">
    <div class="input-group mb-2 goal-row">
        <select name="goal_ids[]" class="form-select goal-select"><?= $goalOptions(null) ?></select>
        <button type="button" class="btn btn-outline-danger goal-remove" title="Убрать цель">✕</button>
    </div>
</template>
<script src="/assets/js/calltracking-form.js"></script>
