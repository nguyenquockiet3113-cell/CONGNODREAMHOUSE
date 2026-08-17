<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$expense = [
    'id' => 0, 'expense_date' => date('Y-m-d'), 'category' => EXPENSE_CATEGORIES[0],
    'room_id' => '', 'amount' => '', 'description' => '',
];
$errors = [];

if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM expenses WHERE id = ?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found) {
        flash('danger', 'Không tìm thấy chi phí.');
        redirect('/expenses/index.php');
    }
    $expense = $found;
}

$rooms = $pdo->query('SELECT * FROM rooms ORDER BY room_code')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $expense['expense_date'] = $_POST['expense_date'] ?? '';
    $expense['category'] = trim($_POST['category'] ?? '');
    $expense['room_id'] = $_POST['room_id'] !== '' ? (int)$_POST['room_id'] : null;
    $expense['amount'] = (float)($_POST['amount'] ?? 0);
    $expense['description'] = trim($_POST['description'] ?? '');

    if (!$expense['expense_date']) $errors[] = 'Vui lòng chọn ngày chi.';
    if ($expense['category'] === '') $errors[] = 'Vui lòng chọn danh mục.';
    if ($expense['amount'] <= 0) $errors[] = 'Số tiền phải lớn hơn 0.';

    if (!$errors) {
        $now = date('Y-m-d H:i:s');
        if ($id) {
            $stmt = $pdo->prepare('UPDATE expenses SET expense_date=?, category=?, room_id=?, amount=?, description=? WHERE id=?');
            $stmt->execute([$expense['expense_date'], $expense['category'], $expense['room_id'], $expense['amount'], $expense['description'], $id]);
            flash('success', 'Đã cập nhật chi phí.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO expenses (expense_date, category, room_id, amount, description, created_at) VALUES (?,?,?,?,?,?)');
            $stmt->execute([$expense['expense_date'], $expense['category'], $expense['room_id'], $expense['amount'], $expense['description'], $now]);
            flash('success', 'Đã thêm chi phí mới.');
        }
        redirect('/expenses/index.php');
    }
}

$pageTitle = $id ? 'Sửa chi phí' : 'Thêm chi phí';
require_once __DIR__ . '/../includes/header.php';
?>
<h4 class="mb-3"><i class="bi bi-cash-coin"></i> <?= $id ? 'Sửa chi phí' : 'Thêm chi phí mới' ?></h4>

<?php foreach ($errors as $err): ?>
  <div class="alert alert-danger py-2"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="card">
  <div class="card-body">
    <form method="post">
      <?= csrf_field() ?>
      <div class="row g-3">
        <div class="col-md-3">
          <label class="form-label">Ngày chi *</label>
          <input type="date" name="expense_date" class="form-control" required value="<?= e($expense['expense_date']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Danh mục *</label>
          <select name="category" class="form-select" required>
            <?php foreach (EXPENSE_CATEGORIES as $cat): ?>
              <option value="<?= e($cat) ?>" <?= $expense['category'] === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Phòng liên quan (nếu có)</label>
          <select name="room_id" class="form-select">
            <option value="">-- Chung / không xác định --</option>
            <?php foreach ($rooms as $r): ?>
              <option value="<?= $r['id'] ?>" <?= (int)$expense['room_id'] === (int)$r['id'] ? 'selected' : '' ?>><?= e($r['room_code']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Số tiền (đ) *</label>
          <input type="number" step="1000" name="amount" class="form-control" required value="<?= e($expense['amount']) ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Mô tả</label>
          <textarea name="description" class="form-control" rows="2"><?= e($expense['description']) ?></textarea>
        </div>
      </div>
      <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-success"><i class="bi bi-check-lg"></i> Lưu</button>
        <a href="<?= url('/expenses/index.php') ?>" class="btn btn-outline-secondary">Hủy</a>
      </div>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
