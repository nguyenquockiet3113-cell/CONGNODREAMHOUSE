<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$period = trim($_GET['period'] ?? '');
$statusFilter = $_GET['status'] ?? '';

$sql = "SELECT i.*, r.room_code, t.full_name AS tenant_name
        FROM invoices i
        JOIN contracts c ON c.id = i.contract_id
        JOIN rooms r ON r.id = i.room_id
        JOIN tenants t ON t.id = c.tenant_id
        WHERE 1=1";
$params = [];
if ($period !== '') {
    $sql .= ' AND i.period_month = ?';
    $params[] = $period;
}
if ($statusFilter !== '') {
    $sql .= ' AND i.status = ?';
    $params[] = $statusFilter;
}
$sql .= ' ORDER BY i.period_month DESC, r.room_code ASC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

$totalAmount = array_sum(array_column($invoices, 'total_amount'));
$totalPaid = array_sum(array_column($invoices, 'paid_amount'));

$pageTitle = 'Doanh thu dài hạn';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="mb-0"><i class="bi bi-receipt"></i> Doanh thu dài hạn (hóa đơn hàng tháng)</h4>
  <a href="<?= url('/invoices/form.php') ?>" class="btn btn-success"><i class="bi bi-plus-lg"></i> Lập hóa đơn</a>
</div>

<div class="row g-3 mb-3">
  <div class="col-sm-4">
    <div class="stat-card"><div class="text-muted small">Tổng hóa đơn (theo bộ lọc)</div><div class="stat-value"><?= money($totalAmount) ?></div></div>
  </div>
  <div class="col-sm-4">
    <div class="stat-card"><div class="text-muted small">Đã thu</div><div class="stat-value text-success"><?= money($totalPaid) ?></div></div>
  </div>
  <div class="col-sm-4">
    <div class="stat-card"><div class="text-muted small">Còn phải thu</div><div class="stat-value text-danger"><?= money($totalAmount - $totalPaid) ?></div></div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form class="row g-2 align-items-end">
      <div class="col-sm-3">
        <label class="form-label small mb-1">Kỳ (tháng)</label>
        <input type="month" name="period" class="form-control" value="<?= e($period) ?>">
      </div>
      <div class="col-sm-3">
        <label class="form-label small mb-1">Trạng thái</label>
        <select name="status" class="form-select">
          <option value="">-- Tất cả --</option>
          <?php foreach (INVOICE_STATUS_LABELS as $k => $v): ?>
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
          <th>Kỳ</th>
          <th>Phòng</th>
          <th>Khách thuê</th>
          <th>Tổng tiền</th>
          <th>Đã thu</th>
          <th>Trạng thái</th>
          <th class="text-end">Thao tác</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$invoices): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">Chưa có hóa đơn nào.</td></tr>
        <?php endif; ?>
        <?php foreach ($invoices as $inv): ?>
          <tr>
            <td><?= e($inv['period_month']) ?></td>
            <td><?= e($inv['room_code']) ?></td>
            <td><?= e($inv['tenant_name']) ?></td>
            <td><?= money($inv['total_amount']) ?></td>
            <td><?= money($inv['paid_amount']) ?></td>
            <td><span class="badge bg-<?= badge_class($inv['status']) ?>"><?= e(INVOICE_STATUS_LABELS[$inv['status']] ?? '') ?></span></td>
            <td class="text-end">
              <a href="<?= url('/invoices/form.php?id=' . $inv['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
              <form method="post" action="<?= url('/invoices/delete.php') ?>" class="d-inline" data-confirm="Xóa hóa đơn kỳ <?= e($inv['period_month']) ?>?">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $inv['id'] ?>">
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
