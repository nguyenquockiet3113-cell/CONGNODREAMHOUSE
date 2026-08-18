<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/deal_helpers.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$deal = [
    'id' => 0, 'room_code' => '', 'bedrooms' => '', 'zone' => '', 'guest_name' => '',
    'checkin_date' => date('Y-m-d'), 'checkout_date' => date('Y-m-d', strtotime('+1 day')),
    'price_per_unit' => 0, 'deposit_amount' => 0, 'deposit_date' => '', 'extra_fee' => 0,
    'payment_method' => 'chuyen_khoan', 'receiving_account' => '', 'paid_amount' => 0,
    'payment_status' => 'unpaid', 'apply_vat' => 0, 'vat_percent' => 0, 'status' => 'active', 'note' => '',
];
$periods = [];
$payments = [];
$errors = [];

if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM deals WHERE id = ?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found) {
        flash('danger', 'Không tìm thấy deal.');
        redirect('/deals/short.php');
    }
    $deal = $found;
    $pStmt = $pdo->prepare('SELECT * FROM deal_periods WHERE deal_id = ? ORDER BY period_index');
    $pStmt->execute([$id]);
    $periods = $pStmt->fetchAll();

    $payStmt = $pdo->prepare('SELECT * FROM deal_payments WHERE deal_id = ? ORDER BY payment_date DESC, id DESC');
    $payStmt->execute([$id]);
    $payments = $payStmt->fetchAll();
}

