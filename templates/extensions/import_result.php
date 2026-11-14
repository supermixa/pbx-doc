<?php
use App\Core\View;

$kindLabels = [
    'created' => ['Создан', 'text-bg-success'],
    'filled' => ['Дополнен', 'text-bg-info'],
    'replaced' => ['Заменён', 'text-bg-primary'],
    'unchanged' => ['Без изменений', 'text-bg-light text-dark border'],
    'skipped' => ['Пропущен', 'text-bg-secondary'],
];
?>
<nav aria-label="breadcrumb"><a href="/extensions?tab=internal">← Внутренние номера</a></nav>
<h3 class="mb-1">Импорт завершён</h3>
<p class="text-muted small mb-3"><?= View::e($fileName) ?></p>

<div class="d-flex flex-wrap gap-2 mb-3">
    <span class="badge text-bg-success fs-6">Создано: <?= (int)$counts['created'] ?></span>
    <span class="badge text-bg-info fs-6">Дополнено: <?= (int)$counts['filled'] ?></span>
    <span class="badge text-bg-primary fs-6">Заменено: <?= (int)$counts['replaced'] ?></span>
    <span class="badge text-bg-light text-dark border fs-6">Без изменений: <?= (int)$counts['unchanged'] ?></span>
    <span class="badge text-bg-secondary fs-6">Пропущено: <?= (int)$counts['skipped'] ?></span>
</div>

<?php if (!empty($createdRefs)): ?>
    <div class="alert alert-info py-2">
        Созданы в справочниках:
        <?php foreach ($createdRefs as $i => $ref): ?><?= $i > 0 ? ', ' : '' ?><?= $ref['field'] === 'division' ? 'отдел' : 'подразделение' ?>
            <a href="/<?= $ref['field'] === 'division' ? 'divisions' : 'departments' ?>/<?= (int)$ref['id'] ?>">«<?= View::e($ref['name']) ?>»</a><?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card mb-3">
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr><th class="small text-muted">Строка</th><th>Номер</th><th>Результат</th><th>Подробности</th></tr></thead>
            <tbody>
            <?php if (empty($results)): ?>
                <tr><td colspan="4" class="text-center text-muted py-3">Нечего было импортировать.</td></tr>
            <?php endif; ?>
            <?php foreach ($results as $r): ?>
                <?php [$label, $cls] = $kindLabels[$r['kind']]; ?>
                <tr>
                    <td class="small text-muted"><?= (int)$r['line'] ?></td>
                    <td class="fw-semibold">
                        <?php if ($r['id']): ?><a href="/extensions/<?= (int)$r['id'] ?>"><?= View::e($r['number']) ?></a><?php else: ?><?= View::e($r['number']) ?><?php endif; ?>
                    </td>
                    <td><span class="badge <?= $cls ?>"><?= View::e($label) ?></span></td>
                    <td class="small"><?= View::e($r['message']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<a href="/extensions/import" class="btn btn-outline-secondary">Импортировать ещё файл</a>
<a href="/extensions?tab=internal" class="btn btn-primary">К списку номеров</a>
