(function () {
    'use strict';

    var input = document.getElementById('extensions-search-input');
    var tabField = document.getElementById('extensions-search-tab');
    var rowsBody = document.getElementById('extensions-rows');
    var spinner = document.getElementById('extensions-search-spinner');
    if (!input || !rowsBody) {
        return;
    }

    var tab = tabField ? tabField.value : (window.EXTENSIONS_TAB || 'external');
    var currentRequestId = 0;

    function debounce(fn, delay) {
        var timer = null;
        return function () {
            var args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function () { fn.apply(null, args); }, delay);
        };
    }

    var filterSelects = Array.prototype.slice.call(document.querySelectorAll('.extensions-filter'));
    var filtersReset = document.getElementById('extensions-filters-reset');

    /** '&f_type=...&f_trunk=...' — выбранные отборы вкладки */
    function filtersQuery() {
        return filterSelects.map(function (sel) {
            return sel.value !== '' ? '&f_' + sel.getAttribute('data-key') + '=' + encodeURIComponent(sel.value) : '';
        }).join('');
    }

    function updateUrl(q) {
        var url = '/extensions?tab=' + encodeURIComponent(tab);
        if (q !== '') {
            url += '&q=' + encodeURIComponent(q);
        }
        url += filtersQuery();
        window.history.replaceState(null, '', url);
        if (filtersReset) {
            filtersReset.style.display = filtersQuery() !== '' ? '' : 'none';
        }
    }

    function runSearch() {
        var q = input.value.trim();
        // Поиск начинает работать от 2 символов — короче просто показываем
        // весь список текущей вкладки (без фильтра).
        var effectiveQ = q.length >= 2 ? q : '';
        updateUrl(q);

        var requestId = ++currentRequestId;
        if (spinner) {
            spinner.classList.remove('d-none');
        }
        var url = '/extensions/search-rows?tab=' + encodeURIComponent(tab) + '&q=' + encodeURIComponent(effectiveQ) + filtersQuery();
        fetch(url)
            .then(function (r) { return r.text(); })
            .then(function (html) {
                if (requestId !== currentRequestId) {
                    return; // устаревший ответ, пришёл более новый запрос
                }
                rowsBody.innerHTML = html;
            })
            .catch(function () { /* сеть недоступна — оставляем текущий список */ })
            .finally(function () {
                if (requestId === currentRequestId && spinner) {
                    spinner.classList.add('d-none');
                }
            });
    }

    input.addEventListener('input', debounce(runSearch, 250));
    filterSelects.forEach(function (sel) {
        sel.addEventListener('change', runSearch);
    });

    // Выгрузка в Excel/PDF — с тем поиском и отборами, которые сейчас выбраны (живой
    // поиск не перезагружает страницу, поэтому ссылку собираем при клике)
    document.querySelectorAll('.extensions-export').forEach(function (link) {
        link.addEventListener('click', function () {
            var q = input.value.trim();
            var url = '/extensions/export?format=' + encodeURIComponent(link.getAttribute('data-format')) +
                '&tab=' + encodeURIComponent(tab);
            if (q.length >= 2) {
                url += '&q=' + encodeURIComponent(q);
            }
            link.href = url + filtersQuery();
        });
    });
})();
