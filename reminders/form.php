<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$reminder = ['id' => 0, 'title' => '', 'due_date' => date('Y-m-d'), 'note' => ''];
$errors = [];

if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM reminders WHERE id = ?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found) {
        flash('danger', 'Không tìm thấy nhắc nhở.');
        redirect('/reminders/index.php');
    }
    $reminder = $found;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $reminder['title'] = trim($_POST['title'] ?? '');
    $reminder['due_date'] = $_POST['due_date'] ?? '';
    $reminder['note'] = trim($_POST['note'] ?? '');

    if ($reminder['title'] === '') $errors[] = 'Vui lòng nhập nội dung nhắc nhở.';
    if (!$reminder['due_date']) $errors[] = 'Vui lòng chọn ngày nhắc.';

    if (!$errors) {
        if ($id) {
            $stmt = $pdo->prepare('UPDATE reminders SET title=?, due_date=?, note=? WHERE id=?');
            $stmt->execute([$reminder['title'], $reminder['due_date'], $reminder['note'], $id]);
            flash('success', 'Đã cập nhật nhắc nhở.');
        } else {
            $now = date('Y-m-d H:i:s');
            $stmt = $pdo->prepare('INSERT INTO reminders (title, due_date, note, is_done, created_at) VALUES (?,?,?,0,?)');
            $stmt->execute([$reminder['title'], $reminder['due_date'], $reminder['note'], $now]);
            flash('success', 'Đã thêm nhắc nhở.');
        }
        redirect('/reminders/index.php');
    }
}

$pageTitle = $id ? 'Sửa nhắc nhở' : 'Thêm nhắc nhở';
require_once __DIR__ . '/../includes/header.php';
?>
<h4 class="mb-3"><i class="bi bi-bell"></i> <?= $id ? 'Sửa nhắc nhở' : 'Thêm nhắc nhở' ?></h4>

<?php foreach ($errors as $err): ?>
  <div class="alert alert-danger py-2"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="card">
  <div class="card-body">
    <form method="post">
      <?= csrf_field() ?>
      <div class="row g-3">
        <div class="col-md-8">
          <label class="form-label">Nội dung *</label>
          <input type="text" name="title" class="form-control" required value="<?= e($reminder['title']) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Ngày nhắc *</label>
          <input type="date" name="due_date" class="form-control" required value="<?= e($reminder['due_date']) ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Ghi chú</label>
          <textarea name="note" class="form-control" rows="2"><?= e($reminder['note']) ?></textarea>
        </div>
      </div>
      <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-success"><i class="bi bi-check-lg"></i> Lưu</button>
        <a href="<?= url('/reminders/index.php') ?>" class="btn btn-outline-secondary">Hủy</a>
      </div>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
