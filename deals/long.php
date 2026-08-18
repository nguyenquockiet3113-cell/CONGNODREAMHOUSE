<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/deal_helpers.php';
require_login();

$search = trim($_GET['q'] ?? '');
$month = trim($_GET['month'] ?? '');
$periodStatusFilter = trim($_GET['period_status'] ?? '');
$today = date('Y-m-d');

$sql = "SELECT * FROM deals WHERE deal_type = 'dai_han'";
$params = [];
if ($search !== '') {
    $sql .= ' AND (guest_name LIKE ? OR room_code LIKE ?)';
    $params = array_merge($params, ["%$search%", "%$search%"]);
}
$sql .= ' ORDER BY checkin_date DESC, id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$deals = $stmt->fetchAll();

// Lay toan bo ky cua cac deal nay, gom nhom theo deal_id
$periodsByDeal = [];
if ($deals) {
    $dealIds = array_column($deals, 'id');
    $placeholders = implode(',', array_fill(0, count($dealIds), '?'));
    $pStmt = $pdo->prepare("SELECT * FROM deal_periods WHERE deal_id IN ($placeholders) ORDER BY period_index");
    $pStmt->execute($dealIds);
    foreach ($pStmt->fetchAll() as $p) {
        $periodsByDeal[$p['deal_id']][] = $p;
    }
}

// Neu co loc theo thang, chi giu lai cac deal co it nhat 1 ky trong thang do
if ($month !== '') {
    $deals = array_filter($deals, function ($d) use ($periodsByDeal, $month) {
        foreach ($periodsByDeal[$d['id']] ?? [] as $p) {
            if ($month >= substr($p['period_start'], 0, 7) && $month <= substr($p['period_end'], 0, 7)) return true;
        }
        return false;
    });
}

// Xac dinh ky sap toi/qua han chua thanh toan du cho tung deal (de nhac nho)
$nextDueByDeal = [];
foreach ($deals as $d) {
    foreach ($periodsByDeal[$d['id']] ?? [] as $p) {
        $periodTotal = (float)$p['rent_amount'] + (float)$p['deposit_amount'] + (float)$p['utilities_amount'];
        $remain = $periodTotal - (float)$p['paid_amount'];
        if ($remain > 0.5) {
            $days = (int)round((strtotime($p['period_end']) - strtotime($today)) / 86400);
            $nextDueByDeal[$d['id']] = [
                'period_index' => (int)$p['period_index'],
                'period_end' => $p['period_end'],
                'remain' => $remain,
                'days' => $days,
                'status' => $days < 0 ? 'overdue' : ($days <= 5 ? 'due_soon' : 'upcoming'),
            ];
            break; // ky som nhat con no
        }
    }
}

// Loc theo trang thai ky thanh toan
if ($periodStatusFilter !== '') {
    $deals = array_filter($deals, function ($d) use ($nextDueByDeal, $periodStatusFilter) {
        $due = $nextDueByDeal[$d['id']] ?? null;
        if ($periodStatusFilter === 'paid') return $due === null;
        return $due !== null && $due['status'] === $periodStatusFilter;
    });
}

$overdueCount = 0; $dueSoonCount = 0;
foreach ($nextDueByDeal as $due) {
    if ($due['status'] === 'overdue') $overdueCount++;
    elseif ($due['status'] === 'due_soon') $dueSoonCount++;
}

$rooms = $pdo->query('SELECT room_code, zone, bedrooms FROM rooms ORDER BY room_code')->fetchAll();

$sumTotal = 0; $sumPaid = 0; $totalPeriods = 0;
$dealStats = [];
foreach ($deals as $d) {
    $periods = $periodsByDeal[$d['id']] ?? [];
    $total = 0; $paid = 0;
    foreach ($periods as $p) {
        $total += (float)$p['rent_amount'] + (float)$p['deposit_amount'] + (float)$p['utilities_amount'];
        $paid += (float)$p['paid_amount'];
    }
    $dealStats[$d['id']] = ['total' => $total, 'paid' => $paid, 'count' => count($periods)];
    $sumTotal += $total; $sumPaid += $paid; $totalPeriods += count($periods);
}

