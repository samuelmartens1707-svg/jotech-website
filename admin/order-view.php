<?php
declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/lexwareOfficeService.php';
require_admin_login();

$pdo = get_pdo();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (($_POST['action'] ?? '') === 'retry_sync') {
        lexoffice_sync_order($id);
    }
    header('Location: order-view.php?id=' . $id . '&synced=1');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: orders.php');
    exit;
}

$itemsStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ?');
$itemsStmt->execute([$id]);
$items = $itemsStmt->fetchAll();

require __DIR__ . '/../includes/admin-partials.php';
admin_head('Bestellung #' . $id);
admin_nav('orders');
?>

<div class="admin-title-row">
  <h1 style="font-size:clamp(1.8rem,5vw,2.6rem);">Bestellung #<?= (int) $id ?></h1>
  <a href="orders.php" class="btn btn--ghost">← Zurück zur Liste</a>
</div>

<?php if (isset($_GET['synced'])): ?>
  <p class="flash flash--ok">Sync-Vorgang wurde ausgeführt.</p>
<?php endif; ?>

<div class="info-card" style="margin-bottom:1.6rem;">
  <h4>Kunde</h4>
  <?php if ($order['payment_status'] !== 'paid'): ?>
    <p style="margin:0 0 .8em;">Noch keine Zahlung eingegangen — Kundendaten werden erst nach erfolgreicher Stripe-Zahlung übernommen.</p>
  <?php else: ?>
    <p style="margin:0 0 .8em;">
      <?= htmlspecialchars(trim($order['first_name'] . ' ' . $order['last_name']), ENT_QUOTES) ?><br>
      <a href="mailto:<?= htmlspecialchars((string) $order['customer_email'], ENT_QUOTES) ?>" style="color:var(--red);"><?= htmlspecialchars((string) $order['customer_email'], ENT_QUOTES) ?></a><br>
      <?php if ($order['customer_phone']): ?><?= htmlspecialchars($order['customer_phone'], ENT_QUOTES) ?><br><?php endif; ?>
      <?= htmlspecialchars((string) $order['billing_street'], ENT_QUOTES) ?><br>
      <?= htmlspecialchars($order['billing_zip'] . ' ' . $order['billing_city'], ENT_QUOTES) ?>,
      <?= htmlspecialchars((string) $order['billing_country_code'], ENT_QUOTES) ?>
    </p>
  <?php endif; ?>
  <p style="margin:0;">Eingegangen am <?= htmlspecialchars($order['created_at'], ENT_QUOTES) ?></p>
</div>

<div class="info-card" style="margin-bottom:1.6rem;">
  <h4>Zahlung (Stripe)</h4>
  <p style="margin:0;">
    Status: <?= payment_status_pill($order['payment_status']) ?><br>
    <?php if ($order['paid_at']): ?>Bezahlt am: <?= htmlspecialchars($order['paid_at'], ENT_QUOTES) ?><br><?php endif; ?>
    <?php if ($order['stripe_checkout_session_id']): ?>Checkout-Session: <code><?= htmlspecialchars($order['stripe_checkout_session_id'], ENT_QUOTES) ?></code><br><?php endif; ?>
    <?php if ($order['stripe_payment_intent_id']): ?>Payment-Intent: <code><?= htmlspecialchars($order['stripe_payment_intent_id'], ENT_QUOTES) ?></code><?php endif; ?>
  </p>
</div>

<div class="info-card" style="margin-bottom:1.6rem;">
  <h4>Positionen</h4>
  <div class="table-wrap">
    <table class="admin-table">
      <thead>
        <tr><th>Artikel</th><th>Menge</th><th>Einzelpreis</th><th>Summe</th></tr>
      </thead>
      <tbody>
        <?php foreach ($items as $item): ?>
          <tr>
            <td data-label="Artikel"><?= htmlspecialchars($item['name'], ENT_QUOTES) ?></td>
            <td data-label="Menge"><?= (int) $item['quantity'] ?>&nbsp;<?= htmlspecialchars($item['unit_name'], ENT_QUOTES) ?></td>
            <td data-label="Einzelpreis"><?= number_format((float) $item['unit_price_gross'], 2, ',', '.') ?>&nbsp;€</td>
            <td data-label="Summe"><?= number_format((float) $item['unit_price_gross'] * (int) $item['quantity'], 2, ',', '.') ?>&nbsp;€</td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p style="text-align:right; margin-top:.8rem; font-family:var(--font-mono);">
    Gesamt (steuerfrei, §19 UStG): <strong><?= number_format((float) $order['total_gross'], 2, ',', '.') ?>&nbsp;€</strong>
  </p>
</div>

<div class="info-card">
  <h4>Lexware-Office-Sync</h4>
  <p style="margin:0 0 .8em;">
    Status: <?= lexoffice_sync_status_pill($order['lexoffice_sync_status']) ?><br>
    Versuche: <?= (int) $order['lexoffice_attempts'] ?><br>
    <?php if ($order['lexoffice_last_attempt_at']): ?>Letzter Versuch: <?= htmlspecialchars($order['lexoffice_last_attempt_at'], ENT_QUOTES) ?><br><?php endif; ?>
    <?php if ($order['lexoffice_contact_id']): ?>Kontakt-ID: <?= htmlspecialchars($order['lexoffice_contact_id'], ENT_QUOTES) ?><br><?php endif; ?>
    <?php if ($order['lexoffice_invoice_id']): ?>Rechnungs-ID: <?= htmlspecialchars($order['lexoffice_invoice_id'], ENT_QUOTES) ?><br><?php endif; ?>
  </p>
  <?php if ($order['lexoffice_last_error']): ?>
    <p class="flash" style="margin-bottom:1.2rem;"><?= nl2br(htmlspecialchars($order['lexoffice_last_error'], ENT_QUOTES)) ?></p>
  <?php endif; ?>
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">
    <input type="hidden" name="action" value="retry_sync">
    <button type="submit" class="btn btn--primary">Jetzt erneut synchronisieren</button>
  </form>
</div>

<?php admin_foot(); ?>
