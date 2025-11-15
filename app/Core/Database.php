<?php

namespace App\Core;

/**
 * Clase central para manejar la conexión PDO.
 * Más adelante podremos usarla en Models en lugar del $pdo global.
 */
class Database
{
    private static ?\PDO $pdo = null;

    public static function getConnection(): \PDO
    {
        if (self::$pdo === null) {
            // Reutilizamos la misma lógica de config.php
            $localConfigPath = __DIR__ . '/../../config.local.php';

            if (file_exists($localConfigPath)) {
                $cfg = require $localConfigPath;
            } else {
                $cfg = [
                    'db_host'    => '127.0.0.1',
                    'db_name'    => 'sansouci_desk',
                    'db_user'    => 'root',
                    'db_pass'    => '',
                    'db_charset' => 'utf8mb4',
                ];
            }

            $host    = $cfg['db_host'];
            $db      = $cfg['db_name'];
            $user    = $cfg['db_user'];
            $pass    = $cfg['db_pass'];
            $charset = $cfg['db_charset'];

            $dsn = "mysql:host=$host;dbname=$db;charset=$charset";

            $options = [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            self::$pdo = new \PDO($dsn, $user, $pass, $options);
        }

        return self::$pdo;
    }
}
