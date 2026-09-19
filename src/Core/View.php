<?php

namespace App\Core;

/**
 * Простой рендерер шаблонов на чистом PHP (include).
 */
class View
{
    private static string $templatesPath = __DIR__ . '/../../templates';

    public static function render(string $template, array $data = [], ?string $layout = 'layout'): void
    {
        extract($data, EXTR_SKIP);

        $file = self::$templatesPath . '/' . $template . '.php';
        if (!is_file($file)) {
            http_response_code(500);
            echo "Шаблон не найден: " . htmlspecialchars($template);
            return;
        }

        if ($layout === null) {
            require $file;
            return;
        }

        $content = function () use ($file, $data) {
            extract($data, EXTR_SKIP);
            require $file;
        };

        $layoutFile = self::$templatesPath . '/' . $layout . '.php';
        require $layoutFile;
    }

    public static function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}
