<?php
use App\Controllers\HistoryController;
use App\Core\Labels;
use App\Core\View;

$periods = ['today' => 'Сегодня', 'yesterday' => 'Вчера', '7d' => '7 дней', '30d' => '30 дней', 'all' => 'Всё время'];
$hasFilters = $f['from'] !== '' || $f['to'] !== '' || $f['type'] !== '' || $f['action'] !== '' || $f['user'] || $f['q'] !== '';

/** Ссылка на страницу истории с текущими отборами + изменения */
$historyUrl = function (array $override = []) use ($queryString): string {
    parse_str($queryString, $params);
    $params = array_merge($params, $override);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    return '/history' . ($params ? '?' . http_build_query($params) : '');
};
$from = ($page - 1) * 50 + 1;
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3 class="mb-0">История изменений</h3>
    <a href="/history/export<?= $queryString !== '' ? '?' . View::e($queryString) : '' ?>" class="btn btn-outline-success btn-sm">Выгрузить в Excel</a>
</div>

<form method="get" action="/history" class="card mb-3">
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
            <span class="small text-muted">Период:</span>
            <?php foreach ($periods as $key => $label): ?>
                <a href="<?= View::e($historyUrl(['period' => $key, 'from' => null, 'to' => null, 'page' => null])) ?>"
                   class="btn btn-sm <?= $f['period'] === $key ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= View::e($label) ?></a>
            <?php endforeach; ?>
        </div>
        <div class="row g-2 align-items-end">
            <div class="col-sm-6 col-lg-2">
                <label class="form-label small text-muted mb-1" for="h-from">С (дата и время)</label>
                <input type="datetime-local" name="from" id="h-from" class="form-control form-control-sm" value="<?= View::e($f['from']) ?>">
            </div>
            <div class="col-sm-6 col-lg-2">
                <label class="form-label small text-muted mb-1" for="h-to">По (дата и время)</label>
                <input type="datetime-local" name="to" id="h-to" class="form-control form-control-sm" value="<?= View::e($f['to']) ?>">
            </div>
            <div class="col-sm-6 col-lg-2">
                <label class="form-label small text-muted mb-1" for="h-type">Объект</label>
                <select name="type" id="h-type" class="form-select form-select-sm">
                    <option value="">Все</option>
                    <?php foreach ($entityTypes as $t): ?>
                        <option value="<?= View::e($t) ?>" <?= $f['type'] === $t ? 'selected' : '' ?>><?= View::e(Labels::entity($t)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6 col-lg-2">
                <label class="form-label small text-muted mb-1" for="h-action">Действие</label>
                <select name="action" id="h-action" class="form-select form-select-sm">
                    <option value="">Все</option>
                    <?php foreach ($actions as $a): ?>
                        <option value="<?= $a ?>" <?= $f['action'] === $a ? 'selected' : '' ?>><?= View::e(Labels::action($a)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6 col-lg-2">
                <label class="form-label small text-muted mb-1" for="h-user">Пользователь</label>
                <select name="user" id="h-user" class="form-select form-select-sm">
                    <option value="">Все</option>
                    <?php foreach ($authors as $u): ?>
                        <option value="<?= (int)$u['id'] ?>" <?= $f['user'] === (int)$u['id'] ? 'selected' : '' ?>><?= View::e($u['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6 col-lg-2">
                <label class="form-label small text-muted mb-1" for="h-q">Текст в изменениях</label>
                <input type="text" name="q" id="h-q" class="form-control form-control-sm" value="<?= View::e($f['q']) ?>" placeholder="номер, имя, значение…">
            </div>
            <?php if ($f['entity_id']): ?>
                <input type="hidden" name="entity_id" value="<?= (int)$f['entity_id'] ?>">
            <?php endif; ?>
            <div class="col-12 d-flex gap-2 align-items-center">
                <button type="submit" class="btn btn-primary btn-sm">Показать</button>
                <?php if ($hasFilters || $f['entity_id']): ?>
                    <a href="/history" class="btn btn-outline-secondary btn-sm">Сбросить отборы</a>
                <?php endif; ?>
                <?php if ($f['entity_id']): ?>
                    <span class="badge text-bg-info">Только один объект: <?= View::e(Labels::entity($f['type'])) ?> #<?= (int)$f['entity_id'] ?>
                        <a href="<?= View::e($historyUrl(['entity_id' => null, 'page' => null])) ?>" class="text-white text-decoration-none ms-1" title="Показать все объекты этого типа">✕</a></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</form>

<p class="text-muted small mb-2">
    Найдено записей: <strong><?= (int)$total ?></strong><?php if ($total > 0): ?> · показаны <?= $from ?>–<?= $from + count($entries) - 1 ?><?php endif; ?>
</p>

<div class="card mb-3">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-top">
            <thead>
                <tr>
                    <th style="width: 140px">Дата и время</th>
                    <th style="width: 160px">Пользователь</th>
                    <th style="width: 110px">Действие</th>
                    <th style="width: 260px">Объект</th>
                    <th>Изменения</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($entries)): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">За выбранный период изменений не найдено</td></tr>
            <?php endif; ?>
            <?php foreach ($entries as $e): ?>
                <?php
                $et = $e['entity_type'];
                $info = $names[$et][(int)$e['entity_id']] ?? ['name' => '', 'exists' => false];
                $label = $info['name'] !== '' ? $info['name'] : '#' . (int)$e['entity_id'];
                $url = Labels::entityUrl($et, (int)$e['entity_id']);
                $changes = HistoryController::visibleChanges($e);
                $fieldNames = array_map(fn($f) => Labels::field($f), array_keys($changes));
                ?>
                <tr>
                    <td class="small text-nowrap"><?= View::e(date('d.m.Y', strtotime($e['created_at']))) ?><br><span class="text-muted"><?= View::e(date('H:i:s', strtotime($e['created_at']))) ?></span></td>
                    <td class="small"><?= View::e($e['user_name'] ?? '—') ?></td>
                    <td><span class="badge <?= Labels::actionBadgeClass($e['action']) ?>"><?= View::e(Labels::action($e['action'])) ?></span></td>
                    <td class="small">
                        <div class="text-muted"><?= View::e(Labels::entity($et)) ?></div>
                        <?php if ($info['exists'] && $url !== '#'): ?>
                            <a href="<?= View::e($url) ?>" class="fw-semibold"><?= View::e($label) ?></a>
                        <?php else: ?>
                            <span class="fw-semibold"><?= View::e($label) ?></span> <span class="text-muted">(удалён)</span>
                        <?php endif; ?>
                        <a href="<?= View::e($historyUrl(['type' => $et, 'entity_id' => (int)$e['entity_id'], 'page' => null])) ?>" class="text-muted text-decoration-none ms-1" title="Вся история этого объекта">⌕</a>
                    </td>
                    <td class="small">
                        <?php if (!$changes): ?>
                            <span class="text-muted">—</span>
                        <?php elseif (count($changes) <= 3 && $e['action'] === 'update'): ?>
                            <?php foreach ($changes as $field => $vals): ?>
                                <div>
                                    <span class="text-muted"><?= View::e(Labels::field($field)) ?>:</span>
                                    <span class="text-decoration-line-through text-muted" style="white-space: pre-wrap;"><?= View::e(Labels::formatFieldValue($et, $field, $vals['old'])) ?></span>
                                    → <span style="white-space: pre-wrap;"><?= View::e(Labels::formatFieldValue($et, $field, $vals['new'])) ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <details>
                                <summary><?= count($changes) ?> <?= count($changes) === 1 ? 'поле' : (count($changes) < 5 ? 'поля' : 'полей') ?>:
                                    <?= View::e(implode(', ', array_slice($fieldNames, 0, 4))) ?><?= count($fieldNames) > 4 ? '…' : '' ?></summary>
                                <table class="table table-sm table-borderless mb-0 mt-1">
                                    <?php foreach ($changes as $field => $vals): ?>
                                        <tr>
                                            <td class="text-muted ps-0" style="width: 30%"><?= View::e(Labels::field($field)) ?></td>
                                            <?php if ($e['action'] === 'update'): ?>
                                                <td class="text-muted" style="white-space: pre-wrap;"><?= View::e(Labels::formatFieldValue($et, $field, $vals['old'])) ?></td>
                                            <?php endif; ?>
                                            <td style="white-space: pre-wrap;"><?= View::e(Labels::formatFieldValue($et, $field, $e['action'] === 'delete' ? $vals['old'] : $vals['new'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </table>
                            </details>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($pages > 1): ?>
    <?php
    $window = array_unique(array_filter(
        [1, $page - 2, $page - 1, $page, $page + 1, $page + 2, $pages],
        fn($p) => $p >= 1 && $p <= $pages
    ));
    sort($window);
    ?>
    <nav aria-label="Страницы истории">
        <ul class="pagination pagination-sm flex-wrap">
            <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>"><a class="page-link" href="<?= View::e($historyUrl(['page' => $page - 1])) ?>">‹ Новее</a></li>
            <?php $prev = 0; foreach ($window as $p): ?>
                <?php if ($p - $prev > 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                <li class="page-item<?= $p === $page ? ' active' : '' ?>"><a class="page-link" href="<?= View::e($historyUrl(['page' => $p])) ?>"><?= $p ?></a></li>
                <?php $prev = $p; endforeach; ?>
            <li class="page-item<?= $page >= $pages ? ' disabled' : '' ?>"><a class="page-link" href="<?= View::e($historyUrl(['page' => $page + 1])) ?>">Старее ›</a></li>
        </ul>
    </nav>
<?php endif; ?>
