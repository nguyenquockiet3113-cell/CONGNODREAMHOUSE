<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$zoneFilter = trim($_GET['zone'] ?? '');
$bedroomsFilter = trim($_GET['bedrooms'] ?? '');
$search = trim($_GET['q'] ?? '');
$availFilter = trim($_GET['avail'] ?? '');
$sortOptions = ['az', 'za', 'bedrooms_asc', 'bedrooms_desc'];
$sortOrder = in_array(trim($_GET['sort'] ?? 'az'), $sortOptions, true) ? trim($_GET['sort']) : 'az';
$today = date('Y-m-d');
$fromDate = trim($_GET['from'] ?? '') ?: $today;
$toDate = trim($_GET['to'] ?? '') ?: $fromDate;
if ($toDate < $fromDate) { $toDate = $fromDate; }

// Phong co khach trong khoang ngay dang loc (deal nao chong lan voi [fromDate, toDate])
$occStmt = $pdo->prepare(
    'SELECT room_code, guest_name, checkin_date, checkout_date FROM deals
     WHERE checkin_date <= ? AND checkout_date > ? ORDER BY checkin_date ASC'
);
$occStmt->execute([$toDate, $fromDate]);
$occupiedMap = [];
foreach ($occStmt->fetchAll() as $d) {
    $occupiedMap[$d['room_code']][] = $d;
}

