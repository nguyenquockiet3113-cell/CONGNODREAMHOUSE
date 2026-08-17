<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$tenant = [
    'id' => 0, 'full_name' => '', 'phone' => '', 'email' => '',
    'id_card_number' => '', 'id_card_address' => '', 'permanent_address' => '', 'note' => '',
];
$errors = [];

if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM tenants WHERE id = ?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found) {
        flash('danger', 'Không tìm thấy khách thuê.');
        redirect('/tenants/index.php');
    }
    $tenant = $found;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach (['full_name', 'phone', 'email', 'id_card_number', 'id_card_address', 'permanent_address', 'note'] as $f) {
        $tenant[$f] = trim($_POST[$f] ?? '');
    }

    if ($tenant['full_name'] === '') {
        $errors[] = 'Vui lòng nhập họ tên khách thuê.';
    }

    if (!$errors) {
        $now = date('Y-m-d H:i:s');
        if ($id) {
            $stmt = $pdo->prepare(
                'UPDATE tenants SET full_name=?, phone=?, email=?, id_card_number=?, id_card_address=?, permanent_address=?, note=? WHERE id=?'
            );
            $stmt->execute([
                $tenant['full_name'], $tenant['phone'], $tenant['email'], $tenant['id_card_number'],
                $tenant['id_card_address'], $tenant['permanent_address'], $tenant['note'], $id,
            ]);
            flash('success', 'Đã cập nhật khách thuê.');
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO tenants (full_name, phone, email, id_card_number, id_card_address, permanent_address, note, created_at) VALUES (?,?,?,?,?,?,?,?)'
            );
            $stmt->execute([
                $tenant['full_name'], $tenant['phone'], $tenant['email'], $tenant['id_card_number'],
                $tenant['id_card_address'], $tenant['permanent_address'], $tenant['note'], $now,
            ]);
            flash('success', 'Đã thêm khách thuê mới.');
        }
        redirect('/tenants/index.php');
    }
}

$pageTitle = $id ? 'Sửa khách thuê' : 'Thêm khách thuê';
require_once __DIR__ . '/../includes/header.php';
?>
<h4 class="mb-3"><i class="bi bi-people"></i> <?= $id ? 'Sửa khách thuê' : 'Thêm khách thuê mới' ?></h4>

<?php foreach ($errors as $err): ?>
  <div class="alert alert-danger py-2"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="card">
  <div class="card-body">
    <form method="post">
      <?= csrf_field() ?>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Họ tên *</label>
          <input type="text" name="full_name" class="form-control" required value="<?= e($tenant['full_name']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Số điện thoại</label>
          <input type="text" name="phone" class="form-control" value="<?= e($tenant['phone']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" value="<?= e($tenant['email']) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Số CCCD/CMND</label>
          <input type="text" name="id_card_number" class="form-control" value="<?= e($tenant['id_card_number']) ?>">
        </div>
        <div class="col-md-8">
          <label class="form-label">Nơi cấp CCCD/CMND</label>
          <input type="text" name="id_card_address" class="form-control" value="<?= e($tenant['id_card_address']) ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Địa chỉ thường trú</label>
          <input type="text" name="permanent_address" class="form-control" value="<?= e($tenant['permanent_address']) ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Ghi chú</label>
          <textarea name="note" class="form-control" rows="2"><?= e($tenant['note']) ?></textarea>
        </div>
      </div>
      <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-success"><i class="bi bi-check-lg"></i> Lưu</button>
        <a href="<?= url('/tenants/index.php') ?>" class="btn btn-outline-secondary">Hủy</a>
      </div>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
