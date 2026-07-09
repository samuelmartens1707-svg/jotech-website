<?php
declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
require_admin_login();

const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];
const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB
const UPLOAD_DIR = __DIR__ . '/../uploads/products/';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: products.php');
    exit;
}

csrf_verify();

$pdo = get_pdo();
$productId = (int) ($_POST['product_id'] ?? 0);

$stmt = $pdo->prepare('SELECT id FROM products WHERE id = ?');
$stmt->execute([$productId]);
if (!$stmt->fetch()) {
    header('Location: products.php');
    exit;
}

$action = (string) ($_POST['action'] ?? '');
$errors = [];

if ($action === 'upload') {
    $files = $_FILES['images'] ?? null;

    if ($files && is_array($files['name'])) {
        $stmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), -1) FROM product_images WHERE product_id = ?');
        $stmt->execute([$productId]);
        $nextSortOrder = (int) $stmt->fetchColumn() + 1;

        $insert = $pdo->prepare('INSERT INTO product_images (product_id, filename, sort_order) VALUES (?, ?, ?)');

        foreach ($files['name'] as $i => $originalName) {
            if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                $errors[] = "$originalName: Upload-Fehler.";
                continue;
            }
            if ($files['size'][$i] > MAX_FILE_SIZE) {
                $errors[] = "$originalName: Datei ist größer als 5 MB.";
                continue;
            }
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if (!in_array($ext, ALLOWED_EXTENSIONS, true)) {
                $errors[] = "$originalName: Nur JPG, PNG oder WEBP erlaubt.";
                continue;
            }
            if (@getimagesize($files['tmp_name'][$i]) === false) {
                $errors[] = "$originalName: Datei ist keine gültige Bilddatei.";
                continue;
            }

            $filename = bin2hex(random_bytes(8)) . '.' . $ext;
            if (!move_uploaded_file($files['tmp_name'][$i], UPLOAD_DIR . $filename)) {
                $errors[] = "$originalName: Konnte nicht gespeichert werden.";
                continue;
            }

            $insert->execute([$productId, $filename, $nextSortOrder]);
            $nextSortOrder++;
        }
    }
} elseif ($action === 'move') {
    $imageId = (int) ($_POST['image_id'] ?? 0);
    $direction = (string) ($_POST['direction'] ?? '');

    $stmt = $pdo->prepare('SELECT id, sort_order FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC');
    $stmt->execute([$productId]);
    $images = $stmt->fetchAll();

    $index = null;
    foreach ($images as $i => $img) {
        if ((int) $img['id'] === $imageId) {
            $index = $i;
            break;
        }
    }

    if ($index !== null) {
        $neighborIndex = $direction === 'up' ? $index - 1 : $index + 1;
        if (isset($images[$neighborIndex])) {
            $a = $images[$index];
            $b = $images[$neighborIndex];
            $update = $pdo->prepare('UPDATE product_images SET sort_order = ? WHERE id = ?');
            $update->execute([$b['sort_order'], $a['id']]);
            $update->execute([$a['sort_order'], $b['id']]);
        }
    }
} elseif ($action === 'delete') {
    $imageId = (int) ($_POST['image_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT filename FROM product_images WHERE id = ? AND product_id = ?');
    $stmt->execute([$imageId, $productId]);
    $image = $stmt->fetch();

    if ($image) {
        $path = UPLOAD_DIR . $image['filename'];
        if (is_file($path)) {
            unlink($path);
        }
        $del = $pdo->prepare('DELETE FROM product_images WHERE id = ?');
        $del->execute([$imageId]);
    }
}

$location = 'product-form.php?id=' . $productId;
if ($errors) {
    $location .= '&img_error=' . urlencode(implode(' ', $errors));
}
header('Location: ' . $location);
exit;
