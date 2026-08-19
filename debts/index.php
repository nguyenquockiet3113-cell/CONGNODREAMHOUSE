<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$search = trim($_GET['q'] ?? '');

// Ngan han: cong no = total_amount - paid_amount
$shortRows = $pdo->query(
    "SELECT guest_name, total_amount, paid_amount FROM deals WHERE deal_type = 'ngan_han'"
)->fetchAll();

// Dai han: cong no = tong (thue+dich vu) tung ky - da TT tung ky, gop theo deal (qua guest_name)
$longRows = $pdo->query(
    "SELECT d.guest_name, COALESCE(SUM(dp.rent_amount + dp.utilities_amount),0) AS total, COALESCE(SUM(dp.paid_amount),0) AS paid
     FROM deal_periods dp JOIN deals d ON d.id = dp.deal_id
     WHERE d.deal_type = 'dai_han' GROUP BY d.id, d.guest_name"
)->fetchAll();

// Chi phi khac: cong no = settle_amount cua cac giao dich chua xu ly (is_done = 0)
$billingRows = $pdo->query(
    "SELECT guest_name, settle_amount FROM billing_entries WHERE is_done = 0"
)->fetchAll();

$bySale = [];
$ensure = function (string $name) use (&$bySale) {
    if (!isset($bySale[$name])) {
        $bySale[$name] = ['short' => 0.0, 'long' => 0.0, 'billing' => 0.0];
    }
};

foreach ($shortRows as $r) {
    $name = trim($r['guest_name']) !== '' ? trim($r['guest_name']) : '(Chưa đặt tên)';
    $ensure($name);
    $bySale[$name]['short'] += (float)$r['total_amount'] - (float)$r['paid_amount'];
}
foreach ($longRows as $r) {
    $name = trim($r['guest_name']) !== '' ? trim($r['guest_name']) : '(Chưa đặt tên)';
    $ensure($name);
    $bySale[$name]['long'] += (float)$r['total'] - (float)$r['paid'];
}
foreach ($billingRows as $r) {
    $name = trim($r['guest_name']) !== '' ? trim($r['guest_name']) : '(Chưa đặt tên)';
    $ensure($name);
    $bySale[$name]['billing'] += (float)$r['settle_amount'];
}

if ($search !== '') {
    $bySale = array_filter($bySale, fn($name) => stripos($name, $search) !== false, ARRAY_FILTER_USE_KEY);
}

uasort($bySale, fn($a, $b) =>
    ($b['short'] + $b['long'] + $b['billing']) <=> ($a['short'] + $a['long'] + $a['billing'])
);

$grandShort = array_sum(array_column($bySale, 'short'));
$grandLong = array_sum(array_column($bySale, 'long'));
$grandBilling = array_sum(array_column($bySale, 'billing'));
$grandTotal = $grandShort + $grandLong + $grandBilling;

$pageTitle = 'Công nợ tổng hợp';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="mb-0"><i class="bi bi-exclamation-diamond"></i> Công nợ tổng hợp</h4>
</div>

<div class="row g-3 mb-3">
  <div class="col-sm-3">
    <div class="stat-card"><div class="text-muted small">Nợ ngắn hạn</div><div class="stat-value"><?= money($grandShort) ?></div></div>
  </div>
  <div class="col-sm-3">
    <div class="stat-card"><div class="text-muted small">Nợ dài hạn</div><div class="stat-value"><?= money($grandLong) ?></div></div>
  </div>
  <div class="col-sm-3">
    <div class="stat-card"><div class="text-muted small">Nợ chi phí khác</div><div class="stat-value"><?= money($grandBilling) ?></div></div>
  </div>
  <div class="col-sm-3">
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
  <div class="card-header">Công nợ theo Sale (ngắn hạn + dài hạn + chi phí khác cộng lại)</div>
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead>
        <tr>
          <th>Sale</th>
          <th class="text-end">Nợ ngắn hạn</th>
          <th class="text-end">Nợ dài hạn</th>
          <th class="text-end">Nợ chi phí khác</th>
          <th class="text-end">Tổng công nợ</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$bySale): ?>
          <tr><td colspan="5" class="text-center text-muted py-4">Chưa có dữ liệu.</td></tr>
        <?php endif; ?>
        <?php foreach ($bySale as $saleName => $s): ?>
          <?php $total = $s['short'] + $s['long'] + $s['billing']; ?>
          <tr>
            <td class="fw-semibold"><?= e($saleName) ?></td>
            <td class="text-end <?= $s['short'] != 0 ? '' : 'text-muted' ?>"><?= money($s['short']) ?></td>
            <td class="text-end <?= $s['long'] != 0 ? '' : 'text-muted' ?>"><?= money($s['long']) ?></td>
            <td class="text-end <?= $s['billing'] != 0 ? '' : 'text-muted' ?>"><?= money($s['billing']) ?></td>
            <td class="text-end fw-semibold <?= $total > 0 ? 'text-danger' : ($total < 0 ? 'text-success' : '') ?>"><?= money($total) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