$rooms = $pdo->query('SELECT room_code, zone, bedrooms FROM rooms ORDER BY room_code')->fetchAll();
$bankAccounts = $pdo->query('SELECT * FROM bank_accounts ORDER BY bank_name')->fetchAll();
// Gia gan nhat da dung cho tung ma phong (goi y de nhap nhanh hon)
$lastPriceByRoom = $pdo->query(
    'SELECT room_code, price_per_unit FROM deals WHERE id IN (SELECT MAX(id) FROM deals GROUP BY room_code)'
)->fetchAll(PDO::FETCH_KEY_PAIR);
$saveAndNew = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $deal['room_code'] = trim($_POST['room_code'] ?? '');
    $deal['bedrooms'] = ($_POST['bedrooms'] ?? '') !== '' ? (int)$_POST['bedrooms'] : null;
    $deal['zone'] = trim($_POST['zone'] ?? '');
    $deal['guest_name'] = trim($_POST['guest_name'] ?? '');
    $deal['checkin_date'] = $_POST['checkin_date'] ?? '';
    $deal['checkout_date'] = $_POST['checkout_date'] ?? '';
    $deal['price_per_unit'] = (float)($_POST['price_per_unit'] ?? 0);
    $deal['deposit_amount'] = (float)($_POST['deposit_amount'] ?? 0);
    $deal['deposit_date'] = ($_POST['deposit_date'] ?? '') ?: null;
    $deal['extra_fee'] = (float)($_POST['extra_fee'] ?? 0);
    $deal['payment_method'] = $_POST['payment_method'] ?? 'chuyen_khoan';
    $deal['receiving_account'] = trim($_POST['receiving_account'] ?? '');
    $deal['status'] = in_array($_POST['status'] ?? '', array_keys(DEAL_STATUS_LABELS), true) ? $_POST['status'] : 'active';
    $deal['note'] = trim($_POST['note'] ?? '');

    if ($deal['room_code'] === '') $errors[] = 'Vui lòng nhập mã phòng.';
    if ($deal['guest_name'] === '') $errors[] = 'Vui lòng nhập tên khách/sale.';
    if (!$deal['checkin_date'] || !$deal['checkout_date']) $errors[] = 'Vui lòng nhập ngày check-in/check-out.';
    if ($deal['checkin_date'] && $deal['checkout_date'] && $deal['checkout_date'] <= $deal['checkin_date']) {
        $errors[] = 'Ngày check-out phải sau ngày check-in.';
    }

    if (!$errors) {
        $nights = deal_nights($deal['checkin_date'], $deal['checkout_date']);
        $dealType = deal_classify($nights);
        $rentTotal = deal_rent_total($nights, $deal['price_per_unit'], $dealType);
        $vatAmount = !empty($deal['apply_vat']) ? round($rentTotal * (float)$deal['vat_percent'] / 100) : 0;
        $total = $rentTotal + $vatAmount + $deal['extra_fee'];
        $now = date('Y-m-d H:i:s');

        $cols = [
            'room_code', 'bedrooms', 'zone', 'guest_name', 'checkin_date', 'checkout_date',
            'nights', 'deal_type', 'price_per_unit', 'deposit_amount', 'deposit_date', 'extra_fee',
            'total_amount', 'payment_method', 'receiving_account', 'apply_vat', 'vat_percent', 'status', 'note',
        ];
        $values = [
            $deal['room_code'], $deal['bedrooms'], $deal['zone'], $deal['guest_name'],
            $deal['checkin_date'], $deal['checkout_date'], $nights, $dealType,
            $deal['price_per_unit'], $deal['deposit_amount'], $deal['deposit_date'], $deal['extra_fee'],
            $total, $deal['payment_method'], $deal['receiving_account'],
            (int)!empty($deal['apply_vat']), (float)($deal['vat_percent'] ?? 0), $deal['status'] ?? 'active', $deal['note'],
        ];

        if ($id) {
            $setSql = implode(', ', array_map(fn($c) => "$c = ?", $cols));
            $pdo->prepare("UPDATE deals SET $setSql, updated_at = ? WHERE id = ?")->execute([...$values, $now, $id]);

            // Cap nhat cac ky da co (khong tu sinh lai de khong mat du lieu da nhap)
            $periodIds = $_POST['period_id'] ?? [];
            foreach ($periodIds as $i => $pid) {
                $electricity = (float)($_POST['period_electricity'][$i] ?? 0);
                $water = (float)($_POST['period_water'][$i] ?? 0);
                $management = (float)($_POST['period_management'][$i] ?? 0);
                $other = (float)($_POST['period_other'][$i] ?? 0);
                $pdo->prepare(
                    'UPDATE deal_periods SET rent_amount=?, deposit_amount=?, electricity_amount=?, water_amount=?, management_fee_amount=?, other_fee_amount=?, utilities_amount=?, paid_amount=?, note=? WHERE id=? AND deal_id=?'
                )->execute([
                    (float)($_POST['period_rent'][$i] ?? 0),
                    (float)($_POST['period_deposit'][$i] ?? 0),
                    $electricity, $water, $management, $other,
                    $electricity + $water + $management + $other,
                    (float)($_POST['period_paid'][$i] ?? 0),
                    trim($_POST['period_note'][$i] ?? ''),
                    (int)$pid, $id,
                ]);
            }

            // Neu chuyen thanh dai han va chua tung co ky nao -> sinh ky lan dau
            if ($dealType === 'dai_han' && !$periods) {
                generate_deal_periods($pdo, $id, $deal['checkin_date'], $deal['checkout_date'], $deal['price_per_unit'], $deal['deposit_amount']);
            }

            // Neu co tung ky rieng (vd doi gia giua chung), tong tien phai la TONG cac ky
            // (khong phai gia dan x so thang) de khop voi so thuc te da chinh sua tung ky
            if ($dealType === 'dai_han') {
                // Coc khong tinh vao tong can thu (coc duoc theo doi rieng qua lich su thanh toan)
                $sumStmt = $pdo->prepare('SELECT COALESCE(SUM(rent_amount + utilities_amount),0) FROM deal_periods WHERE deal_id = ?');
                $sumStmt->execute([$id]);
                $periodsSum = (float)$sumStmt->fetchColumn();
                $pdo->prepare('UPDATE deals SET total_amount = ? WHERE id = ?')->execute([$periodsSum, $id]);
            }

            recompute_deal_paid_amount($pdo, $id);
            flash('success', 'Đã cập nhật deal.');
        } else {
            $cols[] = 'created_at';
            $values[] = $now;
            $cols[] = 'updated_at';
            $values[] = $now;
            $placeholders = implode(',', array_fill(0, count($cols), '?'));
            $pdo->prepare('INSERT INTO deals (' . implode(',', $cols) . ") VALUES ($placeholders)")->execute($values);
            $id = (int)$pdo->lastInsertId();

            if ($dealType === 'dai_han') {
                generate_deal_periods($pdo, $id, $deal['checkin_date'], $deal['checkout_date'], $deal['price_per_unit'], $deal['deposit_amount']);
                // Coc khong tinh vao tong can thu (coc duoc theo doi rieng qua lich su thanh toan)
                $sumStmt = $pdo->prepare('SELECT COALESCE(SUM(rent_amount + utilities_amount),0) FROM deal_periods WHERE deal_id = ?');
                $sumStmt->execute([$id]);
                $periodsSum = (float)$sumStmt->fetchColumn();
                $pdo->prepare('UPDATE deals SET total_amount = ? WHERE id = ?')->execute([$periodsSum, $id]);
            }

            flash('success', 'Đã thêm deal mới (' . ($dealType === 'dai_han' ? 'Dài hạn' : 'Ngắn hạn') . ').');

            if (($_POST['save_and_new'] ?? '0') === '1') {
                redirect('/deals/form.php');
            }
        }

        redirect($dealType === 'dai_han' ? '/deals/long.php' : '/deals/short.php');
    }
}

