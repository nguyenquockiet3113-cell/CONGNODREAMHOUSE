<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$monthsCount = (int)($_GET['months'] ?? 12);
if (!in_array($monthsCount, [6, 12, 24], true)) $monthsCount = 12;

$months = [];
for ($i = $monthsCount - 1; $i >= 0; $i--) {
    $months[] = date('Y-m', strtotime("-$i months"));
}

$rows = [];
$sumRevenueLT = 0; $sumRevenueST = 0; $sumExpense = 0;
foreach ($months as $m) {
    $s1 = $pdo->prepare("SELECT COALESCE(SUM(paid_amount),0) s FROM invoices WHERE paid_date IS NOT NULL AND substr(paid_date,1,7) = ?");
    $s1->execute([$m]);
    $lt = (float)$s1->fetch()['s'];

    $s2 = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) s FROM bookings WHERE payment_status='paid' AND paid_date IS NOT NULL AND substr(paid_date,1,7) = ?");
    $s2->execute([$m]);
    $st = (float)$s2->fetch()['s'];

    $s3 = $pdo->prepare("SELECT COALESCE(SUM(amount),0) s FROM expenses WHERE substr(expense_date,1,7) = ?");
    $s3->execute([$m]);
    $ex = (float)$s3->fetch()['s'];

    $rows[] = ['month' => $m, 'lt' => $lt, 'st' => $st, 'expense' => $ex, 'profit' => $lt + $st - $ex];
    $sumRevenueLT += $lt; $sumRevenueST += $st; $sumExpense += $ex;
}
$sumRevenue = $sumRevenueLT + $sumRevenueST;
$sumProfit = $sumRevenue - $sumExpense;

// Chi phi theo danh muc (trong khoang thoi gian bao cao)
$fromMonth = $months[0];
$toMonth = end($months);
$catStmt = $pdo->prepare(
    "SELECT category, COALESCE(SUM(amount),0) s FROM expenses
     WHERE substr(expense_date,1,7) BETWEEN ? AND ? GROUP BY category ORDER BY s DESC"
);
$catStmt->execute([$fromMonth, $toMonth]);
$categoryBreakdown = $catStmt->fetchAll();

// Ty le lap day phong hien tai
$roomTotal = (int)$pdo->query('SELECT COUNT(*) c FROM rooms')->fetch()['c'];
$roomOccupied = (int)$pdo->query("SELECT COUNT(*) c FROM rooms WHERE status = 'dang_thue'")->fetch()['c'];
$occupancyRate = $roomTotal > 0 ? round($roomOccupied / $roomTotal * 100, 1) : 0;

$pageTitle = 'Báo cáo';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="mb-0"><i class="bi bi-graph-up"></i> Báo cáo doanh thu - chi phí</h4>
  <form class="d-flex gap-2">
    <select name="months" class="form-select" onchange="this.form.submit()">
      <option value="6" <?= $monthsCount === 6 ? 'selected' : '' ?>>6 tháng gần nhất</option>
      <option value="12" <?= $monthsCount === 12 ? 'selected' : '' ?>>12 tháng gần nhất</option>
      <option value="24" <?= $monthsCount === 24 ? 'selected' : '' ?>>24 tháng gần nhất</option>
    </select>
  </form>
</div>

<div class="row g-3 mb-3">
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card"><div class="text-muted small">Tổng doanh thu</div><div class="stat-value"><?= money($sumRevenue) ?></div></div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card"><div class="text-muted small">Tổng chi phí</div><div class="stat-value text-danger"><?= money($sumExpense) ?></div></div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card"><div class="text-muted small">Lợi nhuận</div><div class="stat-value <?= $sumProfit >= 0 ? 'text-success' : 'text-danger' ?>"><?= money($sumProfit) ?></div></div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card"><div class="text-muted small">Tỷ lệ lấp đầy hiện tại</div><div class="stat-value"><?= $occupancyRate ?>%</div><div class="small text-muted"><?= $roomOccupied ?>/<?= $roomTotal ?> phòng</div></div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-header">Doanh thu / Chi phí / Lợi nhuận theo tháng</div>
      <div class="card-body"><canvas id="trendChart" height="240"></canvas></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header">Cơ cấu chi phí theo danh mục</div>
      <div class="card-body"><canvas id="catChart" height="240"></canvas></div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">Chi tiết theo tháng</div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Tháng</th>
          <th>Doanh thu dài hạn</th>
          <th>Doanh thu ngắn hạn</th>
          <th>Tổng doanh thu</th>
          <th>Chi phí</th>
          <th>Lợi nhuận</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (array_reverse($rows) as $r): ?>
          <tr>
            <td><?= e($r['month']) ?></td>
            <td><?= money($r['lt']) ?></td>
            <td><?= money($r['st']) ?></td>
            <td class="fw-semibold"><?= money($r['lt'] + $r['st']) ?></td>
            <td class="text-danger"><?= money($r['expense']) ?></td>
            <td class="<?= $r['profit'] >= 0 ? 'text-success' : 'text-danger' ?> fw-semibold"><?= money($r['profit']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('trendChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($months) ?>,
    datasets: [
      { type: 'bar', label: 'Doanh thu', data: <?= json_encode(array_map(fn($r) => $r['lt'] + $r['st'], $rows)) ?>, backgroundColor: '#2f6f4f' },
      { type: 'bar', label: 'Chi phí', data: <?= json_encode(array_column($rows, 'expense')) ?>, backgroundColor: '#dc2626' },
      { type: 'line', label: 'Lợi nhuận', data: <?= json_encode(array_column($rows, 'profit')) ?>, borderColor: '#2563eb', backgroundColor: '#2563eb', tension: .3 },
    ]
  },
  options: {
    responsive: true,
    plugins: { legend: { position: 'bottom' } },
    scales: { y: { ticks: { callback: v => new Intl.NumberFormat('vi-VN').format(v) } } }
  }
});

new Chart(document.getElementById('catChart'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode(array_column($categoryBreakdown, 'category')) ?>,
    datasets: [{
      data: <?= json_encode(array_map(fn($c) => (float)$c['s'], $categoryBreakdown)) ?>,
      backgroundColor: ['#2f6f4f','#2563eb','#dc2626','#f59e0b','#8b5cf6','#0891b2','#db2777','#65a30d','#71717a'],
    }]
  },
  options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
