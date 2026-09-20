<?php
use App\Core\Auth;
use App\Core\View;
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3 class="mb-0">Юрлица</h3>
    <?php if (Auth::canEdit()): ?>
        <a href="/legal-entities/create" class="btn btn-primary">+ Добавить юрлицо</a>
    <?php endif; ?>
</div>

<form method="get" class="mb-3">
    <div class="input-group" style="max-width: 400px">
        <input type="text" name="q" class="form-control" placeholder="Поиск по названию, ИНН" value="<?= View::e($search) ?>">
        <button class="btn btn-outline-secondary" type="submit">Найти</button>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Наименование</th>
                    <th>ИНН</th>
                    <th>Изменён</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($legalEntities)): ?>
                <tr><td colspan="3" class="text-center text-muted py-4">Записей не найдено</td></tr>
            <?php endif; ?>
            <?php foreach ($legalEntities as $le): ?>
                <tr style="cursor:pointer" onclick="location.href='/legal-entities/<?= (int)$le['id'] ?>'">
                    <td><a href="/legal-entities/<?= (int)$le['id'] ?>" class="text-decoration-none fw-semibold"><?= View::e($le['name']) ?></a></td>
                    <td><?= View::e($le['inn'] ?: '—') ?></td>
                    <td class="text-muted small"><?= View::e($le['updated_by_name'] ?? '—') ?>, <?= View::e(date('d.m.Y H:i', strtotime($le['updated_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
