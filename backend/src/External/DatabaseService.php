<?php

namespace App\External;

use Illuminate\Database\Capsule\Manager as Capsule;
use Exception;

class DatabaseService
{
    private static ?Capsule $capsule = null;
    
    public function __construct()
    {
        if (self::$capsule === null) {
            $this->initializeDatabase();
        }
    }
    
    private function initializeDatabase(): void
    {
        self::$capsule = new Capsule;
        
        self::$capsule->addConnection([
            'driver' => 'mysql',
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'database' => $_ENV['DB_NAME'] ?? 'isitdoneyet',
            'username' => $_ENV['DB_USER'] ?? 'root',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
        ]);
        
        self::$capsule->setAsGlobal();
        self::$capsule->bootEloquent();
    }
    
    public static function getConnection()
    {
        if (self::$capsule === null) {
            new self();
        }
        return self::$capsule;
    }
    
    public function testConnection(): bool
    {
        try {
            self::$capsule->getConnection()->getPdo();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
