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

    function updateUrl(q) {
        var url = '/extensions?tab=' + encodeURIComponent(tab);
        if (q !== '') {
            url += '&q=' + encodeURIComponent(q);
        }
        window.history.replaceState(null, '', url);
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
        var url = '/extensions/search-rows?tab=' + encodeURIComponent(tab) + '&q=' + encodeURIComponent(effectiveQ);
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
})();
