<?php
declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';

$pdo = get_pdo();
$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$tokenHash = hash('sha256', $token);

$stmt = $pdo->prepare(
    'SELECT pr.admin_user_id FROM password_resets pr
     WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > NOW()'
);
$stmt->execute([$tokenHash]);
$reset = $token !== '' ? $stmt->fetch() : false;

$errors = [];
$done = false;

if (!$reset) {
    $errors[] = 'Der Link ist ungültig oder abgelaufen. Bitte fordere einen neuen an.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

    if (strlen($password) < 8) {
        $errors[] = 'Das Passwort muss mindestens 8 Zeichen lang sein.';
    } elseif ($password !== $passwordConfirm) {
        $errors[] = 'Die Passwörter stimmen nicht überein.';
    }

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?')
            ->execute([$hash, $reset['admin_user_id']]);
        $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE admin_user_id = ? AND used_at IS NULL')
            ->execute([$reset['admin_user_id']]);
        $done = true;
    }
}

require __DIR__ . '/../includes/admin-partials.php';
admin_head('Neues Passwort vergeben');
?>

<div class="login-shell">
  <div class="eyebrow">JOTECH Admin</div>
  <h1 style="font-size:clamp(1.8rem,5vw,2.6rem); margin-bottom:1.6rem;">Neues Passwort vergeben</h1>

  <?php foreach ($errors as $err): ?>
    <p class="flash"><?= htmlspecialchars($err, ENT_QUOTES) ?></p>
  <?php endforeach; ?>

  <?php if ($done): ?>
    <p class="flash flash--ok">Passwort wurde geändert. Du kannst dich jetzt anmelden.</p>
    <p><a href="login.php">Zum Login</a></p>
  <?php elseif ($reset): ?>
    <form method="POST" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
      <div class="field">
        <label for="password">Neues Passwort</label>
        <input type="password" id="password" name="password" required minlength="8" autofocus>
      </div>
      <div class="field">
        <label for="password_confirm">Neues Passwort bestätigen</label>
        <input type="password" id="password_confirm" name="password_confirm" required minlength="8">
      </div>
      <button type="submit" class="btn btn--primary btn--block">Passwort speichern</button>
    </form>
  <?php else: ?>
    <p><a href="forgot-password.php">Neuen Link anfordern</a></p>
  <?php endif; ?>
</div>

<?php admin_foot(); ?>
