<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$search = trim($_GET['q'] ?? '');
$sql = 'SELECT * FROM tenants WHERE 1=1';
$params = [];
if ($search !== '') {
    $sql .= ' AND (full_name LIKE ? OR phone LIKE ? OR id_card_number LIKE ?)';
    $params = ["%$search%", "%$search%", "%$search%"];
}
$sql .= ' ORDER BY full_name ASC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tenants = $stmt->fetchAll();

$pageTitle = 'Khách thuê';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="mb-0"><i class="bi bi-people"></i> Khách thuê</h4>
  <a href="<?= url('/tenants/form.php') ?>" class="btn btn-success"><i class="bi bi-plus-lg"></i> Thêm khách thuê</a>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form class="row g-2 align-items-end">
      <div class="col-sm-5">
        <input type="text" name="q" class="form-control" placeholder="Tên, SĐT, CCCD..." value="<?= e($search) ?>">
      </div>
      <div class="col-sm-2">
        <button class="btn btn-outline-secondary w-100"><i class="bi bi-search"></i> Tìm</button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Họ tên</th>
          <th>SĐT</th>
          <th>Email</th>
          <th>Số CCCD/CMND</th>
          <th class="text-end">Thao tác</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$tenants): ?>
          <tr><td colspan="5" class="text-center text-muted py-4">Chưa có khách thuê nào.</td></tr>
        <?php endif; ?>
        <?php foreach ($tenants as $t): ?>
          <tr>
            <td class="fw-semibold"><?= e($t['full_name']) ?></td>
            <td><?= e($t['phone']) ?></td>
            <td><?= e($t['email']) ?></td>
            <td><?= e($t['id_card_number']) ?></td>
            <td class="text-end">
              <a href="<?= url('/tenants/form.php?id=' . $t['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
              <form method="post" action="<?= url('/tenants/delete.php') ?>" class="d-inline" data-confirm="Xóa khách thuê <?= e($t['full_name']) ?>?">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $t['id'] ?>">
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
