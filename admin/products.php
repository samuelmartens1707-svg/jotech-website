<?php
declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
require_admin_login();

$pdo = get_pdo();
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['delete', 'move_up', 'move_down'], true)) {
    csrf_verify();
    $action = $_POST['action'];
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete') {
        $stmt = $pdo->prepare('DELETE FROM products WHERE id = ?');
        $stmt->execute([$id]);
        header('Location: products.php?deleted=1');
        exit;
    }

    $ordered = $pdo->query('SELECT id, sort_order FROM products ORDER BY sort_order ASC, id ASC')->fetchAll();
    $index = null;
    foreach ($ordered as $i => $row) {
        if ((int) $row['id'] === $id) {
            $index = $i;
            break;
        }
    }
    if ($index !== null) {
        $neighborIndex = $action === 'move_up' ? $index - 1 : $index + 1;
        if (isset($ordered[$neighborIndex])) {
            $a = $ordered[$index];
            $b = $ordered[$neighborIndex];
            $update = $pdo->prepare('UPDATE products SET sort_order = ? WHERE id = ?');
            $update->execute([$b['sort_order'], $a['id']]);
            $update->execute([$a['sort_order'], $b['id']]);
        }
    }
    header('Location: products.php');
    exit;
}

if (isset($_GET['deleted'])) {
    $flash = 'Produkt wurde gelöscht.';
} elseif (isset($_GET['created'])) {
    $flash = 'Produkt wurde angelegt.';
} elseif (isset($_GET['updated'])) {
    $flash = 'Produkt wurde gespeichert.';
}

$products = $pdo->query('SELECT * FROM products ORDER BY sort_order ASC, id ASC')->fetchAll();

$categoryLabels = ['pc' => 'Desktop-PC', 'laptop' => 'Laptop', 'komponente' => 'Komponente', 'zubehoer' => 'Zubehör'];

require __DIR__ . '/../includes/admin-partials.php';
admin_head('Produkte');
admin_nav('products');
?>

<div class="admin-title-row">
  <h1 style="font-size:clamp(1.8rem,5vw,2.6rem);">Produkte</h1>
  <a href="product-form.php" class="btn btn--primary">+ Neues Produkt</a>
</div>

<?php if ($flash): ?>
  <p class="flash flash--ok"><?= htmlspecialchars($flash, ENT_QUOTES) ?></p>
<?php endif; ?>

<div class="table-wrap">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Titel</th>
        <th>Kategorie</th>
        <th>Preis</th>
        <th>Status</th>
        <th>Featured</th>
        <th>Aktionen</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$products): ?>
        <tr><td colspan="6">Noch keine Produkte angelegt.</td></tr>
      <?php endif; ?>
      <?php foreach ($products as $i => $p): ?>
        <tr>
          <td><?= htmlspecialchars($p['title'], ENT_QUOTES) ?></td>
          <td><?= htmlspecialchars($categoryLabels[$p['category']] ?? $p['category'], ENT_QUOTES) ?></td>
          <td><?= number_format((float) $p['price'], 2, ',', '.') ?>&nbsp;€</td>
          <td><?= $p['is_active'] ? '<span class="status-pill status-pill--erledigt">Aktiv</span>' : '<span class="status-pill status-pill--neu">Inaktiv</span>' ?></td>
          <td><?= $p['is_featured'] ? 'Ja' : '—' ?></td>
          <td class="actions">
            <form method="POST" style="display:inline;">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
              <button type="submit" name="action" value="move_up" class="link-btn link-btn--muted" <?= $i === 0 ? 'disabled' : '' ?>>↑</button>
              <button type="submit" name="action" value="move_down" class="link-btn link-btn--muted" <?= $i === count($products) - 1 ? 'disabled' : '' ?>>↓</button>
            </form>
            <a href="product-form.php?id=<?= (int) $p['id'] ?>">Bearbeiten</a>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Produkt wirklich löschen?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
              <button type="submit" class="link-btn">Löschen</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php admin_foot(); ?>
