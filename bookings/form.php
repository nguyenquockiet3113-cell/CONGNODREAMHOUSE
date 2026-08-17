<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$booking = [
    'id' => 0, 'room_id' => '', 'guest_name' => '', 'guest_phone' => '', 'guest_id_card' => '',
    'checkin_date' => date('Y-m-d'), 'checkout_date' => date('Y-m-d', strtotime('+1 day')),
    'price_per_night' => 0, 'deposit_amount' => 0, 'extra_fee' => 0, 'total_amount' => 0,
    'status' => 'booked', 'payment_status' => 'unpaid', 'paid_date' => '', 'bank_account_id' => '', 'note' => '',
];
$errors = [];
$bankAccounts = $pdo->query('SELECT * FROM bank_accounts ORDER BY bank_name')->fetchAll();

if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM bookings WHERE id = ?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found) {
        flash('danger', 'Không tìm thấy đặt phòng.');
        redirect('/bookings/index.php');
    }
    $booking = $found;
}

$rooms = $pdo->query('SELECT * FROM rooms ORDER BY room_code')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $booking['room_id'] = (int)($_POST['room_id'] ?? 0);
    $booking['guest_name'] = trim($_POST['guest_name'] ?? '');
    $booking['guest_phone'] = trim($_POST['guest_phone'] ?? '');
    $booking['guest_id_card'] = trim($_POST['guest_id_card'] ?? '');
    $booking['checkin_date'] = $_POST['checkin_date'] ?? '';
    $booking['checkout_date'] = $_POST['checkout_date'] ?? '';
    $booking['price_per_night'] = (float)($_POST['price_per_night'] ?? 0);
    $booking['deposit_amount'] = (float)($_POST['deposit_amount'] ?? 0);
    $booking['extra_fee'] = (float)($_POST['extra_fee'] ?? 0);
    $booking['status'] = $_POST['status'] ?? 'booked';
    $booking['payment_status'] = $_POST['payment_status'] ?? 'unpaid';
    $booking['paid_date'] = $_POST['paid_date'] ?: null;
    $booking['bank_account_id'] = ($_POST['bank_account_id'] ?? '') !== '' ? (int)$_POST['bank_account_id'] : null;
    $booking['note'] = trim($_POST['note'] ?? '');

    if (!$booking['room_id']) $errors[] = 'Vui lòng chọn phòng.';
    if ($booking['guest_name'] === '') $errors[] = 'Vui lòng nhập tên khách.';
    if (!$booking['checkin_date'] || !$booking['checkout_date']) $errors[] = 'Vui lòng nhập ngày nhận/trả phòng.';
    if ($booking['checkin_date'] && $booking['checkout_date'] && $booking['checkout_date'] <= $booking['checkin_date']) {
        $errors[] = 'Ngày trả phòng phải sau ngày nhận phòng.';
    }
    if (!array_key_exists($booking['status'], BOOKING_STATUS_LABELS)) $errors[] = 'Trạng thái không hợp lệ.';

    if (!$errors) {
        $nights = (strtotime($booking['checkout_date']) - strtotime($booking['checkin_date'])) / 86400;
        $total = $nights * $booking['price_per_night'] + $booking['extra_fee'];
        $now = date('Y-m-d H:i:s');

        if ($id) {
            $stmt = $pdo->prepare(
                'UPDATE bookings SET room_id=?, guest_name=?, guest_phone=?, guest_id_card=?, checkin_date=?, checkout_date=?, price_per_night=?, deposit_amount=?, extra_fee=?, total_amount=?, status=?, payment_status=?, paid_date=?, bank_account_id=?, note=? WHERE id=?'
            );
            $stmt->execute([
                $booking['room_id'], $booking['guest_name'], $booking['guest_phone'], $booking['guest_id_card'],
                $booking['checkin_date'], $booking['checkout_date'], $booking['price_per_night'],
                $booking['deposit_amount'], $booking['extra_fee'], $total, $booking['status'],
                $booking['payment_status'], $booking['paid_date'], $booking['bank_account_id'], $booking['note'], $id,
            ]);
            flash('success', 'Đã cập nhật đặt phòng.');
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO bookings (room_id, guest_name, guest_phone, guest_id_card, checkin_date, checkout_date, price_per_night, deposit_amount, extra_fee, total_amount, status, payment_status, paid_date, bank_account_id, note, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $stmt->execute([
                $booking['room_id'], $booking['guest_name'], $booking['guest_phone'], $booking['guest_id_card'],
                $booking['checkin_date'], $booking['checkout_date'], $booking['price_per_night'],
                $booking['deposit_amount'], $booking['extra_fee'], $total, $booking['status'],
                $booking['payment_status'], $booking['paid_date'], $booking['bank_account_id'], $booking['note'], $now,
            ]);
            flash('success', 'Đã thêm đặt phòng mới.');
        }
        redirect('/bookings/index.php');
    }
}

