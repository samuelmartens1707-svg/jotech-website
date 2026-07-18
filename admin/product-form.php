<?php
declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
require_admin_login();

$pdo = get_pdo();

$id = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['id']) ? (int) $_POST['id'] : null);
$isEdit = $id !== null && $id > 0;

$product = [
    'category' => 'pc',
    'title' => '',
    'specs' => '',
    'price' => '',
    'price_note' => '',
    'stock_label' => 'Verfügbar',
    'is_active' => 1,
    'is_featured' => 0,
    'sort_order' => 0,
];

if ($isEdit && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found) {
        header('Location: products.php');
        exit;
    }
    $product = $found;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $product['category'] = (string) ($_POST['category'] ?? 'pc');
    $product['title'] = trim((string) ($_POST['title'] ?? ''));
    $product['specs'] = trim((string) ($_POST['specs'] ?? ''));
    $product['price'] = (string) ($_POST['price'] ?? '');
    $product['price_note'] = trim((string) ($_POST['price_note'] ?? ''));
    $product['stock_label'] = trim((string) ($_POST['stock_label'] ?? '')) ?: 'Verfügbar';
    $product['is_active'] = !empty($_POST['is_active']) ? 1 : 0;
    $product['is_featured'] = !empty($_POST['is_featured']) ? 1 : 0;
    $product['sort_order'] = (int) ($_POST['sort_order'] ?? 0);

    if ($product['title'] === '') {
        $errors[] = 'Titel darf nicht leer sein.';
    }
    if (!is_numeric($product['price']) || (float) $product['price'] < 0) {
        $errors[] = 'Preis muss eine gültige Zahl sein.';
    }
    if (!in_array($product['category'], ['pc', 'laptop', 'komponente', 'zubehoer'], true)) {
        $errors[] = 'Ungültige Kategorie.';
    }

    if (!$errors) {
        if ($isEdit) {
            $stmt = $pdo->prepare(
                'UPDATE products SET category=?, title=?, specs=?, price=?, price_note=?, stock_label=?, is_active=?, is_featured=?, sort_order=? WHERE id=?'
            );
            $stmt->execute([
                $product['category'], $product['title'], $product['specs'], $product['price'],
                $product['price_note'], $product['stock_label'], $product['is_active'],
                $product['is_featured'], $product['sort_order'], $id,
            ]);
            header('Location: products.php?updated=1');
            exit;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO products (category, title, specs, price, price_note, stock_label, is_active, is_featured, sort_order) VALUES (?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $product['category'], $product['title'], $product['specs'], $product['price'],
            $product['price_note'], $product['stock_label'], $product['is_active'],
            $product['is_featured'], $product['sort_order'],
        ]);
        header('Location: products.php?created=1');
        exit;
    }
}

$categoryLabels = ['pc' => 'Desktop-PC', 'laptop' => 'Laptop', 'komponente' => 'Komponente', 'zubehoer' => 'Zubehör'];

$images = [];
if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC');
    $stmt->execute([$id]);
    $images = $stmt->fetchAll();
}
$imgError = isset($_GET['img_error']) ? (string) $_GET['img_error'] : '';

require __DIR__ . '/../includes/admin-partials.php';
admin_head($isEdit ? 'Produkt bearbeiten' : 'Neues Produkt');
admin_nav('products');
?>

<div class="admin-title-row">
  <h1 style="font-size:clamp(1.8rem,5vw,2.6rem);"><?= $isEdit ? 'Produkt bearbeiten' : 'Neues Produkt' ?></h1>
  <a href="products.php" class="btn btn--ghost">← Zurück zur Liste</a>
</div>

<?php foreach ($errors as $err): ?>
  <p class="flash"><?= htmlspecialchars($err, ENT_QUOTES) ?></p>
<?php endforeach; ?>

