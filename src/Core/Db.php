<?php

namespace App\Core;

use PDO;
use PDOException;

/**
 * Singleton-обёртка над PDO для подключения к MySQL/MariaDB.
 */
class Db
{
    private static ?PDO $instance = null;

    public static function connection(): PDO
    {
        if (self::$instance === null) {
            $host = Env::get('DB_HOST', '127.0.0.1');
            $port = Env::get('DB_PORT', '3306');
            $name = Env::get('DB_DATABASE', 'pbxdoc');
            $user = Env::get('DB_USERNAME', 'root');
            $pass = Env::get('DB_PASSWORD', '');

            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

            try {
                self::$instance = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                if (Env::get('APP_DEBUG', false)) {
                    die('Ошибка подключения к базе данных: ' . $e->getMessage());
                }
                die('Ошибка подключения к базе данных. Проверьте настройки .env');
            }
        }

        return self::$instance;
    }
}
