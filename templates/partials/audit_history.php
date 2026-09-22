<?php
/**
 * @var array $history Массив записей из AuditLog::forEntity()
 */
use App\Core\Labels;
use App\Core\View;

$entityType = $history[0]['entity_type'] ?? '';
?>
<div class="card">
    <div class="card-header bg-white">
        <strong>История изменений</strong>
    </div>
    <div class="card-body p-0">
        <?php if (empty($history)): ?>
            <p class="text-muted p-3 mb-0">Изменений пока не зафиксировано.</p>
        <?php else: ?>
            <ul class="list-group list-group-flush">
                <?php foreach ($history as $entry): ?>
                    <?php
                    $et = $entry['entity_type'];
                    $changes = json_decode($entry['changes'] ?? '[]', true) ?: [];
                    $userLabel = $entry['user_name'] ?? null;
                    ?>
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                            <div>
                                <span class="badge <?= Labels::actionBadgeClass($entry['action']) ?>"><?= View::e(Labels::action($entry['action'])) ?></span>
                                <strong class="ms-1"><?= View::e($userLabel ?: 'Пользователь удалён') ?></strong>
                                <?php if (!empty($entry['user_email'])): ?>
                                    <span class="text-muted small">(<?= View::e($entry['user_email']) ?>)</span>
                                <?php endif; ?>
                            </div>
                            <span class="text-muted small"><?= View::e(date('d.m.Y H:i', strtotime($entry['created_at']))) ?></span>
                        </div>

                        <?php if (!empty($changes)): ?>
                            <table class="table table-sm mt-2 mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 25%">Поле</th>
                                        <th>Было</th>
                                        <th>Стало</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($changes as $field => $vals): ?>
                                    <tr>
                                        <td><?= View::e(Labels::field($field)) ?></td>
                                        <?php $extCategory = ($et === 'extension' && isset($extension['category'])) ? $extension['category'] : null; ?>
                                        <td class="audit-diff-old"><?= View::e(Labels::formatFieldValue($et, $field, $vals['old'] ?? null, $extCategory)) ?></td>
                                        <td class="audit-diff-new"><?= View::e(Labels::formatFieldValue($et, $field, $vals['new'] ?? null, $extCategory)) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
