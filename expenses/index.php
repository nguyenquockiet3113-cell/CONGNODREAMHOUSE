<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$month = trim($_GET['month'] ?? date('Y-m'));
$category = trim($_GET['category'] ?? '');

$sql = "SELECT ex.*, r.room_code, ba.bank_name, ba.account_number FROM expenses ex
    LEFT JOIN rooms r ON r.id = ex.room_id
    LEFT JOIN bank_accounts ba ON ba.id = ex.bank_account_id
    WHERE 1=1";
$params = [];
if ($month !== '') {
    $sql .= " AND substr(ex.expense_date, 1, 7) = ?";
    $params[] = $month;
}
if ($category !== '') {
    $sql .= ' AND ex.category = ?';
    $params[] = $category;
}
$sql .= ' ORDER BY ex.expense_date DESC, ex.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll();

$total = array_sum(array_column($expenses, 'amount'));

$pageTitle = 'Chi phí';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="mb-0"><i class="bi bi-cash-coin"></i> Chi phí</h4>
  <a href="<?= url('/expenses/form.php') ?>" class="btn btn-success"><i class="bi bi-plus-lg"></i> Thêm chi phí</a>
</div>

<div class="row g-3 mb-3">
  <div class="col-sm-4">
    <div class="stat-card"><div class="text-muted small">Tổng chi phí (theo bộ lọc)</div><div class="stat-value text-danger"><?= money($total) ?></div></div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form class="row g-2 align-items-end">
      <div class="col-sm-3">
        <label class="form-label small mb-1">Tháng</label>
        <input type="month" name="month" class="form-control" value="<?= e($month) ?>">
      </div>
      <div class="col-sm-3">
        <label class="form-label small mb-1">Danh mục</label>
        <select name="category" class="form-select">
          <option value="">-- Tất cả --</option>
          <?php foreach (EXPENSE_CATEGORIES as $cat): ?>
            <option value="<?= e($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-2">
        <button class="btn btn-outline-secondary w-100"><i class="bi bi-search"></i> Lọc</button>
      </div>
      <div class="col-sm-2">
        <a href="<?= url('/expenses/index.php') ?>" class="btn btn-outline-secondary w-100">Bỏ lọc</a>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Ngày</th>
          <th>Danh mục</th>
          <th>Phòng</th>
          <th>Mô tả</th>
          <th>TK chi</th>
          <th>Số tiền</th>
          <th class="text-end">Thao tác</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$expenses): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">Chưa có chi phí nào.</td></tr>
        <?php endif; ?>
        <?php foreach ($expenses as $ex): ?>
          <tr>
            <td><?= vndate($ex['expense_date']) ?></td>
            <td><span class="badge bg-light text-dark border"><?= e($ex['category']) ?></span></td>
            <td><?= e($ex['room_code'] ?? '') ?></td>
            <td><?= e($ex['description']) ?></td>
            <td class="small"><?= $ex['bank_name'] ? e(trim($ex['bank_name'] . ($ex['account_number'] ? ' - ' . $ex['account_number'] : ''))) : '<span class="text-muted">Tiền mặt</span>' ?></td>
            <td class="text-danger fw-semibold"><?= money($ex['amount']) ?></td>
            <td class="text-end">
              <a href="<?= url('/expenses/form.php?id=' . $ex['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
              <form method="post" action="<?= url('/expenses/delete.php') ?>" class="d-inline" data-confirm="Xóa chi phí này?">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $ex['id'] ?>">
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
