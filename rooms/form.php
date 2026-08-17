<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$room = [
    'id' => 0, 'room_code' => '', 'zone' => '', 'bedrooms' => 1, 'floor' => '', 'room_type' => '', 'area_m2' => '',
    'monthly_price' => '', 'short_term_price' => '', 'max_occupants' => 1,
    'status' => 'trong', 'description' => '',
];
$errors = [];

if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM rooms WHERE id = ?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found) {
        flash('danger', 'Không tìm thấy phòng.');
        redirect('/rooms/index.php');
    }
    $room = $found;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $room['room_code'] = trim($_POST['room_code'] ?? '');
    $room['zone'] = trim($_POST['zone'] ?? '');
    $room['bedrooms'] = (int)($_POST['bedrooms'] ?? 1);
    $room['floor'] = trim($_POST['floor'] ?? '');
    $room['room_type'] = trim($_POST['room_type'] ?? '');
    $room['area_m2'] = ($_POST['area_m2'] ?? '') !== '' ? (float)$_POST['area_m2'] : null;
    $room['monthly_price'] = (float)($_POST['monthly_price'] ?? 0);
    $room['short_term_price'] = (float)($_POST['short_term_price'] ?? 0);
    $room['max_occupants'] = (int)($_POST['max_occupants'] ?? 1);
    $room['status'] = $_POST['status'] ?? 'trong';
    $room['description'] = trim($_POST['description'] ?? '');

    if ($room['room_code'] === '') {
        $errors[] = 'Vui lòng nhập mã phòng.';
    }
    if (!array_key_exists($room['status'], ROOM_STATUS_LABELS)) {
        $errors[] = 'Trạng thái không hợp lệ.';
    }

    if (!$errors) {
        $dupStmt = $pdo->prepare('SELECT id FROM rooms WHERE room_code = ? AND id != ?');
        $dupStmt->execute([$room['room_code'], $id]);
        if ($dupStmt->fetch()) {
            $errors[] = 'Mã phòng đã tồn tại.';
        }
    }

    if (!$errors) {
        $now = date('Y-m-d H:i:s');
        if ($id) {
            $stmt = $pdo->prepare(
                'UPDATE rooms SET room_code=?, zone=?, bedrooms=?, floor=?, room_type=?, area_m2=?, monthly_price=?, short_term_price=?, max_occupants=?, status=?, description=?, updated_at=? WHERE id=?'
            );
            $stmt->execute([
                $room['room_code'], $room['zone'], $room['bedrooms'], $room['floor'], $room['room_type'], $room['area_m2'],
                $room['monthly_price'], $room['short_term_price'], $room['max_occupants'],
                $room['status'], $room['description'], $now, $id,
            ]);
            flash('success', 'Đã cập nhật phòng.');
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO rooms (room_code, zone, bedrooms, floor, room_type, area_m2, monthly_price, short_term_price, max_occupants, status, description, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $stmt->execute([
                $room['room_code'], $room['zone'], $room['bedrooms'], $room['floor'], $room['room_type'], $room['area_m2'],
                $room['monthly_price'], $room['short_term_price'], $room['max_occupants'],
                $room['status'], $room['description'], $now, $now,
            ]);
            flash('success', 'Đã thêm phòng mới.');
        }
        redirect('/rooms/index.php');
    }
}

$zones = $pdo->query("SELECT DISTINCT zone FROM rooms WHERE zone IS NOT NULL AND zone != '' ORDER BY zone")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = $id ? 'Sửa phòng' : 'Thêm phòng';
require_once __DIR__ . '/../includes/header.php';
?>
<h4 class="mb-3"><i class="bi bi-door-closed"></i> <?= $id ? 'Sửa phòng' : 'Thêm phòng mới' ?></h4>

<?php foreach ($errors as $err): ?>
  <div class="alert alert-danger py-2"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="card">
  <div class="card-body">
    <form method="post">
      <?= csrf_field() ?>
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Mã phòng *</label>
          <input type="text" name="room_code" class="form-control" required value="<?= e($room['room_code']) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Khu vực</label>
          <input type="text" name="zone" class="form-control" list="zoneList" placeholder="VD: Vinhomes Central Park" value="<?= e($room['zone']) ?>">
          <datalist id="zoneList">
            <?php foreach ($zones as $z): ?><option value="<?= e($z) ?>"><?php endforeach; ?>
          </datalist>
        </div>
        <div class="col-md-4">
          <label class="form-label">Số phòng ngủ</label>
          <input type="number" min="0" name="bedrooms" class="form-control" value="<?= e($room['bedrooms']) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Tầng</label>
          <input type="text" name="floor" class="form-control" value="<?= e($room['floor']) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Loại phòng</label>
          <input type="text" name="room_type" class="form-control" placeholder="Studio, 1PN, 2PN..." value="<?= e($room['room_type']) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Diện tích (m²)</label>
          <input type="number" step="0.1" name="area_m2" class="form-control" value="<?= e($room['area_m2']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Số người tối đa</label>
          <input type="number" name="max_occupants" class="form-control" value="<?= e($room['max_occupants']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Giá thuê / tháng (đ)</label>
          <input type="number" step="1000" name="monthly_price" class="form-control" value="<?= e($room['monthly_price']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Giá thuê / đêm (đ)</label>
          <input type="number" step="1000" name="short_term_price" class="form-control" value="<?= e($room['short_term_price']) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Trạng thái</label>
          <select name="status" class="form-select">
            <?php foreach (ROOM_STATUS_LABELS as $k => $v): ?>
              <option value="<?= e($k) ?>" <?= $room['status'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12">
          <label class="form-label">Mô tả</label>
          <textarea name="description" class="form-control" rows="3"><?= e($room['description']) ?></textarea>
        </div>
      </div>
      <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-success"><i class="bi bi-check-lg"></i> Lưu</button>
        <a href="<?= url('/rooms/index.php') ?>" class="btn btn-outline-secondary">Hủy</a>
      </div>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
