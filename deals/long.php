<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/deal_helpers.php';
require_login();

$search = trim($_GET['q'] ?? '');
$month = trim($_GET['month'] ?? '');

$sql = "SELECT dp.*, d.room_code, d.guest_name, d.zone
        FROM deal_periods dp JOIN deals d ON d.id = dp.deal_id
        WHERE d.deal_type = 'dai_han'";
$params = [];
if ($search !== '') {
    $sql .= ' AND (d.guest_name LIKE ? OR d.room_code LIKE ?)';
    $params = array_merge($params, ["%$search%", "%$search%"]);
}
if ($month !== '') {
    $sql .= ' AND ? BETWEEN substr(dp.period_start,1,7) AND substr(dp.period_end,1,7)';
    $params[] = $month;
}
$sql .= ' ORDER BY dp.period_start DESC, d.room_code';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$periods = $stmt->fetchAll();

$sumRent = 0; $sumUtil = 0; $sumDeposit = 0; $sumPaid = 0;
foreach ($periods as $p) {
    $sumRent += (float)$p['rent_amount'];
    $sumUtil += (float)$p['utilities_amount'];
    $sumDeposit += (float)$p['deposit_amount'];
    $sumPaid += (float)$p['paid_amount'];
}
$sumTotal = $sumRent + $sumUtil + $sumDeposit;

$pageTitle = 'Doanh thu dài hạn';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="mb-0"><i class="bi bi-receipt"></i> Doanh thu dài hạn (theo kỳ 30 ngày)</h4>
  <a href="<?= url('/deals/form.php') ?>" class="btn btn-success"><i class="bi bi-plus-lg"></i> Thêm deal</a>
</div>

<div class="row g-3 mb-3">
  <div class="col-sm-3">
    <div class="stat-card"><div class="text-muted small">Tổng cộng (theo bộ lọc)</div><div class="stat-value"><?= money($sumTotal) ?></div></div>
  </div>
  <div class="col-sm-3">
    <div class="stat-card"><div class="text-muted small">Đã thu</div><div class="stat-value text-success"><?= money($sumPaid) ?></div></div>
  </div>
  <div class="col-sm-3">
    <div class="stat-card"><div class="text-muted small">Còn phải thu</div><div class="stat-value text-danger"><?= money($sumTotal - $sumPaid) ?></div></div>
  </div>
  <div class="col-sm-3">
    <div class="stat-card"><div class="text-muted small">Số kỳ</div><div class="stat-value"><?= count($periods) ?></div></div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form class="row g-2 align-items-end">
      <div class="col-sm-4">
        <input type="text" name="q" class="form-control" placeholder="Tên khách, mã phòng..." value="<?= e($search) ?>">
      </div>
      <div class="col-sm-3">
        <input type="month" name="month" class="form-control" value="<?= e($month) ?>">
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
      <thead>
        <tr>
          <th>Kỳ</th>
          <th>Phòng</th>
          <th>Khách</th>
          <th>Từ - đến</th>
          <th class="text-end">Thuê</th>
          <th class="text-end">Cọc</th>
          <th class="text-end">Điện/Nước</th>
          <th class="text-end">Tổng kỳ</th>
          <th class="text-end">Đã thanh toán</th>
          <th class="text-end">Còn lại</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$periods): ?>
          <tr><td colspan="11" class="text-center text-muted py-4">Chưa có dữ liệu dài hạn nào.</td></tr>
        <?php endif; ?>
        <?php foreach ($periods as $p): ?>
          <?php
            $periodTotal = (float)$p['rent_amount'] + (float)$p['deposit_amount'] + (float)$p['utilities_amount'];
            $remain = $periodTotal - (float)$p['paid_amount'];
          ?>
          <tr>
            <td><?= e(deal_period_label((int)$p['period_index'], $p['period_start'])) ?></td>
            <td class="fw-semibold"><?= e($p['room_code']) ?><?= $p['zone'] ? '<div class="small text-muted">' . e($p['zone']) . '</div>' : '' ?></td>
            <td><?= e($p['guest_name']) ?></td>
            <td><?= vndate($p['period_start']) ?> - <?= vndate($p['period_end']) ?></td>
            <td class="text-end"><?= money($p['rent_amount']) ?></td>
            <td class="text-end"><?= $p['deposit_amount'] > 0 ? money($p['deposit_amount']) : '' ?></td>
            <td class="text-end"><?= money($p['utilities_amount']) ?></td>
            <td class="text-end fw-semibold"><?= money($periodTotal) ?></td>
            <td class="text-end text-success"><?= money($p['paid_amount']) ?></td>
            <td class="text-end <?= $remain > 0 ? 'text-danger' : '' ?>"><?= money($remain) ?></td>
            <td class="text-end"><a href="<?= url('/deals/form.php?id=' . $p['deal_id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
