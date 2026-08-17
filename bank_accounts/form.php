<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$account = ['id' => 0, 'bank_name' => '', 'account_number' => '', 'account_holder' => '', 'note' => ''];
$errors = [];

if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM bank_accounts WHERE id = ?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found) {
        flash('danger', 'Không tìm thấy tài khoản.');
        redirect('/bank_accounts/index.php');
    }
    $account = $found;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $account['bank_name'] = trim($_POST['bank_name'] ?? '');
    $account['account_number'] = trim($_POST['account_number'] ?? '');
    $account['account_holder'] = trim($_POST['account_holder'] ?? '');
    $account['note'] = trim($_POST['note'] ?? '');

    if ($account['bank_name'] === '') $errors[] = 'Vui lòng nhập tên ngân hàng.';

    if (!$errors) {
        if ($id) {
            $stmt = $pdo->prepare('UPDATE bank_accounts SET bank_name=?, account_number=?, account_holder=?, note=? WHERE id=?');
            $stmt->execute([$account['bank_name'], $account['account_number'], $account['account_holder'], $account['note'], $id]);
            flash('success', 'Đã cập nhật tài khoản.');
        } else {
            $now = date('Y-m-d H:i:s');
            $stmt = $pdo->prepare('INSERT INTO bank_accounts (bank_name, account_number, account_holder, note, created_at) VALUES (?,?,?,?,?)');
            $stmt->execute([$account['bank_name'], $account['account_number'], $account['account_holder'], $account['note'], $now]);
            flash('success', 'Đã thêm tài khoản.');
        }
        redirect('/bank_accounts/index.php');
    }
}

$pageTitle = $id ? 'Sửa tài khoản ngân hàng' : 'Thêm tài khoản ngân hàng';
require_once __DIR__ . '/../includes/header.php';
?>
<h4 class="mb-3"><i class="bi bi-bank"></i> <?= $id ? 'Sửa tài khoản ngân hàng' : 'Thêm tài khoản ngân hàng' ?></h4>

<?php foreach ($errors as $err): ?>
  <div class="alert alert-danger py-2"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="card">
  <div class="card-body">
    <form method="post">
      <?= csrf_field() ?>
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Ngân hàng *</label>
          <input type="text" name="bank_name" class="form-control" required placeholder="VD: Vietcombank" value="<?= e($account['bank_name']) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Số tài khoản</label>
          <input type="text" name="account_number" class="form-control" value="<?= e($account['account_number']) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Chủ tài khoản</label>
          <input type="text" name="account_holder" class="form-control" value="<?= e($account['account_holder']) ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Ghi chú</label>
          <input type="text" name="note" class="form-control" value="<?= e($account['note']) ?>">
        </div>
      </div>
      <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-success"><i class="bi bi-check-lg"></i> Lưu</button>
        <a href="<?= url('/bank_accounts/index.php') ?>" class="btn btn-outline-secondary">Hủy</a>
      </div>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
