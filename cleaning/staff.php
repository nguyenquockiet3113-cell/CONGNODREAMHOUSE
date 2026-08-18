<?php
require_once __DIR__ . '/../config/config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (isset($_POST['add_staff'])) {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $note = trim($_POST['note'] ?? '');
        if ($name === '') {
            flash('danger', 'Vui lòng nhập tên nhân viên.');
        } else {
            $dup = $pdo->prepare('SELECT id FROM cleaning_staff WHERE name = ?');
            $dup->execute([$name]);
            if ($dup->fetch()) {
                flash('danger', 'Tên nhân viên này đã tồn tại.');
            } else {
                $pdo->prepare('INSERT INTO cleaning_staff (name, phone, note, is_active, created_at) VALUES (?,?,?,1,?)')
                    ->execute([$name, $phone, $note, date('Y-m-d H:i:s')]);
                flash('success', 'Đã thêm nhân viên "' . $name . '".');
            }
        }
    } elseif (isset($_POST['edit_staff'])) {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $note = trim($_POST['note'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        if ($name !== '') {
            $pdo->prepare('UPDATE cleaning_staff SET name=?, phone=?, note=?, is_active=? WHERE id=?')
                ->execute([$name, $phone, $note, $isActive, $id]);
            flash('success', 'Đã cập nhật nhân viên.');
        }
    }
    redirect('/cleaning/staff.php');
}

$staffList = $pdo->query('SELECT * FROM cleaning_staff ORDER BY is_active DESC, name')->fetchAll();

$pageTitle = 'Nhân viên vệ sinh';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="mb-0"><i class="bi bi-people"></i> Nhân viên vệ sinh</h4>
  <a href="<?= url('/cleaning/index.php') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Về bảng lương</a>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header">Danh sách nhân viên</div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>Tên</th><th>SĐT</th><th>Ghi chú</th><th>Trạng thái</th><th class="text-end">Thao tác</th></tr></thead>
          <tbody>
            <?php if (!$staffList): ?>
              <tr><td colspan="5" class="text-center text-muted py-4">Chưa có nhân viên nào.</td></tr>
            <?php endif; ?>
            <?php foreach ($staffList as $s): ?>
              <tr>
                <td class="fw-semibold"><?= e($s['name']) ?></td>
                <td><?= e($s['phone']) ?></td>
                <td class="small text-muted"><?= e($s['note']) ?></td>
                <td><span class="badge bg-<?= $s['is_active'] ? 'success' : 'secondary' ?>"><?= $s['is_active'] ? 'Đang làm' : 'Ngừng' ?></span></td>
                <td class="text-end">
                  <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editStaff<?= $s['id'] ?>"><i class="bi bi-pencil"></i></button>
                  <form method="post" action="<?= url('/cleaning/delete_staff.php') ?>" class="d-inline" data-confirm="Xóa nhân viên &quot;<?= e($s['name']) ?>&quot;?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
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
      <div class="card-header">Thêm nhân viên mới</div>
      <div class="card-body">
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="add_staff" value="1">
          <div class="mb-3">
            <label class="form-label">Tên nhân viên *</label>
            <input type="text" name="name" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Số điện thoại</label>
            <input type="text" name="phone" class="form-control">
          </div>
          <div class="mb-3">
            <label class="form-label">Ghi chú</label>
            <input type="text" name="note" class="form-control">
          </div>
          <button type="submit" class="btn btn-success"><i class="bi bi-plus-lg"></i> Thêm nhân viên</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php foreach ($staffList as $s): ?>
  <div class="modal fade" id="editStaff<?= $s['id'] ?>" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="edit_staff" value="1">
          <input type="hidden" name="id" value="<?= $s['id'] ?>">
          <div class="modal-header">
            <h5 class="modal-title">Sửa nhân viên</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Tên *</label>
              <input type="text" name="name" class="form-control" required value="<?= e($s['name']) ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">Số điện thoại</label>
              <input type="text" name="phone" class="form-control" value="<?= e($s['phone']) ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">Ghi chú</label>
              <input type="text" name="note" class="form-control" value="<?= e($s['note']) ?>">
            </div>
            <div class="form-check">
              <input type="checkbox" name="is_active" id="active<?= $s['id'] ?>" class="form-check-input" <?= $s['is_active'] ? 'checked' : '' ?>>
              <label class="form-check-label" for="active<?= $s['id'] ?>">Đang làm việc</label>
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
