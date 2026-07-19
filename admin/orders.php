<?php
declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
require_admin_login();

$pdo = get_pdo();

$syncFilter = (string) ($_GET['sync'] ?? '');
// Standardmäßig werden abgebrochene/nie bezahlte Checkout-Versuche ausgeblendet,
// damit die Liste nicht mit Karteileichen zugemüllt wird — über den Filter
// weiterhin einsehbar.
$paymentFilter = (string) ($_GET['payment'] ?? 'paid');

$where = [];
$params = [];
if (in_array($syncFilter, ['pending', 'synced', 'failed'], true)) {
    $where[] = 'lexoffice_sync_status = ?';
    $params[] = $syncFilter;
}
if (in_array($paymentFilter, ['pending', 'paid'], true)) {
    $where[] = 'payment_status = ?';
    $params[] = $paymentFilter;
}
$sql = 'SELECT * FROM orders';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

function orders_qs(string $sync, string $payment): string
{
    $params = [];
    if ($sync !== '') {
        $params['sync'] = $sync;
    }
    if ($payment !== '') {
        $params['payment'] = $payment;
    }
    return $params ? '?' . http_build_query($params) : '';
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
    Zahlung:
    <a href="<?= orders_qs($syncFilter, 'paid') ?>" class="<?= $paymentFilter === 'paid' ? 'is-active' : '' ?>" style="margin-left:.4em;">Bezahlt</a> ·
    <a href="<?= orders_qs($syncFilter, 'pending') ?>" class="<?= $paymentFilter === 'pending' ? 'is-active' : '' ?>">Ausstehend</a> ·
    <a href="<?= orders_qs($syncFilter, '') ?>" class="<?= $paymentFilter === '' ? 'is-active' : '' ?>">Alle (auch unbezahlt)</a>
  </div>
  <div>
    Sync-Status:
    <a href="<?= orders_qs('', $paymentFilter) ?>" class="<?= $syncFilter === '' ? 'is-active' : '' ?>" style="margin-left:.4em;">Alle</a> ·
    <a href="<?= orders_qs('pending', $paymentFilter) ?>">Ausstehend</a> ·
    <a href="<?= orders_qs('synced', $paymentFilter) ?>">Synchronisiert</a> ·
    <a href="<?= orders_qs('failed', $paymentFilter) ?>">Fehlgeschlagen</a>
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
        <th>Zahlung</th>
        <th>Lexware-Sync</th>
        <th>Aktionen</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$orders): ?>
        <tr><td colspan="7">Keine Bestellungen gefunden.</td></tr>
      <?php endif; ?>
      <?php foreach ($orders as $o): ?>
        <tr>
          <td data-label="Eingegangen"><?= htmlspecialchars($o['created_at'], ENT_QUOTES) ?></td>
          <td data-label="Kunde"><?= htmlspecialchars(trim($o['first_name'] . ' ' . $o['last_name']) ?: '—', ENT_QUOTES) ?></td>
          <td data-label="E-Mail"><?= htmlspecialchars($o['customer_email'] ?? '—', ENT_QUOTES) ?></td>
          <td data-label="Gesamt"><?= number_format((float) $o['total_gross'], 2, ',', '.') ?>&nbsp;€</td>
          <td data-label="Zahlung"><?= payment_status_pill($o['payment_status']) ?></td>
          <td data-label="Lexware-Sync"><?= lexoffice_sync_status_pill($o['lexoffice_sync_status']) ?></td>
          <td class="actions" data-label="Aktionen"><a href="order-view.php?id=<?= (int) $o['id'] ?>">Ansehen</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php admin_foot(); ?>
