<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\ExtensionTable;
use App\Core\View;
?>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <nav aria-label="breadcrumb"><a href="/departments">← Все подразделения</a></nav>
        <h3 class="mb-0"><?= View::e($department['name']) ?></h3>
    </div>
    <div class="d-flex gap-2">
        <?php if (Auth::canEdit()): ?>
            <a href="/departments/<?= (int)$department['id'] ?>/edit" class="btn btn-outline-primary">Редактировать</a>
        <?php endif; ?>
        <?php if (Auth::canDelete()): ?>
            <form method="post" action="/departments/<?= (int)$department['id'] ?>/delete" onsubmit="return confirm('Удалить подразделение «<?= View::e(addslashes($department['name'])) ?>»? Действие необратимо.');">
                <?= Csrf::field() ?>
                <button type="submit" class="btn btn-outline-danger">Удалить</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Описание</dt><dd class="col-sm-8" style="white-space: pre-wrap;"><?= View::e($department['description'] ?: '—') ?></dd>
                </dl>
            </div>
        </div>

        <?php $totalNumbers = array_sum(array_map(fn($g) => count($g['rows']), $numberGroups)); ?>
        <div class="card mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <strong>Номера подразделения (<?= (int)$totalNumbers ?>)</strong>
                <?php if ($totalNumbers > 0): ?>
                    <div class="btn-group btn-group-sm">
                        <a href="/departments/<?= (int)$department['id'] ?>/export?format=xlsx" class="btn btn-outline-success">Excel</a>
                        <a href="/departments/<?= (int)$department['id'] ?>/export?format=pdf" class="btn btn-outline-danger">PDF</a>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if ($totalNumbers === 0): ?>
                    <p class="text-muted p-3 mb-0">К подразделению не привязан ни один номер.</p>
                <?php endif; ?>
                <?php foreach ($numberGroups as $g): ?>
                    <?php $columns = ExtensionTable::columnsForGroup($g['category'], $g['type']); ?>
                    <div class="px-3 pt-3 pb-1 fw-semibold"><?= View::e($g['label']) ?> <span class="text-muted fw-normal">(<?= count($g['rows']) ?>)</span></div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-2">
                            <thead>
                                <tr>
                                    <?php foreach ($columns as $c): ?><th class="small text-muted"><?= View::e(ExtensionTable::label($c)) ?></th><?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($g['rows'] as $e): ?>
                                <tr>
                                    <?php foreach ($columns as $c): ?>
                                        <?php $val = ExtensionTable::value($e, $c); ?>
                                        <td>
                                            <?php if ($c === 'number'): ?>
                                                <a href="/extensions/<?= (int)$e['id'] ?>" class="fw-semibold text-decoration-none"><?= View::e($val) ?></a>
                                            <?php elseif ($c === 'caller_id' && !empty($e['caller_id_extension_id']) && $val !== ''): ?>
                                                <a href="/extensions/<?= (int)$e['caller_id_extension_id'] ?>"><?= View::e($val) ?></a>
                                            <?php elseif ($c === 'status'): ?>
                                                <span class="badge <?= $e['status'] === 'active' ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= View::e($val) ?></span>
                                            <?php else: ?>
                                                <span style="white-space: pre-wrap;"><?= View::e($val !== '' ? $val : '—') ?></span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <p class="text-muted small">
            Создан: <?= View::e($department['created_by_name'] ?? '—') ?>, <?= View::e(date('d.m.Y H:i', strtotime($department['created_at']))) ?><br>
            Изменён: <?= View::e($department['updated_by_name'] ?? '—') ?>, <?= View::e(date('d.m.Y H:i', strtotime($department['updated_at']))) ?>
        </p>
    </div>
    <div class="col-lg-5">
        <?php require __DIR__ . '/../partials/audit_history.php'; ?>
    </div>
</div>
