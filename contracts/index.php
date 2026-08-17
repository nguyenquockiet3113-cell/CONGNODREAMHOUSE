<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$statusFilter = $_GET['status'] ?? '';
$search = trim($_GET['q'] ?? '');

$sql = 'SELECT * FROM contracts WHERE 1=1';
$params = [];
if ($statusFilter !== '') {
    $sql .= ' AND status = ?';
    $params[] = $statusFilter;
}
if ($search !== '') {
    $sql .= ' AND (contract_code LIKE ? OR room_code LIKE ? OR lessee_name LIKE ?)';
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}
$sql .= ' ORDER BY created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$contracts = $stmt->fetchAll();

$today = date('Y-m-d');

$pageTitle = 'Hợp đồng';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="mb-0"><i class="bi bi-file-earmark-text"></i> Hợp đồng thuê</h4>
  <a href="<?= url('/contracts/form.php') ?>" class="btn btn-success"><i class="bi bi-plus-lg"></i> Tạo hợp đồng</a>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form class="row g-2 align-items-end">
      <div class="col-sm-4">
        <input type="text" name="q" class="form-control" placeholder="Số HĐ, phòng, tên khách..." value="<?= e($search) ?>">
      </div>
      <div class="col-sm-3">
        <select name="status" class="form-select">
          <option value="">-- Tất cả trạng thái --</option>
          <?php foreach (CONTRACT_STATUS_LABELS as $k => $v): ?>
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
          <th>Số HĐ</th>
          <th>Phòng</th>
          <th>Bên thuê</th>
          <th>Bắt đầu</th>
          <th>Kết thúc</th>
          <th>Tiền thuê/tháng</th>
          <th>Trạng thái</th>
          <th class="text-end">Thao tác</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$contracts): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">Chưa có hợp đồng nào.</td></tr>
        <?php endif; ?>
        <?php foreach ($contracts as $c): ?>
          <?php
            $expiringSoon = $c['status'] === 'active' && $c['end_date'] >= $today &&
                (strtotime($c['end_date']) - strtotime($today)) <= 30 * 86400;
          ?>
          <tr class="<?= $expiringSoon ? 'table-warning' : '' ?>">
            <td><a href="<?= url('/contracts/view.php?id=' . $c['id']) ?>" class="fw-semibold text-decoration-none"><?= e($c['contract_code']) ?></a></td>
            <td><?= e($c['room_code']) ?><?= $c['zone'] ? '<div class="small text-muted">' . e($c['zone']) . '</div>' : '' ?></td>
            <td><?= e($c['lessee_name']) ?><div class="small text-muted"><?= e($c['lessee_phone']) ?></div></td>
            <td><?= vndate($c['start_date']) ?></td>
            <td><?= vndate($c['end_date']) ?><?php if ($expiringSoon): ?> <i class="bi bi-exclamation-triangle-fill text-warning" title="Sắp hết hạn"></i><?php endif; ?></td>
            <td><?= money($c['monthly_rent']) ?></td>
            <td><span class="badge bg-<?= badge_class($c['status']) ?>"><?= e(CONTRACT_STATUS_LABELS[$c['status']] ?? $c['status']) ?></span></td>
            <td class="text-end">
              <a href="<?= url('/contracts/view.php?id=' . $c['id']) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
              <a href="<?= url('/contracts/print.php?id=' . $c['id']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i></a>
              <a href="<?= url('/contracts/form.php?id=' . $c['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
              <form method="post" action="<?= url('/contracts/delete.php') ?>" class="d-inline" data-confirm="Xóa hợp đồng <?= e($c['contract_code']) ?>?">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $c['id'] ?>">
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
