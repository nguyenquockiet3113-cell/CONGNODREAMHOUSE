<?php
require_once __DIR__ . '/config/config.php';
require_login();

$today = date('Y-m-d');

$filterZone = trim($_GET['zone'] ?? '');
$filterRoomCode = trim($_GET['room_code'] ?? '');
$filterMonth = trim($_GET['month'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $filterMonth)) $filterMonth = date('Y-m');
$filterBank = $_GET['bank_account_id'] ?? '';
$alertDays = (int)($_GET['alert_days'] ?? 21);
if ($alertDays <= 0) $alertDays = 21;

$monthFirst = $filterMonth . '-01';
$monthLast = date('Y-m-d', strtotime($monthFirst . ' +1 month -1 day'));
$fromDate = $_GET['from'] ?? $monthFirst;
$toDate = $_GET['to'] ?? $monthLast;

$zones = $pdo->query("SELECT DISTINCT zone FROM rooms WHERE zone IS NOT NULL AND zone != '' ORDER BY zone")->fetchAll(PDO::FETCH_COLUMN);
$bankAccounts = $pdo->query('SELECT * FROM bank_accounts ORDER BY bank_name')->fetchAll();

function apply_room_filters(string $sql, array &$params, string $roomAlias, string $zone, string $roomCode): string
{
    if ($zone !== '') { $sql .= " AND $roomAlias.zone = ?"; $params[] = $zone; }
    if ($roomCode !== '') { $sql .= " AND $roomAlias.room_code LIKE ?"; $params[] = "%$roomCode%"; }
    return $sql;
}

// --- Thong ke phong (ap dung filter khu vuc/ma can, khong theo ngay) ---
$roomSql = 'SELECT status, COUNT(*) c FROM rooms WHERE 1=1';
$roomParams = [];
$roomSql = apply_room_filters($roomSql, $roomParams, 'rooms', $filterZone, $filterRoomCode);
$roomSql .= ' GROUP BY status';
$stmt = $pdo->prepare($roomSql);
$stmt->execute($roomParams);
$roomStats = ['trong' => 0, 'dang_thue' => 0, 'bao_tri' => 0];
foreach ($stmt->fetchAll() as $row) { $roomStats[$row['status']] = (int)$row['c']; }
$totalRooms = array_sum($roomStats);
$occupancyRate = $totalRooms > 0 ? round($roomStats['dang_thue'] / $totalRooms * 100, 1) : 0;

// --- Doanh thu dai han da thu trong khoang loc (theo paid_date) ---
$sql = "SELECT COALESCE(SUM(i.paid_amount),0) s FROM invoices i JOIN rooms r ON r.id = i.room_id
        WHERE i.paid_date IS NOT NULL AND i.paid_date BETWEEN ? AND ?";
$params = [$fromDate, $toDate];
$sql = apply_room_filters($sql, $params, 'r', $filterZone, $filterRoomCode);
if ($filterBank !== '') { $sql .= ' AND i.bank_account_id = ?'; $params[] = $filterBank; }
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$longTermRevenue = (float)$stmt->fetch()['s'];

// --- Doanh thu ngan han da thu trong khoang loc (theo paid_date) ---
$sql = "SELECT COALESCE(SUM(b.total_amount),0) s FROM bookings b JOIN rooms r ON r.id = b.room_id
        WHERE b.payment_status = 'paid' AND b.paid_date IS NOT NULL AND b.paid_date BETWEEN ? AND ?";
$params = [$fromDate, $toDate];
$sql = apply_room_filters($sql, $params, 'r', $filterZone, $filterRoomCode);
if ($filterBank !== '') { $sql .= ' AND b.bank_account_id = ?'; $params[] = $filterBank; }
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$shortTermRevenue = (float)$stmt->fetch()['s'];

// --- Chi phi trong khoang loc ---
$sql = "SELECT COALESCE(SUM(ex.amount),0) s FROM expenses ex LEFT JOIN rooms r ON r.id = ex.room_id
        WHERE ex.expense_date BETWEEN ? AND ?";
$params = [$fromDate, $toDate];
if ($filterZone !== '') { $sql .= ' AND r.zone = ?'; $params[] = $filterZone; }
if ($filterRoomCode !== '') { $sql .= ' AND r.room_code LIKE ?'; $params[] = "%$filterRoomCode%"; }
if ($filterBank !== '') { $sql .= ' AND ex.bank_account_id = ?'; $params[] = $filterBank; }
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$periodExpense = (float)$stmt->fetch()['s'];

$periodRevenue = $longTermRevenue + $shortTermRevenue;
$periodProfit = $periodRevenue - $periodExpense;

