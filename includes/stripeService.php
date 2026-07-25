<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';

// Prozeduraler Stil passend zum Rest des Projekts, analog zu lexwareOfficeService.php.
class StripeApiException extends RuntimeException
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

function stripe_base_url(): string
{
    return rtrim((string) env('STRIPE_API_BASE_URL', 'https://api.stripe.com/v1'), '/');
}

function stripe_site_base_url(): string
{
    $url = (string) env('SITE_BASE_URL', '');
    if ($url === '') {
        throw new StripeApiException('SITE_BASE_URL ist nicht konfiguriert.', 0);
    }
    return rtrim($url, '/');
}

/**
 * Zentraler HTTP-Client für die Stripe-API. Stripe erwartet form-urlencoded Bodies;
 * verschachtelte Arrays (z.B. line_items) werden von http_build_query() automatisch
 * in der von Stripe erwarteten Bracket-Notation kodiert.
 *
 * @param array<string, mixed>|null $body
 * @return array<string, mixed>
 */
/**
 * Stripe erwartet für boolsche Parameter (z.B. phone_number_collection[enabled])
 * die literalen Strings "true"/"false". http_build_query() würde PHP-Booleans
 * sonst stillschweigend zu "1"/"" machen, was die API mit "Invalid boolean" ablehnt.
 *
 * @param array<string, mixed> $body
 * @return array<string, mixed>
 */
function stripe_normalize_body(array $body): array
{
    $normalized = [];
    foreach ($body as $key => $value) {
        if (is_bool($value)) {
            $normalized[$key] = $value ? 'true' : 'false';
        } elseif (is_array($value)) {
            $normalized[$key] = stripe_normalize_body($value);
        } else {
            $normalized[$key] = $value;
        }
    }
    return $normalized;
}

function stripe_request(string $method, string $path, ?array $body = null): array
{
    $apiKey = env('STRIPE_SECRET_KEY');
    if (!$apiKey) {
        throw new StripeApiException('STRIPE_SECRET_KEY ist nicht konfiguriert.', 0);
    }

    $headers = ['Authorization: Bearer ' . $apiKey];
    $apiVersion = env('STRIPE_API_VERSION', '');
    if ($apiVersion) {
        $headers[] = 'Stripe-Version: ' . $apiVersion;
    }

    $options = [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ];
    if ($body !== null) {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        $options[CURLOPT_POSTFIELDS] = http_build_query(stripe_normalize_body($body));
    }
    $options[CURLOPT_HTTPHEADER] = $headers;

    $ch = curl_init(stripe_base_url() . $path);
    curl_setopt_array($ch, $options);
    $responseRaw = curl_exec($ch);
    $curlErrno = curl_errno($ch);
    $curlError = curl_error($ch);
    $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($curlErrno !== 0) {
        throw new StripeApiException("Netzwerkfehler bei Stripe-Anfrage: $curlError", 0);
    }

    $decoded = ($responseRaw !== false && $responseRaw !== '') ? json_decode($responseRaw, true) : null;
    $decodedArray = is_array($decoded) ? $decoded : null;

    if ($statusCode >= 200 && $statusCode < 300) {
        return $decodedArray ?? [];
    }

    $message = $decodedArray['error']['message'] ?? "Stripe-API-Fehler: HTTP $statusCode bei $method $path";
    throw new StripeApiException((string) $message, $statusCode, $decodedArray);
}

/**
 * Erstellt eine Stripe Checkout Session für eine Bestellung. Adresse, Name und
 * E-Mail werden bewusst NICHT von uns abgefragt, sondern von Stripes gehosteter
 * Seite eingesammelt (billing_address_collection=required) und kommen erst über
 * das Webhook-Event `checkout.session.completed` zurück.
 *
 * @param array<string, mixed> $order
 * @param list<array<string, mixed>> $items
 * @return array<string, mixed>
 */
function stripe_create_checkout_session(array $order, array $items): array
{
    $siteUrl = stripe_site_base_url();

    $lineItems = [];
    foreach ($items as $index => $item) {
        $lineItems[$index] = [
            'quantity' => (int) $item['quantity'],
            'price_data' => [
                'currency' => 'eur',
                'unit_amount' => (int) round(((float) $item['unit_price_gross']) * 100),
                'product_data' => [
                    'name' => (string) $item['name'],
                ],
            ],
        ];
    }

    $payload = [
        'mode' => 'payment',
        'client_reference_id' => (string) $order['id'],
        'line_items' => $lineItems,
        'billing_address_collection' => 'required',
        'phone_number_collection' => ['enabled' => true],
        'success_url' => $siteUrl . '/danke.html?status=success&source=order',
        'cancel_url' => $siteUrl . '/shop.html?checkout=cancelled',
    ];

    return stripe_request('POST', '/checkout/sessions', $payload);
}

/**
 * Verifiziert die Signatur eines Stripe-Webhook-Requests ohne SDK, gemäß
 * https://docs.stripe.com/webhooks#verify-manually — HMAC-SHA256 über
 * "{timestamp}.{payload}" mit dem Webhook-Secret, Toleranz von 5 Minuten gegen
 * Replay-Angriffe.
 */
function stripe_verify_webhook_signature(string $payload, string $sigHeader, string $secret): bool
{
    $timestamp = null;
    $signatures = [];
    foreach (explode(',', $sigHeader) as $part) {
        [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);
        if ($key === 't') {
            $timestamp = $value;
        } elseif ($key === 'v1' && $value !== null) {
            $signatures[] = $value;
        }
    }

    if ($timestamp === null || !$signatures) {
        return false;
    }

    if (abs(time() - (int) $timestamp) > 300) {
        return false;
    }

    $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    foreach ($signatures as $signature) {
        if (hash_equals($expected, $signature)) {
            return true;
        }
    }

    return false;
}
