<?php
require_once __DIR__ . '/config/config.php';
require_login();

$today = date('Y-m-d');
$currentMonth = date('Y-m');

// --- Thong ke phong ---
$roomStats = ['trong' => 0, 'dang_thue' => 0, 'bao_tri' => 0];
foreach ($pdo->query('SELECT status, COUNT(*) c FROM rooms GROUP BY status') as $row) {
    $roomStats[$row['status']] = (int)$row['c'];
}
$totalRooms = array_sum($roomStats);

// --- Doanh thu dai han thang nay (da thu) ---
$stmt = $pdo->prepare('SELECT COALESCE(SUM(paid_amount),0) s FROM invoices WHERE period_month = ?');
$stmt->execute([$currentMonth]);
$longTermRevenue = (float)$stmt->fetch()['s'];

// --- Doanh thu ngan han thang nay (da thu, theo ngay nhan phong) ---
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) s FROM bookings WHERE payment_status = 'paid' AND substr(checkin_date,1,7) = ?");
$stmt->execute([$currentMonth]);
$shortTermRevenue = (float)$stmt->fetch()['s'];

// --- Chi phi thang nay ---
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) s FROM expenses WHERE substr(expense_date,1,7) = ?");
$stmt->execute([$currentMonth]);
$monthExpense = (float)$stmt->fetch()['s'];

$monthRevenue = $longTermRevenue + $shortTermRevenue;
$monthProfit = $monthRevenue - $monthExpense;

// --- Hop dong sap het han (trong 30 ngay) ---
$stmt = $pdo->prepare(
    "SELECT c.*, r.room_code, t.full_name AS tenant_name FROM contracts c
     JOIN rooms r ON r.id = c.room_id JOIN tenants t ON t.id = c.tenant_id
     WHERE c.status = 'active' AND c.end_date >= ? AND c.end_date <= ?
     ORDER BY c.end_date ASC"
);
$in30 = date('Y-m-d', strtotime('+30 days'));
$stmt->execute([$today, $in30]);
$expiringContracts = $stmt->fetchAll();

// --- Hoa don chua thanh toan ---
$stmt = $pdo->query(
    "SELECT i.*, r.room_code, t.full_name AS tenant_name FROM invoices i
     JOIN rooms r ON r.id = i.room_id JOIN contracts c ON c.id = i.contract_id JOIN tenants t ON t.id = c.tenant_id
     WHERE i.status != 'paid' ORDER BY i.due_date ASC LIMIT 8"
);
$unpaidInvoices = $stmt->fetchAll();
$stmt2 = $pdo->query("SELECT COALESCE(SUM(total_amount - paid_amount),0) s FROM invoices WHERE status != 'paid'");
$totalOwed = (float)$stmt2->fetch()['s'];

// --- Dat phong sap toi (7 ngay toi) ---
$stmt = $pdo->prepare(
    "SELECT b.*, r.room_code FROM bookings b JOIN rooms r ON r.id = b.room_id
     WHERE b.status IN ('booked','checked_in') AND b.checkin_date BETWEEN ? AND ?
     ORDER BY b.checkin_date ASC LIMIT 8"
);
$in7 = date('Y-m-d', strtotime('+7 days'));
$stmt->execute([$today, $in7]);
$upcomingBookings = $stmt->fetchAll();

// --- Bieu do 6 thang gan nhat ---
$months = [];
for ($i = 5; $i >= 0; $i--) {
    $months[] = date('Y-m', strtotime("-$i months"));
}
$chartRevenue = [];
$chartExpense = [];
foreach ($months as $m) {
    $s1 = $pdo->prepare('SELECT COALESCE(SUM(paid_amount),0) s FROM invoices WHERE period_month = ?');
    $s1->execute([$m]);
    $lt = (float)$s1->fetch()['s'];

    $s2 = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) s FROM bookings WHERE payment_status='paid' AND substr(checkin_date,1,7) = ?");
    $s2->execute([$m]);
    $st = (float)$s2->fetch()['s'];

    $s3 = $pdo->prepare("SELECT COALESCE(SUM(amount),0) s FROM expenses WHERE substr(expense_date,1,7) = ?");
    $s3->execute([$m]);
    $ex = (float)$s3->fetch()['s'];

    $chartRevenue[] = $lt + $st;
    $chartExpense[] = $ex;
}

$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
?>
<h4 class="mb-3"><i class="bi bi-speedometer2"></i> Dashboard</h4>

