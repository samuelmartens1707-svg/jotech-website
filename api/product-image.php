<?php
declare(strict_types=1);

require __DIR__ . '/../includes/db.php';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    exit;
}

$stmt = get_pdo()->prepare('SELECT data, mime_type FROM product_images WHERE id = ?');
$stmt->execute([$id]);
$image = $stmt->fetch();

if (!$image || $image['data'] === null) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $image['mime_type']);
header('Cache-Control: public, max-age=31536000, immutable');
echo $image['data'];
