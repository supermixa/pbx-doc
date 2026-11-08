# tFPDF (сторонняя библиотека)

Используется для выгрузки списков номеров в PDF (`src/Core/Export/PdfWriter.php`).

- tFPDF 1.34 (на основе FPDF 1.85), авторы: Ian Back, Tycho Veltmeijer,
  Olivier Plathey. Лицензия: LGPL-2.1. Источник: http://fpdf.org/en/script/script92.php
  (копия релиза: https://github.com/Setasign/tFPDF, тег v1.34). Файлы `tfpdf.php` и
  `font/unifont/ttfonts.php` не изменялись.
- Шрифты DejaVu Sans / DejaVu Sans Bold (кириллица) — свободная лицензия, текст в
  `font/unifont/DejaVu_LICENSE.txt`.

При первом формировании PDF библиотека пытается сохранить кэш метрик шрифта
(`font/unifont/*.mtx.php`, `*.cw.dat`, `*.cw127.php`) — если папка доступна на запись.
Это необязательно (без кэша PDF просто строится чуть медленнее); файлы кэша не
хранятся в git (см. `.gitignore`).
