<?php
use App\Core\Auth;
use App\Core\Labels;
use App\Core\View;
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3 class="mb-0">Транки</h3>
    <?php if (Auth::canEdit()): ?>
        <a href="/trunks/create" class="btn btn-primary">+ Добавить транк</a>
    <?php endif; ?>
</div>

<form method="get" class="mb-3">
    <div class="input-group" style="max-width: 400px">
        <input type="text" name="q" class="form-control" placeholder="Поиск по названию, провайдеру, хосту" value="<?= View::e($search) ?>">
        <button class="btn btn-outline-secondary" type="submit">Найти</button>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Название</th>
                    <th>Провайдер</th>
                    <th>Тип</th>
                    <th>Хост</th>
                    <th>Статус</th>
                    <th>Изменён</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($trunks)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">Записей не найдено</td></tr>
            <?php endif; ?>
            <?php foreach ($trunks as $t): ?>
                <tr style="cursor:pointer" onclick="location.href='/trunks/<?= (int)$t['id'] ?>'">
                    <td><a href="/trunks/<?= (int)$t['id'] ?>" class="text-decoration-none fw-semibold"><?= View::e($t['name']) ?></a></td>
                    <td>
                        <?php if (!empty($t['provider_name'])): ?>
                            <?= View::e($t['provider_name']) ?>
                        <?php elseif (!empty($t['provider'])): ?>
                            <span class="text-muted fst-italic"><?= View::e($t['provider']) ?></span>
                            <span class="badge text-bg-warning" title="Провайдер введён вручную и не связан со справочником — откройте транк и выберите провайдера из списка">вручную</span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?= View::e($t['type']) ?></td>
                    <td><?= View::e($t['host']) ?></td>
                    <td><span class="badge <?= $t['status'] === 'active' ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= View::e(Labels::statusLabel($t['status'])) ?></span></td>
                    <td class="text-muted small"><?= View::e($t['updated_by_name'] ?? '—') ?>, <?= View::e(date('d.m.Y H:i', strtotime($t['updated_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
