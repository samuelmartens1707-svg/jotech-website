<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/db.php';

// Prozeduraler Stil passend zum Rest des Projekts. Einzige Ausnahme: eine schlanke
// Exception-Klasse, da PHP dafür kein prozedurales Äquivalent bietet.
class LexofficeApiException extends RuntimeException
{
    /** @var array<string, mixed>|null */
    public $responseBody;
    public $statusCode;

    public function __construct(string $message, int $statusCode, ?array $responseBody = null)
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
        $this->responseBody = $responseBody;
    }
}

function lexoffice_base_url(): string
{
    return rtrim((string) env('LEXOFFICE_API_BASE_URL', 'https://api.lexware.io/v1'), '/');
}

function lexoffice_auto_finalize(): bool
{
    return in_array(strtolower((string) env('LEXOFFICE_AUTO_FINALIZE', 'false')), ['1', 'true', 'yes'], true);
}

/**
 * Zentraler HTTP-Client für die Lexware-Office-API. Hält einen Mindestabstand von
 * 500ms zwischen Aufrufen ein (API-Limit: 2 req/s), retryt bei Netzwerkfehlern und
 * 429-Antworten. Wirft LexofficeApiException mit den Rohdaten der Antwort bei Fehlern
 * (die Doku dokumentiert kein festes Error-JSON-Schema, daher keine Annahme darüber).
 *
 * @param array<string, mixed>|null $body
 * @param array<string, mixed> $query
 * @return array<string, mixed>
 */
function lexoffice_request(string $method, string $path, ?array $body = null, array $query = []): array
{
    static $lastCallAt = 0.0;

    $apiKey = env('LEXOFFICE_API_KEY');
    if (!$apiKey) {
        throw new LexofficeApiException('LEXOFFICE_API_KEY ist nicht konfiguriert.', 0);
    }

    $url = lexoffice_base_url() . $path;
    if ($query) {
        $url .= '?' . http_build_query($query);
    }

    $maxAttempts = 3;
    $lastException = null;

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $minIntervalSeconds = 0.5;
        $elapsed = microtime(true) - $lastCallAt;
        if ($lastCallAt > 0.0 && $elapsed < $minIntervalSeconds) {
            usleep((int) (($minIntervalSeconds - $elapsed) * 1_000_000));
        }
        $lastCallAt = microtime(true);

        $headers = ['Authorization: Bearer ' . $apiKey, 'Accept: application/json'];
        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ];
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            $options[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE);
        }
        $options[CURLOPT_HTTPHEADER] = $headers;

        $ch = curl_init($url);
        curl_setopt_array($ch, $options);
        $responseRaw = curl_exec($ch);
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($curlErrno !== 0) {
            $lastException = new LexofficeApiException("Netzwerkfehler bei Lexware-Office-Anfrage: $curlError", 0);
            continue;
        }

        $decoded = ($responseRaw !== false && $responseRaw !== '') ? json_decode($responseRaw, true) : null;
        $decodedArray = is_array($decoded) ? $decoded : null;

        if ($statusCode === 429) {
            $lastException = new LexofficeApiException('Lexware-Office-Rate-Limit erreicht (429).', $statusCode, $decodedArray);
            usleep(1_000_000);
            continue;
        }

        if ($statusCode >= 200 && $statusCode < 300) {
            return $decodedArray ?? [];
        }

        throw new LexofficeApiException("Lexware-Office-API-Fehler: HTTP $statusCode bei $method $path", $statusCode, $decodedArray);
    }

    throw $lastException ?? new LexofficeApiException('Lexware-Office-Anfrage nach mehreren Versuchen fehlgeschlagen.', 0);
}

/**
 * Findet einen bestehenden Kontakt per E-Mail oder legt einen neuen an.
 *
 * @param array<string, mixed> $customer
 */
function lexoffice_find_or_create_contact(array $customer): string
{
    $email = (string) $customer['email'];

    if (mb_strlen($email) >= 3) {
        $found = lexoffice_request('GET', '/contacts', null, ['email' => $email]);
        $existing = $found['content'][0]['id'] ?? null;
        if (is_string($existing) && $existing !== '') {
            return $existing;
        }
    }

    $payload = [
        'version' => 0,
        'roles' => ['customer' => new stdClass()],
        'person' => [
            'salutation' => '',
            'firstName' => (string) $customer['first_name'],
            'lastName' => (string) $customer['last_name'],
        ],
        'addresses' => [
            'billing' => [[
                'street' => (string) $customer['street'],
                'zip' => (string) $customer['zip'],
                'city' => (string) $customer['city'],
                'countryCode' => (string) $customer['country_code'],
            ]],
        ],
        'emailAddresses' => [
            'private' => [$email],
        ],
    ];

    if (!empty($customer['phone'])) {
        $payload['phoneNumbers'] = ['private' => [(string) $customer['phone']]];
    }

    $created = lexoffice_request('POST', '/contacts', $payload);
    $contactId = $created['id'] ?? null;
    if (!is_string($contactId) || $contactId === '') {
        throw new LexofficeApiException('Lexware-Office-Antwort enthielt keine Kontakt-ID.', 0, $created);
    }

    return $contactId;
}

