<?php
use App\Core\Labels;
use App\Core\View;

/**
 * Partial: строки таблицы номеров (используется и полной страницей
 * /extensions, и AJAX-эндпоинтом живого поиска /extensions/search-rows —
 * чтобы колонки не приходилось дублировать в двух местах).
 * Ожидает $extensions, $isExternalTab и $isInternalTab (вкладка «Внутренние» —
 * дополнительная колонка «Отдел»).
 */
?>
<?php if (empty($extensions)): ?>
    <tr><td colspan="<?= $isExternalTab ? 9 : (!empty($isInternalTab) ? 6 : 5) ?>" class="text-center text-muted py-4">Записей не найдено</td></tr>
<?php endif; ?>
<?php foreach ($extensions as $e): ?>
    <tr style="cursor:pointer" onclick="location.href='/extensions/<?= (int)$e['id'] ?>'">
        <td><a href="/extensions/<?= (int)$e['id'] ?>" class="text-decoration-none fw-semibold"><?= View::e($e['number']) ?></a></td>
        <?php if ($isExternalTab): ?>
            <td><?= View::e(Labels::extensionTypeLabel($e['type'], $e['category'])) ?></td>
            <td><?= View::e($e['provider_name'] ?? '—') ?></td>
            <td><?= View::e($e['trunk_name'] ?? '—') ?></td>
            <td><?= View::e($e['owner'] ?: '—') ?></td>
            <td><?= View::e($e['department_name'] ?? '—') ?></td>
            <td><?= View::e($e['calltracking_target_name'] ?? '—') ?></td>
            <td><span class="badge <?= Labels::statusBadgeClass($e['status']) ?>"><?= View::e(Labels::statusLabel($e['status'])) ?></span><?php if ($e['status'] === 'forwarded'): ?> <span class="small text-nowrap">→ <?= View::e($e['forward_to_number'] ?? '?') ?></span><?php endif; ?></td>
            <td class="text-muted small"><?= View::e($e['updated_by_name'] ?? '—') ?>, <?= View::e(date('d.m.Y H:i', strtotime($e['updated_at']))) ?></td>
        <?php else: ?>
            <td><?= View::e($e['owner'] ?: '—') ?></td>
            <td><?= View::e($e['department_name'] ?? '—') ?></td>
            <?php if (!empty($isInternalTab)): ?><td><?= View::e($e['division_name'] ?? '—') ?></td><?php endif; ?>
            <td><span class="badge <?= Labels::statusBadgeClass($e['status']) ?>"><?= View::e(Labels::statusLabel($e['status'])) ?></span></td>
            <td class="text-muted small"><?= View::e($e['updated_by_name'] ?? '—') ?>, <?= View::e(date('d.m.Y H:i', strtotime($e['updated_at']))) ?></td>
        <?php endif; ?>
    </tr>
<?php endforeach; ?>
