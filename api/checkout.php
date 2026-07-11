<?php
declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/lexwareOfficeService.php';

define('MIN_SECONDS_TO_FILL', 3);
define('MAX_ITEMS_PER_ORDER', 30);
define('MAX_QUANTITY_PER_ITEM', 20);

header('Content-Type: application/json; charset=utf-8');

function respond(int $httpStatus, array $payload): void
{
    http_response_code($httpStatus);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function clean(string $value): string
{
    return trim(strip_tags($value));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['status' => 'error', 'message' => 'Methode nicht erlaubt.']);
}

$raw = file_get_contents('php://input');
$data = json_decode((string) $raw, true);
if (!is_array($data)) {
    respond(400, ['status' => 'error', 'message' => 'Ungültige Anfrage.']);
}

// Honeypot: echte Nutzer füllen dieses Feld nie aus — Bots stumm mit "Erfolg" abspeisen,
// ohne sie darauf hinzuweisen, dass sie erkannt wurden (analog zu php/send-mail.php).
if (!empty($data['website'])) {
    respond(200, ['status' => 'success', 'order_id' => 0]);
}

$loadedAt = isset($data['form_loaded_at']) ? (int) $data['form_loaded_at'] : 0;
if ($loadedAt > 0 && (time() - $loadedAt) < MIN_SECONDS_TO_FILL) {
    respond(400, ['status' => 'error', 'message' => 'Formular wurde zu schnell abgeschickt.']);
}

$customer = is_array($data['customer'] ?? null) ? $data['customer'] : [];
$firstName = clean((string) ($customer['first_name'] ?? ''));
$lastName = clean((string) ($customer['last_name'] ?? ''));
$emailRaw = trim((string) ($customer['email'] ?? ''));
$phone = clean((string) ($customer['phone'] ?? ''));
$street = clean((string) ($customer['street'] ?? ''));
$zip = clean((string) ($customer['zip'] ?? ''));
$city = clean((string) ($customer['city'] ?? ''));
$countryCode = strtoupper(clean((string) ($customer['country_code'] ?? 'DE')));

if ($firstName === '' || $lastName === '' || !filter_var($emailRaw, FILTER_VALIDATE_EMAIL)) {
    respond(400, ['status' => 'error', 'message' => 'Name und E-Mail-Adresse sind erforderlich.']);
}
if ($street === '' || $zip === '' || $city === '') {
    respond(400, ['status' => 'error', 'message' => 'Rechnungsadresse ist unvollständig.']);
}
if (!in_array($countryCode, ['DE', 'AT', 'CH'], true)) {
    $countryCode = 'DE';
}
if (empty($customer['consent'])) {
    respond(400, ['status' => 'error', 'message' => 'Bitte der Datenverarbeitung zustimmen.']);
}
$email = clean($emailRaw);

$cartItems = is_array($data['items'] ?? null) ? $data['items'] : [];
if (!$cartItems || count($cartItems) > MAX_ITEMS_PER_ORDER) {
    respond(400, ['status' => 'error', 'message' => 'Warenkorb ist leer oder enthält zu viele Positionen.']);
}

// Server-seitiges Re-Pricing: der vom Client mitgesendete Preis wird komplett
// ignoriert, der Preis kommt ausschließlich aus der DB zum Zeitpunkt des Checkouts.
$pdo = get_pdo();
$lineItems = [];
foreach ($cartItems as $rawItem) {
    $productId = (int) ($rawItem['product_id'] ?? 0);
    $quantity = (int) ($rawItem['quantity'] ?? 0);
    if ($productId <= 0 || $quantity <= 0 || $quantity > MAX_QUANTITY_PER_ITEM) {
        respond(400, ['status' => 'error', 'message' => 'Ungültige Warenkorb-Position.']);
    }

    $stmt = $pdo->prepare('SELECT id, title, price FROM products WHERE id = ? AND is_active = 1');
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    if (!$product) {
        respond(409, [
            'status' => 'error',
            'message' => 'Ein Produkt in deinem Warenkorb ist nicht mehr verfügbar. Bitte Warenkorb aktualisieren.',
        ]);
    }

    $unitPrice = round((float) $product['price'], 2);
    $lineItems[] = [
        'product_id' => $product['id'],
        'name' => $product['title'],
        'quantity' => $quantity,
        'unit_name' => 'Stück',
        'unit_price_net' => $unitPrice,
        'unit_price_gross' => $unitPrice, // Kleinunternehmerregelung §19 UStG: 0% Steuer
    ];
}

$totalNet = 0.0;
foreach ($lineItems as $item) {
    $totalNet += $item['unit_price_net'] * $item['quantity'];
}
$totalNet = round($totalNet, 2);

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'INSERT INTO orders
         (first_name, last_name, customer_email, customer_phone, billing_street, billing_zip, billing_city, billing_country_code, total_net, total_gross)
         VALUES (?,?,?,?,?,?,?,?,?,?)'
    );
    $stmt->execute([$firstName, $lastName, $email, $phone, $street, $zip, $city, $countryCode, $totalNet, $totalNet]);
    $orderId = (int) $pdo->lastInsertId();

    $itemStmt = $pdo->prepare(
        'INSERT INTO order_items (order_id, product_id, name, quantity, unit_name, unit_price_net, unit_price_gross)
         VALUES (?,?,?,?,?,?,?)'
    );
    foreach ($lineItems as $item) {
        $itemStmt->execute([
            $orderId, $item['product_id'], $item['name'], $item['quantity'],
            $item['unit_name'], $item['unit_price_net'], $item['unit_price_gross'],
        ]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('JOTECH: Checkout-Order konnte nicht gespeichert werden: ' . $e->getMessage());
    respond(500, ['status' => 'error', 'message' => 'Bestellung konnte nicht gespeichert werden. Bitte versuche es erneut.']);
}

// Die Kundenantwort hängt nicht vom Ergebnis des Lexware-Syncs ab — bei Fehlern
// greift später der Cron- bzw. Admin-Retry (lexoffice_sync_order() wirft nie nach außen).
try {
    lexoffice_sync_order($orderId);
} catch (Throwable $e) {
    error_log('JOTECH: Unerwarteter Fehler beim Lexware-Sync-Aufruf für Order #' . $orderId . ': ' . $e->getMessage());
}

respond(200, ['status' => 'success', 'order_id' => $orderId]);
