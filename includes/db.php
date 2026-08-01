<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';

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

/**
 * Vertauscht sort_order zwischen einer Zeile und ihrem Nachbarn in der durch
 * $whereSql/$whereParams definierten Reihenfolge. Gemeinsame Implementierung
 * für die "rauf/runter"-Sortierung von Produkten und Produktbildern.
 */
function reorder_adjacent(PDO $pdo, string $table, string $whereSql, array $whereParams, int $targetId, string $direction): void
{
    $sql = "SELECT id, sort_order FROM $table" . ($whereSql !== '' ? " WHERE $whereSql" : '') . ' ORDER BY sort_order ASC, id ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($whereParams);
    $rows = $stmt->fetchAll();

    $index = null;
    foreach ($rows as $i => $row) {
        if ((int) $row['id'] === $targetId) {
            $index = $i;
            break;
        }
    }
    if ($index === null) {
        return;
    }

    $neighborIndex = $direction === 'up' ? $index - 1 : $index + 1;
    if (!isset($rows[$neighborIndex])) {
        return;
    }

    $a = $rows[$index];
    $b = $rows[$neighborIndex];
    $update = $pdo->prepare("UPDATE $table SET sort_order = ? WHERE id = ?");
    $update->execute([$b['sort_order'], $a['id']]);
    $update->execute([$a['sort_order'], $b['id']]);
}
