<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }
        $config = config('database');
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $config['host'], $config['port'], $config['database'], $config['charset']);
        try {
            self::$connection = new PDO($dsn, $config['username'], $config['password'], $config['options']);
            return self::$connection;
        } catch (PDOException $exception) {
            error_log(sprintf('[database] %s', $exception->getMessage()));
            throw new RuntimeException('ไม่สามารถเชื่อมต่อระบบข้อมูลได้ กรุณาติดต่อผู้ดูแลระบบ');
        }
    }
}