/**
 * Legt eine Rechnung an (Kleinunternehmerregelung §19 UStG: 0% Steuer, vatfree).
 *
 * @param array<string, mixed> $order
 * @param list<array<string, mixed>> $items
 * @return array<string, mixed>
 */
function lexoffice_create_invoice(string $contactId, array $order, array $items): array
{
    $lineItems = array_map(static function (array $item): array {
        $quantity = (float) $item['quantity'];
        $unitPriceNet = (float) $item['unit_price_net'];
        return [
            'type' => 'custom',
            'name' => (string) $item['name'],
            'quantity' => $quantity,
            'unitName' => (string) $item['unit_name'],
            'unitPrice' => [
                'currency' => 'EUR',
                'netAmount' => $unitPriceNet,
                'grossAmount' => $unitPriceNet,
                'taxRatePercentage' => 0,
            ],
        ];
    }, $items);

    $voucherDate = (new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin')))->format('Y-m-d\TH:i:s.vP');

    $payload = [
        'archived' => false,
        'voucherDate' => $voucherDate,
        'address' => [
            'contactId' => $contactId,
            'name' => trim($order['first_name'] . ' ' . $order['last_name']),
            'street' => (string) $order['billing_street'],
            'zip' => (string) $order['billing_zip'],
            'city' => (string) $order['billing_city'],
            'countryCode' => (string) $order['billing_country_code'],
        ],
        'lineItems' => $lineItems,
        'totalPrice' => ['currency' => 'EUR'],
        'taxConditions' => ['taxType' => 'vatfree'],
        'shippingConditions' => ['shippingType' => 'none'],
    ];

    return lexoffice_request('POST', '/invoices', $payload, ['finalize' => lexoffice_auto_finalize() ? 'true' : 'false']);
}

/**
 * Orchestriert den Sync einer Order: lädt Order+Items, legt Kontakt+Rechnung an,
 * schreibt das Ergebnis zurück. Fängt alle Throwable ab und wirft niemals nach außen –
 * Fehler werden in lexoffice_sync_status/lexoffice_last_error festgehalten, damit der
 * Checkout-Response davon unabhängig bleibt und der Cron/Admin-Retry später greifen kann.
 */
function lexoffice_sync_order(int $orderId): void
{
    $pdo = get_pdo();

    try {
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order) {
            throw new RuntimeException("Order #$orderId nicht gefunden.");
        }

        $itemsStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ?');
        $itemsStmt->execute([$orderId]);
        $items = $itemsStmt->fetchAll();

        $contactId = $order['lexoffice_contact_id'];
        if ($contactId === '' || $contactId === null) {
            $contactId = lexoffice_find_or_create_contact([
                'first_name' => $order['first_name'],
                'last_name' => $order['last_name'],
                'email' => $order['customer_email'],
                'phone' => $order['customer_phone'],
                'street' => $order['billing_street'],
                'zip' => $order['billing_zip'],
                'city' => $order['billing_city'],
                'country_code' => $order['billing_country_code'],
            ]);
            // Sofort speichern, unabhängig vom Rechnungs-Schritt: verhindert doppelte
            // Kontakte bei einem Retry, falls nur die Rechnung fehlschlägt.
            $update = $pdo->prepare('UPDATE orders SET lexoffice_contact_id = ? WHERE id = ?');
            $update->execute([$contactId, $orderId]);
        }

        $invoice = lexoffice_create_invoice($contactId, $order, $items);
        $invoiceId = $invoice['id'] ?? null;
        if (!is_string($invoiceId) || $invoiceId === '') {
            throw new LexofficeApiException('Lexware-Office-Antwort enthielt keine Rechnungs-ID.', 0, $invoice);
        }

        $stmt = $pdo->prepare(
            'UPDATE orders SET lexoffice_invoice_id = ?, lexoffice_sync_status = "synced",
             lexoffice_last_error = NULL, lexoffice_attempts = lexoffice_attempts + 1,
             lexoffice_last_attempt_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$invoiceId, $orderId]);
    } catch (Throwable $e) {
        $message = mb_substr($e->getMessage(), 0, 1000);
        error_log("JOTECH: Lexware-Office-Sync für Order #$orderId fehlgeschlagen: $message");
        try {
            $stmt = $pdo->prepare(
                'UPDATE orders SET lexoffice_sync_status = "failed", lexoffice_last_error = ?,
                 lexoffice_attempts = lexoffice_attempts + 1, lexoffice_last_attempt_at = NOW() WHERE id = ?'
            );
            $stmt->execute([$message, $orderId]);
        } catch (Throwable $inner) {
            error_log("JOTECH: Konnte Fehlerstatus für Order #$orderId nicht speichern: " . $inner->getMessage());
        }
    }
}
