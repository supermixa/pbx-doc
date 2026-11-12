<?php

namespace App\Core\Export;

/**
 * Выгрузка внутренних номеров (тип «Внутренний») в CSV для импорта
 * сотрудников в MikoPBX («Сотрудники → Импорт CSV»), формат «стандартный».
 *
 * Файл повторяет собственный экспорт MikoPBX (ExportCSVAction, формат
 * standard): UTF-8 с BOM, разделитель «,», первая строка — имена полей.
 *
 * Сопоставление полей:
 *   number         ← «Номер»
 *   user_username  ← «Владелец»
 *   sip_secret, sip_dtmfmode, sip_transport, sip_enableRecording —
 *                    одинаковые для всех номеров (см. константы ниже)
 *   остальные поля — пустые.
 */
final class MikoPbxCsv
{
    /** Поля формата «стандартный» MikoPBX — в этом порядке */
    public const FIELDS = [
        'number',
        'user_username',
        'user_email',
        'mobile_number',
        'mobile_dialstring',
        'sip_secret',
        'sip_dtmfmode',
        'sip_transport',
        'sip_enableRecording',
        'fwd_ringlength',
        'fwd_forwarding',
        'fwd_forwardingonbusy',
        'fwd_forwardingonunavailable',
    ];

    /** Значения, одинаковые для всех номеров */
    public const FIXED_VALUES = [
        'sip_secret' => 'paswd12345',
        'sip_dtmfmode' => 'auto',
        'sip_transport' => 'udp',
        'sip_enableRecording' => 'true',
    ];

    /** @param array $rows номера в формате ExtensionNumber::all() */
    public static function build(array $rows): string
    {
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, self::FIELDS, ',', '"', '');
        foreach ($rows as $r) {
            $values = [
                'number' => trim((string)($r['number'] ?? '')),
                'user_username' => trim((string)($r['owner'] ?? '')),
            ] + self::FIXED_VALUES;
            $line = [];
            foreach (self::FIELDS as $field) {
                $line[] = $values[$field] ?? '';
            }
            fputcsv($out, $line, ',', '"', '');
        }
        rewind($out);
        $content = (string)stream_get_contents($out);
        fclose($out);
        return $content;
    }

    public static function send(array $rows): void
    {
        $content = self::build($rows);
        $date = date('Y-m-d');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="mikopbx-employees-' . $date . '.csv"; filename*=UTF-8\'\''
            . rawurlencode('Внутренние номера для MikoPBX ' . $date . '.csv'));
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        echo $content;
    }
}
