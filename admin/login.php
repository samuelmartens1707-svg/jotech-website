<?php
declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';

if (isset($_SESSION['admin_user_id'])) {
    header('Location: index.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $stmt = get_pdo()->prepare('SELECT id, username, password_hash FROM admin_users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        login_admin((int) $user['id'], $user['username']);
        header('Location: index.php');
        exit;
    }

    $error = 'Benutzername oder Passwort ist falsch.';
}

require __DIR__ . '/../includes/admin-partials.php';
admin_head('Anmelden');
?>

<div class="login-shell">
  <div class="eyebrow">JOTECH Admin</div>
  <h1 style="font-size:clamp(1.8rem,5vw,2.6rem); margin-bottom:1.6rem;">Anmelden</h1>

  <?php if ($error): ?>
    <p class="flash"><?= htmlspecialchars($error, ENT_QUOTES) ?></p>
  <?php endif; ?>

  <form method="POST" novalidate>
    <?= csrf_field() ?>
    <div class="field">
      <label for="username">Benutzername</label>
      <input type="text" id="username" name="username" required autofocus>
    </div>
    <div class="field">
      <label for="password">Passwort</label>
      <input type="password" id="password" name="password" required>
    </div>
    <button type="submit" class="btn btn--primary btn--block">Anmelden</button>
  </form>
</div>

<?php admin_foot(); ?>
