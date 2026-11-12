<?php
use App\Core\Csrf;
use App\Core\Import\ExtensionCsvImport;
use App\Core\View;

$rows = $parsed['rows'];
$count = ['new' => 0, 'duplicate' => 0, 'error' => 0];
foreach ($rows as $r) {
    $count[$r['status']]++;
}
$valueColumns = array_values(array_diff($parsed['columns'], ['number']));
$delimiterLabel = [';' => '«;»', ',' => '«,»', "\t" => 'табуляция'][$parsed['delimiter']] ?? $parsed['delimiter'];

/** Список изменений «Поле: было → станет» */
$changeList = function (array $changes, array $existing): string {
    if (!$changes) {
        return '<span class="text-muted">изменений не будет</span>';
    }
    $items = [];
    foreach ($changes as $dbField => $new) {
        $items[] = '<li><span class="text-muted">' . View::e(ExtensionCsvImport::fieldLabel($dbField)) . ':</span> '
            . View::e(ExtensionCsvImport::display($dbField, $existing[$dbField] ?? null)) . ' → <strong>'
            . View::e(ExtensionCsvImport::display($dbField, $new)) . '</strong></li>';
    }
    return '<ul class="list-unstyled mb-0">' . implode('', $items) . '</ul>';
};
?>
<nav aria-label="breadcrumb"><a href="/extensions/import">← Загрузить другой файл</a></nav>
<h3 class="mb-1">Импорт: проверка файла</h3>
<p class="text-muted small mb-3">
    <?= View::e($fileName) ?> · кодировка <?= View::e($parsed['encoding']) ?> · разделитель <?= View::e($delimiterLabel) ?> · строк с номерами: <?= count($rows) ?>
</p>

<div class="d-flex flex-wrap gap-2 mb-3">
    <span class="badge text-bg-success fs-6">Новых: <?= $count['new'] ?></span>
    <span class="badge text-bg-warning fs-6">Уже есть в базе: <?= $count['duplicate'] ?></span>
    <span class="badge text-bg-danger fs-6">С ошибками: <?= $count['error'] ?></span>
</div>

<?php if ($count['duplicate'] > 0): ?>
    <div class="alert alert-warning">
        <strong>Найдены дубли: <?= $count['duplicate'] ?>.</strong> Эти номера уже есть в базе. Для каждого выберите действие —
        «Дополнить недостающие» заполнит только пустые поля номера, «Заменить все данные» перезапишет поля значениями из файла
        (пустая ячейка очистит поле), «Пропустить» оставит номер без изменений. Ниже показано, что именно изменится.
        <div class="mt-2 d-flex flex-wrap gap-2 align-items-center">
            <span class="small">Для всех дублей:</span>
            <button type="button" class="btn btn-sm btn-outline-dark import-set-all" data-mode="fill">Дополнить недостающие</button>
            <button type="button" class="btn btn-sm btn-outline-dark import-set-all" data-mode="replace">Заменить все данные</button>
            <button type="button" class="btn btn-sm btn-outline-dark import-set-all" data-mode="skip">Пропустить</button>
        </div>
    </div>
<?php endif; ?>
<?php if ($count['error'] > 0): ?>
    <div class="alert alert-danger py-2">Строки с ошибками импортированы не будут — исправьте их в файле и загрузите его ещё раз, или импортируйте остальные строки сейчас.</div>
<?php endif; ?>

<form method="post" action="/extensions/import/confirm" id="import-confirm-form">
    <?= Csrf::field() ?>
    <input type="hidden" name="token" value="<?= View::e($token) ?>">

    <div class="card mb-3">
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-top">
                <thead>
                    <tr>
                        <th class="small text-muted">Строка</th>
                        <th>Номер</th>
                        <?php foreach ($valueColumns as $c): ?><th><?= View::e(ExtensionCsvImport::COLUMNS[$c]) ?></th><?php endforeach; ?>
                        <th style="min-width: 280px">Проверка</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php $rowClass = ['new' => '', 'duplicate' => 'table-warning', 'error' => 'table-danger'][$r['status']]; ?>
                    <tr class="<?= $rowClass ?>">
                        <td class="small text-muted"><?= (int)$r['line'] ?></td>
                        <td class="fw-semibold"><?= View::e($r['number'] !== '' ? $r['number'] : '—') ?></td>
                        <?php foreach ($valueColumns as $c): ?>
                            <td class="small" style="white-space: pre-wrap;"><?= View::e($r['raw'][$c] !== '' ? $r['raw'][$c] : '—') ?></td>
                        <?php endforeach; ?>
                        <td class="small">
                            <?php if ($r['status'] === 'new'): ?>
                                <span class="badge text-bg-success">Новый</span> будет создан
                            <?php elseif ($r['status'] === 'error'): ?>
                                <span class="badge text-bg-danger">Ошибка</span>
                                <ul class="mb-0 ps-3"><?php foreach ($r['errors'] as $e): ?><li><?= View::e($e) ?></li><?php endforeach; ?></ul>
                            <?php else: ?>
                                <?php $existing = $existingRows[$r['existing_id']]; ?>
                                <div class="mb-1">
                                    <span class="badge text-bg-warning">Уже есть</span>
                                    <a href="/extensions/<?= (int)$r['existing_id'] ?>" target="_blank">открыть номер</a>
                                </div>
                                <select name="action[<?= (int)$r['line'] ?>]" class="form-select form-select-sm mb-1 import-action">
                                    <option value="fill" selected>Дополнить недостающие</option>
                                    <option value="replace">Заменить все данные</option>
                                    <option value="skip">Пропустить</option>
                                </select>
                                <div class="import-changes" data-mode="fill"><?= $changeList($r['changes']['fill'], $existing) ?></div>
                                <div class="import-changes" data-mode="replace" style="display:none"><?= $changeList($r['changes']['replace'], $existing) ?></div>
                                <div class="import-changes text-muted" data-mode="skip" style="display:none">номер не изменится</div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex gap-2 align-items-center">
        <button type="submit" class="btn btn-primary" <?= ($count['new'] + $count['duplicate']) === 0 ? 'disabled' : '' ?>>Импортировать</button>
        <a href="/extensions/import" class="btn btn-outline-secondary">Отмена</a>
        <span class="text-muted small">Будут созданы новые номера и обработаны дубли по выбранным действиям.</span>
    </div>
</form>

<script>
(function () {
    function refresh(select) {
        var cell = select.closest('td');
        cell.querySelectorAll('.import-changes').forEach(function (el) {
            el.style.display = el.getAttribute('data-mode') === select.value ? '' : 'none';
        });
    }
    document.querySelectorAll('.import-action').forEach(function (select) {
        select.addEventListener('change', function () { refresh(select); });
    });
    document.querySelectorAll('.import-set-all').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.import-action').forEach(function (select) {
                select.value = btn.getAttribute('data-mode');
                refresh(select);
            });
        });
    });
})();
</script>
