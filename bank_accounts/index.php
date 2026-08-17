<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$accounts = $pdo->query('SELECT * FROM bank_accounts ORDER BY bank_name')->fetchAll();

$pageTitle = 'Tài khoản ngân hàng';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="mb-0"><i class="bi bi-bank"></i> Tài khoản ngân hàng</h4>
  <div class="d-flex gap-2">
    <a href="<?= url('/reconciliation/index.php') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Về trang đối soát</a>
    <a href="<?= url('/bank_accounts/form.php') ?>" class="btn btn-success"><i class="bi bi-plus-lg"></i> Thêm tài khoản</a>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Ngân hàng</th>
          <th>Số tài khoản</th>
          <th>Chủ tài khoản</th>
          <th>Ghi chú</th>
          <th class="text-end">Thao tác</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$accounts): ?>
          <tr><td colspan="5" class="text-center text-muted py-4">Chưa có tài khoản ngân hàng nào.</td></tr>
        <?php endif; ?>
        <?php foreach ($accounts as $a): ?>
          <tr>
            <td class="fw-semibold"><?= e($a['bank_name']) ?></td>
            <td><?= e($a['account_number']) ?></td>
            <td><?= e($a['account_holder']) ?></td>
            <td><?= e($a['note']) ?></td>
            <td class="text-end">
              <a href="<?= url('/bank_accounts/form.php?id=' . $a['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
              <form method="post" action="<?= url('/bank_accounts/delete.php') ?>" class="d-inline" data-confirm="Xóa tài khoản <?= e($a['bank_name']) ?>?">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $a['id'] ?>">
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
