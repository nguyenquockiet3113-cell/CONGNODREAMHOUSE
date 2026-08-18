<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $dates = $_POST['bill_date'] ?? [];
    $guestNames = $_POST['guest_name'] ?? [];
    $roomCodes = $_POST['room_code'] ?? [];
    $contents = $_POST['content'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $electricity = $_POST['electricity_amount'] ?? [];
    $water = $_POST['water_amount'] ?? [];
    $management = $_POST['management_fee_amount'] ?? [];
    $internet = $_POST['internet_amount'] ?? [];
    $vehicle = $_POST['vehicle_fee_amount'] ?? [];
    $other = $_POST['other_fee_amount'] ?? [];
    $card = $_POST['card_fee_amount'] ?? [];
    $deposits = $_POST['deposit_used'] ?? [];
    $isDoneArr = $_POST['is_done'] ?? [];
    $paidDates = $_POST['customer_paid_date'] ?? [];
    $accounts = $_POST['receiving_account'] ?? [];
    $notes = $_POST['note'] ?? [];

    $validRows = [];
    foreach ($guestNames as $i => $gn) {
        $gn = trim($gn);
        if ($gn === '') continue;
        $e = (float)($electricity[$i] ?? 0);
        $w = (float)($water[$i] ?? 0);
        $m = (float)($management[$i] ?? 0);
        $it = (float)($internet[$i] ?? 0);
        $v = (float)($vehicle[$i] ?? 0);
        $o = (float)($other[$i] ?? 0);
        $c = (float)($card[$i] ?? 0);
        $total = $e + $w + $m + $it + $v + $o + $c;
        $deposit = (float)($deposits[$i] ?? 0);
        $validRows[] = [
            'bill_date' => ($dates[$i] ?? '') ?: date('Y-m-d'),
            'guest_name' => $gn,
            'room_code' => trim($roomCodes[$i] ?? ''),
            'content' => trim($contents[$i] ?? ''),
            'quantity' => ($quantities[$i] ?? '') !== '' ? (float)$quantities[$i] : 1,
            'electricity_amount' => $e, 'water_amount' => $w, 'management_fee_amount' => $m,
            'internet_amount' => $it, 'vehicle_fee_amount' => $v, 'other_fee_amount' => $o, 'card_fee_amount' => $c,
            'total_amount' => $total,
            'deposit_used' => $deposit,
            'settle_amount' => $total - $deposit,
            'is_done' => isset($isDoneArr[$i]) ? 1 : 0,
            'customer_paid_date' => ($paidDates[$i] ?? '') ?: null,
            'receiving_account' => trim($accounts[$i] ?? ''),
            'note' => trim($notes[$i] ?? ''),
        ];
    }

    if (!$validRows) $errors[] = 'Vui lòng nhập ít nhất 1 dòng có Tên KH.';

    if (!$errors) {
        $now = date('Y-m-d H:i:s');
        foreach ($validRows as $row) {
            $pdo->prepare(
                'INSERT INTO billing_entries (bill_date, guest_name, room_code, content, quantity,
                    electricity_amount, water_amount, management_fee_amount, internet_amount, vehicle_fee_amount, other_fee_amount, card_fee_amount,
                    total_amount, deposit_used, settle_amount, is_done, customer_paid_date, receiving_account, note, created_at, updated_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $row['bill_date'], $row['guest_name'], $row['room_code'], $row['content'], $row['quantity'],
                $row['electricity_amount'], $row['water_amount'], $row['management_fee_amount'], $row['internet_amount'], $row['vehicle_fee_amount'], $row['other_fee_amount'], $row['card_fee_amount'],
                $row['total_amount'], $row['deposit_used'], $row['settle_amount'], $row['is_done'], $row['customer_paid_date'], $row['receiving_account'], $row['note'], $now, $now,
            ]);
        }
        flash('success', 'Đã thêm ' . count($validRows) . ' giao dịch.');
        redirect('/billing/index.php');
    }
}

