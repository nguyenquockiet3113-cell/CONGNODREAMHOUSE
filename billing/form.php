<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$row = [
    'id' => 0, 'bill_date' => date('Y-m-d'), 'guest_name' => '', 'room_code' => '', 'content' => '',
    'quantity' => 1, 'electricity_amount' => 0, 'water_amount' => 0, 'management_fee_amount' => 0,
    'internet_amount' => 0, 'vehicle_fee_amount' => 0, 'other_fee_amount' => 0, 'card_fee_amount' => 0,
    'deposit_used' => 0, 'is_done' => 0, 'customer_paid_date' => '', 'receiving_account' => '', 'note' => '',
];
$errors = [];

if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM billing_entries WHERE id = ?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found) {
        flash('danger', 'Không tìm thấy giao dịch.');
        redirect('/billing/index.php');
    }
    $row = $found;
}

$rooms = $pdo->query('SELECT room_code FROM rooms ORDER BY room_code')->fetchAll(PDO::FETCH_COLUMN);
$recipients = $pdo->query('SELECT name FROM billing_recipients ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $row['bill_date'] = $_POST['bill_date'] ?? date('Y-m-d');
    $row['guest_name'] = trim($_POST['guest_name'] ?? '');
    $row['room_code'] = trim($_POST['room_code'] ?? '');
    $row['content'] = trim($_POST['content'] ?? '');
    $row['quantity'] = (float)($_POST['quantity'] ?? 1);
    $row['electricity_amount'] = (float)($_POST['electricity_amount'] ?? 0);
    $row['water_amount'] = (float)($_POST['water_amount'] ?? 0);
    $row['management_fee_amount'] = (float)($_POST['management_fee_amount'] ?? 0);
    $row['internet_amount'] = (float)($_POST['internet_amount'] ?? 0);
    $row['vehicle_fee_amount'] = (float)($_POST['vehicle_fee_amount'] ?? 0);
    $row['other_fee_amount'] = (float)($_POST['other_fee_amount'] ?? 0);
    $row['card_fee_amount'] = (float)($_POST['card_fee_amount'] ?? 0);
    $row['deposit_used'] = (float)($_POST['deposit_used'] ?? 0);
    $row['is_done'] = isset($_POST['is_done']) ? 1 : 0;
    $row['customer_paid_date'] = ($_POST['customer_paid_date'] ?? '') ?: null;
    $row['receiving_account'] = trim($_POST['receiving_account'] ?? '');
    $row['note'] = trim($_POST['note'] ?? '');

    if ($row['guest_name'] === '') $errors[] = 'Vui lòng nhập tên khách.';

    if (!$errors) {
        $total = $row['electricity_amount'] + $row['water_amount'] + $row['management_fee_amount']
            + $row['internet_amount'] + $row['vehicle_fee_amount'] + $row['other_fee_amount'] + $row['card_fee_amount'];
        $settle = $total - $row['deposit_used'];
        $now = date('Y-m-d H:i:s');

        $cols = [
            'bill_date', 'guest_name', 'room_code', 'content', 'quantity',
            'electricity_amount', 'water_amount', 'management_fee_amount', 'internet_amount', 'vehicle_fee_amount', 'other_fee_amount', 'card_fee_amount',
            'total_amount', 'deposit_used', 'settle_amount', 'is_done', 'customer_paid_date', 'receiving_account', 'note',
        ];
        $values = [
            $row['bill_date'], $row['guest_name'], $row['room_code'], $row['content'], $row['quantity'],
            $row['electricity_amount'], $row['water_amount'], $row['management_fee_amount'], $row['internet_amount'], $row['vehicle_fee_amount'], $row['other_fee_amount'], $row['card_fee_amount'],
            $total, $row['deposit_used'], $settle, $row['is_done'], $row['customer_paid_date'], $row['receiving_account'], $row['note'],
        ];

        if ($id) {
            $setSql = implode(', ', array_map(fn($c) => "$c = ?", $cols));
            $pdo->prepare("UPDATE billing_entries SET $setSql, updated_at = ? WHERE id = ?")->execute([...$values, $now, $id]);
            flash('success', 'Đã cập nhật giao dịch.');
        } else {
            $cols[] = 'created_at'; $values[] = $now;
            $cols[] = 'updated_at'; $values[] = $now;
            $placeholders = implode(',', array_fill(0, count($cols), '?'));
            $pdo->prepare('INSERT INTO billing_entries (' . implode(',', $cols) . ") VALUES ($placeholders)")->execute($values);
            flash('success', 'Đã thêm giao dịch.');
        }
        redirect('/billing/index.php');
    }
}

$pageTitle = $id ? 'Sửa giao dịch' : 'Thêm giao dịch';
require_once __DIR__ . '/../includes/header.php';
?>
<h4 class="mb-3"><i class="bi bi-journal-text"></i> <?= $id ? 'Sửa giao dịch' : 'Thêm giao dịch' ?></h4>

