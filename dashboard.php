<?php
require_once __DIR__ . '/config/config.php';
require_login();

$today = date('Y-m-d');

$filterZone = trim($_GET['zone'] ?? '');
$filterRoomCode = trim($_GET['room_code'] ?? '');
$filterMonth = trim($_GET['month'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $filterMonth)) $filterMonth = date('Y-m');
$alertDays = (int)($_GET['alert_days'] ?? 21);
if ($alertDays <= 0) $alertDays = 21;

$monthFirst = $filterMonth . '-01';
$monthLast = date('Y-m-d', strtotime($monthFirst . ' +1 month -1 day'));
$fromDate = $_GET['from'] ?? $monthFirst;
$toDate = $_GET['to'] ?? $monthLast;

$zones = $pdo->query("SELECT DISTINCT zone FROM rooms WHERE zone IS NOT NULL AND zone != '' ORDER BY zone")->fetchAll(PDO::FETCH_COLUMN);

function apply_deal_filters(string $sql, array &$params, string $zone, string $roomCode): string
{
    if ($zone !== '') { $sql .= ' AND zone = ?'; $params[] = $zone; }
    if ($roomCode !== '') { $sql .= ' AND room_code LIKE ?'; $params[] = "%$roomCode%"; }
    return $sql;
}

// --- Thong ke phong (trong / dang o) ---
$roomSql = 'SELECT * FROM rooms WHERE 1=1';
$roomParams = [];
if ($filterZone !== '') { $roomSql .= ' AND zone = ?'; $roomParams[] = $filterZone; }
if ($filterRoomCode !== '') { $roomSql .= ' AND room_code LIKE ?'; $roomParams[] = "%$filterRoomCode%"; }
$stmt = $pdo->prepare($roomSql);
$stmt->execute($roomParams);
$allRooms = $stmt->fetchAll();
$totalRooms = count($allRooms);

$occStmt = $pdo->prepare('SELECT DISTINCT room_code FROM deals WHERE checkin_date <= ? AND checkout_date > ?');
$occStmt->execute([$today, $today]);
$occupiedSet = array_flip($occStmt->fetchAll(PDO::FETCH_COLUMN));
$occupiedCount = 0;
foreach ($allRooms as $r) { if (isset($occupiedSet[$r['room_code']])) $occupiedCount++; }
$vacantCount = $totalRooms - $occupiedCount;
$occupancyRate = $totalRooms > 0 ? round($occupiedCount / $totalRooms * 100, 1) : 0;

// --- Doanh thu ngan han da thu trong khoang loc (theo ngay check-in) ---
$sql = "SELECT COALESCE(SUM(paid_amount),0) s FROM deals WHERE deal_type = 'ngan_han' AND checkin_date BETWEEN ? AND ?";
$params = [$fromDate, $toDate];
$sql = apply_deal_filters($sql, $params, $filterZone, $filterRoomCode);
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$shortTermRevenue = (float)$stmt->fetch()['s'];

// --- Doanh thu dai han da thu trong khoang loc (theo ky, period_start) ---
$sql = "SELECT COALESCE(SUM(dp.paid_amount),0) s FROM deal_periods dp JOIN deals d ON d.id = dp.deal_id
        WHERE d.deal_type = 'dai_han' AND dp.period_start BETWEEN ? AND ?";
$params = [$fromDate, $toDate];
if ($filterZone !== '') { $sql .= ' AND d.zone = ?'; $params[] = $filterZone; }
if ($filterRoomCode !== '') { $sql .= ' AND d.room_code LIKE ?'; $params[] = "%$filterRoomCode%"; }
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$longTermRevenue = (float)$stmt->fetch()['s'];

// --- Chi phi trong khoang loc ---
$sql = "SELECT COALESCE(SUM(ex.amount),0) s FROM expenses ex LEFT JOIN rooms r ON r.id = ex.room_id
        WHERE ex.expense_date BETWEEN ? AND ?";
