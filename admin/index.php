<?php
declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
require_admin_login();

$pdo = get_pdo();
$openInquiries = (int) $pdo->query("SELECT COUNT(*) FROM inquiries WHERE status = 'neu'")->fetchColumn();
$totalInquiries = (int) $pdo->query('SELECT COUNT(*) FROM inquiries')->fetchColumn();
$activeProducts = (int) $pdo->query('SELECT COUNT(*) FROM products WHERE is_active = 1')->fetchColumn();
$totalProducts = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();

require __DIR__ . '/../includes/admin-partials.php';
admin_head('Dashboard');
admin_nav('dashboard');
?>

<div class="admin-title-row">
  <h1 style="font-size:clamp(1.8rem,5vw,2.6rem);">Dashboard</h1>
</div>

<div class="stat-row">
  <div class="stat-card">
    <span class="num"><?= $openInquiries ?></span>
    <span class="label">Neue Anfragen</span>
  </div>
  <div class="stat-card">
    <span class="num"><?= $totalInquiries ?></span>
    <span class="label">Anfragen gesamt</span>
  </div>
  <div class="stat-card">
    <span class="num"><?= $activeProducts ?></span>
    <span class="label">Aktive Produkte</span>
  </div>
  <div class="stat-card">
    <span class="num"><?= $totalProducts ?></span>
    <span class="label">Produkte gesamt</span>
  </div>
</div>

<p><a href="inquiries.php" class="btn btn--primary">Anfragen ansehen</a> &nbsp; <a href="products.php" class="btn btn--ghost">Produkte verwalten</a></p>

<?php admin_foot(); ?>
