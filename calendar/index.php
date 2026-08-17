<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}
$zoneFilter = trim($_GET['zone'] ?? '');

$firstDay = $month . '-01';
$daysInMonth = (int)date('t', strtotime($firstDay));
$lastDay = $month . '-' . str_pad((string)$daysInMonth, 2, '0', STR_PAD_LEFT);

$prevMonth = date('Y-m', strtotime($firstDay . ' -1 month'));
$nextMonth = date('Y-m', strtotime($firstDay . ' +1 month'));

$roomSql = 'SELECT * FROM rooms WHERE 1=1';
$roomParams = [];
if ($zoneFilter !== '') {
    $roomSql .= ' AND zone = ?';
    $roomParams[] = $zoneFilter;
}
$roomSql .= ' ORDER BY zone, room_code';
$roomStmt = $pdo->prepare($roomSql);
$roomStmt->execute($roomParams);
$rooms = $roomStmt->fetchAll();

$zones = $pdo->query("SELECT DISTINCT zone FROM rooms WHERE zone IS NOT NULL AND zone != '' ORDER BY zone")->fetchAll(PDO::FETCH_COLUMN);

// map: room_id => [day => ['type'=>..,'label'=>..]]
$cellMap = [];

$contractStmt = $pdo->prepare(
    "SELECT c.room_id, c.start_date, c.end_date, t.full_name FROM contracts c JOIN tenants t ON t.id = c.tenant_id
     WHERE c.status = 'active' AND c.start_date <= ? AND c.end_date >= ?"
);
$contractStmt->execute([$lastDay, $firstDay]);
foreach ($contractStmt->fetchAll() as $c) {
    $from = max($c['start_date'], $firstDay);
    $to = min($c['end_date'], $lastDay);
    $d = strtotime($from);
    $end = strtotime($to);
    while ($d <= $end) {
        $day = (int)date('j', $d);
        $cellMap[$c['room_id']][$day] = ['type' => 'contract', 'label' => 'HĐ dài hạn: ' . $c['full_name']];
        $d = strtotime('+1 day', $d);
    }
}

$bookingStmt = $pdo->prepare(
    "SELECT room_id, checkin_date, checkout_date, guest_name FROM bookings
     WHERE status != 'cancelled' AND checkin_date <= ? AND checkout_date >= ?"
);
$bookingStmt->execute([$lastDay, $firstDay]);
foreach ($bookingStmt->fetchAll() as $b) {
    $from = max($b['checkin_date'], $firstDay);
    $toExclusive = min($b['checkout_date'], date('Y-m-d', strtotime($lastDay . ' +1 day')));
    $d = strtotime($from);
    $end = strtotime($toExclusive);
    while ($d < $end) {
        $day = (int)date('j', $d);
        $cellMap[$b['room_id']][$day] = ['type' => 'booking', 'label' => 'Khách ngắn hạn: ' . $b['guest_name']];
        $d = strtotime('+1 day', $d);
    }
}

$pageTitle = 'Lịch phòng';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="mb-0"><i class="bi bi-calendar3"></i> Lịch phòng - <?= e($month) ?></h4>
  <div class="d-flex gap-2">
    <a href="<?= url('/calendar/index.php?month=' . $prevMonth . '&zone=' . urlencode($zoneFilter)) ?>" class="btn btn-outline-secondary"><i class="bi bi-chevron-left"></i></a>
    <a href="<?= url('/calendar/index.php?month=' . date('Y-m') . '&zone=' . urlencode($zoneFilter)) ?>" class="btn btn-outline-secondary">Tháng này</a>
    <a href="<?= url('/calendar/index.php?month=' . $nextMonth . '&zone=' . urlencode($zoneFilter)) ?>" class="btn btn-outline-secondary"><i class="bi bi-chevron-right"></i></a>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form class="row g-2 align-items-end">
      <input type="hidden" name="month" value="<?= e($month) ?>">
      <div class="col-sm-4">
        <label class="form-label small mb-1">Khu vực</label>
        <select name="zone" class="form-select" onchange="this.form.submit()">
          <option value="">-- Tất cả khu vực --</option>
          <?php foreach ($zones as $z): ?>
            <option value="<?= e($z) ?>" <?= $zoneFilter === $z ? 'selected' : '' ?>><?= e($z) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-8 d-flex align-items-center gap-3 small text-muted">
        <span><span class="cal-dot bg-success"></span> Trống</span>
        <span><span class="cal-dot bg-primary"></span> Hợp đồng dài hạn</span>
        <span><span class="cal-dot bg-warning"></span> Khách ngắn hạn</span>
        <span><span class="cal-dot bg-secondary"></span> Bảo trì</span>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-bordered table-sm mb-0 calendar-table">
      <thead>
        <tr>
          <th style="min-width:140px;">Phòng</th>
          <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
            <th class="text-center"><?= $d ?></th>
          <?php endfor; ?>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rooms): ?>
          <tr><td colspan="<?= $daysInMonth + 1 ?>" class="text-center text-muted py-4">Không có phòng.</td></tr>
        <?php endif; ?>
        <?php foreach ($rooms as $r): ?>
          <tr>
            <td class="fw-semibold"><?= e($r['room_code']) ?><?php if ($r['zone']): ?><div class="small text-muted"><?= e($r['zone']) ?></div><?php endif; ?></td>
            <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
              <?php
                if ($r['status'] === 'bao_tri' && empty($cellMap[$r['id']][$d])) {
                    $cls = 'bg-secondary'; $title = 'Bảo trì';
                } elseif (!empty($cellMap[$r['id']][$d])) {
                    $cell = $cellMap[$r['id']][$d];
                    $cls = $cell['type'] === 'contract' ? 'bg-primary' : 'bg-warning';
                    $title = $cell['label'];
                } else {
                    $cls = 'bg-success-subtle'; $title = 'Trống';
                }
              ?>
              <td class="cal-cell <?= $cls ?>" title="<?= e($title) ?>"></td>
            <?php endfor; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