$pageTitle = 'Doanh thu dài hạn';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="mb-0"><i class="bi bi-receipt"></i> Doanh thu dài hạn</h4>
  <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addLongDealModal"><i class="bi bi-plus-lg"></i> Thêm deal</button>
</div>

<?php if ($overdueCount > 0 || $dueSoonCount > 0): ?>
  <div class="alert <?= $overdueCount > 0 ? 'alert-danger' : 'alert-warning' ?> d-flex align-items-center gap-2 flex-wrap">
    <i class="bi bi-bell-fill"></i>
    <?php if ($overdueCount > 0): ?>
      <span><strong><?= $overdueCount ?></strong> deal đã <strong>quá hạn</strong> thanh toán kỳ.</span>
    <?php endif; ?>
    <?php if ($dueSoonCount > 0): ?>
      <span><strong><?= $dueSoonCount ?></strong> deal sắp đến hạn thanh toán kỳ (trong 5 ngày tới).</span>
    <?php endif; ?>
  </div>
<?php endif; ?>

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
    <div class="stat-card"><div class="text-muted small">Số deal / kỳ</div><div class="stat-value"><?= count($deals) ?> / <?= $totalPeriods ?></div></div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form class="row g-2 align-items-end">
      <div class="col-sm-4">
        <input type="text" name="q" class="form-control" placeholder="Tên khách, mã phòng..." value="<?= e($search) ?>">
      </div>
      <div class="col-sm-3">
        <input type="month" name="month" class="form-control" value="<?= e($month) ?>" title="Chỉ hiện deal có kỳ rơi vào tháng này">
      </div>
      <div class="col-sm-3">
        <select name="period_status" class="form-select">
          <option value="">-- Trạng thái kỳ: Tất cả --</option>
          <option value="overdue" <?= $periodStatusFilter === 'overdue' ? 'selected' : '' ?>>Quá hạn</option>
          <option value="due_soon" <?= $periodStatusFilter === 'due_soon' ? 'selected' : '' ?>>Sắp đến hạn (≤5 ngày)</option>
          <option value="upcoming" <?= $periodStatusFilter === 'upcoming' ? 'selected' : '' ?>>Còn nợ, chưa đến hạn</option>
          <option value="paid" <?= $periodStatusFilter === 'paid' ? 'selected' : '' ?>>Đã thanh toán đủ</option>
        </select>
      </div>
      <div class="col-sm-2">
        <button class="btn btn-outline-secondary w-100"><i class="bi bi-search"></i> Lọc</button>
      </div>
    </form>
  </div>
</div>

<?php if (!$deals): ?>
  <div class="card"><div class="text-center text-muted py-5">Chưa có deal dài hạn nào.</div></div>
<?php endif; ?>

<?php
$groupedDeals = [];
foreach ($deals as $d) {
    $zoneKey = $d['zone'] !== null && $d['zone'] !== '' ? $d['zone'] : 'Chưa phân khu vực';
    $groupedDeals[$zoneKey][] = $d;
}
?>

