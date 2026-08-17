<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$statusFilter = $_GET['status'] ?? '';
$search = trim($_GET['q'] ?? '');

$sql = 'SELECT t.*, r.room_code, r.zone FROM tickets t JOIN rooms r ON r.id = t.room_id WHERE 1=1';
$params = [];
if ($statusFilter !== '') {
    $sql .= ' AND t.status = ?';
    $params[] = $statusFilter;
}
if ($search !== '') {
    $sql .= ' AND (t.title LIKE ? OR r.room_code LIKE ?)';
    $params = array_merge($params, ["%$search%", "%$search%"]);
}
$sql .= " ORDER BY CASE t.status WHEN 'open' THEN 0 WHEN 'in_progress' THEN 1 ELSE 2 END, t.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

$pageTitle = 'Ticket bảo trì';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="mb-0"><i class="bi bi-tools"></i> Ticket bảo trì - sửa chữa</h4>
  <a href="<?= url('/tickets/form.php') ?>" class="btn btn-success"><i class="bi bi-plus-lg"></i> Tạo ticket</a>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form class="row g-2 align-items-end">
      <div class="col-sm-4">
        <input type="text" name="q" class="form-control" placeholder="Tiêu đề, mã phòng..." value="<?= e($search) ?>">
      </div>
      <div class="col-sm-3">
        <select name="status" class="form-select">
          <option value="">-- Tất cả trạng thái --</option>
          <?php foreach (TICKET_STATUS_LABELS as $k => $v): ?>
            <option value="<?= e($k) ?>" <?= $statusFilter === $k ? 'selected' : '' ?>><?= e($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-2">
        <button class="btn btn-outline-secondary w-100"><i class="bi bi-search"></i> Lọc</button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Phòng</th>
          <th>Tiêu đề</th>
          <th>Mức độ</th>
          <th>Người báo</th>
          <th>Ngày tạo</th>
          <th>Trạng thái</th>
          <th class="text-end">Thao tác</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$tickets): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">Chưa có ticket nào.</td></tr>
        <?php endif; ?>
        <?php foreach ($tickets as $t): ?>
          <tr>
            <td class="fw-semibold"><?= e($t['room_code']) ?><?= $t['zone'] ? '<div class="small text-muted">' . e($t['zone']) . '</div>' : '' ?></td>
            <td><?= e($t['title']) ?></td>
            <td><span class="badge bg-<?= badge_class($t['priority']) ?>"><?= e(TICKET_PRIORITY_LABELS[$t['priority']] ?? '') ?></span></td>
            <td><?= e($t['reported_by']) ?></td>
            <td><?= vndate(substr($t['created_at'], 0, 10)) ?></td>
            <td><span class="badge bg-<?= badge_class($t['status']) ?>"><?= e(TICKET_STATUS_LABELS[$t['status']] ?? '') ?></span></td>
            <td class="text-end">
              <a href="<?= url('/tickets/form.php?id=' . $t['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
              <form method="post" action="<?= url('/tickets/delete.php') ?>" class="d-inline" data-confirm="Xóa ticket này?">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $t['id'] ?>">
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
