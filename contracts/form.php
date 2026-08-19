<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$contract = [
    'id' => 0, 'contract_code' => '', 'room_code' => '', 'zone' => '',
    'lessee_name' => '', 'lessee_dob' => '', 'lessee_nationality' => 'Việt Nam',
    'lessee_id_number' => '', 'lessee_id_issue_date' => '', 'lessee_id_issue_place' => '',
    'lessee_address' => '', 'lessee_phone' => '', 'lessee_email' => '',
    'lessor_name' => '', 'lessor_dob' => '', 'lessor_id_number' => '',
    'lessor_id_issue_date' => '', 'lessor_id_issue_place' => '', 'lessor_address' => '',
    'monthly_rent' => '', 'rent_note' => '', 'deposit_amount' => '',
    'start_date' => date('Y-m-d'), 'end_date' => date('Y-m-d', strtotime('+1 year')),
    'checkin_time' => '14:00', 'checkout_time' => '12:00',
    'payment_method' => 'chuyen_khoan', 'receiving_account' => '', 'bank_name' => '',
    'beneficiary_name' => '', 'payment_note' => '', 'status' => 'active',
    'file_path' => '', 'note' => '',
];
$errors = [];

if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM contracts WHERE id = ?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found) {
        flash('danger', 'Không tìm thấy hợp đồng.');
        redirect('/contracts/index.php');
    }
    $contract = $found;
}

$rooms = $pdo->query('SELECT room_code, zone, bedrooms FROM rooms ORDER BY room_code')->fetchAll();
$bankAccounts = $pdo->query('SELECT * FROM bank_accounts ORDER BY bank_name')->fetchAll();

