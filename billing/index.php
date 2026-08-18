<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$search = trim($_GET['q'] ?? '');
$fromDate = $_GET['from'] ?? '';
$toDate = $_GET['to'] ?? '';
$doneFilter = $_GET['done'] ?? '';

if (!isset($_GET['from']) && !isset($_GET['to']) && !isset($_GET['q'])) {
    $fromDate = date('Y-m-01');
}

$sql = 'SELECT * FROM billing_entries WHERE 1=1';
$params = [];
if ($search !== '') {
    $sql .= ' AND (guest_name LIKE ? OR room_code LIKE ? OR content LIKE ? OR note LIKE ?)';
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%"]);
}
if ($fromDate !== '') { $sql .= ' AND bill_date >= ?'; $params[] = $fromDate; }
if ($toDate !== '') { $sql .= ' AND bill_date <= ?'; $params[] = $toDate; }
if ($doneFilter === 'done') { $sql .= ' AND is_done = 1'; }
if ($doneFilter === 'undone') { $sql .= ' AND is_done = 0'; }
$sql .= ' ORDER BY bill_date ASC, id ASC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$sumTotal = 0; $sumDeposit = 0; $sumSettle = 0;
foreach ($rows as $r) {
    $sumTotal += (float)$r['total_amount'];
    $sumDeposit += (float)$r['deposit_used'];
    $sumSettle += (float)$r['settle_amount'];
}

$pageTitle = 'Chi phí dài hạn';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="mb-0"><i class="bi bi-journal-text"></i> Chi phí dài hạn</h4>
  <div class="d-flex gap-2">
    <a href="<?= url('/billing/add.php') ?>" class="btn btn-success"><i class="bi bi-plus-lg"></i> Thêm giao dịch</a>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-sm-4">
    <div class="stat-card"><div class="text-muted small">Tổng tiền (theo bộ lọc)</div><div class="stat-value"><?= money($sumTotal) ?></div></div>
  </div>
  <div class="col-sm-4">
    <div class="stat-card"><div class="text-muted small">Tiền cọc đã trừ</div><div class="stat-value"><?= money($sumDeposit) ?></div></div>
  </div>
  <div class="col-sm-4">
    <div class="stat-card"><div class="text-muted small">Sale thanh toán cho công ty</div><div class="stat-value <?= $sumSettle < 0 ? 'text-danger' : 'text-success' ?>"><?= money($sumSettle) ?></div></div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form class="row g-2 align-items-end">
      <div class="col-sm-3">
        <input type="text" name="q" class="form-control" placeholder="Tên KH, mã căn, nội dung..." value="<?= e($search) ?>">
      </div>
      <div class="col-sm-2">
        <input type="date" name="from" class="form-control" value="<?= e($fromDate) ?>" title="Từ ngày">
      </div>
      <div class="col-sm-2">
        <input type="date" name="to" class="form-control" value="<?= e($toDate) ?>" title="Đến ngày">
      </div>
      <div class="col-sm-2">
        <select name="done" class="form-select">
          <option value="">-- Tình trạng --</option>
          <option value="done" <?= $doneFilter === 'done' ? 'selected' : '' ?>>Đã xử lý</option>
          <option value="undone" <?= $doneFilter === 'undone' ? 'selected' : '' ?>>Chưa xử lý</option>
        </select>
      </div>
      <div class="col-sm-2">
        <button class="btn btn-outline-secondary w-100"><i class="bi bi-search"></i> Lọc</button>
      </div>
      <div class="col-sm-1">
        <a href="<?= url('/billing/index.php?from=&to=&q=') ?>" class="btn btn-outline-secondary w-100" title="Bỏ lọc"><i class="bi bi-x-lg"></i></a>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover table-sm mb-0 align-middle">
      <thead class="table-warning">
        <tr>
          <th>STT</th>
          <th>Ngày tính</th>
          <th>Tên KH</th>
          <th>Mã căn</th>
          <th>Nội dung</th>
          <th class="text-end">SL</th>
          <th class="text-end">Điện</th>
          <th class="text-end">Nước</th>
          <th class="text-end">Phí QL</th>
          <th class="text-end">Inter</th>
          <th class="text-end">Xe</th>
          <th class="text-end">Phí khác</th>
          <th class="text-end">Thẻ nhà</th>
          <th class="text-end">Tổng tiền</th>
          <th class="text-end">Tiền cọc</th>
          <th class="text-end">Thanh toán</th>
          <th>TT</th>
          <th>Khách TT</th>
          <th>TK nhận</th>
          <th>Note</th>
          <th class="text-end">Thao tác</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="21" class="text-center text-muted py-4">Chưa có giao dịch nào.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $i => $r): ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td><?= vndate($r['bill_date']) ?></td>
            <td class="fw-semibold"><?= e($r['guest_name']) ?></td>
            <td><?= e($r['room_code']) ?></td>
            <td class="small"><?= e($r['content']) ?></td>
            <td class="text-end"><?= rtrim(rtrim(number_format((float)$r['quantity'], 2, '.', ''), '0'), '.') ?></td>
            <td class="text-end"><?= $r['electricity_amount'] > 0 ? money($r['electricity_amount']) : '' ?></td>
            <td class="text-end"><?= $r['water_amount'] > 0 ? money($r['water_amount']) : '' ?></td>
            <td class="text-end"><?= $r['management_fee_amount'] > 0 ? money($r['management_fee_amount']) : '' ?></td>
            <td class="text-end"><?= $r['internet_amount'] > 0 ? money($r['internet_amount']) : '' ?></td>
            <td class="text-end"><?= $r['vehicle_fee_amount'] > 0 ? money($r['vehicle_fee_amount']) : '' ?></td>
            <td class="text-end"><?= $r['other_fee_amount'] > 0 ? money($r['other_fee_amount']) : '' ?></td>
            <td class="text-end"><?= $r['card_fee_amount'] != 0 ? money($r['card_fee_amount']) : '' ?></td>
            <td class="text-end fw-semibold"><?= money($r['total_amount']) ?></td>
            <td class="text-end"><?= $r['deposit_used'] > 0 ? money($r['deposit_used']) : '' ?></td>
            <td class="text-end fw-semibold <?= $r['settle_amount'] < 0 ? 'text-danger' : 'text-success' ?>"><?= money($r['settle_amount']) ?></td>
            <td class="text-center"><?= $r['is_done'] ? '<i class="bi bi-check-square-fill text-success"></i>' : '<i class="bi bi-square text-muted"></i>' ?></td>
            <td><?= $r['customer_paid_date'] ? vndate($r['customer_paid_date']) : '' ?></td>
            <td class="small"><?= e($r['receiving_account']) ?></td>
            <td class="small"><?= e($r['note']) ?></td>
            <td class="text-end">
              <a href="<?= url('/billing/form.php?id=' . $r['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
              <form method="post" action="<?= url('/billing/delete.php') ?>" class="d-inline" data-confirm="Xóa giao dịch của <?= e($r['guest_name']) ?>?">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $r['id'] ?>">
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
