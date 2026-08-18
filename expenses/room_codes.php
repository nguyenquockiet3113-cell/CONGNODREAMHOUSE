<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$zoneFilter = trim($_GET['zone'] ?? '');
$search = trim($_GET['q'] ?? '');

$sql = 'SELECT id, room_code, zone, electricity_code, water_code FROM rooms WHERE 1=1';
$params = [];
if ($zoneFilter !== '') {
    $sql .= ' AND zone = ?';
    $params[] = $zoneFilter;
}
if ($search !== '') {
    $sql .= ' AND room_code LIKE ?';
    $params[] = "%$search%";
}
$sql .= ' ORDER BY zone ASC, room_code ASC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rooms = $stmt->fetchAll();

$zones = $pdo->query("SELECT DISTINCT zone FROM rooms WHERE zone IS NOT NULL AND zone != '' ORDER BY zone")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Mã điện & nước theo phòng';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="mb-0"><i class="bi bi-lightning-charge"></i> Mã điện &amp; nước theo phòng</h4>
  <a href="<?= url('/expenses/index.php') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Về Chi phí</a>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form class="row g-2 align-items-end">
      <div class="col-sm-3">
        <label class="form-label small mb-1">Tìm kiếm</label>
        <input type="text" name="q" class="form-control" placeholder="Mã phòng..." value="<?= e($search) ?>">
      </div>
      <div class="col-sm-3">
        <label class="form-label small mb-1">Khu vực</label>
        <select name="zone" class="form-select">
          <option value="">-- Tất cả khu vực --</option>
          <?php foreach ($zones as $z): ?>
            <option value="<?= e($z) ?>" <?= $zoneFilter === $z ? 'selected' : '' ?>><?= e($z) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-2">
        <button class="btn btn-outline-secondary w-100"><i class="bi bi-search"></i> Lọc</button>
      </div>
    </form>
  </div>
</div>

<?php if (!$rooms): ?>
  <div class="card"><div class="text-center text-muted py-5">Không có phòng nào.</div></div>
<?php else: ?>
<form method="post" action="<?= url('/expenses/update_room_codes.php') ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="return_qs" value="<?= e($_SERVER['QUERY_STRING'] ?? '') ?>">
  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Mã phòng</th>
            <th>Khu vực</th>
            <th style="width:220px;">Mã điện (PE)</th>
            <th style="width:220px;">Mã nước</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rooms as $r): ?>
            <tr>
              <td class="fw-semibold"><?= e($r['room_code']) ?></td>
              <td class="small text-muted"><?= e($r['zone']) ?></td>
              <td>
                <input type="hidden" name="room_id[]" value="<?= $r['id'] ?>">
                <input type="text" name="electricity_code[]" class="form-control form-control-sm" value="<?= e($r['electricity_code']) ?>">
              </td>
              <td>
                <input type="text" name="water_code[]" class="form-control form-control-sm" value="<?= e($r['water_code']) ?>">
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="mt-3">
    <button type="submit" class="btn btn-success"><i class="bi bi-check-lg"></i> Lưu tất cả</button>
  </div>
</form>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