$pageTitle = $id ? 'Sửa đặt phòng' : 'Thêm đặt phòng';
require_once __DIR__ . '/../includes/header.php';
?>
<h4 class="mb-3"><i class="bi bi-calendar-check"></i> <?= $id ? 'Sửa đặt phòng' : 'Thêm đặt phòng ngắn hạn' ?></h4>

<?php foreach ($errors as $err): ?>
  <div class="alert alert-danger py-2"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="card">
  <div class="card-body">
    <form method="post">
      <?= csrf_field() ?>
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Phòng *</label>
          <select name="room_id" id="room_id" class="form-select" required>
            <option value="">-- Chọn phòng --</option>
            <?php foreach ($rooms as $r): ?>
              <option value="<?= $r['id'] ?>" data-price="<?= $r['short_term_price'] ?>" <?= (int)$booking['room_id'] === (int)$r['id'] ? 'selected' : '' ?>>
                <?= e($r['room_code']) ?><?= $r['zone'] ? ' - ' . e($r['zone']) : '' ?> (<?= e(ROOM_STATUS_LABELS[$r['status']] ?? '') ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Tên khách *</label>
          <input type="text" name="guest_name" class="form-control" required value="<?= e($booking['guest_name']) ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">SĐT</label>
          <input type="text" name="guest_phone" class="form-control" value="<?= e($booking['guest_phone']) ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">CCCD/CMND</label>
          <input type="text" name="guest_id_card" class="form-control" value="<?= e($booking['guest_id_card']) ?>">
        </div>

        <div class="col-md-3">
          <label class="form-label">Ngày nhận phòng *</label>
          <input type="date" name="checkin_date" class="form-control" required value="<?= e($booking['checkin_date']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Ngày trả phòng *</label>
          <input type="date" name="checkout_date" class="form-control" required value="<?= e($booking['checkout_date']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Giá / đêm (đ)</label>
          <input type="number" step="1000" id="price_per_night" name="price_per_night" class="form-control" value="<?= e($booking['price_per_night']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Tiền cọc (đ)</label>
          <input type="number" step="1000" name="deposit_amount" class="form-control" value="<?= e($booking['deposit_amount']) ?>">
        </div>

        <div class="col-md-3">
          <label class="form-label">Phụ phí khác (đ)</label>
          <input type="number" step="1000" name="extra_fee" class="form-control" value="<?= e($booking['extra_fee']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Trạng thái</label>
          <select name="status" class="form-select">
            <?php foreach (BOOKING_STATUS_LABELS as $k => $v): ?>
              <option value="<?= e($k) ?>" <?= $booking['status'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Thanh toán</label>
          <select name="payment_status" class="form-select">
            <option value="unpaid" <?= $booking['payment_status'] === 'unpaid' ? 'selected' : '' ?>>Chưa thu</option>
            <option value="paid" <?= $booking['payment_status'] === 'paid' ? 'selected' : '' ?>>Đã thu</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Ngày thanh toán</label>
          <input type="date" name="paid_date" class="form-control" value="<?= e($booking['paid_date']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Thu qua tài khoản</label>
          <select name="bank_account_id" class="form-select">
            <option value="">-- Tiền mặt / chưa chọn --</option>
            <?php foreach ($bankAccounts as $ba): ?>
              <option value="<?= $ba['id'] ?>" <?= (int)$booking['bank_account_id'] === (int)$ba['id'] ? 'selected' : '' ?>><?= e($ba['bank_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <div class="text-muted small">Tổng tiền hiện tại: <strong><?= money($booking['total_amount']) ?></strong></div>
        </div>

        <div class="col-12">
          <label class="form-label">Ghi chú</label>
          <textarea name="note" class="form-control" rows="2"><?= e($booking['note']) ?></textarea>
        </div>
      </div>
      <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-success"><i class="bi bi-check-lg"></i> Lưu</button>
        <a href="<?= url('/bookings/index.php') ?>" class="btn btn-outline-secondary">Hủy</a>
      </div>
    </form>
  </div>
</div>

<script>
document.getElementById('room_id').addEventListener('change', function () {
  var opt = this.options[this.selectedIndex];
  var priceInput = document.getElementById('price_per_night');
  if (opt && opt.getAttribute('data-price') && !priceInput.value) {
    priceInput.value = opt.getAttribute('data-price');
  }
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