$params = [$fromDate, $toDate];
if ($filterZone !== '') { $sql .= ' AND r.zone = ?'; $params[] = $filterZone; }
if ($filterRoomCode !== '') { $sql .= ' AND r.room_code LIKE ?'; $params[] = "%$filterRoomCode%"; }
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$periodExpense = (float)$stmt->fetch()['s'];

$periodRevenue = $longTermRevenue + $shortTermRevenue;
$periodProfit = $periodRevenue - $periodExpense;

// --- No ton dong ---
$sql = "SELECT COALESCE(SUM(total_amount - paid_amount),0) s FROM deals WHERE deal_type = 'ngan_han' AND payment_status != 'paid'";
$params = [];
$sql = apply_deal_filters($sql, $params, $filterZone, $filterRoomCode);
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$owedShort = (float)$stmt->fetch()['s'];

$sql = "SELECT COALESCE(SUM(dp.rent_amount + dp.utilities_amount - dp.paid_amount),0) s
        FROM deal_periods dp JOIN deals d ON d.id = dp.deal_id WHERE d.deal_type = 'dai_han'";
$params = [];
if ($filterZone !== '') { $sql .= ' AND d.zone = ?'; $params[] = $filterZone; }
if ($filterRoomCode !== '') { $sql .= ' AND d.room_code LIKE ?'; $params[] = "%$filterRoomCode%"; }
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$owedLong = (float)$stmt->fetch()['s'];
$totalOwed = $owedShort + $owedLong;

// --- Doanh thu theo ngay thanh toan (thuc nhan tien) va theo ngay phat sinh (chia deu theo ngay o/ky) ---
$dayCursor = $fromDate;
$dayLabels = [];
$cashByDay = [];
$accrualByDay = [];
while ($dayCursor <= $toDate) {
    $dayLabels[] = $dayCursor;
    $cashByDay[$dayCursor] = 0.0;
    $accrualByDay[$dayCursor] = 0.0;
    $dayCursor = date('Y-m-d', strtotime($dayCursor . ' +1 day'));
}

// Theo ngay thanh toan: cong tien vao dung ngay nhan (deal_payments.payment_date), khong quan tam ngay o
$sql = "SELECT dp.payment_date, dp.amount FROM deal_payments dp JOIN deals d ON d.id = dp.deal_id
        WHERE dp.payment_date BETWEEN ? AND ?";
$params = [$fromDate, $toDate];
if ($filterZone !== '') { $sql .= ' AND d.zone = ?'; $params[] = $filterZone; }
if ($filterRoomCode !== '') { $sql .= ' AND d.room_code LIKE ?'; $params[] = "%$filterRoomCode%"; }
$stmt = $pdo->prepare($sql); $stmt->execute($params);
foreach ($stmt->fetchAll() as $row) {
    if (isset($cashByDay[$row['payment_date']])) $cashByDay[$row['payment_date']] += (float)$row['amount'];
}

// Theo ngay phat sinh (ngan han): tong tien deal / so dem, chia deu tung ngay tu checkin den truoc checkout
$sql = "SELECT checkin_date, checkout_date, nights, total_amount FROM deals
        WHERE deal_type = 'ngan_han' AND checkin_date <= ? AND checkout_date > ?";
$params = [$toDate, $fromDate];
$sql = apply_deal_filters($sql, $params, $filterZone, $filterRoomCode);
$stmt = $pdo->prepare($sql); $stmt->execute($params);
foreach ($stmt->fetchAll() as $d) {
    $nights = max(1, (int)$d['nights']);
    $daily = (float)$d['total_amount'] / $nights;
    $day = $d['checkin_date'];
    while ($day < $d['checkout_date']) {
        if (isset($accrualByDay[$day])) $accrualByDay[$day] += $daily;
        $day = date('Y-m-d', strtotime($day . ' +1 day'));
    }
}

