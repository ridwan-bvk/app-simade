<?php
require_once __DIR__ . '/env.php';

/**
 * Create and return a singleton PDO connection.
 * Reads configuration from .env first, then falls back to local defaults.
 */
function app_env(): string
{
    env_load();
    return strtolower((string)env('APP_ENV', 'production'));
}

function app_is_production(): bool
{
    $env = app_env();
    return $env === 'production' || $env === 'prod';
}

function get_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    env_load();

    $host = env('DB_HOST', '127.0.0.1');
    $port = env('DB_PORT', '3306');
    $name = env('DB_NAME', 'pos_system');
    $user = env('DB_USER', 'root');
    $pass = env('DB_PASS', '');
    $charset = env('DB_CHARSET', 'utf8mb4');

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $name, $charset);
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        // Keep full detail in server log, never expose in production response.
        error_log('[DB_CONNECT_ERROR] ' . $e->getMessage());

        if (app_is_production()) {
            throw new RuntimeException('Koneksi database gagal. Silakan hubungi administrator.');
        }

        throw new RuntimeException('Koneksi database gagal: ' . $e->getMessage());
    }

    return $pdo;
}
