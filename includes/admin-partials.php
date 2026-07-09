<?php
declare(strict_types=1);

function admin_head(string $title): void
{
    ?><!doctype html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?= htmlspecialchars($title, ENT_QUOTES) ?> | JOTECH Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Big+Shoulders+Display:wght@600;700;800;900&family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div class="admin-shell">
<?php
}

function admin_nav(string $active): void
{
    $items = [
        'dashboard' => ['index.php', 'Dashboard'],
        'products' => ['products.php', 'Produkte'],
        'inquiries' => ['inquiries.php', 'Anfragen'],
    ];
    ?>
    <div class="admin-topbar">
      <nav class="admin-nav">
        <?php foreach ($items as $key => [$href, $label]): ?>
          <a href="<?= htmlspecialchars($href, ENT_QUOTES) ?>" class="<?= $key === $active ? 'is-active' : '' ?>"><?= htmlspecialchars($label, ENT_QUOTES) ?></a>
        <?php endforeach; ?>
      </nav>
      <div class="who">
        Angemeldet als <?= htmlspecialchars(current_admin_username() ?? '', ENT_QUOTES) ?> · <a href="logout.php" class="link-btn link-btn--muted">Abmelden</a>
      </div>
    </div>
    <?php
}

function admin_foot(): void
{
    ?>
</div>
</body>
</html>
<?php
}

function status_pill(string $status): string
{
    $labels = ['neu' => 'Neu', 'bearbeitet' => 'Bearbeitet', 'erledigt' => 'Erledigt'];
    $label = $labels[$status] ?? $status;
    return '<span class="status-pill status-pill--' . htmlspecialchars($status, ENT_QUOTES) . '">' . htmlspecialchars($label, ENT_QUOTES) . '</span>';
}
