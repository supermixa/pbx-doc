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
        <div class="d-flex gap-2">
            <a href="/extensions/import" class="btn btn-outline-secondary" title="Импорт внутренних номеров с типом «Внутренний» из CSV">Импорт CSV</a>
            <a href="/extensions/create" class="btn btn-primary">+ Добавить номер</a>
        </div>
    <?php endif; ?>
</div>

<ul class="nav nav-pills mb-3">
    <?php foreach ($tabLabels as $key => $label): ?>
        <li class="nav-item">
            <a class="nav-link<?= $tab === $key ? ' active' : '' ?>" href="<?= extTabUrl($key, $search) ?>"><?= View::e($label) ?> (<?= (int)($counts[$key] ?? 0) ?>)</a>
        </li>
    <?php endforeach; ?>
</ul>

<div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
<form method="get" class="mb-3" id="extensions-search-form" onsubmit="return false;">
    <input type="hidden" name="tab" id="extensions-search-tab" value="<?= View::e($tab) ?>">
    <div class="input-group" style="max-width: 400px">
        <input type="text" name="q" id="extensions-search-input" class="form-control" placeholder="Поиск по номеру, владельцу (от 2 символов)" value="<?= View::e($search) ?>" autocomplete="off">
        <span class="input-group-text d-none" id="extensions-search-spinner">
            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
        </span>
    </div>
</form>
<div class="btn-group btn-group-sm mb-3" title="Выгрузить список текущей вкладки (с учётом поиска)">
    <?php $exportQs = 'tab=' . urlencode($tab) . ($search !== '' ? '&q=' . urlencode($search) : ''); ?>
    <a href="/extensions/export?format=xlsx&<?= $exportQs ?>" class="btn btn-outline-success extensions-export" data-format="xlsx">Excel</a>
    <a href="/extensions/export?format=pdf&<?= $exportQs ?>" class="btn btn-outline-danger extensions-export" data-format="pdf">PDF</a>
    <?php if ($tab === 'internal'): ?>
        <a href="/extensions/export?format=mikopbx&<?= $exportQs ?>" class="btn btn-outline-primary extensions-export" data-format="mikopbx"
           title="CSV для импорта сотрудников в MikoPBX (формат «стандартный»): номера с типом «Внутренний», с учётом поиска">CSV для MikoPBX</a>
    <?php endif; ?>
</div>
</div>
<?php if ($tab === 'internal' && ($withoutOwnerCount ?? 0) > 0): ?>
    <div class="alert alert-warning py-2 small">
        У <?= (int)$withoutOwnerCount ?> внутр. номеров не заполнен «Владелец». В CSV для MikoPBX он попадает в обязательное поле
        <code>user_username</code> — такие строки MikoPBX не импортирует. Заполните владельца перед выгрузкой.
    </div>
<?php endif; ?>

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
            <tbody id="extensions-rows">
            <?php require __DIR__ . '/_rows.php'; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
var EXTENSIONS_TAB = <?= json_encode($tab) ?>;
</script>
<script src="/assets/js/extensions-index.js"></script>
