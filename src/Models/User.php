<?php

namespace App\Models;

use App\Core\Db;
use PDO;

class User
{
    public const ROLES = ['admin', 'user', 'observer'];

    public static function all(): array
    {
        $stmt = Db::connection()->query('SELECT * FROM users ORDER BY name');
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Db::connection()->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $stmt = Db::connection()->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function emailExists(string $email, ?int $exceptId = null): bool
    {
        if ($exceptId !== null) {
            $stmt = Db::connection()->prepare('SELECT COUNT(*) FROM users WHERE email = :email AND id != :id');
            $stmt->execute(['email' => $email, 'id' => $exceptId]);
        } else {
            $stmt = Db::connection()->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
            $stmt->execute(['email' => $email]);
        }
        return (int)$stmt->fetchColumn() > 0;
    }

    public static function create(array $data): int
    {
        $sql = 'INSERT INTO users (name, email, password_hash, role, is_active)
                VALUES (:name, :email, :password_hash, :role, :is_active)';
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute([
            'name' => $data['name'],
            'email' => $data['email'],
            'password_hash' => $data['password_hash'],
            'role' => $data['role'],
            'is_active' => $data['is_active'] ?? 1,
        ]);
        return (int)Db::connection()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $fields = [];
        $params = ['id' => $id];
        foreach (['name', 'email', 'role', 'is_active'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[$field] = $data[$field];
            }
        }
        if (!empty($data['password_hash'])) {
            $fields[] = 'password_hash = :password_hash';
            $params['password_hash'] = $data['password_hash'];
        }
        if (empty($fields)) {
            return;
        }
        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id';
        Db::connection()->prepare($sql)->execute($params);
    }

    public static function delete(int $id): void
    {
        Db::connection()->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $id]);
    }

    public static function countAdmins(): int
    {
        $stmt = Db::connection()->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1");
        return (int)$stmt->fetchColumn();
    }
}
