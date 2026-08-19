<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$search = trim($_GET['q'] ?? '');

// Ngan han: cong no = total_amount - paid_amount
$shortRows = $pdo->query(
    "SELECT id, guest_name, room_code, checkin_date, checkout_date, total_amount, paid_amount FROM deals WHERE deal_type = 'ngan_han'"
)->fetchAll();

// Chi phi khac: cong no = settle_amount cua cac giao dich chua xu ly (is_done = 0)
$billingRows = $pdo->query(
    "SELECT id, guest_name, room_code, bill_date, content, total_amount, deposit_used, settle_amount FROM billing_entries WHERE is_done = 0"
)->fetchAll();

$bySale = [];
$ensure = function (string $name) use (&$bySale) {
    if (!isset($bySale[$name])) {
        $bySale[$name] = ['short' => 0.0, 'billing' => 0.0, 'short_rows' => [], 'billing_rows' => []];
    }
};

foreach ($shortRows as $r) {
    $name = trim($r['guest_name']) !== '' ? trim($r['guest_name']) : '(Chưa đặt tên)';
    $ensure($name);
    $bySale[$name]['short'] += (float)$r['total_amount'] - (float)$r['paid_amount'];
    $bySale[$name]['short_rows'][] = $r;
}
foreach ($billingRows as $r) {
    $name = trim($r['guest_name']) !== '' ? trim($r['guest_name']) : '(Chưa đặt tên)';
    $ensure($name);
    $bySale[$name]['billing'] += (float)$r['settle_amount'];
    $bySale[$name]['billing_rows'][] = $r;
}

// Chi hien nhung Sale con cong no thuc su (chua thu), bo qua da tra du/qua tay
$bySale = array_filter($bySale, fn($s) => ($s['short'] + $s['billing']) > 0.5);

if ($search !== '') {
    $bySale = array_filter($bySale, fn($name) => stripos($name, $search) !== false, ARRAY_FILTER_USE_KEY);
}

uasort($bySale, fn($a, $b) => ($b['short'] + $b['billing']) <=> ($a['short'] + $a['billing']));

$grandShort = array_sum(array_column($bySale, 'short'));
$grandBilling = array_sum(array_column($bySale, 'billing'));
$grandTotal = $grandShort + $grandBilling;

$pageTitle = 'Công nợ tổng hợp';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="mb-0"><i class="bi bi-exclamation-diamond"></i> Công nợ tổng hợp</h4>
</div>