<?php foreach ($groupedDeals as $zoneName => $zoneDeals): ?>
  <div class="deal-zone-banner"><?= e(mb_strtoupper($zoneName)) ?></div>
  <div class="card mb-4">
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead>
          <tr>
            <th>Phòng</th>
            <th>Khách</th>
            <th>Thời hạn</th>
            <th>Số kỳ</th>
            <th class="text-end">Tổng cộng</th>
            <th class="text-end">Đã thu</th>
            <th class="text-end">Còn lại</th>
            <th>TK nhận</th>
            <th>Hạn thanh toán</th>
            <th>Tình trạng</th>
            <th class="text-end">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($zoneDeals as $d): ?>
            <?php $stat = $dealStats[$d['id']]; $remain = $stat['total'] - $stat['paid']; $due = $nextDueByDeal[$d['id']] ?? null; ?>
            <tr>
              <td class="fw-semibold"><?= e($d['room_code']) ?></td>
              <td><?= e($d['guest_name']) ?></td>
              <td><?= vndate($d['checkin_date']) ?> - <?= vndate($d['checkout_date']) ?></td>
              <td><?= $stat['count'] ?> kỳ</td>
              <td class="text-end"><?= money($stat['total']) ?></td>
              <td class="text-end text-success"><?= money($stat['paid']) ?></td>
              <td class="text-end <?= $remain > 0 ? 'text-danger' : '' ?>"><?= money($remain) ?></td>
              <td class="small"><?= $d['receiving_account'] ? e($d['receiving_account']) : '<span class="text-muted">—</span>' ?></td>
              <td class="small">
                <?php if (!$due): ?>
                  <span class="text-success"><i class="bi bi-check-circle"></i> Đã TT đủ</span>
                <?php elseif ($due['status'] === 'overdue'): ?>
                  <span class="text-danger fw-semibold">Quá hạn <?= abs($due['days']) ?> ngày (kỳ <?= $due['period_index'] ?>)</span>
                <?php elseif ($due['status'] === 'due_soon'): ?>
                  <span class="text-warning fw-semibold">Còn <?= $due['days'] ?> ngày (kỳ <?= $due['period_index'] ?>)</span>
                <?php else: ?>
                  <span class="text-muted">Hạn <?= vndate($due['period_end']) ?> (kỳ <?= $due['period_index'] ?>)</span>
                <?php endif; ?>
              </td>
              <td><span class="badge bg-<?= $d['status'] === 'active' ? 'primary' : ($d['status'] === 'ended' ? 'secondary' : 'danger') ?>"><?= e(DEAL_STATUS_LABELS[$d['status']] ?? $d['status']) ?></span></td>
              <td class="text-end">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#periodModal<?= $d['id'] ?>"><i class="bi bi-eye"></i> Chi tiết</button>
                <a href="<?= url('/deals/form.php?id=' . $d['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endforeach; ?>

<?php foreach ($deals as $d): ?>
  <?php $periods = $periodsByDeal[$d['id']] ?? []; ?>
  <div class="modal fade" id="periodModal<?= $d['id'] ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><?= e($d['room_code']) ?> - <?= e($d['guest_name']) ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead>
              <tr>
                <th>Kỳ</th><th>Từ - đến</th><th class="text-end">Thuê</th><th class="text-end">Cọc</th>
                <th class="text-end">Điện</th><th class="text-end">Nước</th><th class="text-end">Phí QL</th>
                <th class="text-end">Internet</th><th class="text-end">Vệ sinh</th><th class="text-end">Xe</th><th class="text-end">Phí khác</th>
                <th class="text-end">Tổng cần TT</th><th class="text-end">Đã TT</th><th class="text-end">Còn lại</th><th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($periods as $p): ?>
                <?php $pt = (float)$p['rent_amount'] + (float)$p['deposit_amount'] + (float)$p['utilities_amount']; $pr = $pt - (float)$p['paid_amount']; ?>
                <tr>
                  <td><?= e(deal_period_label((int)$p['period_index'], $p['period_start'])) ?></td>
                  <td><?= vndate($p['period_start']) ?> - <?= vndate($p['period_end']) ?></td>
                  <td class="text-end"><?= money($p['rent_amount']) ?></td>
                  <td class="text-end"><?= $p['deposit_amount'] > 0 ? money($p['deposit_amount']) : '' ?></td>
                  <?php
                    $periodDbCols2 = ['electricity' => 'electricity_amount', 'water' => 'water_amount', 'management' => 'management_fee_amount', 'internet' => 'internet_amount', 'cleaning' => 'cleaning_fee_amount', 'vehicle' => 'vehicle_fee_amount', 'other' => 'other_fee_amount'];
                    $selfPaidSet2 = array_filter(explode(',', $p['self_paid_items'] ?? ''));
                  ?>
                  <?php foreach ($periodDbCols2 as $feeKey => $col): ?>
                    <td class="text-end<?= in_array($feeKey, $selfPaidSet2, true) ? ' text-muted' : '' ?>">
                      <?= money($p[$col] ?? 0) ?><?= in_array($feeKey, $selfPaidSet2, true) ? ' <span title="Khách tự đóng">*</span>' : '' ?>
                    </td>
                  <?php endforeach; ?>
                  <td class="text-end fw-semibold"><?= money($pt) ?></td>
                  <td class="text-end text-success"><?= money($p['paid_amount']) ?></td>
                  <td class="text-end <?= $pr > 0 ? 'text-danger' : '' ?>"><?= money($pr) ?></td>
                  <td class="text-end"><a href="<?= url('/deals/bill.php?deal_id=' . $d['id'] . '&period_id=' . $p['id']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Xem/in bill"><i class="bi bi-printer"></i></a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          </div>
        </div>
        <div class="modal-footer">
          <a href="<?= url('/deals/form.php?id=' . $d['id']) ?>" class="btn btn-primary btn-sm"><i class="bi bi-pencil"></i> Sửa chi tiết từng kỳ</a>
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Đóng</button>
        </div>
      </div>
    </div>
  </div>
