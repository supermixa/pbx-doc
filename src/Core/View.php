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
        // Внимание: внутренние переменные этого метода (templateFile,
        // layoutFile и т.п.) названы так, чтобы НЕ совпадать с ключами
        // $data (например, 'file') — extract($data) кладёт их в эту же
        // область видимости, и совпадение имени тихо затирало бы значение
        // из $data при присвоении ниже.
        $templateFile = self::$templatesPath . '/' . $template . '.php';
        if (!is_file($templateFile)) {
            http_response_code(500);
            echo "Шаблон не найден: " . htmlspecialchars($template);
            return;
        }

        if ($layout === null) {
            extract($data, EXTR_SKIP);
            require $templateFile;
            return;
        }

        $content = function () use ($templateFile, $data) {
            extract($data, EXTR_SKIP);
            require $templateFile;
        };

        $layoutFile = self::$templatesPath . '/' . $layout . '.php';
        extract($data, EXTR_SKIP);
        require $layoutFile;
    }

    public static function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}
