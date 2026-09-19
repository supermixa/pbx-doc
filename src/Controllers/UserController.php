<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\AuditLogger;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Validator;
use App\Core\View;
use App\Models\User;

class UserController
{
    public function index(): void
    {
        if (!Auth::requireAdmin()) {
            return;
        }
        View::render('users/index', [
            'title' => 'Пользователи',
            'users' => User::all(),
        ]);
    }

    public function create(): void
    {
        if (!Auth::requireAdmin()) {
            return;
        }
        View::render('users/form', [
            'title' => 'Новый пользователь',
            'user' => null,
            'errors' => [],
        ]);
    }

    public function store(): void
    {
        if (!Auth::requireAdmin()) {
            return;
        }
        Csrf::verifyRequestOrFail();

        $v = new Validator($_POST);
        $v->required('name', 'Имя')->maxLength('name', 150, 'Имя')
            ->required('email', 'Email')->email('email', 'Email')->maxLength('email', 190, 'Email')
            ->required('role', 'Роль')->in('role', User::ROLES, 'Роль')
            ->required('password', 'Пароль')->minLength('password', 6, 'Пароль');

        $email = $v->get('email');
        $emailTaken = $email && User::emailExists($email);

        if ($v->fails() || $emailTaken) {
            $errors = $v->errors();
            if ($emailTaken) {
                $errors['email'] = 'Пользователь с таким email уже существует';
            }
            View::render('users/form', [
                'title' => 'Новый пользователь',
                'user' => $_POST,
                'errors' => $errors,
            ]);
            return;
        }

        $data = [
            'name' => $v->get('name'),
            'email' => $email,
            'password_hash' => password_hash($v->get('password'), PASSWORD_BCRYPT),
            'role' => $v->get('role'),
            'is_active' => isset($_POST['is_active']) ? 1 : 1,
        ];
        $id = User::create($data);
        AuditLogger::logCreate('user', $id, ['name' => $data['name'], 'email' => $data['email'], 'role' => $data['role']]);

        Flash::success('Пользователь создан');
        header('Location: /users');
    }

    public function edit(array $params): void
    {
        if (!Auth::requireAdmin()) {
            return;
        }
        $user = User::find((int)$params['id']);
        if (!$user) {
            http_response_code(404);
            View::render('errors/404', []);
            return;
        }
        View::render('users/form', [
            'title' => 'Редактирование пользователя',
            'user' => $user,
            'errors' => [],
        ]);
    }

    public function update(array $params): void
    {
        if (!Auth::requireAdmin()) {
            return;
        }
        Csrf::verifyRequestOrFail();

        $id = (int)$params['id'];
        $old = User::find($id);
        if (!$old) {
            http_response_code(404);
            View::render('errors/404', []);
            return;
        }

        $v = new Validator($_POST);
        $v->required('name', 'Имя')->maxLength('name', 150, 'Имя')
            ->required('email', 'Email')->email('email', 'Email')->maxLength('email', 190, 'Email')
            ->required('role', 'Роль')->in('role', User::ROLES, 'Роль');

        $password = $v->get('password');
        if ($password) {
            $v->minLength('password', 6, 'Пароль');
        }

        $email = $v->get('email');
        $emailTaken = $email && User::emailExists($email, $id);

        // Защита: нельзя разжаловать последнего активного администратора
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $demotingLastAdmin = $old['role'] === 'admin'
            && ($v->get('role') !== 'admin' || $isActive === 0)
            && User::countAdmins() <= 1;

        if ($v->fails() || $emailTaken || $demotingLastAdmin) {
            $errors = $v->errors();
            if ($emailTaken) {
                $errors['email'] = 'Пользователь с таким email уже существует';
            }
            if ($demotingLastAdmin) {
                $errors['role'] = 'Нельзя убрать права последнего администратора';
            }
            View::render('users/form', [
                'title' => 'Редактирование пользователя',
                'user' => array_merge($old, $_POST, ['id' => $id]),
                'errors' => $errors,
            ]);
            return;
        }

        $data = [
            'name' => $v->get('name'),
            'email' => $email,
            'role' => $v->get('role'),
            'is_active' => $isActive,
        ];
        if ($password) {
            $data['password_hash'] = password_hash($password, PASSWORD_BCRYPT);
        }

        User::update($id, $data);

        $compareOld = ['name' => $old['name'], 'email' => $old['email'], 'role' => $old['role'], 'is_active' => $old['is_active']];
        $compareNew = ['name' => $data['name'], 'email' => $data['email'], 'role' => $data['role'], 'is_active' => $data['is_active']];
        AuditLogger::logUpdate('user', $id, $compareOld, $compareNew);

        Flash::success('Изменения сохранены');
        header('Location: /users');
    }

    public function destroy(array $params): void
    {
        if (!Auth::requireAdmin()) {
            return;
        }
        Csrf::verifyRequestOrFail();

        $id = (int)$params['id'];
        if ($id === Auth::id()) {
            Flash::error('Нельзя удалить самого себя');
            header('Location: /users');
            return;
        }

        $old = User::find($id);
        if ($old) {
            if ($old['role'] === 'admin' && User::countAdmins() <= 1) {
                Flash::error('Нельзя удалить последнего администратора');
                header('Location: /users');
                return;
            }
            AuditLogger::logDelete('user', $id, ['name' => $old['name'], 'email' => $old['email'], 'role' => $old['role']]);
            User::delete($id);
            Flash::success('Пользователь удалён');
        }
        header('Location: /users');
    }
}