<form method="POST" novalidate style="max-width:640px;">
  <?= csrf_field() ?>
  <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $id ?>"><?php endif; ?>

  <div class="field">
    <label for="title">Titel *</label>
    <input type="text" id="title" name="title" value="<?= htmlspecialchars($product['title'], ENT_QUOTES) ?>" required>
  </div>

  <div class="field-row">
    <div class="field">
      <label for="category">Kategorie *</label>
      <select id="category" name="category" required>
        <?php foreach ($categoryLabels as $key => $label): ?>
          <option value="<?= htmlspecialchars($key, ENT_QUOTES) ?>" <?= $product['category'] === $key ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="price">Preis (€) *</label>
      <input type="text" id="price" name="price" value="<?= htmlspecialchars((string) $product['price'], ENT_QUOTES) ?>" placeholder="z. B. 449.00" required>
    </div>
  </div>

  <div class="field">
    <label for="specs">Beschreibung</label>
    <textarea id="specs" name="specs" placeholder="Eine Angabe pro Zeile, z. B.&#10;Ryzen 5 5600 · 16GB DDR4&#10;512GB NVMe · RTX 3060"><?= htmlspecialchars($product['specs'], ENT_QUOTES) ?></textarea>
    <p class="hint">Jede Zeile wird auf der Website als eigene Zeile angezeigt.</p>
  </div>

  <div class="field-row">
    <div class="field">
      <label for="price_note">Zusatzhinweis (optional)</label>
      <input type="text" id="price_note" name="price_note" value="<?= htmlspecialchars($product['price_note'], ENT_QUOTES) ?>" placeholder="z. B. Geprüft & funktionsgetestet">
    </div>
    <div class="field">
      <label for="stock_label">Verfügbarkeits-Label</label>
      <input type="text" id="stock_label" name="stock_label" value="<?= htmlspecialchars($product['stock_label'], ENT_QUOTES) ?>" placeholder="z. B. Verfügbar">
    </div>
  </div>

  <div class="field">
    <label for="sort_order">Sortierposition</label>
    <input type="text" id="sort_order" name="sort_order" value="<?= htmlspecialchars((string) $product['sort_order'], ENT_QUOTES) ?>">
    <p class="hint">Kleinere Zahl = weiter vorne im Shop.</p>
  </div>

  <label class="consent">
    <input type="checkbox" name="is_active" value="1" <?= $product['is_active'] ? 'checked' : '' ?>>
    <span>Aktiv (im Shop sichtbar)</span>
  </label>
  <label class="consent">
    <input type="checkbox" name="is_featured" value="1" <?= $product['is_featured'] ? 'checked' : '' ?>>
    <span>Auf der Startseite als "Aktuell im Shop" hervorheben</span>
  </label>

  <button type="submit" class="btn btn--primary"><?= $isEdit ? 'Speichern' : 'Produkt anlegen' ?></button>
</form>

<div style="max-width:640px; margin-top:2.4rem; padding-top:2rem; border-top:1px solid var(--line);">
  <h2 style="font-size:1.3rem; margin-bottom:1rem;">Bilder</h2>

  <?php if (!$isEdit): ?>
    <p class="hint">Bilder können hinzugefügt werden, sobald das Produkt angelegt ist. Bitte zuerst oben speichern.</p>
  <?php else: ?>

    <?php if ($imgError): ?>
      <p class="flash"><?= htmlspecialchars($imgError, ENT_QUOTES) ?></p>
    <?php endif; ?>

    <?php if ($images): ?>
      <div style="display:flex; flex-wrap:wrap; gap:1rem; margin-bottom:1.6rem;">
        <?php foreach ($images as $i => $img): ?>
          <div style="width:130px;">
            <img src="../api/product-image.php?id=<?= (int) $img['id'] ?>" alt="" style="width:130px; height:95px; object-fit:cover; border:1px solid var(--line); display:block;">
            <div style="display:flex; gap:.3rem; margin-top:.4rem;">
              <form method="POST" action="product-images.php">
                <?= csrf_field() ?>
                <input type="hidden" name="product_id" value="<?= (int) $id ?>">
                <input type="hidden" name="image_id" value="<?= (int) $img['id'] ?>">
                <input type="hidden" name="action" value="move">
                <button type="submit" name="direction" value="up" class="link-btn link-btn--muted" <?= $i === 0 ? 'disabled' : '' ?>>↑</button>
                <button type="submit" name="direction" value="down" class="link-btn link-btn--muted" <?= $i === count($images) - 1 ? 'disabled' : '' ?>>↓</button>
              </form>
              <form method="POST" action="product-images.php" onsubmit="return confirm('Bild wirklich löschen?');">
                <?= csrf_field() ?>
                <input type="hidden" name="product_id" value="<?= (int) $id ?>">
                <input type="hidden" name="image_id" value="<?= (int) $img['id'] ?>">
                <input type="hidden" name="action" value="delete">
                <button type="submit" class="link-btn">Löschen</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="hint">Noch keine Bilder hochgeladen. Ohne Bild wird auf der Website ein generisches Symbol angezeigt.</p>
    <?php endif; ?>

    <form method="POST" action="product-images.php" enctype="multipart/form-data" style="margin-top:1rem;">
      <?= csrf_field() ?>
      <input type="hidden" name="product_id" value="<?= (int) $id ?>">
      <input type="hidden" name="action" value="upload">
      <div class="field">
        <label for="images">Neue Bilder hochladen</label>
        <input type="file" id="images" name="images[]" accept="image/png,image/jpeg,image/webp" multiple>
        <p class="hint">JPG, PNG oder WEBP, max. 5 MB pro Datei. Das erste Bild in der Liste oben wird auf der Website als Hauptbild angezeigt.</p>
      </div>
      <button type="submit" class="btn btn--ghost">Hochladen</button>
    </form>

  <?php endif; ?>
</div>

<?php admin_foot(); ?>
