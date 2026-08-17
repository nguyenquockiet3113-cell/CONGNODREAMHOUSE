<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$staffFilter = trim($_GET['staff'] ?? '');
$fromDate = $_GET['from'] ?? date('Y-m-01');
$toDate = $_GET['to'] ?? date('Y-m-d');

$sql = 'SELECT * FROM cleaning_logs WHERE work_date BETWEEN ? AND ?';
$params = [$fromDate, $toDate];
if ($staffFilter !== '') {
    $sql .= ' AND staff_name = ?';
    $params[] = $staffFilter;
}
$sql .= ' ORDER BY work_date DESC, id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$staffList = $pdo->query('SELECT DISTINCT staff_name FROM cleaning_logs ORDER BY staff_name')->fetchAll(PDO::FETCH_COLUMN);

// Tong luong theo tung nhan vien (trong khoang loc ngay, khong phu thuoc staffFilter de hien thi bang tong)
$sumSql = 'SELECT staff_name, COALESCE(SUM(price + plus),0) total FROM cleaning_logs WHERE work_date BETWEEN ? AND ? GROUP BY staff_name ORDER BY total DESC';
$sumStmt = $pdo->prepare($sumSql);
$sumStmt->execute([$fromDate, $toDate]);
$staffTotals = $sumStmt->fetchAll();
$grandTotal = array_sum(array_column($staffTotals, 'total'));

$filteredTotal = 0;
foreach ($logs as $l) { $filteredTotal += (float)$l['price'] + (float)$l['plus']; }

$pageTitle = 'Tiền lương vệ sinh';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="mb-0"><i class="bi bi-bucket"></i> Tiền lương vệ sinh</h4>
  <a href="<?= url('/cleaning/form.php') ?>" class="btn btn-success"><i class="bi bi-plus-lg"></i> Thêm công việc</a>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card mb-3">
      <div class="card-body">
        <form class="row g-2 align-items-end">
          <div class="col-sm-4">
            <label class="form-label small mb-1">Nhân viên</label>
            <select name="staff" class="form-select">
              <option value="">-- Tất cả --</option>
              <?php foreach ($staffList as $s): ?>
                <option value="<?= e($s) ?>" <?= $staffFilter === $s ? 'selected' : '' ?>><?= e($s) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-sm-3">
            <label class="form-label small mb-1">Từ ngày</label>
            <input type="date" name="from" class="form-control" value="<?= e($fromDate) ?>">
          </div>
          <div class="col-sm-3">
            <label class="form-label small mb-1">Đến ngày</label>
            <input type="date" name="to" class="form-control" value="<?= e($toDate) ?>">
          </div>
          <div class="col-sm-2">
            <button class="btn btn-outline-secondary w-100"><i class="bi bi-search"></i> Lọc</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-header d-flex justify-content-between">
        <span>Bảng công</span>
        <strong><?= money($filteredTotal) ?></strong>
      </div>
      <div class="table-responsive">
        <table class="table table-hover table-sm mb-0">
          <thead>
            <tr>
              <th>Ngày</th>
              <th>Tên</th>
              <th>Code</th>
              <th>PN</th>
              <th>Hạng mục</th>
              <th>Loại</th>
              <th class="text-end">Price</th>
              <th class="text-end">Plus</th>
              <th class="text-end">Total</th>
              <th>Note</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$logs): ?>
              <tr><td colspan="11" class="text-center text-muted py-4">Chưa có dữ liệu.</td></tr>
            <?php endif; ?>
            <?php foreach ($logs as $l): ?>
              <tr>
                <td><?= vndate($l['work_date']) ?></td>
                <td class="fw-semibold"><?= e($l['staff_name']) ?></td>
                <td><?= e($l['room_code']) ?></td>
                <td><?= e($l['bedrooms']) ?></td>
                <td><?= e($l['work_item']) ?></td>
                <td><?= e($l['work_type']) ?></td>
                <td class="text-end"><?= money($l['price']) ?></td>
                <td class="text-end"><?= $l['plus'] > 0 ? money($l['plus']) : '' ?></td>
                <td class="text-end fw-semibold"><?= money((float)$l['price'] + (float)$l['plus']) ?></td>
                <td class="small"><?= e($l['note']) ?></td>
                <td class="text-end">
                  <a href="<?= url('/cleaning/form.php?id=' . $l['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                  <form method="post" action="<?= url('/cleaning/delete.php') ?>" class="d-inline" data-confirm="Xóa công việc này?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $l['id'] ?>">
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

  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">Lương vệ sinh theo nhân viên</div>
      <div class="list-group list-group-flush">
        <?php if (!$staffTotals): ?>
          <div class="list-group-item text-muted small">Chưa có dữ liệu.</div>
        <?php endif; ?>
        <?php foreach ($staffTotals as $st): ?>
          <a href="<?= url('/cleaning/index.php?staff=' . urlencode($st['staff_name']) . '&from=' . $fromDate . '&to=' . $toDate) ?>" class="list-group-item list-group-item-action d-flex justify-content-between">
            <span><?= e($st['staff_name']) ?></span>
            <strong><?= money($st['total']) ?></strong>
          </a>
        <?php endforeach; ?>
      </div>
      <div class="card-footer d-flex justify-content-between fw-semibold">
        <span>TỔNG TIỀN</span>
        <span><?= money($grandTotal) ?></span>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
