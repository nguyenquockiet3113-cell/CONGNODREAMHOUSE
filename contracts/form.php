<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$contract = [
    'id' => 0, 'contract_code' => '', 'room_id' => '', 'tenant_id' => '',
    'start_date' => date('Y-m-d'), 'end_date' => date('Y-m-d', strtotime('+1 year')),
    'monthly_rent' => '', 'deposit_amount' => '', 'electricity_price' => 3500,
    'water_price' => 20000, 'service_fee' => 0, 'status' => 'active', 'note' => '',
    'file_path' => '',
];
$members = [];
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
    $mStmt = $pdo->prepare('SELECT * FROM contract_members WHERE contract_id = ?');
    $mStmt->execute([$id]);
    $members = $mStmt->fetchAll();
}

// Danh sach phong: phong dang trong, hoac phong hien tai cua hop dong nay
$roomStmt = $pdo->prepare('SELECT * FROM rooms WHERE status = ? OR id = ? ORDER BY room_code');
$roomStmt->execute(['trong', (int)$contract['room_id']]);
$rooms = $roomStmt->fetchAll();

$tenants = $pdo->query('SELECT * FROM tenants ORDER BY full_name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $contract['room_id'] = (int)($_POST['room_id'] ?? 0);
    $contract['tenant_id'] = (int)($_POST['tenant_id'] ?? 0);
    $contract['start_date'] = $_POST['start_date'] ?? '';
    $contract['end_date'] = $_POST['end_date'] ?? '';
    $contract['monthly_rent'] = (float)($_POST['monthly_rent'] ?? 0);
    $contract['deposit_amount'] = (float)($_POST['deposit_amount'] ?? 0);
    $contract['electricity_price'] = (float)($_POST['electricity_price'] ?? 0);
    $contract['water_price'] = (float)($_POST['water_price'] ?? 0);
    $contract['service_fee'] = (float)($_POST['service_fee'] ?? 0);
    $contract['status'] = $_POST['status'] ?? 'active';
    $contract['note'] = trim($_POST['note'] ?? '');
    $memberNames = $_POST['member_name'] ?? [];
    $memberPhones = $_POST['member_phone'] ?? [];
    $memberCards = $_POST['member_card'] ?? [];

    if (!$contract['room_id']) $errors[] = 'Vui lòng chọn phòng.';
    if (!$contract['tenant_id']) $errors[] = 'Vui lòng chọn khách thuê đại diện.';
    if (!$contract['start_date'] || !$contract['end_date']) $errors[] = 'Vui lòng nhập ngày bắt đầu và kết thúc.';
    if ($contract['start_date'] && $contract['end_date'] && $contract['start_date'] > $contract['end_date']) {
        $errors[] = 'Ngày kết thúc phải sau ngày bắt đầu.';
    }
    if (!array_key_exists($contract['status'], CONTRACT_STATUS_LABELS)) {
        $errors[] = 'Trạng thái không hợp lệ.';
    }

    // Upload file hop dong (neu co)
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
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
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
        try {
            $pdo->beginTransaction();

            if ($id) {
                $stmt = $pdo->prepare(
                    'UPDATE contracts SET room_id=?, tenant_id=?, start_date=?, end_date=?, monthly_rent=?, deposit_amount=?, electricity_price=?, water_price=?, service_fee=?, status=?, file_path=?, note=? WHERE id=?'
                );
                $stmt->execute([
                    $contract['room_id'], $contract['tenant_id'], $contract['start_date'], $contract['end_date'],
                    $contract['monthly_rent'], $contract['deposit_amount'], $contract['electricity_price'],
                    $contract['water_price'], $contract['service_fee'], $contract['status'],
                    $uploadedPath, $contract['note'], $id,
                ]);
                $pdo->prepare('DELETE FROM contract_members WHERE contract_id = ?')->execute([$id]);
                flash('success', 'Đã cập nhật hợp đồng.');
            } else {
                $countStmt = $pdo->query('SELECT COUNT(*) c FROM contracts');
                $next = (int)$countStmt->fetch()['c'] + 1;
                $contract['contract_code'] = generate_code('HD', $next);
                $stmt = $pdo->prepare(
                    'INSERT INTO contracts (contract_code, room_id, tenant_id, start_date, end_date, monthly_rent, deposit_amount, electricity_price, water_price, service_fee, status, file_path, note, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                );
                $stmt->execute([
                    $contract['contract_code'], $contract['room_id'], $contract['tenant_id'],
                    $contract['start_date'], $contract['end_date'], $contract['monthly_rent'],
                    $contract['deposit_amount'], $contract['electricity_price'], $contract['water_price'],
                    $contract['service_fee'], $contract['status'], $uploadedPath, $contract['note'], $now,
                ]);
                $id = (int)$pdo->lastInsertId();
                flash('success', 'Đã tạo hợp đồng ' . $contract['contract_code'] . '.');
            }

            foreach ($memberNames as $i => $mName) {
                $mName = trim($mName);
                if ($mName === '') continue;
                $pdo->prepare('INSERT INTO contract_members (contract_id, full_name, phone, id_card_number) VALUES (?,?,?,?)')
                    ->execute([$id, $mName, trim($memberPhones[$i] ?? ''), trim($memberCards[$i] ?? '')]);
            }

            // Cap nhat trang thai phong tuong ung
            if ($contract['status'] === 'active') {
                $pdo->prepare("UPDATE rooms SET status = 'dang_thue', updated_at = ? WHERE id = ?")
                    ->execute([$now, $contract['room_id']]);
            } else {
                // Neu hop dong ket thuc/huy va khong con hop dong active nao khac cho phong nay -> tra phong ve trong
                $activeStmt = $pdo->prepare("SELECT COUNT(*) c FROM contracts WHERE room_id = ? AND status = 'active' AND id != ?");
                $activeStmt->execute([$contract['room_id'], $id]);
                if ((int)$activeStmt->fetch()['c'] === 0) {
                    $pdo->prepare("UPDATE rooms SET status = 'trong', updated_at = ? WHERE id = ?")
                        ->execute([$now, $contract['room_id']]);
                }
            }

            $pdo->commit();
            redirect('/contracts/index.php');
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Lỗi khi lưu hợp đồng: ' . $e->getMessage();
        }
    }

    if ($errors) {
        $members = [];
        foreach ($memberNames as $i => $mName) {
            if (trim($mName) === '') continue;
            $members[] = ['full_name' => $mName, 'phone' => $memberPhones[$i] ?? '', 'id_card_number' => $memberCards[$i] ?? ''];
        }
    }
}