// Theo ngay phat sinh (dai han): (tien thue + tien dich vu) cua tung ky / so ngay ky, chia deu tu period_start den truoc period_end
$sql = "SELECT dp.period_start, dp.period_end, dp.rent_amount, dp.utilities_amount FROM deal_periods dp
        JOIN deals d ON d.id = dp.deal_id
        WHERE d.deal_type = 'dai_han' AND dp.period_start <= ? AND dp.period_end > ?";
$params = [$toDate, $fromDate];
if ($filterZone !== '') { $sql .= ' AND d.zone = ?'; $params[] = $filterZone; }
if ($filterRoomCode !== '') { $sql .= ' AND d.room_code LIKE ?'; $params[] = "%$filterRoomCode%"; }
$stmt = $pdo->prepare($sql); $stmt->execute($params);
foreach ($stmt->fetchAll() as $p) {
    $periodDays = max(1, (int)((strtotime($p['period_end']) - strtotime($p['period_start'])) / 86400));
    $daily = ((float)$p['rent_amount'] + (float)$p['utilities_amount']) / $periodDays;
    $day = $p['period_start'];
    while ($day < $p['period_end']) {
        if (isset($accrualByDay[$day])) $accrualByDay[$day] += $daily;
        $day = date('Y-m-d', strtotime($day . ' +1 day'));
    }
}

// --- Hop dong sap het han ---
$sql = "SELECT * FROM contracts WHERE status = 'active' AND end_date >= ? AND end_date <= ?";
$params = [$today, date('Y-m-d', strtotime("+$alertDays days"))];
if ($filterZone !== '') { $sql .= ' AND zone = ?'; $params[] = $filterZone; }
if ($filterRoomCode !== '') { $sql .= ' AND room_code LIKE ?'; $params[] = "%$filterRoomCode%"; }
$sql .= ' ORDER BY end_date ASC';
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$expiringContracts = $stmt->fetchAll();

// --- Nhac nho sap toi (7 ngay, chua hoan thanh) ---
$stmt = $pdo->prepare('SELECT * FROM reminders WHERE is_done = 0 AND due_date <= ? ORDER BY due_date ASC LIMIT 6');
$stmt->execute([date('Y-m-d', strtotime('+7 days'))]);
$upcomingReminders = $stmt->fetchAll();

// --- Bieu do 6 thang gan nhat (thuc thu) ---
$months = [];
for ($i = 5; $i >= 0; $i--) { $months[] = date('Y-m', strtotime("-$i months")); }
$chartRevenue = [];
$chartExpense = [];
foreach ($months as $m) {
    $mf = $m . '-01'; $ml = date('Y-m-d', strtotime($mf . ' +1 month -1 day'));

    $s1 = $pdo->prepare("SELECT COALESCE(SUM(paid_amount),0) s FROM deals WHERE deal_type='ngan_han' AND checkin_date BETWEEN ? AND ?");
    $s1->execute([$mf, $ml]);
    $st = (float)$s1->fetch()['s'];

    $s2 = $pdo->prepare("SELECT COALESCE(SUM(dp.paid_amount),0) s FROM deal_periods dp JOIN deals d ON d.id=dp.deal_id WHERE d.deal_type='dai_han' AND dp.period_start BETWEEN ? AND ?");
    $s2->execute([$mf, $ml]);
    $lt = (float)$s2->fetch()['s'];

    $s3 = $pdo->prepare('SELECT COALESCE(SUM(amount),0) s FROM expenses WHERE expense_date BETWEEN ? AND ?');
    $s3->execute([$mf, $ml]);
    $ex = (float)$s3->fetch()['s'];

    $chartRevenue[] = $lt + $st;
    $chartExpense[] = $ex;
}

$pageTitle = 'Tổng quan';
require_once __DIR__ . '/includes/header.php';
?>
<h4 class="mb-3"><i class="bi bi-speedometer2"></i> Tổng quan</h4>

