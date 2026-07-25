<?php
declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/env.php';
require __DIR__ . '/../includes/stripeService.php';
require __DIR__ . '/../includes/lexwareOfficeService.php';
require __DIR__ . '/../includes/mailer.php';

header('Content-Type: application/json; charset=utf-8');

function respond(int $httpStatus, array $payload): void
{
    http_response_code($httpStatus);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['status' => 'error']);
}

$payload = (string) file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$webhookSecret = env('STRIPE_WEBHOOK_SECRET', '');

if ($webhookSecret === '' || !stripe_verify_webhook_signature($payload, (string) $sigHeader, $webhookSecret)) {
    error_log('JOTECH: Stripe-Webhook mit ungültiger Signatur abgelehnt.');
    respond(400, ['status' => 'error', 'message' => 'Ungültige Signatur.']);
}

$event = json_decode($payload, true);
if (!is_array($event) || !isset($event['id'], $event['type'])) {
    respond(400, ['status' => 'error', 'message' => 'Ungültiges Event.']);
}

$eventId = (string) $event['id'];
$eventType = (string) $event['type'];

if ($eventType !== 'checkout.session.completed') {
    // Alle anderen Event-Typen quittieren wir ungesehen mit 200, sonst
    // versucht Stripe endlos, sie erneut zuzustellen.
    respond(200, ['status' => 'ignored']);
}

$session = $event['data']['object'] ?? null;
$orderId = (int) ($session['client_reference_id'] ?? 0);
if (!is_array($session) || $orderId <= 0) {
    respond(400, ['status' => 'error', 'message' => 'Session ohne gültige Order-Referenz.']);
}

$pdo = get_pdo();

try {
    $pdo->beginTransaction();

    try {
        $insert = $pdo->prepare('INSERT INTO stripe_webhook_events (stripe_event_id) VALUES (?)');
        $insert->execute([$eventId]);
    } catch (PDOException $e) {
        // Duplikat: dieses Event wurde schon einmal verarbeitet (Stripe stellt
        // Events bei ausbleibender/verzögerter 200-Antwort mehrfach zu).
        $pdo->rollBack();
        respond(200, ['status' => 'already_processed']);
    }

    $stmt = $pdo->prepare('SELECT id FROM orders WHERE id = ? FOR UPDATE');
    $stmt->execute([$orderId]);
    if (!$stmt->fetch()) {
        throw new RuntimeException("Order #$orderId nicht gefunden.");
    }

    $customerDetails = $session['customer_details'] ?? [];
    $address = $customerDetails['address'] ?? [];
    $fullName = trim((string) ($customerDetails['name'] ?? ''));
    $lastSpace = strrpos($fullName, ' ');
    $firstName = $lastSpace !== false ? substr($fullName, 0, $lastSpace) : '';
    $lastName = $lastSpace !== false ? substr($fullName, $lastSpace + 1) : $fullName;

    $update = $pdo->prepare(
        'UPDATE orders SET
            first_name = ?, last_name = ?, customer_email = ?, customer_phone = ?,
            billing_street = ?, billing_zip = ?, billing_city = ?, billing_country_code = ?,
            payment_status = "paid", stripe_payment_intent_id = ?, paid_at = NOW(), status = "new"
         WHERE id = ?'
    );
    $update->execute([
        $firstName !== '' ? $firstName : null,
        $lastName !== '' ? $lastName : null,
        $customerDetails['email'] ?? null,
        $customerDetails['phone'] ?? '',
        $address['line1'] ?? null,
        $address['postal_code'] ?? null,
        $address['city'] ?? null,
        $address['country'] ?? 'DE',
        $session['payment_intent'] ?? null,
        $orderId,
    ]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('JOTECH: Stripe-Webhook-Verarbeitung für Order #' . $orderId . ' fehlgeschlagen: ' . $e->getMessage());
    // 500 zurückgeben, damit Stripe das Event später erneut zustellt (die
    // Event-ID wurde durch das Rollback nicht reserviert).
    respond(500, ['status' => 'error']);
}

// Rechnung erst jetzt (nach bestätigter Zahlung) anlegen und Kunden informieren.
// Beides läuft außerhalb der DB-Transaktion und wirft nie nach außen.
try {
    lexoffice_sync_order($orderId);
} catch (Throwable $e) {
    error_log('JOTECH: Unerwarteter Fehler beim Lexware-Sync-Aufruf für Order #' . $orderId . ': ' . $e->getMessage());
}

if (!empty($customerDetails['email'])) {
    $itemsStmt = $pdo->prepare('SELECT name, quantity, unit_name, unit_price_gross FROM order_items WHERE order_id = ?');
    $itemsStmt->execute([$orderId]);
    $lines = array_map(
        static fn (array $i): string => sprintf(
            '%dx %s – %s €',
            (int) $i['quantity'],
            $i['name'],
            number_format((float) $i['unit_price_gross'] * (int) $i['quantity'], 2, ',', '.')
        ),
        $itemsStmt->fetchAll()
    );
    $body = "Vielen Dank für deine Bestellung bei JOTECH (Bestellnummer #$orderId)!\n\n"
        . implode("\n", $lines) . "\n\n"
        . "Die Zahlung ist bei uns eingegangen. Die Rechnung erhältst du in Kürze separat per E-Mail.\n\n"
        . "Bei Fragen antworte einfach auf diese E-Mail.";
    send_mail((string) $customerDetails['email'], trim($fullName), 'Deine Bestellung bei JOTECH #' . $orderId, $body);
}

respond(200, ['status' => 'success']);
