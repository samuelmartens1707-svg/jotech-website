<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';

// Schlanker SMTP-Client ohne Composer-Abhängigkeit (passend zum Rest des Projekts).
// Konfiguration ausschließlich über Umgebungsvariablen (siehe .env.example):
// SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, SMTP_FROM_EMAIL, SMTP_FROM_NAME,
// SMTP_ENCRYPTION (ssl|tls|none, Default abhängig vom Port).
// Solange SMTP_HOST nicht gesetzt ist, liefert send_mail() einfach false —
// das Feature ist dann inaktiv, ohne Fehler zu werfen.

function smtp_configured(): bool
{
    return env('SMTP_HOST', '') !== '';
}

function send_mail(string $toEmail, string $toName, string $subject, string $body): bool
{
    if (!smtp_configured()) {
        return false;
    }

    try {
        mailer_send_via_smtp($toEmail, $toName, $subject, $body);
        return true;
    } catch (Throwable $e) {
        error_log('JOTECH Mailer: ' . $e->getMessage());
        return false;
    }
}

function mailer_send_via_smtp(string $toEmail, string $toName, string $subject, string $body): void
{
    $host = env('SMTP_HOST', '');
    $port = (int) env('SMTP_PORT', '587');
    $user = env('SMTP_USER', '');
    $pass = env('SMTP_PASS', '');
    $fromEmail = env('SMTP_FROM_EMAIL', $user !== '' ? $user : 'no-reply@' . $host);
    $fromName = env('SMTP_FROM_NAME', 'JOTECH');
    $encryption = env('SMTP_ENCRYPTION', $port === 465 ? 'ssl' : ($port === 587 ? 'tls' : 'none'));
    $allowSelfSigned = env('SMTP_ALLOW_SELF_SIGNED', 'false') === 'true';

    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => !$allowSelfSigned,
            'verify_peer_name' => !$allowSelfSigned,
            'allow_self_signed' => $allowSelfSigned,
        ],
    ]);

    $transport = $encryption === 'ssl' ? 'ssl://' : 'tcp://';
    $socket = @stream_socket_client(
        $transport . $host . ':' . $port,
        $errno,
        $errstr,
        10,
        STREAM_CLIENT_CONNECT,
        $context
    );
    if (!$socket) {
        throw new RuntimeException("SMTP-Verbindung zu $host:$port fehlgeschlagen: $errstr ($errno)");
    }
    stream_set_timeout($socket, 10);

    try {
        mailer_read($socket, [220]);
        $localHost = $_SERVER['SERVER_NAME'] ?? 'localhost';
        mailer_command($socket, 'EHLO ' . $localHost, [250]);

        if ($encryption === 'tls') {
            mailer_command($socket, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('SMTP-TLS-Handshake fehlgeschlagen.');
            }
            mailer_command($socket, 'EHLO ' . $localHost, [250]);
        }

        if ($user !== '') {
            mailer_command($socket, 'AUTH LOGIN', [334]);
            mailer_command($socket, base64_encode($user), [334]);
            mailer_command($socket, base64_encode($pass), [235]);
        }

        mailer_command($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
        mailer_command($socket, 'RCPT TO:<' . $toEmail . '>', [250, 251]);
        mailer_command($socket, 'DATA', [354]);

        $headers = [
            'Date: ' . date('r'),
            'From: ' . mailer_encode_header($fromName) . ' <' . $fromEmail . '>',
            'To: ' . mailer_encode_header($toName) . ' <' . $toEmail . '>',
            'Subject: ' . mailer_encode_header($subject),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];
        $normalizedBody = str_replace("\r\n", "\n", $body);
        $normalizedBody = str_replace("\n", "\r\n", $normalizedBody);
        // SMTP-Regel: Zeilen, die mit einem Punkt beginnen, müssen verdoppelt werden.
        $escapedBody = preg_replace('/^\./m', '..', $normalizedBody);

        $message = implode("\r\n", $headers) . "\r\n\r\n" . $escapedBody;
        mailer_write($socket, $message . "\r\n.\r\n");
        mailer_read($socket, [250]);

        mailer_command($socket, 'QUIT', [221]);
    } finally {
        fclose($socket);
    }
}

/** @param resource $socket */
function mailer_command($socket, string $command, array $expectedCodes): void
{
    mailer_write($socket, $command . "\r\n");
    mailer_read($socket, $expectedCodes);
}

/** @param resource $socket */
function mailer_write($socket, string $data): void
{
    if (fwrite($socket, $data) === false) {
        throw new RuntimeException('SMTP: Konnte keine Daten senden.');
    }
}

/** @param resource $socket */
function mailer_read($socket, array $expectedCodes): string
{
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        // Letzte Zeile einer (Multiline-)Antwort hat ein Leerzeichen statt Bindestrich an Position 4.
        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }
    if ($response === '') {
        throw new RuntimeException('SMTP: Keine Antwort vom Server erhalten.');
    }
    $code = (int) substr($response, 0, 3);
    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException("SMTP-Fehler: " . trim($response));
    }
    return $response;
}

function mailer_encode_header(string $value): string
{
    if (preg_match('/^[\x20-\x7E]*$/', $value) === 1) {
        return $value;
    }
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}