$textFields = [
    'contract_code', 'room_code', 'zone', 'lessee_name', 'lessee_dob', 'lessee_nationality',
    'lessee_id_number', 'lessee_id_issue_date', 'lessee_id_issue_place', 'lessee_address',
    'lessee_phone', 'lessee_email', 'lessor_name', 'lessor_dob', 'lessor_id_number',
    'lessor_id_issue_date', 'lessor_id_issue_place', 'lessor_address', 'rent_note',
    'start_date', 'end_date', 'checkin_time', 'checkout_time', 'payment_method',
    'receiving_account', 'bank_name', 'beneficiary_name', 'payment_note', 'status', 'note',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach ($textFields as $f) {
        $contract[$f] = trim($_POST[$f] ?? '');
    }
    $contract['monthly_rent'] = (float)($_POST['monthly_rent'] ?? 0);
    $contract['deposit_amount'] = (float)($_POST['deposit_amount'] ?? 0);

    if ($contract['contract_code'] === '') $errors[] = 'Vui lòng nhập số hợp đồng.';
    if ($contract['room_code'] === '') $errors[] = 'Vui lòng nhập mã phòng.';
    if ($contract['lessee_name'] === '') $errors[] = 'Vui lòng nhập tên bên thuê.';
    if (!$contract['start_date'] || !$contract['end_date']) $errors[] = 'Vui lòng nhập ngày bắt đầu và kết thúc.';
    if ($contract['start_date'] && $contract['end_date'] && $contract['start_date'] > $contract['end_date']) {
        $errors[] = 'Ngày kết thúc phải sau ngày bắt đầu.';
    }
    if (!array_key_exists($contract['status'], CONTRACT_STATUS_LABELS)) $errors[] = 'Trạng thái không hợp lệ.';

    if (!$errors) {
        $dupStmt = $pdo->prepare('SELECT id FROM contracts WHERE contract_code = ? AND id != ?');
        $dupStmt->execute([$contract['contract_code'], $id]);
        if ($dupStmt->fetch()) $errors[] = 'Số hợp đồng đã tồn tại.';
    }

    $uploadedPath = $contract['file_path'];
    if (!empty($_FILES['contract_file']['name'])) {
        $file = $_FILES['contract_file'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $allowedExt = ['pdf', 'jpg', 'jpeg', 'png'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExt, true)) {
                $errors[] = 'File hợp đồng chỉ chấp nhận PDF, JPG, PNG.';
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $errors[] = 'File hợp đồng tối đa 5MB.';
            } else {
                $uploadDir = __DIR__ . '/../uploads/contracts/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $newName = 'hd_' . bin2hex(random_bytes(8)) . '.' . $ext;
                if (move_uploaded_file($file['tmp_name'], $uploadDir . $newName)) {
                    $uploadedPath = 'uploads/contracts/' . $newName;
                } else {
                    $errors[] = 'Tải file hợp đồng lên thất bại.';
                }
            }
        }
    }

    if (!$errors) {
        $now = date('Y-m-d H:i:s');
        $cols = [
            'contract_code', 'room_code', 'zone', 'lessee_name', 'lessee_dob', 'lessee_nationality',
            'lessee_id_number', 'lessee_id_issue_date', 'lessee_id_issue_place', 'lessee_address',
            'lessee_phone', 'lessee_email', 'lessor_name', 'lessor_dob', 'lessor_id_number',
            'lessor_id_issue_date', 'lessor_id_issue_place', 'lessor_address',
            'monthly_rent', 'rent_note', 'deposit_amount', 'start_date', 'end_date',
            'checkin_time', 'checkout_time', 'payment_method', 'receiving_account', 'bank_name',
            'beneficiary_name', 'payment_note', 'status', 'file_path', 'note',
        ];
        $values = array_map(fn($c) => $c === 'file_path' ? $uploadedPath : $contract[$c], $cols);

        if ($id) {
            $setSql = implode(', ', array_map(fn($c) => "$c = ?", $cols));
            $stmt = $pdo->prepare("UPDATE contracts SET $setSql WHERE id = ?");
            $stmt->execute([...$values, $id]);
            flash('success', 'Đã cập nhật hợp đồng.');
        } else {
            $cols[] = 'created_at';
            $values[] = $now;
            $placeholders = implode(',', array_fill(0, count($cols), '?'));
            $stmt = $pdo->prepare('INSERT INTO contracts (' . implode(',', $cols) . ") VALUES ($placeholders)");
            $stmt->execute($values);
            flash('success', 'Đã tạo hợp đồng ' . $contract['contract_code'] . '.');
        }
        redirect('/contracts/index.php');
    }
}

$pageTitle = $id ? 'Sửa hợp đồng' : 'Tạo hợp đồng';
require_once __DIR__ . '/../includes/header.php';
?>
<h4 class="mb-3"><i class="bi bi-file-earmark-text"></i> <?= $id ? 'Sửa hợp đồng ' . e($contract['contract_code']) : 'Tạo hợp đồng mới' ?></h4>

<?php foreach ($errors as $err): ?>
  <div class="alert alert-danger py-2"><?= e($err) ?></div>
<?php endforeach; ?>