<div class="row g-3 mb-3">
  <div class="col-sm-4">
    <div class="stat-card"><div class="text-muted small">Nợ ngắn hạn</div><div class="stat-value"><?= money($grandShort) ?></div></div>
  </div>
  <div class="col-sm-4">
    <div class="stat-card"><div class="text-muted small">Nợ chi phí khác</div><div class="stat-value"><?= money($grandBilling) ?></div></div>
  </div>
  <div class="col-sm-4">
    <div class="stat-card"><div class="text-muted small">Tổng công nợ</div><div class="stat-value text-danger"><?= money($grandTotal) ?></div></div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form class="row g-2 align-items-end">
      <div class="col-sm-4">
        <input type="text" name="q" class="form-control" placeholder="Tên sale..." value="<?= e($search) ?>">
      </div>
      <div class="col-sm-2">
        <button class="btn btn-outline-secondary w-100"><i class="bi bi-search"></i> Lọc</button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header">Công nợ theo Sale (ngắn hạn + chi phí khác cộng lại)</div>
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead>
        <tr>
          <th>Sale</th>
          <th class="text-end">Nợ ngắn hạn</th>
          <th class="text-end">Nợ chi phí khác</th>
          <th class="text-end">Tổng công nợ</th>
          <th class="text-end">Thao tác</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$bySale): ?>
          <tr><td colspan="5" class="text-center text-muted py-4">Chưa có dữ liệu.</td></tr>
        <?php endif; ?>
        <?php $idx = 0; foreach ($bySale as $saleName => $s): $idx++; ?>
          <?php $total = $s['short'] + $s['billing']; ?>
          <tr>
            <td class="fw-semibold"><?= e($saleName) ?></td>
            <td class="text-end <?= $s['short'] != 0 ? '' : 'text-muted' ?>"><?= money($s['short']) ?></td>
            <td class="text-end <?= $s['billing'] != 0 ? '' : 'text-muted' ?>"><?= money($s['billing']) ?></td>
            <td class="text-end fw-semibold <?= $total > 0 ? 'text-danger' : ($total < 0 ? 'text-success' : '') ?>"><?= money($total) ?></td>
            <td class="text-end">
              <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#debtModal<?= $idx ?>"><i class="bi bi-eye"></i> Xem chi tiết</button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php $idx = 0; foreach ($bySale as $saleName => $s): $idx++; ?>
  <div class="modal fade" id="debtModal<?= $idx ?>" tabindex="-1">
    <div class="modal-dialog modal-xl">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-person"></i> Chi tiết công nợ - <?= e($saleName) ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">

          <?php $modalTotal = $s['short'] + $s['billing']; ?>
          <div class="text-center p-3 mb-4 rounded" style="background: var(--bs-light);">
            <div class="text-muted text-uppercase small" style="letter-spacing:.05em;">Tổng cộng công nợ</div>
            <div class="fw-bold <?= $modalTotal > 0 ? 'text-danger' : ($modalTotal < 0 ? 'text-success' : '') ?>" style="font-size:2rem;"><?= money($modalTotal) ?></div>
          </div>

          <div class="fw-semibold mb-2">Doanh thu ngắn hạn <span class="text-muted small">(<?= count($s['short_rows']) ?> deal, còn nợ <?= money($s['short']) ?>)</span></div>
          <?php if (!$s['short_rows']): ?>
            <div class="text-muted small mb-3">Không có.</div>
          <?php else: ?>
          <div class="table-responsive mb-3">
            <table class="table table-sm">
              <thead><tr><th>Phòng</th><th>IN</th><th>OUT</th><th class="text-end">Tổng</th><th class="text-end">Đã thu</th><th class="text-end">Còn nợ</th><th></th></tr></thead>
              <tbody>
                <?php foreach ($s['short_rows'] as $r): ?>
                  <?php $r_remain = (float)$r['total_amount'] - (float)$r['paid_amount']; ?>
                  <tr>
                    <td><?= e($r['room_code']) ?></td>
                    <td><?= vndate($r['checkin_date']) ?></td>
                    <td><?= vndate($r['checkout_date']) ?></td>
                    <td class="text-end"><?= money($r['total_amount']) ?></td>
                    <td class="text-end text-success"><?= money($r['paid_amount']) ?></td>
                    <td class="text-end fw-semibold <?= $r_remain > 0 ? 'text-danger' : '' ?>"><?= money($r_remain) ?></td>
                    <td class="text-end"><a href="<?= url('/deals/form.php?id=' . $r['id']) ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>

          <div class="fw-semibold mb-2">Chi phí khác <span class="text-muted small">(<?= count($s['billing_rows']) ?> giao dịch chưa xử lý, còn nợ <?= money($s['billing']) ?>)</span></div>
          <?php if (!$s['billing_rows']): ?>
            <div class="text-muted small">Không có.</div>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm mb-0">
              <thead><tr><th>Ngày</th><th>Phòng</th><th>Nội dung</th><th class="text-end">Tổng tiền</th><th class="text-end">Tiền cọc</th><th class="text-end">Còn nợ</th><th></th></tr></thead>
              <tbody>
                <?php foreach ($s['billing_rows'] as $r): ?>
                  <tr>
                    <td><?= vndate($r['bill_date']) ?></td>
                    <td><?= e($r['room_code']) ?></td>
                    <td class="small"><?= e($r['content']) ?></td>
                    <td class="text-end"><?= money($r['total_amount']) ?></td>
                    <td class="text-end"><?= money($r['deposit_used']) ?></td>
                    <td class="text-end fw-semibold <?= $r['settle_amount'] > 0 ? 'text-danger' : ($r['settle_amount'] < 0 ? 'text-success' : '') ?>"><?= money($r['settle_amount']) ?></td>
                    <td class="text-end"><a href="<?= url('/billing/form.php?id=' . $r['id']) ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>

        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Đóng</button>
        </div>
      </div>
    </div>
  </div>
<?php endforeach; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
