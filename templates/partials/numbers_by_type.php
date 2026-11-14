<?php
/**
 * Карточка «номера по типам» (карточки подразделения и отдела).
 *
 * @var array  $numberGroups  ExtensionTable::groupByType(...)
 * @var string $numbersTitle  заголовок карточки
 * @var string $exportUrl     адрес выгрузки (к нему добавляется ?format=xlsx|pdf)
 * @var string $emptyText     текст, если номеров нет
 * @var array  $omitColumns   колонки, которые на этой карточке не нужны
 */
use App\Core\ExtensionTable;
use App\Core\Labels;
use App\Core\View;
?>
<?php $totalNumbers = array_sum(array_map(fn($g) => count($g['rows']), $numberGroups)); ?>
<div class="card mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <strong><?= View::e($numbersTitle) ?> (<?= (int)$totalNumbers ?>)</strong>
        <?php if ($totalNumbers > 0): ?>
            <div class="btn-group btn-group-sm">
                <a href="<?= View::e($exportUrl) ?>?format=xlsx" class="btn btn-outline-success">Excel</a>
                <a href="<?= View::e($exportUrl) ?>?format=pdf" class="btn btn-outline-danger">PDF</a>
            </div>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <?php if ($totalNumbers === 0): ?>
            <p class="text-muted p-3 mb-0"><?= View::e($emptyText) ?></p>
        <?php endif; ?>
        <?php foreach ($numberGroups as $g): ?>
            <?php $columns = ExtensionTable::columnsForGroup($g['category'], $g['type'], $omitColumns); ?>
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
                                        <span class="badge <?= Labels::statusBadgeClass($e['status']) ?>"><?= View::e(Labels::statusLabel($e['status'])) ?></span>
                                        <?php if ($e['status'] === 'forwarded'): ?><span class="small text-nowrap">→ <?php if (!empty($e['forward_to_extension_id'])): ?><a href="/extensions/<?= (int)$e['forward_to_extension_id'] ?>"><?= View::e($e['forward_to_number'] ?? '') ?></a><?php else: ?>?<?php endif; ?></span><?php endif; ?>
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
