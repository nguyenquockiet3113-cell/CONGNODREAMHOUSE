<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$room = ['id' => 0, 'room_code' => '', 'zone' => '', 'bedrooms' => 1, 'electricity_code' => '', 'water_code' => '', 'internet_code' => ''];
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

$zones = $pdo->query("SELECT DISTINCT zone FROM rooms WHERE zone IS NOT NULL AND zone != '' ORDER BY zone")->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $room['room_code'] = trim($_POST['room_code'] ?? '');
    $room['zone'] = trim($_POST['zone'] ?? '');
    $room['bedrooms'] = (int)($_POST['bedrooms'] ?? 1);
    $room['electricity_code'] = trim($_POST['electricity_code'] ?? '');
    $room['water_code'] = trim($_POST['water_code'] ?? '');
    $room['internet_code'] = trim($_POST['internet_code'] ?? '');

    if ($room['room_code'] === '') {
        $errors[] = 'Vui lòng nhập mã phòng.';
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
            $stmt = $pdo->prepare('UPDATE rooms SET room_code=?, zone=?, bedrooms=?, electricity_code=?, water_code=?, internet_code=?, updated_at=? WHERE id=?');
            $stmt->execute([$room['room_code'], $room['zone'], $room['bedrooms'], $room['electricity_code'], $room['water_code'], $room['internet_code'], $now, $id]);
            flash('success', 'Đã cập nhật phòng.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO rooms (room_code, zone, bedrooms, electricity_code, water_code, internet_code, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?)');
            $stmt->execute([$room['room_code'], $room['zone'], $room['bedrooms'], $room['electricity_code'], $room['water_code'], $room['internet_code'], $now, $now]);
            flash('success', 'Đã thêm phòng mới.');
        }
        redirect('/rooms/index.php');
    }
}

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
        <div class="col-md-5">
          <label class="form-label">Khu vực</label>
          <input type="text" name="zone" class="form-control" list="zoneList" placeholder="VD: Vinhomes Central Park" value="<?= e($room['zone']) ?>">
          <datalist id="zoneList">
            <?php foreach ($zones as $z): ?><option value="<?= e($z) ?>"><?php endforeach; ?>
          </datalist>
        </div>
        <div class="col-md-3">
          <label class="form-label">Số phòng ngủ</label>
          <input type="number" min="0" name="bedrooms" class="form-control" value="<?= e($room['bedrooms']) ?>">
        </div>
      </div>

      <hr>
      <div class="fw-semibold mb-2">Mã dịch vụ (dùng để tra cứu, tính tiền điện/nước theo chỉ số)</div>
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Mã điện (PE)</label>
          <input type="text" name="electricity_code" class="form-control" value="<?= e($room['electricity_code']) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Mã nước</label>
          <input type="text" name="water_code" class="form-control" value="<?= e($room['water_code']) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Mã Internet</label>
          <input type="text" name="internet_code" class="form-control" value="<?= e($room['internet_code']) ?>">
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
