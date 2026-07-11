<?php
declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
require_admin_login();

$pdo = get_pdo();

$syncFilter = (string) ($_GET['sync'] ?? '');

$where = [];
$params = [];
if (in_array($syncFilter, ['pending', 'synced', 'failed'], true)) {
    $where[] = 'lexoffice_sync_status = ?';
    $params[] = $syncFilter;
}
$sql = 'SELECT * FROM orders';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

function orders_qs(string $sync): string
{
    return $sync !== '' ? '?sync=' . urlencode($sync) : '';
}

require __DIR__ . '/../includes/admin-partials.php';
admin_head('Bestellungen');
admin_nav('orders');
?>

<div class="admin-title-row">
  <h1 style="font-size:clamp(1.8rem,5vw,2.6rem);">Bestellungen</h1>
</div>

<div style="display:flex; gap:1.5rem; flex-wrap:wrap; margin-bottom:1.6rem; font-family:var(--font-mono); font-size:.8rem;">
  <div>
    Sync-Status:
    <a href="<?= orders_qs('') ?>" class="<?= $syncFilter === '' ? 'is-active' : '' ?>" style="margin-left:.4em;">Alle</a> ·
    <a href="<?= orders_qs('pending') ?>">Ausstehend</a> ·
    <a href="<?= orders_qs('synced') ?>">Synchronisiert</a> ·
    <a href="<?= orders_qs('failed') ?>">Fehlgeschlagen</a>
  </div>
</div>

<div class="table-wrap">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Eingegangen</th>
        <th>Kunde</th>
        <th>E-Mail</th>
        <th>Gesamt</th>
        <th>Lexware-Sync</th>
        <th>Aktionen</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$orders): ?>
        <tr><td colspan="6">Keine Bestellungen gefunden.</td></tr>
      <?php endif; ?>
      <?php foreach ($orders as $o): ?>
        <tr>
          <td><?= htmlspecialchars($o['created_at'], ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars($o['first_name'] . ' ' . $o['last_name'], ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars($o['customer_email'], ENT_QUOTES) ?></td>
          <td><?= number_format((float) $o['total_gross'], 2, ',', '.') ?>&nbsp;€</td>
          <td><?= lexoffice_sync_status_pill($o['lexoffice_sync_status']) ?></td>
          <td class="actions"><a href="order-view.php?id=<?= (int) $o['id'] ?>">Ansehen</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php admin_foot(); ?>
