<?php
declare(strict_types=1);

/**
 * Connexion PDO unique (singleton) vers MariaDB.
 */
final class Database
{
    private static ?PDO $instance = null;

    public static function get(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $configFile = __DIR__ . '/../config.php';
        if (!is_file($configFile)) {
            Response::error(
                'Configuration manquante : copiez api/config.example.php en api/config.php.',
                500
            );
        }

        $config = require $configFile;
        $db = $config['db'];

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $db['host'],
            $db['port'],
            $db['name'],
            $db['charset']
        );

        try {
            self::$instance = new PDO($dsn, $db['user'], $db['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            Response::error('Connexion à la base de données impossible.', 500);
        }

        return self::$instance;
    }
}
