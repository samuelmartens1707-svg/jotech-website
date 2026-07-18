<?php
declare(strict_types=1);

require __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

$featuredOnly = isset($_GET['featured']);

$sql = 'SELECT id, category, title, specs, price, price_note, stock_label,
    (SELECT filename FROM product_images pi WHERE pi.product_id = products.id ORDER BY pi.sort_order ASC, pi.id ASC LIMIT 1) AS image_filename
    FROM products WHERE is_active = 1';
if ($featuredOnly) {
    $sql .= ' AND is_featured = 1';
}
$sql .= ' ORDER BY sort_order ASC, id ASC';
if ($featuredOnly) {
    $sql .= ' LIMIT 3';
}

try {
    $rows = get_pdo()->query($sql)->fetchAll();
} catch (Throwable $e) {
    http_response_code(500);
    $debug = ($_GET['debug'] ?? '') === 'jotech2026';
    echo json_encode(['error' => $debug ? $e->getMessage() : 'internal_error']);
    exit;
}

foreach ($rows as &$row) {
    $row['id'] = (int) $row['id'];
    $price = (float) $row['price'];
    $isWhole = abs($price - round($price)) < 0.001;
    $row['price_label'] = $isWhole
        ? number_format($price, 0, ',', '.')
        : number_format($price, 2, ',', '.');
    $row['image'] = $row['image_filename'] ? 'uploads/products/' . $row['image_filename'] : null;
    unset($row['image_filename']);
}

echo json_encode($rows, JSON_UNESCAPED_UNICODE);
