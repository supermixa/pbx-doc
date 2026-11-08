<?php

namespace App\Core\Export;

/**
 * Отдаёт браузеру выгрузку в Excel (.xlsx) или PDF.
 *
 * $sections — список таблиц:
 *   [['title' => ?string, 'headers' => string[], 'rows' => string[][]], ...]
 * (удобно собирать через App\Core\ExtensionTable::section()).
 */
final class Exporter
{
    public static function send(string $format, string $fileBase, string $title, string $subtitle, array $sections): void
    {
        if ($format === 'pdf') {
            $content = PdfWriter::build($title, $subtitle, $sections);
            $mime = 'application/pdf';
            $ext = 'pdf';
        } else {
            $content = XlsxWriter::build($title, $subtitle, $sections);
            $mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            $ext = 'xlsx';
        }

        $date = date('Y-m-d');
        $fileName = self::safeFileName($fileBase) . ' ' . $date . '.' . $ext;
        $asciiName = 'pbxdoc-export-' . $date . '.' . $ext;

        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $asciiName . '"; filename*=UTF-8\'\'' . rawurlencode($fileName));
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        echo $content;
    }

    private static function safeFileName(string $name): string
    {
        $name = preg_replace('/[\\\\\/:*?"<>|«»\r\n]+/u', '', $name) ?? 'export';
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        return $name !== '' ? mb_substr($name, 0, 120) : 'export';
    }
}
