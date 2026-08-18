<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$user = ['id' => 0, 'full_name' => '', 'username' => '', 'role' => 'staff', 'is_active' => 1, 'permissions' => ''];
$errors = [];

if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found) {
        flash('danger', 'Không tìm thấy tài khoản.');
        redirect('/users/index.php');
    }
    $user = $found;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $user['full_name'] = trim($_POST['full_name'] ?? '');
    $user['username'] = trim($_POST['username'] ?? '');
    $user['role'] = $_POST['role'] ?? 'staff';
    $user['is_active'] = isset($_POST['is_active']) ? 1 : 0;
    $selectedModules = array_intersect($_POST['permissions'] ?? [], array_keys(APP_MODULES));
    $user['permissions'] = implode(',', $selectedModules);
    $password = $_POST['password'] ?? '';

    if ($user['full_name'] === '') $errors[] = 'Vui lòng nhập họ tên.';
    if ($user['username'] === '') $errors[] = 'Vui lòng nhập tên đăng nhập.';
    if (!$id && $password === '') $errors[] = 'Vui lòng nhập mật khẩu.';
    if ($password !== '' && strlen($password) < 6) $errors[] = 'Mật khẩu phải có ít nhất 6 ký tự.';
    if (!in_array($user['role'], ['admin', 'staff'], true)) $errors[] = 'Vai trò không hợp lệ.';

    if (!$errors) {
        $dupStmt = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
        $dupStmt->execute([$user['username'], $id]);
        if ($dupStmt->fetch()) $errors[] = 'Tên đăng nhập đã tồn tại.';
    }

    if (!$errors) {
        if ($id) {
            if ($password !== '') {
                $stmt = $pdo->prepare('UPDATE users SET full_name=?, username=?, role=?, permissions=?, is_active=?, password_hash=? WHERE id=?');
                $stmt->execute([$user['full_name'], $user['username'], $user['role'], $user['permissions'], $user['is_active'], password_hash($password, PASSWORD_DEFAULT), $id]);
            } else {
                $stmt = $pdo->prepare('UPDATE users SET full_name=?, username=?, role=?, permissions=?, is_active=? WHERE id=?');
                $stmt->execute([$user['full_name'], $user['username'], $user['role'], $user['permissions'], $user['is_active'], $id]);
            }
            flash('success', 'Đã cập nhật tài khoản.');
        } else {
            $now = date('Y-m-d H:i:s');
            $stmt = $pdo->prepare('INSERT INTO users (full_name, username, password_hash, role, permissions, is_active, created_at) VALUES (?,?,?,?,?,?,?)');
            $stmt->execute([$user['full_name'], $user['username'], password_hash($password, PASSWORD_DEFAULT), $user['role'], $user['permissions'], $user['is_active'], $now]);
            flash('success', 'Đã thêm tài khoản mới.');
        }
        redirect('/users/index.php');
    }
}

$pageTitle = $id ? 'Sửa tài khoản' : 'Thêm tài khoản';
require_once __DIR__ . '/../includes/header.php';
?>
<h4 class="mb-3"><i class="bi bi-person-gear"></i> <?= $id ? 'Sửa tài khoản' : 'Thêm tài khoản mới' ?></h4>

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
          <input type="text" name="full_name" class="form-control" required value="<?= e($user['full_name']) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Tên đăng nhập *</label>
          <input type="text" name="username" class="form-control" required value="<?= e($user['username']) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label"><?= $id ? 'Mật khẩu mới (để trống nếu không đổi)' : 'Mật khẩu *' ?></label>
          <input type="password" name="password" class="form-control" <?= $id ? '' : 'required' ?>>
        </div>
        <div class="col-md-3">
          <label class="form-label">Vai trò</label>
          <select name="role" class="form-select">
            <option value="staff" <?= $user['role'] === 'staff' ? 'selected' : '' ?>>Nhân viên</option>
            <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Quản trị</option>
          </select>
        </div>
        <div class="col-md-3 d-flex align-items-center">
          <div class="form-check mt-4">
            <input type="checkbox" name="is_active" id="is_active" class="form-check-input" <?= $user['is_active'] ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_active">Đang hoạt động</label>
          </div>
        </div>
      </div>

      <?php $selectedPerms = array_filter(explode(',', $user['permissions'])); ?>
      <div id="permissionsBlock" class="mt-4" style="<?= $user['role'] === 'admin' ? 'display:none;' : '' ?>">
        <label class="form-label fw-semibold">Quyền truy cập từng mục (chỉ áp dụng cho Nhân viên)</label>
        <div class="form-text mb-2">Bỏ trống mục nào thì tài khoản này sẽ không nhìn thấy và không truy cập được mục đó.</div>
        <div class="row g-2">
          <?php foreach (APP_MODULES as $key => $label): ?>
            <div class="col-md-4">
              <div class="form-check">
                <input type="checkbox" name="permissions[]" value="<?= e($key) ?>" id="perm_<?= e($key) ?>" class="form-check-input" <?= in_array($key, $selectedPerms, true) ? 'checked' : '' ?>>
                <label class="form-check-label" for="perm_<?= e($key) ?>"><?= e($label) ?></label>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-success"><i class="bi bi-check-lg"></i> Lưu</button>
        <a href="<?= url('/users/index.php') ?>" class="btn btn-outline-secondary">Hủy</a>
      </div>
    </form>
  </div>
</div>

<script>
document.querySelector('select[name="role"]').addEventListener('change', function () {
  document.getElementById('permissionsBlock').style.display = this.value === 'admin' ? 'none' : '';
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
