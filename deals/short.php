<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$search = trim($_GET['q'] ?? '');
$fromDate = $_GET['from'] ?? '';
$toDate = $_GET['to'] ?? '';
$paidFilter = $_GET['paid'] ?? '';

$sql = "SELECT * FROM deals WHERE deal_type = 'ngan_han'";
$params = [];
if ($search !== '') {
    $sql .= ' AND (guest_name LIKE ? OR room_code LIKE ? OR note LIKE ?)';
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}
if ($fromDate !== '') { $sql .= ' AND checkin_date >= ?'; $params[] = $fromDate; }
if ($toDate !== '') { $sql .= ' AND checkin_date <= ?'; $params[] = $toDate; }
if ($paidFilter !== '') { $sql .= ' AND payment_status = ?'; $params[] = $paidFilter; }
$sql .= ' ORDER BY checkin_date DESC, id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$deals = $stmt->fetchAll();

$sumTotal = 0; $sumPaid = 0;
foreach ($deals as $d) { $sumTotal += (float)$d['total_amount']; $sumPaid += (float)$d['paid_amount']; }

$pageTitle = 'Doanh thu ngắn hạn';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="mb-0"><i class="bi bi-calendar-check"></i> Doanh thu ngắn hạn</h4>
  <div class="d-flex gap-2">
    <a href="<?= url('/deals/export.php?' . http_build_query($_GET)) ?>" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-excel"></i> Xuất Excel</a>
    <a href="<?= url('/deals/import.php') ?>" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-arrow-up"></i> Nhập từ Excel</a>
    <a href="<?= url('/deals/form.php') ?>" class="btn btn-success"><i class="bi bi-plus-lg"></i> Thêm deal</a>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-sm-4">
    <div class="stat-card"><div class="text-muted small">Tổng cộng</div><div class="stat-value"><?= money($sumTotal) ?></div></div>
  </div>
  <div class="col-sm-4">
    <div class="stat-card"><div class="text-muted small">Đã thu (CK/TM)</div><div class="stat-value text-success"><?= money($sumPaid) ?></div></div>
  </div>
  <div class="col-sm-4">
    <div class="stat-card"><div class="text-muted small">Còn lại</div><div class="stat-value text-danger"><?= money($sumTotal - $sumPaid) ?></div></div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form class="row g-2 align-items-end">
      <div class="col-sm-3">
        <input type="text" name="q" class="form-control" placeholder="Tên khách, mã phòng, note..." value="<?= e($search) ?>">
      </div>
      <div class="col-sm-2">
        <input type="date" name="from" class="form-control" value="<?= e($fromDate) ?>" title="Check-in từ">
      </div>
      <div class="col-sm-2">
        <input type="date" name="to" class="form-control" value="<?= e($toDate) ?>" title="Check-in đến">
      </div>
      <div class="col-sm-2">
        <select name="paid" class="form-select">
          <option value="">-- Đã TT --</option>
          <option value="paid" <?= $paidFilter === 'paid' ? 'selected' : '' ?>>Đã TT</option>
          <option value="unpaid" <?= $paidFilter === 'unpaid' ? 'selected' : '' ?>>Chưa TT</option>
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
    <table class="table table-hover table-sm mb-0 align-middle">
      <thead class="table-warning">
        <tr>
          <th>Note</th>
          <th>Sale</th>
          <th>Code</th>
          <th>PN</th>
          <th>TIME</th>
          <th>Price/unit</th>
          <th>IN</th>
          <th>OUT</th>
          <th class="text-end">Total</th>
          <th class="text-end">Charge</th>
          <th class="text-end">TỔNG</th>
          <th class="text-end">ĐÃ CK/TM</th>
          <th class="text-end">Payment</th>
          <th>Đã TT</th>
          <th>TK nhận</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$deals): ?>
          <tr><td colspan="16" class="text-center text-muted py-4">Chưa có deal ngắn hạn nào.</td></tr>
        <?php endif; ?>
        <?php foreach ($deals as $d): ?>
          <?php
            $total = (float)$d['nights'] * (float)$d['price_per_unit'];
            $grand = (float)$d['total_amount'];
            $remain = $grand - (float)$d['paid_amount'];
          ?>
          <tr>
            <td class="small"><?= e($d['note']) ?></td>
            <td class="fw-semibold"><?= e($d['guest_name']) ?></td>
            <td><?= e($d['room_code']) ?></td>
            <td><?= e($d['bedrooms']) ?></td>
            <td><?= $d['nights'] ?></td>
            <td class="text-end"><?= money($d['price_per_unit']) ?></td>
            <td><?= vndate($d['checkin_date']) ?></td>
            <td><?= vndate($d['checkout_date']) ?></td>
            <td class="text-end"><?= money($total) ?></td>
            <td class="text-end"><?= $d['extra_fee'] > 0 ? money($d['extra_fee']) : '' ?></td>
            <td class="text-end fw-semibold"><?= money($grand) ?></td>
            <td class="text-end"><?= money($d['paid_amount']) ?></td>
            <td class="text-end fw-semibold <?= $remain > 0 ? 'text-danger' : '' ?>"><?= money($remain) ?></td>
            <td class="text-center"><?= $d['payment_status'] === 'paid' ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle text-danger"></i>' ?></td>
            <td class="small"><?= $d['receiving_account'] ? e($d['receiving_account']) : '<span class="text-muted">—</span>' ?></td>
            <td class="text-end">
              <?php if ($d['payment_status'] !== 'paid'): ?>
                <form method="post" action="<?= url('/deals/mark_paid.php') ?>" class="d-inline" data-confirm="Đánh dấu deal của <?= e($d['guest_name']) ?> đã thu đủ <?= money($remain) ?>?">
                  <?= csrf_field() ?>
                  <input type="hidden" name="deal_id" value="<?= $d['id'] ?>">
                  <input type="hidden" name="back_to" value="short">
                  <button class="btn btn-sm btn-outline-success" title="Đánh dấu đã thu đủ"><i class="bi bi-cash-coin"></i></button>
                </form>
              <?php endif; ?>
              <a href="<?= url('/deals/receipt.php?id=' . $d['id']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Xem biên nhận"><i class="bi bi-receipt"></i></a>
              <a href="<?= url('/deals/form.php?id=' . $d['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
              <form method="post" action="<?= url('/deals/delete.php') ?>" class="d-inline" data-confirm="Xóa deal của <?= e($d['guest_name']) ?>?">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $d['id'] ?>">
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
