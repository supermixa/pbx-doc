<?php

namespace App\Core;

/**
 * Версия приложения — показывается в подвале каждой страницы.
 * При каждом выпуске изменений номер увеличивается на 0.01, дата — дата
 * выпуска; краткое описание изменений — в CHANGELOG.md.
 */
final class Version
{
    public const NUMBER = '0.24';
    public const RELEASE_DATE = '2026-11-13';

    /** Например: «версия 0.23 от 09.11.2026» */
    public static function label(): string
    {
        return 'версия ' . self::NUMBER . ' от ' . date('d.m.Y', strtotime(self::RELEASE_DATE));
    }
}
