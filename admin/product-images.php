<?php
declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
require_admin_login();

const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];
const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB

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

        $insert = $pdo->prepare('INSERT INTO product_images (product_id, filename, mime_type, data, sort_order) VALUES (?, ?, ?, ?, ?)');

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
            $imageInfo = @getimagesize($files['tmp_name'][$i]);
            if ($imageInfo === false) {
                $errors[] = "$originalName: Datei ist keine gültige Bilddatei.";
                continue;
            }

            // In der DB gespeichert (nicht auf dem Container-Dateisystem), da dieses
            // bei jedem Deploy frisch aus dem Git-Repo gebaut wird und hochgeladene
            // Dateien sonst verloren gehen.
            $data = file_get_contents($files['tmp_name'][$i]);
            if ($data === false) {
                $errors[] = "$originalName: Konnte nicht gelesen werden.";
                continue;
            }

            $insert->execute([$productId, $originalName, $imageInfo['mime'], $data, $nextSortOrder]);
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
    $pdo->prepare('DELETE FROM product_images WHERE id = ? AND product_id = ?')->execute([$imageId, $productId]);
}

$location = 'product-form.php?id=' . $productId;
if ($errors) {
    $location .= '&img_error=' . urlencode(implode(' ', $errors));
}
header('Location: ' . $location);
exit;
