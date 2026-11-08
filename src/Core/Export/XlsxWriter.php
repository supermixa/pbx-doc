<?php

namespace App\Core\Export;

/**
 * Минимальный генератор файлов Excel (.xlsx) без внешних библиотек и без
 * PHP-расширения zip: XML-части книги упаковываются в ZIP вручную
 * (deflate через gzdeflate()). Все значения пишутся как текст — номера
 * телефонов не превращаются в числа и не теряют ведущие нули/«+».
 *
 * Формат секций — см. Exporter::send().
 */
final class XlsxWriter
{
    private const STYLE_DEFAULT = 0;
    private const STYLE_HEADER = 1;
    private const STYLE_TITLE = 2;
    private const STYLE_SUBTITLE = 3;
    private const STYLE_SECTION = 4;
    private const STYLE_CELL = 5;

    public static function build(string $title, string $subtitle, array $sections, string $sheetName = 'Номера'): string
    {
        $rows = [];      // [[style, [values...]], ...]
        $widths = [];

        $rows[] = [self::STYLE_TITLE, [$title]];
        if ($subtitle !== '') {
            $rows[] = [self::STYLE_SUBTITLE, [$subtitle]];
        }
        foreach ($sections as $section) {
            $rows[] = [self::STYLE_DEFAULT, []];
            if (!empty($section['title'])) {
                $rows[] = [self::STYLE_SECTION, [$section['title']]];
            }
            $rows[] = [self::STYLE_HEADER, $section['headers']];
            self::measure($widths, $section['headers']);
            if (empty($section['rows'])) {
                $rows[] = [self::STYLE_CELL, ['Записей нет']];
            }
            foreach ($section['rows'] as $r) {
                $rows[] = [self::STYLE_CELL, $r];
                self::measure($widths, $r);
            }
        }

        $files = [
            '[Content_Types].xml' => self::contentTypes(),
            '_rels/.rels' => self::rootRels(),
            'docProps/core.xml' => self::coreProps($title),
            'xl/workbook.xml' => self::workbook($sheetName),
            'xl/_rels/workbook.xml.rels' => self::workbookRels(),
            'xl/styles.xml' => self::styles(),
            'xl/worksheets/sheet1.xml' => self::sheet($rows, $widths),
        ];
        return self::zip($files);
    }

    private static function measure(array &$widths, array $values): void
    {
        foreach (array_values($values) as $i => $v) {
            $len = 0;
            foreach (explode("\n", (string)$v) as $line) {
                $len = max($len, mb_strlen($line));
            }
            $widths[$i] = max($widths[$i] ?? 8, min(60, $len + 2));
        }
    }

    private static function sheet(array $rows, array $widths): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        if ($widths) {
            $xml .= '<cols>';
            foreach ($widths as $i => $w) {
                $xml .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"/>';
            }
            $xml .= '</cols>';
        }
        $xml .= '<sheetData>';
        foreach ($rows as $n => [$style, $values]) {
            $rowNum = $n + 1;
            $xml .= '<row r="' . $rowNum . '">';
            foreach (array_values($values) as $i => $v) {
                $ref = self::column($i) . $rowNum;
                $text = self::xml((string)$v);
                $xml .= '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">' . $text . '</t></is></c>';
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData></worksheet>';
        return $xml;
    }

    /** 0 -> A, 25 -> Z, 26 -> AA ... */
    private static function column(int $index): string
    {
        $s = '';
        for ($n = $index + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $s = chr(65 + ($n - 1) % 26) . $s;
        }
        return $s;
    }

    private static function xml(string $s): string
    {
        // Управляющие символы недопустимы в XML 1.0
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $s) ?? '';
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '</Types>';
    }

    private static function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '</Relationships>';
    }

    private static function coreProps(string $title): string
    {
        $now = gmdate('Y-m-d\TH:i:s\Z');
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:title>' . self::xml($title) . '</dc:title>'
            . '<dc:creator>PBX Doc</dc:creator>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created>'
            . '</cp:coreProperties>';
    }

    private static function workbook(string $sheetName): string
    {
        // Имя листа: до 31 символа, без []:*?/\
        $sheetName = mb_substr(preg_replace('#[\[\]:*?/\\\\]#u', ' ', $sheetName) ?: 'Лист1', 0, 31);
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . self::xml($sheetName) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private static function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private static function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="4">'
            . '<font><sz val="11"/><name val="Calibri"/><family val="2"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/><family val="2"/></font>'
            . '<font><b/><sz val="14"/><name val="Calibri"/><family val="2"/></font>'
            . '<font><i/><sz val="10"/><color rgb="FF666666"/><name val="Calibri"/><family val="2"/></font>'
            . '</fonts>'
            . '<fills count="3">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFE9ECEF"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="2">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border><left style="thin"><color rgb="FFBBBBBB"/></left><right style="thin"><color rgb="FFBBBBBB"/></right>'
            . '<top style="thin"><color rgb="FFBBBBBB"/></top><bottom style="thin"><color rgb="FFBBBBBB"/></bottom><diagonal/></border>'
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="6">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="49" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyNumberFormat="1"/>'
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '<xf numFmtId="49" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyNumberFormat="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    /**
     * Упаковка файлов в ZIP (метод deflate). Реализовано вручную, чтобы не
     * зависеть от расширения php-zip, которого может не быть на хостинге.
     */
    private static function zip(array $files): string
    {
        $data = '';
        $central = '';
        [$dosTime, $dosDate] = self::dosDateTime(time());

        foreach ($files as $name => $content) {
            $compressed = gzdeflate($content, 6);
            $crc = crc32($content);
            $offset = strlen($data);
            $header = pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, 8, $dosTime, $dosDate,
                $crc, strlen($compressed), strlen($content), strlen($name), 0);
            $data .= $header . $name . $compressed;

            $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, 8, $dosTime, $dosDate,
                $crc, strlen($compressed), strlen($content), strlen($name), 0, 0, 0, 0, 0, $offset) . $name;
        }

        $end = pack('VvvvvVVv', 0x06054b50, 0, 0, count($files), count($files), strlen($central), strlen($data), 0);
        return $data . $central . $end;
    }

    private static function dosDateTime(int $ts): array
    {
        $d = getdate($ts);
        $time = ($d['hours'] << 11) | ($d['minutes'] << 5) | intdiv($d['seconds'], 2);
        $date = (($d['year'] - 1980) << 9) | ($d['mon'] << 5) | $d['mday'];
        return [$time, $date];
    }
}
