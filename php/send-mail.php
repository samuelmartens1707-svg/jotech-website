<?php
declare(strict_types=1);

require __DIR__ . '/../includes/db.php';

// ACHTUNG vor dem Livegang: Domain jotech.de ist noch nicht registriert, dieses Postfach
// existiert also noch nicht. Bis das Postfach eingerichtet ist, ersatzweise auf eine
// erreichbare Adresse (z.B. jotechstomin@gmail.com) umstellen, sonst gehen alle
// Formular-Anfragen ins Leere.
define('RECIPIENT_EMAIL', 'info@jotech.de');
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

$headers = [
    'From: JOTECH Website <no-reply@jotech.de>',
    'Reply-To: ' . encode_header($name) . ' <' . $email . '>',
    'Content-Type: text/plain; charset=UTF-8',
    'X-Mailer: PHP/' . phpversion(),
];

$sent = mail(RECIPIENT_EMAIL, encode_header('JOTECH — ' . $subjectLabel), $body, implode("\r\n", $headers));

redirect_to($sent ? 'success' : 'error');
