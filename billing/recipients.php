<?php
require_once __DIR__ . '/../config/config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (isset($_POST['add_recipient'])) {
        $name = trim($_POST['name'] ?? '');
        $note = trim($_POST['note'] ?? '');
        if ($name === '') {
            flash('danger', 'Vui lòng nhập tên TK nhận.');
        } else {
            $dup = $pdo->prepare('SELECT id FROM billing_recipients WHERE name = ?');
            $dup->execute([$name]);
            if ($dup->fetch()) {
                flash('danger', 'TK nhận này đã tồn tại.');
            } else {
                $pdo->prepare('INSERT INTO billing_recipients (name, note, created_at) VALUES (?,?,?)')
                    ->execute([$name, $note, date('Y-m-d H:i:s')]);
                flash('success', 'Đã thêm TK nhận "' . $name . '".');
            }
        }
    } elseif (isset($_POST['edit_recipient'])) {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $note = trim($_POST['note'] ?? '');
        if ($name !== '') {
            $pdo->prepare('UPDATE billing_recipients SET name=?, note=? WHERE id=?')->execute([$name, $note, $id]);
            flash('success', 'Đã cập nhật TK nhận.');
        }
    } elseif (isset($_POST['delete_recipient'])) {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM billing_recipients WHERE id=?')->execute([$id]);
        flash('success', 'Đã xóa TK nhận.');
    }
    redirect('/billing/recipients.php');
}

$recipients = $pdo->query('SELECT * FROM billing_recipients ORDER BY name')->fetchAll();

$pageTitle = 'Danh sách TK nhận';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="mb-0"><i class="bi bi-person-lines-fill"></i> Danh sách TK nhận (Chi phí khác)</h4>
  <a href="<?= url('/billing/index.php') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Về danh sách giao dịch</a>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header">Danh sách TK nhận</div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>Tên</th><th>Ghi chú</th><th class="text-end">Thao tác</th></tr></thead>
          <tbody>
            <?php if (!$recipients): ?>
              <tr><td colspan="3" class="text-center text-muted py-4">Chưa có TK nhận nào.</td></tr>
            <?php endif; ?>
            <?php foreach ($recipients as $r): ?>
              <tr>
                <td class="fw-semibold"><?= e($r['name']) ?></td>
                <td class="small text-muted"><?= e($r['note']) ?></td>
                <td class="text-end">
                  <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editRecipient<?= $r['id'] ?>"><i class="bi bi-pencil"></i></button>
                  <form method="post" class="d-inline" data-confirm="Xóa TK nhận &quot;<?= e($r['name']) ?>&quot;?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="delete_recipient" value="1">
                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card">
      <div class="card-header">Thêm TK nhận mới</div>
      <div class="card-body">
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="add_recipient" value="1">
          <div class="mb-3">
            <label class="form-label">Tên TK nhận *</label>
            <input type="text" name="name" class="form-control" required placeholder="VD: TRỊNH THỊ HẰNG">
          </div>
          <div class="mb-3">
            <label class="form-label">Ghi chú</label>
            <input type="text" name="note" class="form-control">
          </div>
          <button type="submit" class="btn btn-success"><i class="bi bi-plus-lg"></i> Thêm TK nhận</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php foreach ($recipients as $r): ?>
  <div class="modal fade" id="editRecipient<?= $r['id'] ?>" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="edit_recipient" value="1">
          <input type="hidden" name="id" value="<?= $r['id'] ?>">
          <div class="modal-header">
            <h5 class="modal-title">Sửa TK nhận</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Tên *</label>
              <input type="text" name="name" class="form-control" required value="<?= e($r['name']) ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">Ghi chú</label>
              <input type="text" name="note" class="form-control" value="<?= e($r['note']) ?>">
            </div>
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-success">Lưu</button>
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Đóng</button>
          </div>
        </form>
      </div>
    </div>
  </div>
<?php endforeach; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
