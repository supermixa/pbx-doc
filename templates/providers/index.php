<?php
use App\Core\Auth;
use App\Core\View;
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3 class="mb-0">Провайдеры</h3>
    <?php if (Auth::canEdit()): ?>
        <a href="/providers/create" class="btn btn-primary">+ Добавить провайдера</a>
    <?php endif; ?>
</div>

<form method="get" class="mb-3">
    <div class="input-group" style="max-width: 400px">
        <input type="text" name="q" class="form-control" placeholder="Поиск по названию, № договора" value="<?= View::e($search) ?>">
        <button class="btn btn-outline-secondary" type="submit">Найти</button>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Наименование</th>
                    <th>№ договора</th>
                    <th>Телефон ТП</th>
                    <th>Юрлица</th>
                    <th>Изменён</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($providers)): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">Записей не найдено</td></tr>
            <?php endif; ?>
            <?php foreach ($providers as $p): ?>
                <tr style="cursor:pointer" onclick="location.href='/providers/<?= (int)$p['id'] ?>'">
                    <td><a href="/providers/<?= (int)$p['id'] ?>" class="text-decoration-none fw-semibold"><?= View::e($p['name']) ?></a></td>
                    <td><?= View::e($p['contract_number'] ?: '—') ?></td>
                    <td><?= View::e($p['support_phone'] ?: '—') ?></td>
                    <td><span class="badge text-bg-light text-dark border"><?= (int)$p['legal_entities_count'] ?></span></td>
                    <td class="text-muted small"><?= View::e($p['updated_by_name'] ?? '—') ?>, <?= View::e(date('d.m.Y H:i', strtotime($p['updated_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