$pageTitle = $id ? 'Sửa hợp đồng' : 'Tạo hợp đồng';
require_once __DIR__ . '/../includes/header.php';
?>
<h4 class="mb-3"><i class="bi bi-file-earmark-text"></i> <?= $id ? 'Sửa hợp đồng ' . e($contract['contract_code']) : 'Tạo hợp đồng mới' ?></h4>

<?php foreach ($errors as $err): ?>
  <div class="alert alert-danger py-2"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="card">
  <div class="card-body">
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Phòng *</label>
          <select name="room_id" class="form-select" required>
            <option value="">-- Chọn phòng --</option>
            <?php foreach ($rooms as $r): ?>
              <option value="<?= $r['id'] ?>" data-price="<?= $r['monthly_price'] ?>" <?= (int)$contract['room_id'] === (int)$r['id'] ? 'selected' : '' ?>>
                <?= e($r['room_code']) ?><?= $r['zone'] ? ' - ' . e($r['zone']) : '' ?> (<?= money($r['monthly_price']) ?>/tháng)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-5">
          <label class="form-label">Khách thuê đại diện *</label>
          <select name="tenant_id" class="form-select" required>
            <option value="">-- Chọn khách thuê --</option>
            <?php foreach ($tenants as $t): ?>
              <option value="<?= $t['id'] ?>" <?= (int)$contract['tenant_id'] === (int)$t['id'] ? 'selected' : '' ?>><?= e($t['full_name']) ?> - <?= e($t['phone']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <a href="<?= url('/tenants/form.php') ?>" target="_blank" class="btn btn-outline-secondary w-100"><i class="bi bi-plus-lg"></i> Thêm khách thuê mới</a>
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
          <label class="form-label">Tiền thuê / tháng (đ)</label>
          <input type="number" step="1000" id="monthly_rent" name="monthly_rent" class="form-control" value="<?= e($contract['monthly_rent']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Tiền đặt cọc (đ)</label>
          <input type="number" step="1000" name="deposit_amount" class="form-control" value="<?= e($contract['deposit_amount']) ?>">
        </div>

        <div class="col-md-3">
          <label class="form-label">Đơn giá điện (đ/kWh)</label>
          <input type="number" step="100" name="electricity_price" class="form-control" value="<?= e($contract['electricity_price']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Đơn giá nước (đ/m³)</label>
          <input type="number" step="100" name="water_price" class="form-control" value="<?= e($contract['water_price']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Phí dịch vụ / tháng (đ)</label>
          <input type="number" step="1000" name="service_fee" class="form-control" value="<?= e($contract['service_fee']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Trạng thái</label>
          <select name="status" class="form-select">
            <?php foreach (CONTRACT_STATUS_LABELS as $k => $v): ?>
              <option value="<?= e($k) ?>" <?= $contract['status'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

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

        <div class="col-12">
          <hr>
          <div class="d-flex justify-content-between align-items-center mb-2">
            <label class="form-label mb-0 fw-semibold">Người ở cùng (ngoài người đại diện)</label>
            <button type="button" id="addMemberBtn" class="btn btn-sm btn-outline-secondary"><i class="bi bi-plus-lg"></i> Thêm người</button>
          </div>
          <div id="membersWrap">
            <?php foreach ($members as $m): ?>
              <div class="row g-2 mb-2 member-row">
                <div class="col-md-5"><input type="text" name="member_name[]" class="form-control" placeholder="Họ tên" value="<?= e($m['full_name']) ?>"></div>
                <div class="col-md-3"><input type="text" name="member_phone[]" class="form-control" placeholder="SĐT" value="<?= e($m['phone']) ?>"></div>
                <div class="col-md-3"><input type="text" name="member_card[]" class="form-control" placeholder="CCCD/CMND" value="<?= e($m['id_card_number']) ?>"></div>
                <div class="col-md-1"><button type="button" class="btn btn-outline-danger removeMemberBtn"><i class="bi bi-x"></i></button></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-success"><i class="bi bi-check-lg"></i> Lưu hợp đồng</button>
        <a href="<?= url('/contracts/index.php') ?>" class="btn btn-outline-secondary">Hủy</a>
      </div>
    </form>
  </div>
</div>

<template id="memberRowTpl">
  <div class="row g-2 mb-2 member-row">
    <div class="col-md-5"><input type="text" name="member_name[]" class="form-control" placeholder="Họ tên"></div>
    <div class="col-md-3"><input type="text" name="member_phone[]" class="form-control" placeholder="SĐT"></div>
    <div class="col-md-3"><input type="text" name="member_card[]" class="form-control" placeholder="CCCD/CMND"></div>
    <div class="col-md-1"><button type="button" class="btn btn-outline-danger removeMemberBtn"><i class="bi bi-x"></i></button></div>
  </div>
</template>

<script>
document.getElementById('addMemberBtn').addEventListener('click', function () {
  var tpl = document.getElementById('memberRowTpl');
  document.getElementById('membersWrap').appendChild(tpl.content.cloneNode(true));
});
document.getElementById('membersWrap').addEventListener('click', function (e) {
  var btn = e.target.closest('.removeMemberBtn');
  if (btn) btn.closest('.member-row').remove();
});
document.querySelector('select[name="room_id"]').addEventListener('change', function () {
  var opt = this.options[this.selectedIndex];
  var price = opt ? opt.getAttribute('data-price') : '';
  var rentInput = document.getElementById('monthly_rent');
  if (price && !rentInput.value) rentInput.value = price;
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