$sql = 'SELECT * FROM rooms WHERE 1=1';
$params = [];
if ($zoneFilter !== '') {
    $sql .= ' AND zone = ?';
    $params[] = $zoneFilter;
}
if ($bedroomsFilter !== '') {
    $sql .= ' AND bedrooms = ?';
    $params[] = (int)$bedroomsFilter;
}
if ($search !== '') {
    $sql .= ' AND (room_code LIKE ? OR zone LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
switch ($sortOrder) {
    case 'za':
        $sql .= ' ORDER BY zone ASC, room_code DESC';
        break;
    case 'bedrooms_asc':
        $sql .= ' ORDER BY zone ASC, bedrooms ASC, room_code ASC';
        break;
    case 'bedrooms_desc':
        $sql .= ' ORDER BY zone ASC, bedrooms DESC, room_code ASC';
        break;
    default:
        $sql .= ' ORDER BY zone ASC, room_code ASC';
}
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$allRooms = $stmt->fetchAll();

if ($availFilter === 'occupied') {
    $rooms = array_values(array_filter($allRooms, fn($r) => isset($occupiedMap[$r['room_code']])));
} elseif ($availFilter === 'vacant') {
    $rooms = array_values(array_filter($allRooms, fn($r) => !isset($occupiedMap[$r['room_code']])));
} else {
    $rooms = $allRooms;
}

$occupiedCount = count(array_filter($allRooms, fn($r) => isset($occupiedMap[$r['room_code']])));
$vacantCount = count($allRooms) - $occupiedCount;

$zones = $pdo->query("SELECT DISTINCT zone FROM rooms WHERE zone IS NOT NULL AND zone != '' ORDER BY zone")->fetchAll(PDO::FETCH_COLUMN);
$bedroomOptions = $pdo->query('SELECT DISTINCT bedrooms FROM rooms WHERE bedrooms IS NOT NULL ORDER BY bedrooms')->fetchAll(PDO::FETCH_COLUMN);
$occupiedSet = $occupiedMap;

$pageTitle = 'Danh sách phòng';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="mb-0"><i class="bi bi-door-closed"></i> Danh sách phòng</h4>
  <div class="d-flex gap-2">
    <a href="<?= url('/rooms/export.php') ?>" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-arrow-down"></i> Xuất CSV</a>
    <a href="<?= url('/rooms/import.php') ?>" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-arrow-up"></i> Nhập từ Excel</a>
    <a href="<?= url('/rooms/form.php') ?>" class="btn btn-success"><i class="bi bi-plus-lg"></i> Thêm phòng</a>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-sm-4">
    <div class="stat-card"><div class="text-muted small">Tổng số phòng (theo bộ lọc)</div><div class="stat-value"><?= count($allRooms) ?></div></div>
  </div>
  <div class="col-sm-4">
    <div class="stat-card"><div class="text-muted small">Đang có khách <?= $fromDate === $toDate ? '(' . vndate($fromDate) . ')' : '(' . vndate($fromDate) . ' - ' . vndate($toDate) . ')' ?></div><div class="stat-value text-primary"><?= $occupiedCount ?></div></div>
  </div>
  <div class="col-sm-4">
    <div class="stat-card"><div class="text-muted small">Còn trống cả khoảng này</div><div class="stat-value text-success"><?= $vacantCount ?></div></div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form class="row g-2 align-items-end">
      <div class="col-sm-3">
        <label class="form-label small mb-1">Tìm kiếm</label>
        <input type="text" name="q" class="form-control" placeholder="Mã phòng, khu vực..." value="<?= e($search) ?>">
      </div>
      <div class="col-sm-2">
        <label class="form-label small mb-1">Khu vực</label>
        <select name="zone" class="form-select">
          <option value="">-- Tất cả khu vực --</option>
          <?php foreach ($zones as $z): ?>
            <option value="<?= e($z) ?>" <?= $zoneFilter === $z ? 'selected' : '' ?>><?= e($z) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-1">
        <label class="form-label small mb-1">Số PN</label>
        <select name="bedrooms" class="form-select">
          <option value="">-- Tất cả --</option>
          <?php foreach ($bedroomOptions as $b): ?>
            <option value="<?= (int)$b ?>" <?= $bedroomsFilter === (string)$b ? 'selected' : '' ?>><?= (int)$b ?> PN</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-2">
        <label class="form-label small mb-1">Từ ngày</label>
        <input type="date" name="from" class="form-control" value="<?= e($fromDate) ?>">
      </div>
      <div class="col-sm-2">
        <label class="form-label small mb-1">Đến ngày</label>
        <input type="date" name="to" class="form-control" value="<?= e($toDate) ?>">
      </div>
      <div class="col-sm-2">
        <label class="form-label small mb-1">Tình trạng</label>
        <select name="avail" class="form-select">
          <option value="">-- Tất cả --</option>
          <option value="occupied" <?= $availFilter === 'occupied' ? 'selected' : '' ?>>Đang có khách</option>
          <option value="vacant" <?= $availFilter === 'vacant' ? 'selected' : '' ?>>Còn trống</option>
        </select>
      </div>
      <div class="col-sm-2">
        <label class="form-label small mb-1">Sắp xếp</label>
        <select name="sort" class="form-select">
          <option value="az" <?= $sortOrder === 'az' ? 'selected' : '' ?>>Mã phòng A - Z</option>
          <option value="za" <?= $sortOrder === 'za' ? 'selected' : '' ?>>Mã phòng Z - A</option>
          <option value="bedrooms_asc" <?= $sortOrder === 'bedrooms_asc' ? 'selected' : '' ?>>Số PN tăng dần</option>
          <option value="bedrooms_desc" <?= $sortOrder === 'bedrooms_desc' ? 'selected' : '' ?>>Số PN giảm dần</option>
        </select>
      </div>
      <div class="col-sm-2">
        <button class="btn btn-outline-secondary w-100"><i class="bi bi-search"></i> Lọc</button>
      </div>
      <div class="col-sm-2">
        <a href="<?= url('/rooms/index.php') ?>" class="btn btn-outline-secondary w-100">Bỏ lọc (hôm nay)</a>
      </div>
    </form>
  </div>
</div>

<?php if (!$rooms): ?>
  <div class="card"><div class="text-center text-muted py-5">Chưa có phòng nào.</div></div>
<?php endif; ?>

<?php
$grouped = [];
foreach ($rooms as $r) {
    $zoneKey = $r['zone'] !== null && $r['zone'] !== '' ? $r['zone'] : 'Chưa phân khu vực';
    $grouped[$zoneKey][] = $r;
}
?>

<?php foreach ($grouped as $zoneName => $zoneRooms): ?>
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="bi bi-geo-alt"></i> Khu vực: <strong><?= e($zoneName) ?></strong></span>
      <span class="badge bg-light text-dark border"><?= count($zoneRooms) ?> phòng</span>
    </div>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Mã phòng</th>
            <th>Số phòng ngủ</th>
            <th>Tình trạng</th>
            <th class="text-end">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($zoneRooms as $r): ?>
            <?php $roomDeals = $occupiedMap[$r['room_code']] ?? []; $occupied = !empty($roomDeals); ?>
            <tr>
              <td class="fw-semibold">
                <span class="status-dot bg-<?= $occupied ? 'primary' : 'success' ?>" title="<?= $occupied ? 'Đang có khách' : 'Còn trống' ?>"></span>
                <a href="<?= url('/rooms/form.php?id=' . $r['id']) ?>" class="text-decoration-none text-reset"><?= e($r['room_code']) ?></a>
              </td>
              <td><?= (int)$r['bedrooms'] ?> PN</td>
              <td class="small">
                <?php if ($occupied): ?>
                  <span class="text-primary"><?= e($roomDeals[0]['guest_name']) ?> — đến <?= vndate($roomDeals[0]['checkout_date']) ?></span>
                  <?php if (count($roomDeals) > 1): ?>
                    <span class="text-muted">(+<?= count($roomDeals) - 1 ?> lượt khác)</span>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="text-success">Trống</span>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <a href="<?= url('/rooms/form.php?id=' . $r['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                <form method="post" action="<?= url('/rooms/delete.php') ?>" class="d-inline" data-confirm="Xóa phòng <?= e($r['room_code']) ?>?">
                  <?= csrf_field() ?>
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
<?php endforeach; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