$nightsPreview = $deal['checkin_date'] && $deal['checkout_date'] ? deal_nights($deal['checkin_date'], $deal['checkout_date']) : 0;
$typePreview = deal_classify($nightsPreview);

$pageTitle = $id ? 'Sửa deal' : 'Thêm deal';
require_once __DIR__ . '/../includes/header.php';
?>
<h4 class="mb-3"><i class="bi bi-calendar-check"></i> <?= $id ? 'Sửa deal' : 'Thêm deal mới' ?></h4>

<?php foreach ($errors as $err): ?>
  <div class="alert alert-danger py-2"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="card mb-3">
  <div class="card-body">
    <form method="post" id="dealForm">
      <?= csrf_field() ?>
      <input type="hidden" name="save_and_new" id="save_and_new" value="0">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Ghi chú (Note)</label>
          <input type="text" name="note" class="form-control" value="<?= e($deal['note']) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Tên Sale / Khách *</label>
          <input type="text" name="guest_name" class="form-control" required value="<?= e($deal['guest_name']) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Mã phòng *</label>
          <input type="text" name="room_code" id="room_code" class="form-control" list="roomList" required value="<?= e($deal['room_code']) ?>">
          <datalist id="roomList">
            <?php foreach ($rooms as $r): ?><option value="<?= e($r['room_code']) ?>"><?php endforeach; ?>
          </datalist>
        </div>
        <div class="col-md-2">
          <label class="form-label">Số PN</label>
          <input type="number" min="0" name="bedrooms" id="bedrooms" class="form-control" value="<?= e($deal['bedrooms']) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Khu vực</label>
          <input type="text" name="zone" id="zone" class="form-control" value="<?= e($deal['zone']) ?>">
        </div>

        <div class="col-md-3">
          <label class="form-label">Check-in *</label>
          <input type="date" name="checkin_date" id="checkin_date" class="form-control" required value="<?= e($deal['checkin_date']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Check-out *</label>
          <input type="date" name="checkout_date" id="checkout_date" class="form-control" required value="<?= e($deal['checkout_date']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Số đêm / Hình thức</label>
          <input type="text" id="nightsDisplay" class="form-control" disabled value="<?= $nightsPreview ?> đêm (<?= $typePreview === 'dai_han' ? 'Dài hạn' : 'Ngắn hạn' ?>)">
        </div>
        <div class="col-md-3">
          <label class="form-label">Đơn giá (đ) <span class="text-muted small">/đêm hoặc /kỳ 30 ngày</span></label>
          <input type="number" step="1000" name="price_per_unit" id="price_per_unit" class="form-control" value="<?= e($deal['price_per_unit']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Phụ phí (Charge)</label>
          <input type="number" step="1000" name="extra_fee" id="extra_fee" class="form-control" value="<?= e($deal['extra_fee']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Thành tiền tổng</label>
          <input type="text" id="totalDisplay" class="form-control" disabled>
        </div>
        <div class="col-md-3">
          <label class="form-label">Đã CK/TM (đ)</label>
          <input type="text" class="form-control" disabled value="<?= money($deal['paid_amount']) ?>">
          <div class="form-text">Cộng dồn từ lịch sử thanh toán bên dưới, không nhập trực tiếp.</div>
        </div>
      </div>

      <div class="mt-2">
        <a href="#advancedFields" data-bs-toggle="collapse" class="small"><i class="bi bi-chevron-down"></i> Thêm chi tiết thanh toán (cọc, hình thức, tài khoản nhận)</a>
        <div class="collapse<?= ($deal['deposit_amount'] || $deal['deposit_date'] || $deal['receiving_account']) ? ' show' : '' ?> mt-2" id="advancedFields">
          <div class="row g-3">
            <div class="col-md-3">
              <label class="form-label">Tiền cọc (đ)</label>
              <input type="number" step="1000" name="deposit_amount" id="deposit_amount" class="form-control" value="<?= e($deal['deposit_amount']) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Ngày cọc</label>
              <input type="date" name="deposit_date" class="form-control" value="<?= e($deal['deposit_date']) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Hình thức thanh toán</label>
              <select name="payment_method" class="form-select">
                <option value="chuyen_khoan" <?= $deal['payment_method'] === 'chuyen_khoan' ? 'selected' : '' ?>>Chuyển khoản</option>
                <option value="tien_mat" <?= $deal['payment_method'] === 'tien_mat' ? 'selected' : '' ?>>Tiền mặt</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Tài khoản nhận</label>
              <?php
                $recvOptions = array_map(fn($ba) => trim($ba['bank_name'] . ($ba['account_number'] ? ' - ' . $ba['account_number'] : '')), $bankAccounts);
                $recvCurrent = (string)$deal['receiving_account'];
              ?>
              <select name="receiving_account" class="form-select">
                <option value="">-- Chưa chọn --</option>
                <?php foreach ($recvOptions as $opt): ?>
                  <option value="<?= e($opt) ?>" <?= $recvCurrent === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                <?php endforeach; ?>
                <?php if ($recvCurrent !== '' && !in_array($recvCurrent, $recvOptions, true)): ?>
                  <option value="<?= e($recvCurrent) ?>" selected><?= e($recvCurrent) ?></option>
                <?php endif; ?>
              </select>
              <div class="form-text"><a href="<?= url('/bank_accounts/index.php') ?>" target="_blank">+ Quản lý danh sách tài khoản nhận</a></div>
            </div>
          </div>
        </div>
      </div>

      <div class="mt-3 d-flex align-items-center gap-3 flex-wrap">
        <span class="badge bg-<?= $deal['payment_status'] === 'paid' ? 'success' : 'secondary' ?> py-2 px-3">
          <?= $deal['payment_status'] === 'paid' ? 'Đã thanh toán đủ' : 'Chưa thanh toán đủ' ?>
        </span>
        <div class="d-flex align-items-center gap-2">
          <label class="form-label mb-0 small">Tình trạng</label>
          <select name="status" class="form-select form-select-sm" style="width:auto;">
            <?php foreach (DEAL_STATUS_LABELS as $k => $v): ?>
              <option value="<?= e($k) ?>" <?= $deal['status'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <?php if ($periods): ?>
        <hr>
        <div class="fw-semibold mb-2">Chi tiết công nợ theo từng kỳ (quy ước vòng đời 30 ngày)</div>
        <div class="table-responsive">
          <table class="table table-sm align-middle" id="periodsTable">
            <thead>
              <tr>
                <th style="min-width:150px;">Kỳ</th>
                <th style="width:110px;">Thuê</th>
                <th style="width:110px;">Cọc</th>
                <th style="width:100px;">Điện</th>
                <th style="width:100px;">Nước</th>
                <th style="width:100px;">Phí QL</th>
                <th style="width:100px;">Phí khác</th>
                <th style="width:120px;" class="text-end">Tổng cần TT</th>
                <th style="width:110px;">Đã TT</th>
                <th style="width:120px;" class="text-end">Còn lại</th>
                <th style="min-width:120px;">Note</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($periods as $i => $p): ?>
                <tr class="period-row">
                  <td>
                    <input type="hidden" name="period_id[]" value="<?= $p['id'] ?>">
                    <div class="small text-muted"><?= e(deal_period_label($p['period_index'], $p['period_start'])) ?></div>
                    <div class="small">Từ <?= vndate($p['period_start']) ?> đến <?= vndate($p['period_end']) ?></div>
                  </td>
                  <td><input type="number" step="1000" name="period_rent[]" class="form-control form-control-sm p-rent" value="<?= e($p['rent_amount']) ?>"></td>
                  <td><input type="number" step="1000" name="period_deposit[]" class="form-control form-control-sm p-deposit" value="<?= e($p['deposit_amount']) ?>"></td>
                  <td><input type="number" step="1000" name="period_electricity[]" class="form-control form-control-sm p-fee" value="<?= e($p['electricity_amount'] ?? 0) ?>"></td>
                  <td><input type="number" step="1000" name="period_water[]" class="form-control form-control-sm p-fee" value="<?= e($p['water_amount'] ?? 0) ?>"></td>
                  <td><input type="number" step="1000" name="period_management[]" class="form-control form-control-sm p-fee" value="<?= e($p['management_fee_amount'] ?? 0) ?>"></td>
                  <td><input type="number" step="1000" name="period_other[]" class="form-control form-control-sm p-fee" value="<?= e($p['other_fee_amount'] ?? 0) ?>"></td>
                  <td class="text-end fw-semibold p-total">0 đ</td>
                  <td><input type="number" step="1000" name="period_paid[]" class="form-control form-control-sm p-paid" value="<?= e($p['paid_amount']) ?>"></td>
                  <td class="text-end p-remain">0 đ</td>
                  <td><input type="text" name="period_note[]" class="form-control form-control-sm" value="<?= e($p['note'] ?? '') ?>" placeholder="VD: gồm internet, xe..."></td>
                  <td><a href="<?= url('/deals/bill.php?deal_id=' . $id . '&period_id=' . $p['id']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Xem/in bill"><i class="bi bi-printer"></i></a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php elseif ($id && $typePreview === 'dai_han'): ?>
        <hr>
        <div class="alert alert-info small mb-0">Lưu lại để hệ thống tự sinh các kỳ thanh toán 30 ngày cho deal dài hạn này.</div>
      <?php endif; ?>

      <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-success"><i class="bi bi-check-lg"></i> Thêm / Cập nhật Deal</button>
        <?php if (!$id): ?>
          <button type="submit" id="saveAndNewBtn" class="btn btn-outline-success"><i class="bi bi-plus-circle"></i> Lưu &amp; Thêm mới</button>
        <?php endif; ?>
        <a href="<?= url('/deals/short.php') ?>" class="btn btn-outline-secondary">Hủy</a>
      </div>
    </form>

    <?php if ($id && $periods): ?>
      <form method="post" action="<?= url('/deals/regenerate_periods.php') ?>" class="mt-2" data-confirm="Tạo lại toàn bộ các kỳ thanh toán sẽ XÓA dữ liệu điện/nước, đã thanh toán đã nhập cho từng kỳ hiện tại. Tiếp tục?">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-arrow-repeat"></i> Tạo lại các kỳ thanh toán</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php if ($id): ?>
<div class="card mb-3">
  <div class="card-header">Lịch sử thanh toán</div>
  <div class="card-body">
    <?php if (!$payments): ?>
      <div class="text-muted small mb-3">Chưa có lần thanh toán nào.</div>
    <?php else: ?>
      <table class="table table-sm mb-3">
        <thead><tr><th>Ngày</th><th>Số tiền</th><th>Hình thức</th><th>TK nhận</th><th>Ghi chú</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($payments as $p): ?>
            <tr>
              <td><?= vndate($p['payment_date']) ?></td>
              <td class="fw-semibold"><?= money($p['amount']) ?></td>
              <td><?= $p['method'] === 'tien_mat' ? 'Tiền mặt' : 'Chuyển khoản' ?></td>
              <td class="small"><?= $p['receiving_account'] ? e($p['receiving_account']) : '<span class="text-muted">—</span>' ?></td>
              <td class="small"><?= e($p['note']) ?></td>
              <td class="text-end">
                <form method="post" action="<?= url('/deals/delete_payment.php') ?>" data-confirm="Xóa lần thanh toán này?">
                  <?= csrf_field() ?>
                  <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
                  <input type="hidden" name="deal_id" value="<?= $id ?>">
                  <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <form method="post" action="<?= url('/deals/add_payment.php') ?>" class="row g-2 align-items-end">
      <?= csrf_field() ?>
      <input type="hidden" name="deal_id" value="<?= $id ?>">
      <div class="col-md-3">
        <label class="form-label small mb-1">Ngày thanh toán</label>
        <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
      </div>
      <div class="col-md-3">
        <label class="form-label small mb-1">Số tiền (đ)</label>
        <input type="number" step="1000" name="amount" class="form-control" required>
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">Hình thức</label>
        <select name="method" class="form-select">
          <option value="chuyen_khoan">Chuyển khoản</option>
          <option value="tien_mat">Tiền mặt</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label small mb-1">TK nhận</label>
        <select name="receiving_account" class="form-select">
          <option value="">-- Theo deal --</option>
          <?php foreach ($recvOptions as $opt): ?>
            <option value="<?= e($opt) ?>" <?= $recvCurrent === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label small mb-1">Ghi chú</label>
        <input type="text" name="note" class="form-control">
      </div>
      <div class="col-md-1">
        <button class="btn btn-success w-100"><i class="bi bi-plus-lg"></i></button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
var periodsTableBody = document.querySelector('#periodsTable tbody');
function fmtVnd(n) { return new Intl.NumberFormat('vi-VN').format(Math.round(n)) + ' đ'; }
function recalcPeriodRow(row) {
  var val = function (cls) { return parseFloat(row.querySelector('.' + cls) ? row.querySelector('.' + cls).value : 0) || 0; };
  var rent = val('p-rent'), deposit = val('p-deposit');
  var fees = 0;
  row.querySelectorAll('.p-fee').forEach(function (inp) { fees += parseFloat(inp.value) || 0; });
  var paid = val('p-paid');
  var total = rent + deposit + fees;
  row.querySelector('.p-total').textContent = fmtVnd(total);
  var remainCell = row.querySelector('.p-remain');
  var remain = total - paid;
  remainCell.textContent = fmtVnd(remain);
  remainCell.classList.toggle('text-danger', remain > 0);
}
if (periodsTableBody) {
  periodsTableBody.querySelectorAll('.period-row').forEach(recalcPeriodRow);
  periodsTableBody.addEventListener('input', function (e) {
    var row = e.target.closest('.period-row');
    if (row) recalcPeriodRow(row);
    if (typeof recalc === 'function') recalc();
  });
}

var roomMap = <?= json_encode(array_map(fn($r) => ['zone' => $r['zone'], 'bedrooms' => $r['bedrooms']], array_column($rooms, null, 'room_code'))) ?>;
var lastPriceMap = <?= json_encode($lastPriceByRoom) ?>;
function applyRoomInfo() {
  var roomCode = document.getElementById('room_code').value;
  var info = roomMap[roomCode];
  if (info) {
    if (info.zone) document.getElementById('zone').value = info.zone;
    if (info.bedrooms) document.getElementById('bedrooms').value = info.bedrooms;
  }
  var priceInput = document.getElementById('price_per_unit');
  if ((!priceInput.value || parseFloat(priceInput.value) === 0) && lastPriceMap[roomCode]) {
    priceInput.value = lastPriceMap[roomCode];
    recalc();
  }
}
document.getElementById('room_code').addEventListener('input', applyRoomInfo);
document.getElementById('room_code').addEventListener('change', applyRoomInfo);

var saveAndNewBtn = document.getElementById('saveAndNewBtn');
if (saveAndNewBtn) {
  saveAndNewBtn.addEventListener('click', function () {
    document.getElementById('save_and_new').value = '1';
  });
}
document.getElementById('dealForm').addEventListener('submit', function (e) {
  if (e.submitter && e.submitter.id !== 'saveAndNewBtn') {
    document.getElementById('save_and_new').value = '0';
  }
});

function fmt(n) { return new Intl.NumberFormat('vi-VN').format(Math.round(n)) + ' đ'; }

function recalc() {
  var checkin = new Date(document.getElementById('checkin_date').value);
  var checkout = new Date(document.getElementById('checkout_date').value);
  var nights = 0;
  if (checkin && checkout && checkout > checkin) {
    nights = Math.round((checkout - checkin) / 86400000);
  }
  var isLong = nights >= 30;
  var type = isLong ? 'Dài hạn' : 'Ngắn hạn';
  document.getElementById('nightsDisplay').value = nights + ' đêm (' + type + ')';

  var price = parseFloat(document.getElementById('price_per_unit').value) || 0;
  var extra = parseFloat(document.getElementById('extra_fee').value) || 0;

  // Da co bang chi tiet tung ky -> tong that su la TONG cac ky (co the da chinh sua gia rieng tung ky),
  // khong phai gia dan x so thang nua
  var periodRows = periodsTableBody ? periodsTableBody.querySelectorAll('.period-row') : [];
  if (periodRows.length > 0) {
    var sum = 0;
    periodRows.forEach(function (row) {
      var val = function (cls) { return parseFloat(row.querySelector('.' + cls) ? row.querySelector('.' + cls).value : 0) || 0; };
      var fees = 0;
      row.querySelectorAll('.p-fee').forEach(function (inp) { fees += parseFloat(inp.value) || 0; });
      sum += val('p-rent') + fees;
    });
    document.getElementById('totalDisplay').value = fmt(sum + extra);
    return;
  }

  var rentTotal;
  if (isLong) {
    var fullMonths = Math.floor(nights / 30);
    var remainderDays = nights % 30;
    rentTotal = fullMonths * price + (remainderDays > 0 ? Math.round(price * remainderDays / 30) : 0);
  } else {
    rentTotal = nights * price;
  }
  document.getElementById('totalDisplay').value = fmt(rentTotal + extra);
}

['checkin_date', 'checkout_date', 'price_per_unit', 'extra_fee'].forEach(function (id) {
  document.getElementById(id).addEventListener('input', recalc);
});
recalc();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
