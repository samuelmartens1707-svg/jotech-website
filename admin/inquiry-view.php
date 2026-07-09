<?php
declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
require_admin_login();

$pdo = get_pdo();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $newStatus = (string) ($_POST['status'] ?? '');
    if (in_array($newStatus, ['neu', 'bearbeitet', 'erledigt'], true)) {
        $stmt = $pdo->prepare('UPDATE inquiries SET status = ? WHERE id = ?');
        $stmt->execute([$newStatus, $id]);
    }
    header('Location: inquiry-view.php?id=' . $id . '&saved=1');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM inquiries WHERE id = ?');
$stmt->execute([$id]);
$inquiry = $stmt->fetch();

if (!$inquiry) {
    header('Location: inquiries.php');
    exit;
}

$details = json_decode($inquiry['details_json'], true) ?: [];
$typeLabels = ['ankauf' => 'Ankauf-Anfrage', 'reparatur' => 'Reparatur-Anfrage', 'kontakt' => 'Kontaktanfrage'];

require __DIR__ . '/../includes/admin-partials.php';
admin_head('Anfrage #' . $id);
admin_nav('inquiries');
?>

<div class="admin-title-row">
  <h1 style="font-size:clamp(1.8rem,5vw,2.6rem);"><?= htmlspecialchars($typeLabels[$inquiry['form_type']] ?? $inquiry['form_type'], ENT_QUOTES) ?></h1>
  <a href="inquiries.php" class="btn btn--ghost">← Zurück zur Liste</a>
</div>

<?php if (isset($_GET['saved'])): ?>
  <p class="flash flash--ok">Status wurde aktualisiert.</p>
<?php endif; ?>

<div class="info-card" style="margin-bottom:1.6rem;">
  <h4>Kontaktdaten</h4>
  <p style="margin:0;">
    <?= htmlspecialchars($inquiry['name'], ENT_QUOTES) ?><br>
    <a href="mailto:<?= htmlspecialchars($inquiry['email'], ENT_QUOTES) ?>" style="color:var(--red);"><?= htmlspecialchars($inquiry['email'], ENT_QUOTES) ?></a><br>
    <?php if ($inquiry['phone']): ?><?= htmlspecialchars($inquiry['phone'], ENT_QUOTES) ?><br><?php endif; ?>
    <?php if ($inquiry['location']): ?><?= htmlspecialchars($inquiry['location'], ENT_QUOTES) ?><br><?php endif; ?>
    Eingegangen am <?= htmlspecialchars($inquiry['created_at'], ENT_QUOTES) ?>
  </p>
</div>

<div class="info-card" style="margin-bottom:1.6rem;">
  <h4>Details</h4>
  <?php foreach ($details as $label => $value): ?>
    <?php if ($value === '' || $value === null) continue; ?>
    <p style="margin-bottom:.6em;"><strong><?= htmlspecialchars((string) $label, ENT_QUOTES) ?>:</strong><br><?= nl2br(htmlspecialchars((string) $value, ENT_QUOTES)) ?></p>
  <?php endforeach; ?>
</div>

<form method="POST" style="max-width:320px;">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $id ?>">
  <div class="field">
    <label for="status">Status</label>
    <select id="status" name="status">
      <option value="neu" <?= $inquiry['status'] === 'neu' ? 'selected' : '' ?>>Neu</option>
      <option value="bearbeitet" <?= $inquiry['status'] === 'bearbeitet' ? 'selected' : '' ?>>Bearbeitet</option>
      <option value="erledigt" <?= $inquiry['status'] === 'erledigt' ? 'selected' : '' ?>>Erledigt</option>
    </select>
  </div>
  <button type="submit" class="btn btn--primary">Status speichern</button>
</form>

<?php admin_foot(); ?>