<?php endforeach; ?>

<div class="modal fade" id="addLongDealModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="post" action="<?= url('/deals/save_long.php') ?>" id="longDealForm">
        <?= csrf_field() ?>
        <input type="hidden" name="bedrooms" id="lr_bedrooms">
        <input type="hidden" name="zone" id="lr_zone">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-receipt"></i> Thêm deal dài hạn</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div id="lr_overlapWarning" class="alert alert-warning py-2" style="display:none;"></div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Mã căn hộ *</label>
              <input type="text" name="room_code" id="lr_room_code" class="form-control" list="longRoomList" required>
              <datalist id="longRoomList">
                <?php foreach ($rooms as $r): ?><option value="<?= e($r['room_code']) ?>"><?php endforeach; ?>
              </datalist>
            </div>
            <div class="col-md-6">
              <label class="form-label">Tên Sale *</label>
              <input type="text" name="guest_name" id="lr_guest_name" class="form-control" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">Quy đổi sang ngày</label>
              <input type="text" id="lr_days_display" class="form-control" disabled value="0 ngày">
            </div>
            <div class="col-md-3">
              <label class="form-label">Quy đổi sang tháng</label>
              <input type="text" id="lr_months_display" class="form-control" disabled value="0 tháng">
            </div>
            <div class="col-md-3">
              <label class="form-label">Check-in *</label>
              <input type="date" name="checkin_date" id="lr_checkin" class="form-control" required value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Check-out *</label>
              <input type="date" name="checkout_date" id="lr_checkout" class="form-control" required value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Giá (đ/tháng)</label>
              <input type="number" step="1000" name="price_per_unit" id="lr_price" class="form-control" value="0">
            </div>
            <div class="col-md-3">
              <label class="form-label">Tiền cọc (đ)</label>
              <input type="number" step="1000" name="deposit_amount" id="lr_deposit" class="form-control" value="0">
              <div class="form-text">Tự ghi nhận là đã thanh toán khi lưu.</div>
            </div>
            <div class="col-md-3 d-flex align-items-center gap-2 mt-4">
              <div class="form-check">
                <input type="checkbox" name="apply_vat" id="lr_apply_vat" class="form-check-input">
                <label class="form-check-label" for="lr_apply_vat">Xuất hóa đơn VAT</label>
              </div>
            </div>
            <div class="col-md-3" id="lr_vat_percent_wrap" style="display:none;">
              <label class="form-label">% VAT</label>
              <input type="number" step="0.1" name="vat_percent" id="lr_vat_percent" class="form-control" value="10">
            </div>
            <div class="col-md-6">
              <label class="form-label">Tổng cộng tiền cần phải thanh toán</label>
              <input type="text" id="lr_total_display" class="form-control fw-semibold" disabled value="0 đ">
            </div>
            <div class="col-md-8">
              <label class="form-label">Note (ghi chú)</label>
              <input type="text" name="note" class="form-control">
            </div>
            <div class="col-md-4">
              <label class="form-label">Tình trạng</label>
              <select name="status" class="form-select">
                <?php foreach (DEAL_STATUS_LABELS as $k => $v): ?>
                  <option value="<?= e($k) ?>" <?= $k === 'active' ? 'selected' : '' ?>><?= e($v) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
          <button type="submit" class="btn btn-success"><i class="bi bi-check-lg"></i> Lưu deal</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
var lrRoomMap = <?= json_encode(array_map(fn($r) => ['zone' => $r['zone'], 'bedrooms' => $r['bedrooms']], array_column($rooms, null, 'room_code'))) ?>;

