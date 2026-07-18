<?php
declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/mailer.php';

if (isset($_SESSION['admin_user_id'])) {
    header('Location: index.php');
    exit;
}

const RESET_TOKEN_TTL_MINUTES = 30;

$sent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $identifier = trim((string) ($_POST['identifier'] ?? ''));
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT id, username, email FROM admin_users WHERE username = ? OR email = ?');
    $stmt->execute([$identifier, $identifier]);
    $user = $stmt->fetch();

    if ($user && $user['email']) {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + RESET_TOKEN_TTL_MINUTES * 60);

        $pdo->prepare('INSERT INTO password_resets (admin_user_id, token_hash, expires_at) VALUES (?, ?, ?)')
            ->execute([$user['id'], $tokenHash, $expiresAt]);

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $resetUrl = $scheme . '://' . $host . '/admin/reset-password.php?token=' . $token;

        $body = "Hallo {$user['username']},\n\n"
            . "für dein JOTECH-Admin-Konto wurde ein Passwort-Reset angefordert.\n"
            . "Falls du das warst, klicke innerhalb von " . RESET_TOKEN_TTL_MINUTES . " Minuten auf folgenden Link:\n\n"
            . $resetUrl . "\n\n"
            . "Falls du das nicht warst, kannst du diese E-Mail einfach ignorieren.\n";

        send_mail($user['email'], $user['username'], 'JOTECH Admin – Passwort zurücksetzen', $body);
    }

    // Immer dieselbe Meldung anzeigen, unabhängig davon ob Konto/E-Mail existiert
    // (verhindert, dass Angreifer gültige Benutzernamen/E-Mails erraten können).
    $sent = true;
}

require __DIR__ . '/../includes/admin-partials.php';
admin_head('Passwort vergessen');
?>

<div class="login-shell">
  <div class="eyebrow">JOTECH Admin</div>
  <h1 style="font-size:clamp(1.8rem,5vw,2.6rem); margin-bottom:1.6rem;">Passwort vergessen</h1>

  <?php if ($sent): ?>
    <p class="flash flash--ok">Falls das Konto existiert und eine E-Mail-Adresse hinterlegt ist, wurde soeben ein Link zum Zurücksetzen verschickt.</p>
    <p><a href="login.php">Zurück zum Login</a></p>
  <?php else: ?>
    <form method="POST" novalidate>
      <?= csrf_field() ?>
      <div class="field">
        <label for="identifier">Benutzername oder E-Mail-Adresse</label>
        <input type="text" id="identifier" name="identifier" required autofocus>
      </div>
      <button type="submit" class="btn btn--primary btn--block">Link anfordern</button>
    </form>
    <p style="margin-top:1rem;"><a href="login.php">Zurück zum Login</a></p>
  <?php endif; ?>
</div>

<?php admin_foot(); ?>