<div class="card mb-3">
  <div class="card-body">
    <div class="text-uppercase small text-muted mb-2" style="letter-spacing:.05em;"><i class="bi bi-funnel"></i> Bộ lọc</div>
    <form class="row g-2 align-items-end">
      <div class="col-sm-6 col-lg-3">
        <label class="form-label small mb-1">Khu vực</label>
        <select name="zone" class="form-select">
          <option value="">-- Tất cả --</option>
          <?php foreach ($zones as $z): ?>
            <option value="<?= e($z) ?>" <?= $filterZone === $z ? 'selected' : '' ?>><?= e($z) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-6 col-lg-3">
        <label class="form-label small mb-1">Mã căn</label>
        <input type="text" name="room_code" class="form-control" placeholder="VD: A0101" value="<?= e($filterRoomCode) ?>">
      </div>
      <div class="col-sm-6 col-lg-2">
        <label class="form-label small mb-1">Tháng / Năm</label>
        <input type="month" name="month" class="form-control" value="<?= e($filterMonth) ?>">
      </div>
      <div class="col-sm-6 col-lg-2">
        <label class="form-label small mb-1">Từ ngày</label>
        <input type="date" name="from" class="form-control" value="<?= e($fromDate) ?>">
      </div>
      <div class="col-sm-6 col-lg-2">
        <label class="form-label small mb-1">Đến ngày</label>
        <input type="date" name="to" class="form-control" value="<?= e($toDate) ?>">
      </div>
      <div class="col-12">
        <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-search"></i> Áp dụng bộ lọc</button>
        <a href="<?= url('/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm">Bỏ lọc</a>
      </div>
    </form>
    <div class="mt-3 d-flex flex-wrap gap-2">
      <span class="small text-muted me-1">Chi phí:</span>
      <?php foreach (EXPENSE_CATEGORIES as $cat): ?>
        <a href="<?= url('/expenses/index.php?month=' . $filterMonth . '&category=' . urlencode($cat)) ?>" class="badge bg-light text-dark border text-decoration-none"><?= e($cat) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card"><div class="text-muted small">Tổng doanh thu</div><div class="stat-value text-success"><?= money($periodRevenue) ?></div></div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card"><div class="text-muted small">Tổng chi phí</div><div class="stat-value text-danger"><?= money($periodExpense) ?></div></div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card"><div class="text-muted small">Lợi nhuận</div><div class="stat-value <?= $periodProfit >= 0 ? 'text-success' : 'text-danger' ?>"><?= money($periodProfit) ?></div></div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card"><div class="text-muted small">Thực thu tháng <?= e(substr($filterMonth, 5, 2) . '/' . substr($filterMonth, 0, 4)) ?></div><div class="stat-value"><?= money($periodRevenue) ?></div></div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card"><div class="text-muted small">Nợ tồn đọng</div><div class="stat-value text-danger" style="font-size:1.15rem;"><?= money($totalOwed) ?></div></div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card"><div class="text-muted small"><i class="bi bi-door-open"></i> Trống</div><div class="stat-value"><?= $vacantCount ?></div></div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card"><div class="text-muted small"><i class="bi bi-door-closed-fill"></i> Đang ở</div><div class="stat-value"><?= $occupiedCount ?></div></div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="stat-card"><div class="text-muted small">HĐ sắp hết hạn</div><div class="stat-value text-warning"><?= count($expiringContracts) ?></div></div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between">
        <span>Doanh thu thực thu</span>
        <a href="<?= url('/reports/index.php') ?>" class="small text-decoration-none">Xem chi tiết <i class="bi bi-arrow-right"></i></a>
      </div>
      <div class="card-body">
        <canvas id="revenueChart" height="220"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between">
        <span>Tỉ lệ lấp đầy</span>
        <a href="<?= url('/rooms/index.php') ?>" class="small text-decoration-none">Xem phòng <i class="bi bi-arrow-right"></i></a>
      </div>
      <div class="card-body">
        <div class="fs-1 fw-bold"><?= $occupancyRate ?>%</div>
        <div class="small text-muted mb-2"><?= $occupiedCount ?>/<?= $totalRooms ?> phòng đang ở</div>
        <div class="progress mb-3" style="height:8px;">
          <div class="progress-bar bg-success" style="width: <?= $occupancyRate ?>%"></div>
        </div>
        <ul class="list-unstyled small mb-0">
          <li><span class="status-dot bg-success"></span> Trống <span class="float-end"><?= $vacantCount ?></span></li>
          <li><span class="status-dot bg-primary"></span> Đang ở <span class="float-end"><?= $occupiedCount ?></span></li>
        </ul>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <span>Doanh thu theo ngày thanh toán và theo ngày phát sinh (<?= vndate($fromDate) ?> - <?= vndate($toDate) ?>)</span>
        <div class="small text-muted mt-1">"Theo ngày thanh toán": tiền được cộng vào đúng ngày thực nhận. "Theo ngày phát sinh": tổng tiền deal/kỳ được chia đều cho từng ngày ở/kỳ, không phụ thuộc ngày thanh toán.</div>
      </div>
      <div class="card-body">
        <canvas id="dailyRevenueChart" height="90"></canvas>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header">Hợp đồng sắp hết hạn (<?= $alertDays ?> ngày tới)</div>
      <div class="list-group list-group-flush" style="max-height:320px;overflow-y:auto;">
        <?php if (!$expiringContracts): ?>
          <div class="list-group-item text-muted small">Không có hợp đồng nào sắp hết hạn.</div>
        <?php endif; ?>
        <?php foreach ($expiringContracts as $c): ?>
          <a href="<?= url('/contracts/view.php?id=' . $c['id']) ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
            <div>
              <div class="fw-semibold"><?= e($c['room_code']) ?> - <?= e($c['lessee_name']) ?></div>
              <div class="small text-muted">Số HĐ <?= e($c['contract_code']) ?></div>
            </div>
            <span class="badge bg-warning">Còn <?= max(0, (int)((strtotime($c['end_date']) - strtotime($today)) / 86400)) ?> ngày</span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between">
        <span>Nhắc nhở</span>
        <a href="<?= url('/reminders/index.php') ?>" class="small text-decoration-none">Xem tất cả</a>
      </div>
      <div class="list-group list-group-flush" style="max-height:320px;overflow-y:auto;">
        <?php if (!$upcomingReminders): ?>
          <div class="list-group-item text-muted small">Không có nhắc nhở nào sắp tới.</div>
        <?php endif; ?>
        <?php foreach ($upcomingReminders as $r): ?>
          <div class="list-group-item">
            <div class="fw-semibold"><?= e($r['title']) ?></div>
            <div class="small text-muted">Hạn <?= vndate($r['due_date']) ?><?= $r['due_date'] < $today ? ' · <span class="text-danger">Quá hạn</span>' : '' ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('dailyRevenueChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode(array_map(fn($d) => vndate($d), $dayLabels)) ?>,
    datasets: [
      { label: 'Theo ngày thanh toán', data: <?= json_encode(array_values($cashByDay)) ?>, borderColor: '#2e7d32', backgroundColor: 'rgba(46,125,50,.15)', tension: 0.2, fill: true },
      { label: 'Theo ngày phát sinh', data: <?= json_encode(array_values($accrualByDay)) ?>, borderColor: '#f4511e', backgroundColor: 'rgba(244,81,30,.15)', tension: 0.2, fill: true },
    ]
  },
  options: {
    responsive: true,
    plugins: { legend: { position: 'bottom' } },
    scales: { y: { beginAtZero: true, ticks: { callback: v => new Intl.NumberFormat('vi-VN').format(v) } } }
  }
});

new Chart(document.getElementById('revenueChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($months) ?>,
    datasets: [
      { label: 'Doanh thu', data: <?= json_encode($chartRevenue) ?>, backgroundColor: '#f4511e' },
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