<form method="post" enctype="multipart/form-data">
  <?= csrf_field() ?>

  <div class="card mb-3">
    <div class="card-header">Thông tin hợp đồng</div>
    <div class="card-body row g-3">
      <div class="col-md-3">
        <label class="form-label">Số hợp đồng *</label>
        <input type="text" name="contract_code" class="form-control" required placeholder="VD: LP-31.15" value="<?= e($contract['contract_code']) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Mã phòng *</label>
        <input type="text" name="room_code" id="room_code" class="form-control" list="roomList" required value="<?= e($contract['room_code']) ?>">
        <datalist id="roomList">
          <?php foreach ($rooms as $r): ?><option value="<?= e($r['room_code']) ?>"><?php endforeach; ?>
        </datalist>
      </div>
      <div class="col-md-6">
        <label class="form-label">Khu vực / Dự án</label>
        <input type="text" name="zone" id="zone" class="form-control" placeholder="VD: Vinhomes Central Park" value="<?= e($contract['zone']) ?>">
      </div>
    </div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-header">Bên thuê (Lessee)</div>
        <div class="card-body row g-3">
          <div class="col-md-8">
            <label class="form-label">Họ tên *</label>
            <input type="text" name="lessee_name" class="form-control" required value="<?= e($contract['lessee_name']) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Ngày sinh</label>
            <input type="date" name="lessee_dob" class="form-control" value="<?= e($contract['lessee_dob']) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Quốc tịch</label>
            <input type="text" name="lessee_nationality" class="form-control" value="<?= e($contract['lessee_nationality']) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">SĐT</label>
            <input type="text" name="lessee_phone" class="form-control" value="<?= e($contract['lessee_phone']) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Email</label>
            <input type="email" name="lessee_email" class="form-control" value="<?= e($contract['lessee_email']) ?>">
          </div>
          <div class="col-md-5">
            <label class="form-label">Số hộ chiếu/CCCD</label>
            <input type="text" name="lessee_id_number" class="form-control" value="<?= e($contract['lessee_id_number']) ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Cấp ngày</label>
            <input type="date" name="lessee_id_issue_date" class="form-control" value="<?= e($contract['lessee_id_issue_date']) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Nơi cấp</label>
            <input type="text" name="lessee_id_issue_place" class="form-control" value="<?= e($contract['lessee_id_issue_place']) ?>">
          </div>
          <div class="col-12">
            <label class="form-label">Địa chỉ liên lạc</label>
            <input type="text" name="lessee_address" class="form-control" value="<?= e($contract['lessee_address']) ?>">
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-header">Bên cho thuê (Lessor - chủ nhà)</div>
        <div class="card-body row g-3">
          <div class="col-md-8">
            <label class="form-label">Họ tên</label>
            <input type="text" name="lessor_name" class="form-control" value="<?= e($contract['lessor_name']) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Ngày sinh</label>
            <input type="date" name="lessor_dob" class="form-control" value="<?= e($contract['lessor_dob']) ?>">
          </div>
          <div class="col-md-5">
            <label class="form-label">Số hộ chiếu/CCCD</label>
            <input type="text" name="lessor_id_number" class="form-control" value="<?= e($contract['lessor_id_number']) ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Cấp ngày</label>
            <input type="date" name="lessor_id_issue_date" class="form-control" value="<?= e($contract['lessor_id_issue_date']) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Nơi cấp</label>
            <input type="text" name="lessor_id_issue_place" class="form-control" value="<?= e($contract['lessor_id_issue_place']) ?>">
          </div>
          <div class="col-12">
            <label class="form-label">Địa chỉ liên lạc</label>
            <input type="text" name="lessor_address" class="form-control" value="<?= e($contract['lessor_address']) ?>">
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header">Tiền thuê, đặt cọc &amp; thời hạn</div>
    <div class="card-body row g-3">
      <div class="col-md-3">
        <label class="form-label">Tiền thuê / tháng (đ)</label>
        <input type="number" step="1" name="monthly_rent" class="form-control" value="<?= e($contract['monthly_rent']) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Tiền đặt cọc (đ)</label>
        <input type="number" step="1" name="deposit_amount" class="form-control" value="<?= e($contract['deposit_amount']) ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Ghi chú giá thuê</label>
        <input type="text" name="rent_note" class="form-control" placeholder="VD: Không bao gồm phí quản lý, internet" value="<?= e($contract['rent_note']) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Ngày bắt đầu *</label>
        <input type="date" name="start_date" class="form-control" required value="<?= e($contract['start_date']) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Ngày kết thúc *</label>
        <input type="date" name="end_date" class="form-control" required value="<?= e($contract['end_date']) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Giờ nhận phòng</label>
        <input type="text" name="checkin_time" class="form-control" value="<?= e($contract['checkin_time']) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Giờ trả phòng</label>
        <input type="text" name="checkout_time" class="form-control" value="<?= e($contract['checkout_time']) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Trạng thái</label>
        <select name="status" class="form-select">
          <?php foreach (CONTRACT_STATUS_LABELS as $k => $v): ?>
            <option value="<?= e($k) ?>" <?= $contract['status'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header">Thanh toán</div>
    <div class="card-body row g-3">
      <div class="col-md-3">
        <label class="form-label">Hình thức thanh toán</label>
        <select name="payment_method" class="form-select">
          <option value="chuyen_khoan" <?= $contract['payment_method'] === 'chuyen_khoan' ? 'selected' : '' ?>>Chuyển khoản</option>
          <option value="tien_mat" <?= $contract['payment_method'] === 'tien_mat' ? 'selected' : '' ?>>Tiền mặt</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Chọn tài khoản có sẵn</label>
        <select id="bankAccountPicker" class="form-select">
          <option value="">-- Chọn để tự điền --</option>
          <?php foreach ($bankAccounts as $ba): ?>
            <option value="<?= (int)$ba['id'] ?>"
              data-account="<?= e($ba['account_number']) ?>"
              data-bank="<?= e($ba['bank_name']) ?>"
              data-holder="<?= e($ba['account_holder']) ?>">
              <?= e($ba['bank_name']) ?><?= $ba['account_number'] ? ' - ' . e($ba['account_number']) : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="form-text"><a href="<?= url('/bank_accounts/index.php') ?>" target="_blank">+ Quản lý danh sách tài khoản nhận</a></div>
      </div>
      <div class="col-md-3">
        <label class="form-label">Tài khoản nhận (số TK)</label>
        <input type="text" name="receiving_account" id="receiving_account" class="form-control" placeholder="Số TK" value="<?= e($contract['receiving_account']) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Ngân hàng</label>
        <input type="text" name="bank_name" id="bank_name" class="form-control" value="<?= e($contract['bank_name']) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Người thụ hưởng</label>
        <input type="text" name="beneficiary_name" id="beneficiary_name" class="form-control" value="<?= e($contract['beneficiary_name']) ?>">
      </div>
      <div class="col-12">
        <label class="form-label">Ghi chú thanh toán</label>
        <input type="text" name="payment_note" class="form-control" placeholder="VD: Có thể chậm 1-5 ngày từ đầu tháng" value="<?= e($contract['payment_note']) ?>">
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header">Đính kèm &amp; ghi chú</div>
    <div class="card-body row g-3">
      <div class="col-12">
        <label class="form-label">File hợp đồng (PDF/JPG/PNG, tối đa 5MB)</label>
        <input type="file" name="contract_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
        <?php if (!empty($contract['file_path'])): ?>
          <div class="small mt-1"><i class="bi bi-paperclip"></i> <a href="<?= url('/' . $contract['file_path']) ?>" target="_blank">File hiện tại</a></div>
        <?php endif; ?>
      </div>
      <div class="col-12">
        <label class="form-label">Ghi chú</label>
        <textarea name="note" class="form-control" rows="2"><?= e($contract['note']) ?></textarea>
      </div>
    </div>
  </div>

  <div class="d-flex gap-2 mb-4">
    <button type="submit" class="btn btn-success"><i class="bi bi-check-lg"></i> Lưu hợp đồng</button>
    <a href="<?= url('/contracts/index.php') ?>" class="btn btn-outline-secondary">Hủy</a>
  </div>
</form>

<script>
var roomZoneMap = <?= json_encode(array_column($rooms, 'zone', 'room_code')) ?>;
document.getElementById('room_code').addEventListener('change', function () {
  var z = roomZoneMap[this.value];
  var zoneInput = document.getElementById('zone');
  if (z && !zoneInput.value) zoneInput.value = z;
});

document.getElementById('bankAccountPicker').addEventListener('change', function () {
  var opt = this.options[this.selectedIndex];
  if (!opt.value) return;
  document.getElementById('receiving_account').value = opt.getAttribute('data-account') || '';
  document.getElementById('bank_name').value = opt.getAttribute('data-bank') || '';
  document.getElementById('beneficiary_name').value = opt.getAttribute('data-holder') || '';
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
