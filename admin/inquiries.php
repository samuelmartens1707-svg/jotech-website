<?php
declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
require_admin_login();

$pdo = get_pdo();

$statusFilter = (string) ($_GET['status'] ?? '');
$typeFilter = (string) ($_GET['type'] ?? '');

$where = [];
$params = [];
if (in_array($statusFilter, ['neu', 'bearbeitet', 'erledigt'], true)) {
    $where[] = 'status = ?';
    $params[] = $statusFilter;
}
if (in_array($typeFilter, ['ankauf', 'reparatur', 'kontakt'], true)) {
    $where[] = 'form_type = ?';
    $params[] = $typeFilter;
}
$sql = 'SELECT * FROM inquiries';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$inquiries = $stmt->fetchAll();

$typeLabels = ['ankauf' => 'Ankauf', 'reparatur' => 'Reparatur', 'kontakt' => 'Kontakt'];

function qs(string $status, string $type): string
{
    $parts = [];
    if ($status !== '') $parts[] = 'status=' . urlencode($status);
    if ($type !== '') $parts[] = 'type=' . urlencode($type);
    return $parts ? '?' . implode('&', $parts) : '';
}

require __DIR__ . '/../includes/admin-partials.php';
admin_head('Anfragen');
admin_nav('inquiries');
?>

<div class="admin-title-row">
  <h1 style="font-size:clamp(1.8rem,5vw,2.6rem);">Anfragen</h1>
</div>

<div style="display:flex; gap:1.5rem; flex-wrap:wrap; margin-bottom:1.6rem; font-family:var(--font-mono); font-size:.8rem;">
  <div>
    Status:
    <a href="<?= qs('', $typeFilter) ?>" class="<?= $statusFilter === '' ? 'is-active' : '' ?>" style="margin-left:.4em;">Alle</a> ·
    <a href="<?= qs('neu', $typeFilter) ?>">Neu</a> ·
    <a href="<?= qs('bearbeitet', $typeFilter) ?>">Bearbeitet</a> ·
    <a href="<?= qs('erledigt', $typeFilter) ?>">Erledigt</a>
  </div>
  <div>
    Typ:
    <a href="<?= qs($statusFilter, '') ?>" style="margin-left:.4em;">Alle</a> ·
    <a href="<?= qs($statusFilter, 'ankauf') ?>">Ankauf</a> ·
    <a href="<?= qs($statusFilter, 'reparatur') ?>">Reparatur</a> ·
    <a href="<?= qs($statusFilter, 'kontakt') ?>">Kontakt</a>
  </div>
</div>

<div class="table-wrap">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Eingegangen</th>
        <th>Typ</th>
        <th>Name</th>
        <th>E-Mail</th>
        <th>Status</th>
        <th>Aktionen</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$inquiries): ?>
        <tr><td colspan="6">Keine Anfragen gefunden.</td></tr>
      <?php endif; ?>
      <?php foreach ($inquiries as $inq): ?>
        <tr>
          <td data-label="Eingegangen"><?= htmlspecialchars($inq['created_at'], ENT_QUOTES) ?></td>
          <td data-label="Typ"><?= htmlspecialchars($typeLabels[$inq['form_type']] ?? $inq['form_type'], ENT_QUOTES) ?></td>
          <td data-label="Name"><?= htmlspecialchars($inq['name'], ENT_QUOTES) ?></td>
          <td data-label="E-Mail"><?= htmlspecialchars($inq['email'], ENT_QUOTES) ?></td>
          <td data-label="Status"><?= status_pill($inq['status']) ?></td>
          <td class="actions" data-label="Aktionen"><a href="inquiry-view.php?id=<?= (int) $inq['id'] ?>">Ansehen</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php admin_foot(); ?>
