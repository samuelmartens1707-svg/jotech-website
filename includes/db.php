<?php
declare(strict_types=1);

require __DIR__ . '/env.php';

// Zugangsdaten kommen bevorzugt aus echten Server-Umgebungsvariablen (production,
// z.B. mittwald Container Hosting), da config.php nie eingecheckt wird. Lokal
// dient config.php als Fallback.
function get_db_config(): array
{
    $fileConfig = is_file(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : [];

    return [
        'db_host' => env('DB_HOST', (string) ($fileConfig['db_host'] ?? '127.0.0.1')),
        'db_port' => (int) env('DB_PORT', (string) ($fileConfig['db_port'] ?? 3306)),
        'db_name' => env('DB_NAME', (string) ($fileConfig['db_name'] ?? 'jotech')),
        'db_user' => env('DB_USER', (string) ($fileConfig['db_user'] ?? '')),
        'db_pass' => env('DB_PASS', (string) ($fileConfig['db_pass'] ?? '')),
    ];
}

function get_pdo(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $config = get_db_config();
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $config['db_host'],
        $config['db_port'],
        $config['db_name']
    );

    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}
