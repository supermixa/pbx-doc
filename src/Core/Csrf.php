<?php

namespace App\Core;

class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . self::token() . '">';
    }

    public static function verify(?string $token): bool
    {
        if (empty($_SESSION['_csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['_csrf_token'], $token);
    }

    public static function verifyRequestOrFail(): void
    {
        $token = $_POST['_csrf'] ?? null;
        if (!self::verify($token)) {
            http_response_code(419);
            echo 'Сессия истекла или запрос недействителен (CSRF). Обновите страницу и попробуйте снова.';
            exit;
        }
    }
}
