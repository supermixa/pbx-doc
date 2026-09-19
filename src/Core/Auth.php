<?php

namespace App\Core;

use App\Models\User;

/**
 * Аутентификация на сессиях и проверка ролей.
 *
 * Роли:
 *   admin    — администратор: полный доступ, управление пользователями
 *   user     — пользователь: может добавлять/редактировать транки, маршруты,
 *              сценарии, номера, но не может удалять и не управляет пользователями
 *   observer — наблюдатель: доступ только на просмотр
 */
class Auth
{
    private const SESSION_KEY = 'user_id';

    public static function attempt(string $email, string $password): bool
    {
        $user = User::findByEmail($email);
        if (!$user) {
            return false;
        }
        if ((int)$user['is_active'] !== 1) {
            return false;
        }
        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = (int)$user['id'];
        return true;
    }

    public static function logout(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
        session_regenerate_id(true);
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        return $_SESSION[self::SESSION_KEY] ?? null;
    }

    private static ?array $cachedUser = null;
    private static bool $cacheResolved = false;

    public static function user(): ?array
    {
        if (self::$cacheResolved) {
            return self::$cachedUser;
        }
        self::$cacheResolved = true;

        $id = $_SESSION[self::SESSION_KEY] ?? null;
        if (!$id) {
            self::$cachedUser = null;
            return null;
        }

        $user = User::find((int)$id);
        if (!$user || (int)$user['is_active'] !== 1) {
            self::$cachedUser = null;
            return null;
        }

        self::$cachedUser = $user;
        return $user;
    }

    public static function role(): ?string
    {
        $user = self::user();
        return $user['role'] ?? null;
    }

    public static function isAdmin(): bool
    {
        return self::role() === 'admin';
    }

    public static function isObserver(): bool
    {
        return self::role() === 'observer';
    }

    /** Может ли текущий пользователь создавать/редактировать документацию */
    public static function canEdit(): bool
    {
        return in_array(self::role(), ['admin', 'user'], true);
    }

    /** Может ли текущий пользователь удалять записи */
    public static function canDelete(): bool
    {
        return self::role() === 'admin';
    }

    public static function requireLogin(): bool
    {
        if (!self::check()) {
            $redirect = $_SERVER['REQUEST_URI'] ?? '/';
            header('Location: /login?redirect=' . urlencode($redirect));
            return false;
        }
        return true;
    }

    public static function requireRole(array $roles): bool
    {
        if (!self::requireLogin()) {
            return false;
        }
        if (!in_array(self::role(), $roles, true)) {
            http_response_code(403);
            View::render('errors/403', []);
            return false;
        }
        return true;
    }

    public static function requireEdit(): bool
    {
        return self::requireRole(['admin', 'user']);
    }

    public static function requireAdmin(): bool
    {
        return self::requireRole(['admin']);
    }
}
