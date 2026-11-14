<?php
use App\Core\Csrf;
use App\Core\View;
?>
<nav aria-label="breadcrumb"><a href="/extensions?tab=internal">← Номера</a></nav>
<h3 class="mb-3">Импорт внутренних номеров из CSV</h3>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= View::e($error) ?></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-6">
        <form method="post" action="/extensions/import" enctype="multipart/form-data" class="card">
            <div class="card-body">
                <?= Csrf::field() ?>
                <p class="mb-2">1. Скачайте шаблон и заполните его в Excel (одна строка — один номер).</p>
                <a href="/extensions/import/template" class="btn btn-outline-secondary btn-sm mb-3">Скачать шаблон CSV</a>
                <p class="mb-2">2. Сохраните файл как CSV и загрузите его:</p>
                <input type="file" name="csv" accept=".csv,text/csv,.txt" class="form-control mb-2" required>
                <div class="form-text">До 2 МБ и 5000 строк. Кодировка UTF-8 или Windows-1251, разделитель «;», «,» или табуляция — определяются автоматически.</div>
            </div>
            <div class="card-footer bg-white">
                <button type="submit" class="btn btn-primary">Проверить файл</button>
                <span class="text-muted small ms-2">Ничего не сохранится, пока вы не подтвердите импорт на следующем шаге.</span>
            </div>
        </form>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-white"><strong>Колонки файла</strong></div>
            <div class="card-body small">
                <p>Импортируются внутренние номера с типом «Внутренний». Первая строка — заголовки, порядок колонок любой, обязательна только «Номер». Если колонки нет в файле, это поле у номеров не трогается.</p>
                <dl class="row mb-2">
                    <dt class="col-4">Номер *</dt><dd class="col-8">внутренний номер, например 101</dd>
                    <dt class="col-4">Владелец</dt><dd class="col-8">до 150 символов</dd>
                    <dt class="col-4">Подразделение</dt><dd class="col-8">название из справочника «Подразделения»; если такого нет — на следующем шаге будет предложено его создать</dd>
                    <dt class="col-4">Отдел</dt><dd class="col-8">название из справочника «Отделы»; если такого нет — будет предложено создать</dd>
                    <dt class="col-4">АОН</dt><dd class="col-8">внешний номер «Городской» или «Сотовый» (пробелы, скобки, «+» не важны)</dd>
                    <dt class="col-4">Сценарий</dt><dd class="col-8">название из справочника «Сценарии»</dd>
                    <dt class="col-4">Контекст</dt><dd class="col-8">контекст диалплана</dd>
                    <dt class="col-4">Статус</dt><dd class="col-8">Активен / Отключён (пусто — «Активен» для новых, без изменений для существующих)</dd>
                    <dt class="col-4">Описание</dt><dd class="col-8">произвольный текст</dd>
                </dl>
                <p class="mb-1">Строки, где номер начинается с «#», пропускаются — так в шаблоне оформлен пример.</p>
                <p class="mb-0">Если номер уже есть, на следующем шаге для него можно выбрать: <strong>дополнить недостающие данные</strong> (заполнятся только пустые поля), <strong>заменить все данные</strong> (поля из файла перезапишут текущие, пустая ячейка очистит поле) или пропустить.</p>
            </div>
        </div>
    </div>
</div>