// --- No ton dong (hoa don chua thu du, khong phu thuoc khoang ngay) ---
$sql = "SELECT COALESCE(SUM(i.total_amount - i.paid_amount),0) s FROM invoices i JOIN rooms r ON r.id = i.room_id WHERE i.status != 'paid'";
$params = [];
$sql = apply_room_filters($sql, $params, 'r', $filterZone, $filterRoomCode);
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$totalOwed = (float)$stmt->fetch()['s'];

// --- Hop dong sap het han ---
$sql = "SELECT c.*, r.room_code, t.full_name AS tenant_name FROM contracts c
        JOIN rooms r ON r.id = c.room_id JOIN tenants t ON t.id = c.tenant_id
        WHERE c.status = 'active' AND c.end_date >= ? AND c.end_date <= ?";
$params = [$today, date('Y-m-d', strtotime("+$alertDays days"))];
$sql = apply_room_filters($sql, $params, 'r', $filterZone, $filterRoomCode);
$sql .= ' ORDER BY c.end_date ASC';
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$expiringContracts = $stmt->fetchAll();

// --- Hoa don chua thanh toan (danh sach) ---
$sql = "SELECT i.*, r.room_code, t.full_name AS tenant_name FROM invoices i
        JOIN rooms r ON r.id = i.room_id JOIN contracts c ON c.id = i.contract_id JOIN tenants t ON t.id = c.tenant_id
        WHERE i.status != 'paid'";
$params = [];
$sql = apply_room_filters($sql, $params, 'r', $filterZone, $filterRoomCode);
$sql .= ' ORDER BY i.due_date ASC LIMIT 8';
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$unpaidInvoices = $stmt->fetchAll();

// --- Ticket bao tri dang mo ---
$sql = "SELECT tk.*, r.room_code FROM tickets tk JOIN rooms r ON r.id = tk.room_id WHERE tk.status != 'resolved'";
$params = [];
$sql = apply_room_filters($sql, $params, 'r', $filterZone, $filterRoomCode);
$sql .= ' ORDER BY tk.created_at DESC LIMIT 8';
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$openTickets = $stmt->fetchAll();
$openTicketsCount = count($openTickets);

// --- Nhac nho sap toi (7 ngay, chua hoan thanh) ---
$stmt = $pdo->prepare("SELECT * FROM reminders WHERE is_done = 0 AND due_date <= ? ORDER BY due_date ASC LIMIT 6");
$stmt->execute([date('Y-m-d', strtotime('+7 days'))]);
$upcomingReminders = $stmt->fetchAll();