<?php foreach ($errors as $err): ?>
  <div class="alert alert-danger py-2"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="card">
  <div class="card-body">
    <form method="post" id="billForm">
      <?= csrf_field() ?>
      <div class="row g-3">
        <div class="col-md-3">
          <label class="form-label">Ngày tính *</label>
          <input type="date" name="bill_date" class="form-control" required value="<?= e($row['bill_date']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Tên KH *</label>
          <input type="text" name="guest_name" class="form-control" required value="<?= e($row['guest_name']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Mã căn</label>
          <input type="text" name="room_code" class="form-control" list="roomListBill" value="<?= e($row['room_code']) ?>">
          <datalist id="roomListBill"><?php foreach ($rooms as $rc): ?><option value="<?= e($rc) ?>"><?php endforeach; ?></datalist>
        </div>
        <div class="col-md-3">
          <label class="form-label">SL</label>
          <input type="number" step="0.5" name="quantity" class="form-control" value="<?= e($row['quantity']) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Nội dung</label>
          <input type="text" name="content" class="form-control" placeholder="VD: Điện nước tháng 8" value="<?= e($row['content']) ?>">
        </div>
      </div>

      <hr>
      <div class="row g-3">
        <div class="col-md-3"><label class="form-label">Điện</label><input type="number" step="1000" name="electricity_amount" id="f_electricity" class="form-control b-fee" value="<?= e($row['electricity_amount']) ?>"></div>
        <div class="col-md-3"><label class="form-label">Nước</label><input type="number" step="1000" name="water_amount" id="f_water" class="form-control b-fee" value="<?= e($row['water_amount']) ?>"></div>
        <div class="col-md-3"><label class="form-label">Phí quản lý</label><input type="number" step="1000" name="management_fee_amount" id="f_mgmt" class="form-control b-fee" value="<?= e($row['management_fee_amount']) ?>"></div>
        <div class="col-md-3"><label class="form-label">Internet</label><input type="number" step="1000" name="internet_amount" id="f_internet" class="form-control b-fee" value="<?= e($row['internet_amount']) ?>"></div>
        <div class="col-md-3"><label class="form-label">Phí xe</label><input type="number" step="1000" name="vehicle_fee_amount" id="f_vehicle" class="form-control b-fee" value="<?= e($row['vehicle_fee_amount']) ?>"></div>
        <div class="col-md-3"><label class="form-label">Phí khác</label><input type="number" step="1000" name="other_fee_amount" id="f_other" class="form-control b-fee" value="<?= e($row['other_fee_amount']) ?>"></div>
        <div class="col-md-3"><label class="form-label">Thẻ nhà <span class="text-muted small">(có thể âm)</span></label><input type="number" step="1000" name="card_fee_amount" id="f_card" class="form-control b-fee" value="<?= e($row['card_fee_amount']) ?>"></div>
        <div class="col-md-3">
          <label class="form-label">Tổng tiền</label>
          <input type="text" id="totalDisplay" class="form-control fw-semibold" disabled>
        </div>
      </div>

      <hr>
      <div class="row g-3">
        <div class="col-md-3"><label class="form-label">Tiền cọc trừ</label><input type="number" step="1000" name="deposit_used" id="f_deposit" class="form-control" value="<?= e($row['deposit_used']) ?>"></div>
        <div class="col-md-3">
          <label class="form-label">Thanh toán (= Tổng - Cọc)</label>
          <input type="text" id="settleDisplay" class="form-control fw-semibold" disabled>
        </div>
        <div class="col-md-3">
          <label class="form-label">Khách TT (ngày khách trả)</label>
          <input type="date" name="customer_paid_date" class="form-control" value="<?= e($row['customer_paid_date']) ?>">
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <div class="form-check">
            <input type="checkbox" name="is_done" id="isDone" class="form-check-input" <?= $row['is_done'] ? 'checked' : '' ?>>
            <label class="form-check-label" for="isDone">Đã xử lý xong (Tình trạng)</label>
          </div>
        </div>
        <div class="col-md-6">
          <label class="form-label">TK nhận</label>
          <input type="text" name="receiving_account" class="form-control" list="accListBill" value="<?= e($row['receiving_account']) ?>">
          <datalist id="accListBill">
            <?php foreach ($recipients as $rc): ?><option value="<?= e($rc) ?>"><?php endforeach; ?>
          </datalist>
          <div class="form-text"><a href="<?= url('/billing/recipients.php') ?>" target="_blank">+ Quản lý danh sách TK nhận</a></div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Note</label>
          <input type="text" name="note" class="form-control" value="<?= e($row['note']) ?>">
        </div>
      </div>

      <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-success"><i class="bi bi-check-lg"></i> Lưu</button>
        <a href="<?= url('/billing/index.php') ?>" class="btn btn-outline-secondary">Hủy</a>
      </div>
    </form>
  </div>
</div>

<script>
function fmtVnd(n) { return new Intl.NumberFormat('vi-VN').format(Math.round(n)) + ' đ'; }
function recalc() {
  var fees = 0;
  document.querySelectorAll('.b-fee').forEach(function (inp) { fees += parseFloat(inp.value) || 0; });
  var deposit = parseFloat(document.getElementById('f_deposit').value) || 0;
  document.getElementById('totalDisplay').value = fmtVnd(fees);
  document.getElementById('settleDisplay').value = fmtVnd(fees - deposit);
}
document.querySelectorAll('.b-fee').forEach(function (inp) { inp.addEventListener('input', recalc); });
document.getElementById('f_deposit').addEventListener('input', recalc);
recalc();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
