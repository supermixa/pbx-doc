<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\View;

class AuthController
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            header('Location: /');
            return;
        }
        View::render('auth/login', [
            'title' => 'Вход в систему',
            'redirect' => $_GET['redirect'] ?? '/',
        ]);
    }

    public function login(): void
    {
        Csrf::verifyRequestOrFail();

        $email = trim($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $redirect = $_POST['redirect'] ?? '/';
        if (!str_starts_with($redirect, '/') || str_starts_with($redirect, '//')) {
            $redirect = '/';
        }

        if ($email === '' || $password === '') {
            Flash::error('Введите email и пароль');
            header('Location: /login');
            return;
        }

        if (Auth::attempt($email, $password)) {
            Flash::success('Добро пожаловать!');
            header('Location: ' . $redirect);
            return;
        }

        Flash::error('Неверный email, пароль, либо учётная запись отключена');
        header('Location: /login');
    }

    public function logout(): void
    {
        Csrf::verifyRequestOrFail();
        Auth::logout();
        header('Location: /login');
    }
}
