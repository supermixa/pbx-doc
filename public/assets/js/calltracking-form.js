(function () {
    'use strict';

    var form = document.getElementById('calltracking-form');
    if (!form) {
        return;
    }
    var rowsBox = document.getElementById('goal-rows');
    var template = document.getElementById('goal-row-template');
    var addBtn = document.getElementById('goal-add-row');
    var newName = document.getElementById('new-goal-name');
    var createBtn = document.getElementById('new-goal-create');
    var status = document.getElementById('new-goal-status');
    var csrfToken = form.querySelector('input[name="_csrf"]').value;

    function selects() {
        return Array.prototype.slice.call(rowsBox.querySelectorAll('.goal-select'));
    }

    /**
     * Цель, уже выбранная в одной строке, недоступна в остальных — так
     * у подразделения не получится одна цель дважды.
     */
    function refreshDisabledOptions() {
        var all = selects();
        var chosen = all.map(function (s) { return s.value; });
        all.forEach(function (sel, idx) {
            Array.prototype.forEach.call(sel.options, function (opt) {
                if (opt.value === '') {
                    return;
                }
                opt.disabled = chosen.some(function (v, j) { return j !== idx && v === opt.value; });
            });
        });
    }

    function addRow(selectedId) {
        var node = template.content.firstElementChild.cloneNode(true);
        rowsBox.appendChild(node);
        var sel = node.querySelector('.goal-select');
        if (selectedId) {
            sel.value = String(selectedId);
        }
        refreshDisabledOptions();
        return sel;
    }

    function addOptionEverywhere(id, name) {
        var lists = selects().concat([template.content.querySelector('.goal-select')]);
        lists.forEach(function (sel) {
            if (sel.querySelector('option[value="' + id + '"]')) {
                return;
            }
            var opt = document.createElement('option');
            opt.value = String(id);
            opt.textContent = name;
            // вставляем по алфавиту, после пустого варианта
            var before = null;
            for (var i = 1; i < sel.options.length; i++) {
                if (sel.options[i].textContent.localeCompare(name, 'ru', { sensitivity: 'base' }) > 0) {
                    before = sel.options[i];
                    break;
                }
            }
            sel.insertBefore(opt, before);
        });
    }

    addBtn.addEventListener('click', function () {
        addRow(null).focus();
    });

    rowsBox.addEventListener('click', function (e) {
        if (!e.target.classList.contains('goal-remove')) {
            return;
        }
        e.target.closest('.goal-row').remove();
        if (!rowsBox.querySelector('.goal-row')) {
            addRow(null);
        }
        refreshDisabledOptions();
    });

    rowsBox.addEventListener('change', function (e) {
        if (e.target.classList.contains('goal-select')) {
            refreshDisabledOptions();
        }
    });

    function setStatus(text, isError) {
        status.textContent = text;
        status.className = 'small mt-1 ' + (isError ? 'text-danger' : 'text-success');
    }

    function createGoal() {
        var name = newName.value.trim();
        if (name === '') {
            setStatus('Введите название цели', true);
            newName.focus();
            return;
        }
        createBtn.disabled = true;
        var fd = new FormData();
        fd.append('_csrf', csrfToken);
        fd.append('name', name);

        fetch('/calltracking/goals/quick-create', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.error) {
                    setStatus('Ошибка: ' + data.error, true);
                    return;
                }
                addOptionEverywhere(data.id, data.name);
                // уже выбрана в одной из строк — новую строку не добавляем
                var already = selects().some(function (s) { return s.value === String(data.id); });
                if (!already) {
                    var empty = selects().filter(function (s) { return s.value === '' && !s.disabled; })[0];
                    if (empty) {
                        empty.value = String(data.id);
                    } else {
                        addRow(data.id);
                    }
                }
                refreshDisabledOptions();
                newName.value = '';
                setStatus(data.existed
                    ? 'Цель «' + data.name + '» уже была в справочнике — выбрана она.'
                    : 'Цель «' + data.name + '» добавлена в справочник и выбрана.', false);
            })
            .catch(function () {
                setStatus('Ошибка связи с сервером', true);
            })
            .then(function () {
                createBtn.disabled = false;
            });
    }

    if (createBtn) {
        createBtn.addEventListener('click', createGoal);
        newName.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault(); // не отправлять всю форму
                createGoal();
            }
        });
    }

    refreshDisabledOptions();
})();