<div class="row g-3 mb-3">
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card d-flex gap-3 align-items-center">
      <div class="stat-icon" style="background:#2f6f4f;"><i class="bi bi-door-closed"></i></div>
      <div>
        <div class="text-muted small">Tổng số phòng</div>
        <div class="stat-value"><?= $totalRooms ?></div>
        <div class="small text-muted"><?= $roomStats['trong'] ?> trống · <?= $roomStats['dang_thue'] ?> đang thuê · <?= $roomStats['bao_tri'] ?> bảo trì</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card d-flex gap-3 align-items-center">
      <div class="stat-icon" style="background:#2563eb;"><i class="bi bi-cash-stack"></i></div>
      <div>
        <div class="text-muted small">Doanh thu tháng này</div>
        <div class="stat-value"><?= money($monthRevenue) ?></div>
        <div class="small text-muted">Dài hạn <?= money($longTermRevenue) ?> · Ngắn hạn <?= money($shortTermRevenue) ?></div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card d-flex gap-3 align-items-center">
      <div class="stat-icon" style="background:#dc2626;"><i class="bi bi-cash-coin"></i></div>
      <div>
        <div class="text-muted small">Chi phí tháng này</div>
        <div class="stat-value"><?= money($monthExpense) ?></div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card d-flex gap-3 align-items-center">
      <div class="stat-icon" style="background:<?= $monthProfit >= 0 ? '#16a34a' : '#dc2626' ?>;"><i class="bi bi-graph-up-arrow"></i></div>
      <div>
        <div class="text-muted small">Lợi nhuận tháng này</div>
        <div class="stat-value"><?= money($monthProfit) ?></div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header">Doanh thu &amp; chi phí 6 tháng gần nhất</div>
      <div class="card-body">
        <canvas id="revenueChart" height="220"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between">
        <span>Hóa đơn chưa thanh toán</span>
        <span class="text-danger fw-semibold"><?= money($totalOwed) ?></span>
      </div>
      <div class="list-group list-group-flush" style="max-height:280px;overflow-y:auto;">
        <?php if (!$unpaidInvoices): ?>
          <div class="list-group-item text-muted small">Không có hóa đơn tồn đọng.</div>
        <?php endif; ?>
        <?php foreach ($unpaidInvoices as $inv): ?>
          <a href="<?= url('/invoices/form.php?id=' . $inv['id']) ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
            <div>
              <div class="fw-semibold"><?= e($inv['room_code']) ?> - <?= e($inv['tenant_name']) ?></div>
              <div class="small text-muted">Kỳ <?= e($inv['period_month']) ?> · Hạn <?= vndate($inv['due_date']) ?></div>
            </div>
            <span class="badge bg-<?= badge_class($inv['status']) ?>"><?= money($inv['total_amount'] - $inv['paid_amount']) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header">Hợp đồng sắp hết hạn (30 ngày tới)</div>
      <div class="list-group list-group-flush">
        <?php if (!$expiringContracts): ?>
          <div class="list-group-item text-muted small">Không có hợp đồng nào sắp hết hạn.</div>
        <?php endif; ?>
        <?php foreach ($expiringContracts as $c): ?>
          <a href="<?= url('/contracts/view.php?id=' . $c['id']) ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
            <div>
              <div class="fw-semibold"><?= e($c['room_code']) ?> - <?= e($c['tenant_name']) ?></div>
              <div class="small text-muted">Mã HĐ <?= e($c['contract_code']) ?></div>
            </div>
            <span class="badge bg-warning">Hết hạn <?= vndate($c['end_date']) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header">Khách nhận/trả phòng sắp tới (7 ngày)</div>
      <div class="list-group list-group-flush">
        <?php if (!$upcomingBookings): ?>
          <div class="list-group-item text-muted small">Không có lịch nhận phòng nào sắp tới.</div>
        <?php endif; ?>
        <?php foreach ($upcomingBookings as $b): ?>
          <a href="<?= url('/bookings/form.php?id=' . $b['id']) ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
            <div>
              <div class="fw-semibold"><?= e($b['room_code']) ?> - <?= e($b['guest_name']) ?></div>
              <div class="small text-muted">Nhận phòng <?= vndate($b['checkin_date']) ?></div>
            </div>
            <span class="badge bg-<?= badge_class($b['status']) ?>"><?= e(BOOKING_STATUS_LABELS[$b['status']] ?? '') ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('revenueChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($months) ?>,
    datasets: [
      { label: 'Doanh thu', data: <?= json_encode($chartRevenue) ?>, backgroundColor: '#2f6f4f' },
      { label: 'Chi phí', data: <?= json_encode($chartExpense) ?>, backgroundColor: '#dc2626' },
    ]
  },
  options: {
    responsive: true,
    plugins: { legend: { position: 'bottom' } },
    scales: { y: { beginAtZero: true, ticks: { callback: v => new Intl.NumberFormat('vi-VN').format(v) } } }
  }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
