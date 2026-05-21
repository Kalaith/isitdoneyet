<?php

declare(strict_types=1);

namespace App\External;

use App\Core\Env;
use PDO;

final class DatabaseService
{
    private static ?PDO $connection = null;

    public function getConnection(): PDO
    {
        if (self::$connection === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                Env::required('DB_HOST'),
                Env::requiredInt('DB_PORT'),
                Env::required('DB_DATABASE')
            );

            self::$connection = new PDO(
                $dsn,
                Env::required('DB_USER'),
                Env::required('DB_PASSWORD', true),
                [
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        }

        return self::$connection;
    }

    public function testConnection(): bool
    {
        $this->getConnection()->query('SELECT 1');
        return true;
    }
}
