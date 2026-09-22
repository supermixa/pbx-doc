<?php
use App\Core\Auth;
use App\Core\Labels;
use App\Core\View;

$tabLabels = [
    'external' => 'Внешние',
    'internal' => 'Внутренние',
    'queue'    => 'Очередь',
    'ivr'      => 'Групповые',
    'other'    => 'Служебные',
];
$isExternalTab = $tab === 'external';

if (!function_exists('extTabUrl')) {
    function extTabUrl(string $tab, string $search): string
    {
        $url = '/extensions?tab=' . urlencode($tab);
        if ($search !== '') {
            $url .= '&q=' . urlencode($search);
        }
        return $url;
    }
}
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3 class="mb-0">Номера</h3>
    <?php if (Auth::canEdit()): ?>
        <a href="/extensions/create" class="btn btn-primary">+ Добавить номер</a>
    <?php endif; ?>
</div>

<ul class="nav nav-pills mb-3">
    <?php foreach ($tabLabels as $key => $label): ?>
        <li class="nav-item">
            <a class="nav-link<?= $tab === $key ? ' active' : '' ?>" href="<?= extTabUrl($key, $search) ?>"><?= View::e($label) ?> (<?= (int)($counts[$key] ?? 0) ?>)</a>
        </li>
    <?php endforeach; ?>
</ul>

<form method="get" class="mb-3">
    <input type="hidden" name="tab" value="<?= View::e($tab) ?>">
    <div class="input-group" style="max-width: 400px">
        <input type="text" name="q" class="form-control" placeholder="Поиск по номеру, владельцу" value="<?= View::e($search) ?>">
        <button class="btn btn-outline-secondary" type="submit">Найти</button>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Номер</th>
                    <?php if ($isExternalTab): ?>
                        <th>Тип</th>
                        <th>Провайдер</th>
                        <th>Транк</th>
                        <th>Владелец</th>
                        <th>Подразделение</th>
                        <th>Цель</th>
                        <th>Статус</th>
                        <th>Изменён</th>
                    <?php else: ?>
                        <th>Владелец</th>
                        <th>Подразделение</th>
                        <th>Статус</th>
                        <th>Изменён</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($extensions)): ?>
                <tr><td colspan="<?= $isExternalTab ? 9 : 5 ?>" class="text-center text-muted py-4">Записей не найдено</td></tr>
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
                        <td><span class="badge <?= $e['status'] === 'active' ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= View::e(Labels::statusLabel($e['status'])) ?></span></td>
                        <td class="text-muted small"><?= View::e($e['updated_by_name'] ?? '—') ?>, <?= View::e(date('d.m.Y H:i', strtotime($e['updated_at']))) ?></td>
                    <?php else: ?>
                        <td><?= View::e($e['owner'] ?: '—') ?></td>
                        <td><?= View::e($e['department_name'] ?? '—') ?></td>
                        <td><span class="badge <?= $e['status'] === 'active' ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= View::e(Labels::statusLabel($e['status'])) ?></span></td>
                        <td class="text-muted small"><?= View::e($e['updated_by_name'] ?? '—') ?>, <?= View::e(date('d.m.Y H:i', strtotime($e['updated_at']))) ?></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
