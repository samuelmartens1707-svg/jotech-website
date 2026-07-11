<?php
declare(strict_types=1);

// CLI-Skript zum erneuten Anstoßen fehlgeschlagener Lexware-Office-Syncs.
// Für einen Cron-Eintrag gedacht (z.B. alle 5 Minuten), der Nutzer muss den
// Cronjob selbst auf seinem Hosting einrichten, z.B.:
//   */5 * * * * php /pfad/zu/jotech/bin/retry-lexoffice-sync.php >> /pfad/zu/logs/lexoffice-retry.log 2>&1

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Nur per CLI ausführbar.');
}

require __DIR__ . '/../includes/lexwareOfficeService.php';

const MAX_ATTEMPTS = 5;

$pdo = get_pdo();
$stmt = $pdo->prepare(
    "SELECT id FROM orders WHERE lexoffice_sync_status IN ('pending', 'failed') AND lexoffice_attempts < ? ORDER BY created_at ASC"
);
$stmt->execute([MAX_ATTEMPTS]);
$orderIds = array_column($stmt->fetchAll(), 'id');

if (!$orderIds) {
    echo "Keine offenen Lexware-Office-Syncs.\n";
    exit(0);
}

echo count($orderIds) . " offene Bestellung(en) werden synchronisiert...\n";

foreach ($orderIds as $orderId) {
    lexoffice_sync_order((int) $orderId);

    $check = $pdo->prepare('SELECT lexoffice_sync_status FROM orders WHERE id = ?');
    $check->execute([$orderId]);
    $status = $check->fetchColumn();

    echo "Order #$orderId -> $status\n";
}
