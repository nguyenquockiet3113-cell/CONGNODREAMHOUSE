<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

$users = $pdo->query('SELECT * FROM users ORDER BY id ASC')->fetchAll();

$pageTitle = 'Tài khoản';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="mb-0"><i class="bi bi-person-gear"></i> Tài khoản đăng nhập</h4>
  <a href="<?= url('/users/form.php') ?>" class="btn btn-success"><i class="bi bi-plus-lg"></i> Thêm tài khoản</a>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Họ tên</th>
          <th>Tên đăng nhập</th>
          <th>Vai trò</th>
          <th>Quyền truy cập</th>
          <th>Trạng thái</th>
          <th class="text-end">Thao tác</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td class="fw-semibold"><?= e($u['full_name']) ?></td>
            <td><?= e($u['username']) ?></td>
            <td><?= $u['role'] === 'admin' ? 'Quản trị' : 'Nhân viên' ?></td>
            <td class="small">
              <?php if ($u['role'] === 'admin'): ?>
                <span class="text-muted">Toàn quyền</span>
              <?php else: ?>
                <?php $perms = array_filter(explode(',', $u['permissions'] ?? '')); ?>
                <?php if (!$perms): ?>
                  <span class="text-muted">Chưa cấp quyền nào</span>
                <?php else: ?>
                  <?php foreach ($perms as $p): ?>
                    <span class="badge bg-light text-dark border"><?= e(APP_MODULES[$p] ?? $p) ?></span>
                  <?php endforeach; ?>
                <?php endif; ?>
              <?php endif; ?>
            </td>
            <td><span class="badge bg-<?= $u['is_active'] ? 'success' : 'secondary' ?>"><?= $u['is_active'] ? 'Đang hoạt động' : 'Đã khóa' ?></span></td>
            <td class="text-end">
              <a href="<?= url('/users/form.php?id=' . $u['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
              <?php if ((int)$u['id'] !== (int)current_user()['id']): ?>
              <form method="post" action="<?= url('/users/delete.php') ?>" class="d-inline" data-confirm="Xóa tài khoản <?= e($u['username']) ?>?">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
