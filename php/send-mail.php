<?php
declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/mailer.php';

// Empfänger über INQUIRY_RECIPIENT_EMAIL konfigurierbar (siehe .env.example) — Fallback
// info@jotech.de nur nutzen, solange die Domain/das Postfach tatsächlich existiert.
define('RECIPIENT_EMAIL', (string) env('INQUIRY_RECIPIENT_EMAIL', 'info@jotech.de'));
define('MIN_SECONDS_TO_FILL', 3);
define('MAX_FIELD_LENGTH', 4000);

function redirect_to(string $status): void
{
    header('Location: ../danke.html?status=' . $status);
    exit;
}

function clean(string $value): string
{
    $value = trim(strip_tags($value));
    $value = str_replace(["\r", "\n"], ' ', $value);
    return mb_substr($value, 0, MAX_FIELD_LENGTH);
}

function clean_multiline(string $value): string
{
    $value = trim(strip_tags($value));
    $value = str_replace("\r\n", "\n", $value);
    return mb_substr($value, 0, MAX_FIELD_LENGTH);
}

function encode_header(string $value): string
{
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

// Versucht zuerst den konfigurierten SMTP-Versand (send_mail()); solange SMTP nicht
// eingerichtet oder fehlerhaft ist, fällt das auf PHP-natives mail() zurück, damit
// Formular-Anfragen nie stillschweigend verloren gehen.
function send_notification(
    string $toEmail,
    string $toName,
    string $subject,
    string $body,
    ?string $replyToEmail = null,
    ?string $replyToName = null
): bool {
    if (send_mail($toEmail, $toName, $subject, $body, $replyToEmail, $replyToName)) {
        return true;
    }

    $headers = [
        'From: JOTECH Website <no-reply@jotech.de>',
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: PHP/' . phpversion(),
    ];
    if ($replyToEmail !== null && $replyToEmail !== '') {
        $headers[] = 'Reply-To: ' . encode_header($replyToName ?? $replyToEmail) . ' <' . $replyToEmail . '>';
    }

    return mail($toEmail, encode_header($subject), $body, implode("\r\n", $headers));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('error');
}

// Honeypot field: real users never fill this in, so treat it as spam and
// silently pretend success rather than tipping off the bot.
if (!empty($_POST['website'])) {
    redirect_to('success');
}

// Forms younger than a few seconds are almost always scripted submissions.
$loadedAt = isset($_POST['form_loaded_at']) ? (int) $_POST['form_loaded_at'] : 0;
if ($loadedAt > 0 && (time() - $loadedAt) < MIN_SECONDS_TO_FILL) {
    redirect_to('error');
}

$formType = clean((string) ($_POST['form_type'] ?? ''));
if (!in_array($formType, ['ankauf', 'reparatur', 'kontakt'], true)) {
    redirect_to('error');
}

$name = clean((string) ($_POST['name'] ?? ''));
$emailRaw = trim((string) ($_POST['email'] ?? ''));

if ($name === '' || !filter_var($emailRaw, FILTER_VALIDATE_EMAIL)) {
    redirect_to('error');
}
$email = clean($emailRaw);

if (empty($_POST['consent'])) {
    redirect_to('error');
}

$phone = clean((string) ($_POST['phone'] ?? ''));
$location = clean((string) ($_POST['location'] ?? ''));

$subjectLabel = '';
$inquirySubject = '';
$detailLines = [];

switch ($formType) {
    case 'ankauf':
        $subjectLabel = 'Neue Ankauf-Anfrage';
        $deviceType = clean((string) ($_POST['device_type'] ?? ''));
        $condition = clean((string) ($_POST['condition'] ?? ''));
        $brandModel = clean((string) ($_POST['brand_model'] ?? ''));
        if ($deviceType === '' || $condition === '' || $brandModel === '') {
            redirect_to('error');
        }
        $detailLines[] = ['Gerätetyp', $deviceType];
        $detailLines[] = ['Zustand', $condition];
        $detailLines[] = ['Marke / Modell', $brandModel];
        $detailLines[] = ['Baujahr', clean((string) ($_POST['build_year'] ?? ''))];
        $detailLines[] = ['Geschätzter Neupreis', clean((string) ($_POST['original_price'] ?? ''))];
        $detailLines[] = ['Beschreibung', clean_multiline((string) ($_POST['description'] ?? ''))];
        $inquirySubject = $brandModel;
        break;

    case 'reparatur':
        $subjectLabel = 'Neue Reparatur-Anfrage';
        $deviceType = clean((string) ($_POST['device_type'] ?? ''));
        $brandModel = clean((string) ($_POST['brand_model'] ?? ''));
        $issuesRaw = $_POST['issues'] ?? [];
        $issues = is_array($issuesRaw) ? array_map(fn($v) => clean((string) $v), $issuesRaw) : [];
        if ($deviceType === '' || $brandModel === '' || empty($issues)) {
            redirect_to('error');
        }
        $detailLines[] = ['Gerätetyp', $deviceType];
        $detailLines[] = ['Fehlerbild', implode(', ', $issues)];
        $detailLines[] = ['Marke / Modell', $brandModel];
        $detailLines[] = ['Fehler seit', clean((string) ($_POST['issue_since'] ?? ''))];
        $detailLines[] = ['Beschreibung', clean_multiline((string) ($_POST['description'] ?? ''))];
        $inquirySubject = $brandModel;
        break;

    case 'kontakt':
        $subjectLabel = 'Neue Kontaktanfrage';
        $message = clean_multiline((string) ($_POST['message'] ?? ''));
        if ($message === '') {
            redirect_to('error');
        }
        $inquirySubject = clean((string) ($_POST['subject'] ?? ''));
        $detailLines[] = ['Betreff', $inquirySubject];
        $detailLines[] = ['Nachricht', $message];
        break;
}

// Anfrage in der Datenbank speichern, damit sie im Admin-Bereich sichtbar ist.
// Ein DB-Fehler darf den Versand der E-Mail nicht verhindern.
try {
    $detailsAssoc = [];
    foreach ($detailLines as [$label, $value]) {
        $detailsAssoc[$label] = $value;
    }
    $stmt = get_pdo()->prepare(
        'INSERT INTO inquiries (form_type, name, email, phone, location, subject, details_json) VALUES (?,?,?,?,?,?,?)'
    );
    $stmt->execute([
        $formType, $name, $email, $phone, $location, $inquirySubject,
        json_encode($detailsAssoc, JSON_UNESCAPED_UNICODE),
    ]);
} catch (Throwable $e) {
    error_log('JOTECH: Anfrage konnte nicht in DB gespeichert werden: ' . $e->getMessage());
}

$body = "$subjectLabel über die JOTECH Website\n";
$body .= str_repeat('-', 40) . "\n\n";
$body .= "Name: $name\n";
$body .= "E-Mail: $email\n";
if ($phone !== '') {
    $body .= "Telefon: $phone\n";
}
if ($location !== '') {
    $body .= "PLZ / Ort: $location\n";
}
$body .= "\n";
foreach ($detailLines as [$label, $value]) {
    if ($value === '') {
        continue;
    }
    $body .= "$label:\n$value\n\n";
}

$sent = send_notification(RECIPIENT_EMAIL, 'JOTECH', 'JOTECH — ' . $subjectLabel, $body, $email, $name);

// Best-effort: eine fehlgeschlagene Kundenbestätigung darf den eigentlichen
// Anfragen-Eingang (oben) nicht als "error" erscheinen lassen.
if ($sent) {
    $confirmationBody = "Hallo $name,\n\n"
        . "vielen Dank für deine Anfrage bei JOTECH. Wir haben sie erhalten und melden uns so schnell wie möglich bei dir.\n\n"
        . "Zur Erinnerung, das hast du uns geschickt:\n"
        . str_repeat('-', 40) . "\n\n";
    foreach ($detailLines as [$label, $value]) {
        if ($value === '') {
            continue;
        }
        $confirmationBody .= "$label:\n$value\n\n";
    }
    $confirmationBody .= "Viele Grüße\nDein JOTECH-Team";

    send_notification($email, $name, 'Deine Anfrage bei JOTECH ist eingegangen', $confirmationBody);
}

redirect_to($sent ? 'success' : 'error');