function lrFmt(n) { return new Intl.NumberFormat('vi-VN').format(Math.round(n)) + ' đ'; }

function lrApplyRoomInfo() {
  var info = lrRoomMap[document.getElementById('lr_room_code').value];
  document.getElementById('lr_zone').value = info ? (info.zone || '') : '';
  document.getElementById('lr_bedrooms').value = info ? (info.bedrooms || '') : '';
}
document.getElementById('lr_room_code').addEventListener('input', lrApplyRoomInfo);
document.getElementById('lr_room_code').addEventListener('change', lrApplyRoomInfo);

function lrRecalc() {
  var checkin = new Date(document.getElementById('lr_checkin').value);
  var checkout = new Date(document.getElementById('lr_checkout').value);
  var nights = 0;
  if (checkin && checkout && checkout > checkin) {
    nights = Math.round((checkout - checkin) / 86400000);
  }
  document.getElementById('lr_days_display').value = nights + ' ngày';

  var fullMonths = Math.floor(nights / 30);
  var remainderDays = nights % 30;
  var monthsLabel = remainderDays > 0 ? (fullMonths + ' tháng ' + remainderDays + ' ngày') : (fullMonths + ' tháng');
  document.getElementById('lr_months_display').value = monthsLabel;

  var price = parseFloat(document.getElementById('lr_price').value) || 0;
  var rentTotal = fullMonths * price + (remainderDays > 0 ? Math.round(price * remainderDays / 30) : 0);

  var applyVat = document.getElementById('lr_apply_vat').checked;
  var vatPercent = applyVat ? (parseFloat(document.getElementById('lr_vat_percent').value) || 0) : 0;
  var vatAmount = applyVat ? Math.round(rentTotal * vatPercent / 100) : 0;

  var deposit = parseFloat(document.getElementById('lr_deposit').value) || 0;

  document.getElementById('lr_total_display').value = lrFmt(rentTotal + vatAmount + deposit);
}

['lr_checkin', 'lr_checkout', 'lr_price', 'lr_deposit', 'lr_vat_percent'].forEach(function (id) {
  document.getElementById(id).addEventListener('input', lrRecalc);
});
document.getElementById('lr_apply_vat').addEventListener('change', function () {
  document.getElementById('lr_vat_percent_wrap').style.display = this.checked ? '' : 'none';
  lrRecalc();
});
document.getElementById('addLongDealModal').addEventListener('shown.bs.modal', lrRecalc);
lrRecalc();

var lrOverlapTimer = null;
var lrOverlapBox = document.getElementById('lr_overlapWarning');
function lrCheckOverlap() {
  var roomCode = document.getElementById('lr_room_code').value.trim();
  var checkin = document.getElementById('lr_checkin').value;
  var checkout = document.getElementById('lr_checkout').value;
  if (!roomCode || !checkin || !checkout) { lrOverlapBox.style.display = 'none'; return; }
  var url = '<?= url('/deals/check_overlap.php') ?>?room_code=' + encodeURIComponent(roomCode)
    + '&checkin=' + encodeURIComponent(checkin) + '&checkout=' + encodeURIComponent(checkout);
  fetch(url).then(function (r) { return r.json(); }).then(function (data) {
    if (data.conflicts && data.conflicts.length) {
      var lines = data.conflicts.map(function (c) {
        return c.guest_name + ' (' + c.checkin + ' - ' + c.checkout + ', ' + c.deal_type + ')';
      });
      lrOverlapBox.innerHTML = '⚠️ <strong>Trùng lịch phòng ' + roomCode + '</strong> với: ' + lines.join('; ');
      lrOverlapBox.style.display = '';
    } else {
      lrOverlapBox.style.display = 'none';
    }
  }).catch(function () {});
}
['lr_room_code', 'lr_checkin', 'lr_checkout'].forEach(function (id) {
  document.getElementById(id).addEventListener('input', function () {
    clearTimeout(lrOverlapTimer);
    lrOverlapTimer = setTimeout(lrCheckOverlap, 400);
  });
  document.getElementById(id).addEventListener('change', lrCheckOverlap);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
