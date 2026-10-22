<?php
use App\Core\Auth;
use App\Core\View;
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3 class="mb-0">Calltracking</h3>
    <div class="d-flex gap-2">
        <a href="/calltracking/goals" class="btn btn-outline-secondary">Справочник целей</a>
        <?php if (Auth::canEdit()): ?>
            <a href="/calltracking/create" class="btn btn-primary">+ Добавить подразделение</a>
        <?php endif; ?>
    </div>
</div>

<form method="get" class="mb-3">
    <div class="input-group" style="max-width: 400px">
        <input type="text" name="q" class="form-control" placeholder="Поиск по подразделению, цели" value="<?= View::e($search) ?>">
        <button class="btn btn-outline-secondary" type="submit">Найти</button>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th style="width: 30%">Подразделение</th>
                    <th>Цели</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="2" class="text-center text-muted py-4">Записей не найдено</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><a href="/calltracking/departments/<?= (int)$r['department_id'] ?>" class="text-decoration-none fw-semibold"><?= View::e($r['department_name']) ?></a></td>
                    <td>
                        <?php foreach ($r['goals'] as $i => $g): ?><?= $i > 0 ? ', ' : '' ?><a href="/calltracking/goals/<?= (int)$g['id'] ?>"><?= View::e($g['name']) ?></a><?php endforeach; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