// --- Bieu do 6 thang gan nhat (thuc thu) ---
$months = [];
for ($i = 5; $i >= 0; $i--) { $months[] = date('Y-m', strtotime("-$i months")); }
$chartRevenue = [];
$chartExpense = [];
foreach ($months as $m) {
    $mf = $m . '-01'; $ml = date('Y-m-d', strtotime($mf . ' +1 month -1 day'));

    $s1 = $pdo->prepare("SELECT COALESCE(SUM(paid_amount),0) s FROM invoices WHERE paid_date BETWEEN ? AND ?");
    $s1->execute([$mf, $ml]);
    $lt = (float)$s1->fetch()['s'];

    $s2 = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) s FROM bookings WHERE payment_status='paid' AND paid_date BETWEEN ? AND ?");
    $s2->execute([$mf, $ml]);
    $st = (float)$s2->fetch()['s'];

    $s3 = $pdo->prepare("SELECT COALESCE(SUM(amount),0) s FROM expenses WHERE expense_date BETWEEN ? AND ?");
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
      <div class="col-sm-6 col-lg-2">
        <label class="form-label small mb-1">Khu vực</label>
        <select name="zone" class="form-select">
          <option value="">-- Tất cả --</option>
          <?php foreach ($zones as $z): ?>
            <option value="<?= e($z) ?>" <?= $filterZone === $z ? 'selected' : '' ?>><?= e($z) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-6 col-lg-2">
        <label class="form-label small mb-1">Mã căn</label>
        <input type="text" name="room_code" class="form-control" placeholder="VD: A0101" value="<?= e($filterRoomCode) ?>">
      </div>
      <div class="col-sm-6 col-lg-2">
        <label class="form-label small mb-1">Tháng / Năm</label>
        <input type="month" name="month" class="form-control" value="<?= e($filterMonth) ?>">
      </div>
      <div class="col-sm-6 col-lg-2">
        <label class="form-label small mb-1">TK Ngân hàng</label>
        <select name="bank_account_id" class="form-select">
          <option value="">-- Tất cả --</option>
          <?php foreach ($bankAccounts as $ba): ?>
            <option value="<?= $ba['id'] ?>" <?= (string)$filterBank === (string)$ba['id'] ? 'selected' : '' ?>><?= e($ba['bank_name']) ?></option>
          <?php endforeach; ?>
        </select>
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
  <div class="col-sm-6 col-lg-2">
    <div class="stat-card"><div class="text-muted small">Nợ tồn đọng</div><div class="stat-value text-danger" style="font-size:1.15rem;"><?= money($totalOwed) ?></div></div>
  </div>
  <div class="col-sm-6 col-lg-2">
    <div class="stat-card"><div class="text-muted small"><i class="bi bi-door-open"></i> Trống</div><div class="stat-value"><?= $roomStats['trong'] ?></div></div>
  </div>
  <div class="col-sm-6 col-lg-2">
    <div class="stat-card"><div class="text-muted small"><i class="bi bi-door-closed-fill"></i> Đang ở</div><div class="stat-value"><?= $roomStats['dang_thue'] ?></div></div>
  </div>
  <div class="col-sm-6 col-lg-2">
    <div class="stat-card"><div class="text-muted small"><i class="bi bi-tools"></i> Đang sửa</div><div class="stat-value"><?= $roomStats['bao_tri'] ?></div></div>
  </div>
  <div class="col-sm-6 col-lg-2">
    <div class="stat-card"><div class="text-muted small">HĐ sắp hết hạn</div><div class="stat-value text-warning"><?= count($expiringContracts) ?></div></div>
  </div>
  <div class="col-sm-6 col-lg-2">
    <div class="stat-card"><div class="text-muted small"><i class="bi bi-tools"></i> Ticket đang mở</div><div class="stat-value <?= $openTicketsCount > 0 ? 'text-warning' : '' ?>"><?= $openTicketsCount ?></div></div>
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
        <div class="small text-muted mb-2"><?= $roomStats['dang_thue'] ?>/<?= $totalRooms ?> phòng đang ở</div>
        <div class="progress mb-3" style="height:8px;">
          <div class="progress-bar bg-success" style="width: <?= $occupancyRate ?>%"></div>
        </div>
        <ul class="list-unstyled small mb-0">
          <li><span class="status-dot bg-success"></span> Trống <span class="float-end"><?= $roomStats['trong'] ?></span></li>
          <li><span class="status-dot bg-primary"></span> Đang ở <span class="float-end"><?= $roomStats['dang_thue'] ?></span></li>
          <li><span class="status-dot bg-warning"></span> Đang sửa <span class="float-end"><?= $roomStats['bao_tri'] ?></span></li>
        </ul>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header">Hợp đồng sắp hết hạn (<?= $alertDays ?> ngày tới)</div>
      <div class="list-group list-group-flush" style="max-height:280px;overflow-y:auto;">
        <?php if (!$expiringContracts): ?>
          <div class="list-group-item text-muted small">Không có hợp đồng nào sắp hết hạn.</div>
        <?php endif; ?>
        <?php foreach ($expiringContracts as $c): ?>
          <a href="<?= url('/contracts/view.php?id=' . $c['id']) ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
            <div>
              <div class="fw-semibold"><?= e($c['room_code']) ?> - <?= e($c['tenant_name']) ?></div>
              <div class="small text-muted">Mã HĐ <?= e($c['contract_code']) ?></div>
            </div>
            <span class="badge bg-warning">Còn <?= max(0, (int)((strtotime($c['end_date']) - strtotime($today)) / 86400)) ?> ngày</span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between">
        <span>Ticket bảo trì đang mở</span>
        <a href="<?= url('/tickets/index.php') ?>" class="small text-decoration-none">Xem tất cả</a>
      </div>
      <div class="list-group list-group-flush" style="max-height:280px;overflow-y:auto;">
        <?php if (!$openTickets): ?>
          <div class="list-group-item text-muted small">Không có ticket nào đang mở.</div>
        <?php endif; ?>
        <?php foreach ($openTickets as $t): ?>
          <a href="<?= url('/tickets/form.php?id=' . $t['id']) ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
            <div>
              <div class="fw-semibold"><?= e($t['room_code']) ?> - <?= e($t['title']) ?></div>
              <div class="small text-muted"><?= vndate(substr($t['created_at'], 0, 10)) ?></div>
            </div>
            <span class="badge bg-<?= badge_class($t['status']) ?>"><?= e(TICKET_STATUS_LABELS[$t['status']] ?? '') ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between">
        <span>Nhắc nhở</span>
        <a href="<?= url('/reminders/index.php') ?>" class="small text-decoration-none">Xem tất cả</a>
      </div>
      <div class="list-group list-group-flush" style="max-height:280px;overflow-y:auto;">
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

<div class="card mb-3">
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