$rooms = $pdo->query('SELECT room_code FROM rooms ORDER BY room_code')->fetchAll(PDO::FETCH_COLUMN);
$recipients = $pdo->query('SELECT name FROM billing_recipients ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Thêm giao dịch dài hạn';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="mb-0"><i class="bi bi-journal-plus"></i> Thêm giao dịch (Chi phí khác)</h4>
  <div class="d-flex gap-2">
    <a href="<?= url('/billing/recipients.php') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-person-lines-fill"></i> Quản lý TK nhận</a>
    <a href="<?= url('/billing/index.php') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-list"></i> Danh sách</a>
  </div>
</div>

<?php foreach ($errors as $err): ?>
  <div class="alert alert-danger py-2"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="card mb-3">
  <div class="card-body">
    <div class="alert alert-info small mb-3">
      Mỗi dòng là 1 khoản thu tự do cho 1 khách (điện nước tháng, phí làm thẻ, mất thẻ, tiền nhà...) — không cần gắn với deal nào. Tổng tiền và Thanh toán tự tính khi bạn nhập.
    </div>
    <form method="post" id="batchForm">
      <?= csrf_field() ?>
      <div class="table-responsive">
        <table class="table table-sm align-middle" id="rowsTable">
          <thead>
            <tr>
              <th style="width:130px;">Ngày tính</th>
              <th style="min-width:130px;">Tên KH</th>
              <th style="min-width:110px;">Mã căn</th>
              <th style="min-width:150px;">Nội dung</th>
              <th style="width:70px;">SL</th>
              <th style="width:110px;">Điện</th>
              <th style="width:110px;">Nước</th>
              <th style="width:100px;">Phí QL</th>
              <th style="width:100px;">Inter</th>
              <th style="width:90px;">Xe</th>
              <th style="width:100px;">Phí khác</th>
              <th style="width:100px;">Thẻ nhà</th>
              <th style="width:120px;" class="text-end">Tổng tiền</th>
              <th style="width:110px;">Tiền cọc</th>
              <th style="width:120px;" class="text-end">Thanh toán</th>
              <th style="width:50px;">TT</th>
              <th style="width:130px;">Khách TT</th>
              <th style="min-width:140px;">TK nhận</th>
              <th style="min-width:120px;">Note</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="rowsBody"></tbody>
        </table>
      </div>
      <button type="button" id="addRowBtn" class="btn btn-outline-secondary btn-sm mb-3"><i class="bi bi-plus-lg"></i> Thêm dòng</button>

      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-success"><i class="bi bi-check-lg"></i> Lưu tất cả</button>
        <a href="<?= url('/billing/index.php') ?>" class="btn btn-outline-secondary">Hủy</a>
      </div>
    </form>
  </div>
</div>

<template id="rowTemplate">
  <tr class="work-row">
    <td><input type="date" name="bill_date[]" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>"></td>
    <td><input type="text" name="guest_name[]" class="form-control form-control-sm"></td>
    <td><input type="text" name="room_code[]" class="form-control form-control-sm" list="billRoomList"></td>
    <td><input type="text" name="content[]" class="form-control form-control-sm" placeholder="VD: Điện nước tháng 8"></td>
    <td><input type="number" step="0.5" name="quantity[]" class="form-control form-control-sm" value="1"></td>
    <td><input type="number" step="1000" name="electricity_amount[]" class="form-control form-control-sm b-fee"></td>
    <td><input type="number" step="1000" name="water_amount[]" class="form-control form-control-sm b-fee"></td>
    <td><input type="number" step="1000" name="management_fee_amount[]" class="form-control form-control-sm b-fee"></td>
    <td><input type="number" step="1000" name="internet_amount[]" class="form-control form-control-sm b-fee"></td>
    <td><input type="number" step="1000" name="vehicle_fee_amount[]" class="form-control form-control-sm b-fee"></td>
    <td><input type="number" step="1000" name="other_fee_amount[]" class="form-control form-control-sm b-fee"></td>
    <td><input type="number" step="1000" name="card_fee_amount[]" class="form-control form-control-sm b-fee"></td>
    <td class="text-end fw-semibold b-total">0 đ</td>
    <td><input type="number" step="1000" name="deposit_used[]" class="form-control form-control-sm b-deposit"></td>
    <td class="text-end fw-semibold b-settle">0 đ</td>
    <td class="text-center"><input type="checkbox" name="is_done[]" class="form-check-input"></td>
    <td><input type="date" name="customer_paid_date[]" class="form-control form-control-sm"></td>
    <td>
      <input type="text" name="receiving_account[]" class="form-control form-control-sm" list="billAccList">
    </td>
    <td><input type="text" name="note[]" class="form-control form-control-sm"></td>
    <td><button type="button" class="btn btn-sm btn-outline-danger remove-row-btn"><i class="bi bi-x"></i></button></td>
  </tr>
</template>

<datalist id="billRoomList">
  <?php foreach ($rooms as $r): ?><option value="<?= e($r) ?>"><?php endforeach; ?>
</datalist>
<datalist id="billAccList">
  <?php foreach ($recipients as $rc): ?><option value="<?= e($rc) ?>"><?php endforeach; ?>
</datalist>

<script>
var rowsBody = document.getElementById('rowsBody');
var rowTemplate = document.getElementById('rowTemplate');

function fmtVnd(n) { return new Intl.NumberFormat('vi-VN').format(Math.round(n)) + ' đ'; }

function addRow() {
  var clone = rowTemplate.content.cloneNode(true);
  rowsBody.appendChild(clone);
}
document.getElementById('addRowBtn').addEventListener('click', addRow);

function recalcRow(row) {
  var fees = 0;
  row.querySelectorAll('.b-fee').forEach(function (inp) { fees += parseFloat(inp.value) || 0; });
  var deposit = parseFloat(row.querySelector('.b-deposit').value) || 0;
  row.querySelector('.b-total').textContent = fmtVnd(fees);
  var settle = fees - deposit;
  var settleCell = row.querySelector('.b-settle');
  settleCell.textContent = fmtVnd(settle);
  settleCell.classList.toggle('text-danger', settle < 0);
  settleCell.classList.toggle('text-success', settle >= 0);
}

rowsBody.addEventListener('input', function (e) {
  var row = e.target.closest('.work-row');
  if (row) recalcRow(row);
});
rowsBody.addEventListener('click', function (e) {
  var btn = e.target.closest('.remove-row-btn');
  if (btn) {
    var rows = rowsBody.querySelectorAll('.work-row');
    if (rows.length > 1) btn.closest('.work-row').remove();
  }
});

// Bat dau voi 5 dong trong
for (var i = 0; i < 5; i++) addRow();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
