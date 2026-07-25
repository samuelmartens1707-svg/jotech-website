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
require __DIR__ . '/../includes/mailer.php';

const MAX_ATTEMPTS = 5;

$pdo = get_pdo();
// payment_status = 'paid': Rechnungen werden erst nach bestätigter Stripe-Zahlung
// erstellt, nie für abgebrochene/unbezahlte Checkout-Versuche.
$stmt = $pdo->prepare(
    "SELECT id FROM orders WHERE payment_status = 'paid'
     AND lexoffice_sync_status IN ('pending', 'failed') AND lexoffice_attempts < ? ORDER BY created_at ASC"
);
$stmt->execute([MAX_ATTEMPTS]);
$orderIds = array_column($stmt->fetchAll(), 'id');

if (!$orderIds) {
    echo "Keine offenen Lexware-Office-Syncs.\n";
    exit(0);
}

echo count($orderIds) . " offene Bestellung(en) werden synchronisiert...\n";

$adminAlertEmail = env('ADMIN_ALERT_EMAIL', '');

foreach ($orderIds as $orderId) {
    lexoffice_sync_order((int) $orderId);

    $check = $pdo->prepare('SELECT lexoffice_sync_status, lexoffice_attempts, lexoffice_last_error FROM orders WHERE id = ?');
    $check->execute([$orderId]);
    $row = $check->fetch();
    $status = $row['lexoffice_sync_status'];

    echo "Order #$orderId -> $status\n";

    // Nach MAX_ATTEMPTS gescheiterten Versuchen wird diese Order künftig nicht mehr
    // von der obigen WHERE-Klausel selektiert — die Alarm-Mail feuert also genau
    // einmal, statt dass der endgültige Fehlschlag admin-seitig unbemerkt bleibt.
    $isFinalFailure = $status === 'failed' && (int) $row['lexoffice_attempts'] >= MAX_ATTEMPTS;
    if ($isFinalFailure && $adminAlertEmail !== '') {
        send_mail(
            $adminAlertEmail,
            'JOTECH Admin',
            "Lexware-Office-Sync für Bestellung #$orderId endgültig fehlgeschlagen",
            "Der Rechnungs-Sync für Bestellung #$orderId ist nach {$row['lexoffice_attempts']} Versuchen endgültig fehlgeschlagen "
                . "und wird nicht mehr automatisch wiederholt.\n\n"
                . "Letzter Fehler: " . ($row['lexoffice_last_error'] ?? '(unbekannt)') . "\n\n"
                . "Bitte im Admin-Bereich unter Bestellung #$orderId manuell prüfen und ggf. erneut synchronisieren."
        );
    }
}
