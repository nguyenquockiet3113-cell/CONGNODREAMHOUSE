<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$log = [
    'id' => 0, 'work_date' => date('Y-m-d'), 'staff_name' => '', 'room_code' => '',
    'bedrooms' => '', 'work_item' => '', 'work_type' => '', 'price' => 0, 'plus' => 0, 'note' => '',
];
$errors = [];

if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM cleaning_logs WHERE id = ?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found) {
        flash('danger', 'Không tìm thấy công việc.');
        redirect('/cleaning/index.php');
    }
    $log = $found;
}

$staffList = $pdo->query('SELECT DISTINCT staff_name FROM cleaning_logs ORDER BY staff_name')->fetchAll(PDO::FETCH_COLUMN);
$rooms = $pdo->query('SELECT room_code, bedrooms FROM rooms ORDER BY room_code')->fetchAll();
$workItems = $pdo->query("SELECT DISTINCT work_item FROM cleaning_logs WHERE work_item IS NOT NULL AND work_item != '' ORDER BY work_item")->fetchAll(PDO::FETCH_COLUMN);
$workTypes = $pdo->query("SELECT DISTINCT work_type FROM cleaning_logs WHERE work_type IS NOT NULL AND work_type != '' ORDER BY work_type")->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $log['work_date'] = $_POST['work_date'] ?? '';
    $log['staff_name'] = trim($_POST['staff_name'] ?? '');
    $log['room_code'] = trim($_POST['room_code'] ?? '');
    $log['bedrooms'] = ($_POST['bedrooms'] ?? '') !== '' ? (int)$_POST['bedrooms'] : null;
    $log['work_item'] = trim($_POST['work_item'] ?? '');
    $log['work_type'] = trim($_POST['work_type'] ?? '');
    $log['price'] = (float)($_POST['price'] ?? 0);
    $log['plus'] = (float)($_POST['plus'] ?? 0);
    $log['note'] = trim($_POST['note'] ?? '');

    if (!$log['work_date']) $errors[] = 'Vui lòng chọn ngày.';
    if ($log['staff_name'] === '') $errors[] = 'Vui lòng nhập tên nhân viên.';

    if (!$errors) {
        if ($id) {
            $stmt = $pdo->prepare('UPDATE cleaning_logs SET work_date=?, staff_name=?, room_code=?, bedrooms=?, work_item=?, work_type=?, price=?, plus=?, note=? WHERE id=?');
            $stmt->execute([$log['work_date'], $log['staff_name'], $log['room_code'], $log['bedrooms'], $log['work_item'], $log['work_type'], $log['price'], $log['plus'], $log['note'], $id]);
            flash('success', 'Đã cập nhật.');
        } else {
            $now = date('Y-m-d H:i:s');
            $stmt = $pdo->prepare('INSERT INTO cleaning_logs (work_date, staff_name, room_code, bedrooms, work_item, work_type, price, plus, note, created_at) VALUES (?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$log['work_date'], $log['staff_name'], $log['room_code'], $log['bedrooms'], $log['work_item'], $log['work_type'], $log['price'], $log['plus'], $log['note'], $now]);
            flash('success', 'Đã thêm công việc.');
        }
        redirect('/cleaning/index.php');
    }
}

$pageTitle = $id ? 'Sửa công việc' : 'Thêm công việc';
require_once __DIR__ . '/../includes/header.php';
?>
<h4 class="mb-3"><i class="bi bi-bucket"></i> <?= $id ? 'Sửa công việc' : 'Thêm công việc vệ sinh' ?></h4>

<?php foreach ($errors as $err): ?>
  <div class="alert alert-danger py-2"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="card">
  <div class="card-body">
    <form method="post">
      <?= csrf_field() ?>
      <div class="row g-3">
        <div class="col-md-3">
          <label class="form-label">Ngày *</label>
          <input type="date" name="work_date" class="form-control" required value="<?= e($log['work_date']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Tên nhân viên *</label>
          <input type="text" name="staff_name" class="form-control" list="staffList" required value="<?= e($log['staff_name']) ?>">
          <datalist id="staffList">
            <?php foreach ($staffList as $s): ?><option value="<?= e($s) ?>"><?php endforeach; ?>
          </datalist>
        </div>
        <div class="col-md-3">
          <label class="form-label">Mã phòng</label>
          <input type="text" name="room_code" id="cl_room_code" class="form-control" list="roomList" value="<?= e($log['room_code']) ?>">
          <datalist id="roomList">
            <?php foreach ($rooms as $r): ?><option value="<?= e($r['room_code']) ?>"><?php endforeach; ?>
          </datalist>
        </div>
        <div class="col-md-3">
          <label class="form-label">Số PN</label>
          <input type="number" min="0" name="bedrooms" id="cl_bedrooms" class="form-control" value="<?= e($log['bedrooms']) ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label">Hạng mục (Change)</label>
          <input type="text" name="work_item" class="form-control" list="workItemList" placeholder="VD: Set up 1PN" value="<?= e($log['work_item']) ?>">
          <datalist id="workItemList">
            <?php foreach ($workItems as $w): ?><option value="<?= e($w) ?>"><?php endforeach; ?>
          </datalist>
        </div>
        <div class="col-md-4">
          <label class="form-label">Loại (Type)</label>
          <input type="text" name="work_type" class="form-control" list="workTypeList" value="<?= e($log['work_type']) ?>">
          <datalist id="workTypeList">
            <?php foreach ($workTypes as $w): ?><option value="<?= e($w) ?>"><?php endforeach; ?>
          </datalist>
        </div>

        <div class="col-md-2">
          <label class="form-label">Price (đ)</label>
          <input type="number" step="1000" name="price" class="form-control" value="<?= e($log['price']) ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">Plus (đ)</label>
          <input type="number" step="1000" name="plus" class="form-control" value="<?= e($log['plus']) ?>">
        </div>

        <div class="col-12">
          <label class="form-label">Note</label>
          <input type="text" name="note" class="form-control" value="<?= e($log['note']) ?>">
        </div>
      </div>
      <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-success"><i class="bi bi-check-lg"></i> Lưu</button>
        <a href="<?= url('/cleaning/index.php') ?>" class="btn btn-outline-secondary">Hủy</a>
      </div>
    </form>
  </div>
</div>

<script>
var roomBedrooms = <?= json_encode(array_column($rooms, 'bedrooms', 'room_code')) ?>;
document.getElementById('cl_room_code').addEventListener('change', function () {
  var b = roomBedrooms[this.value];
  var bField = document.getElementById('cl_bedrooms');
  if (b && !bField.value) bField.value = b;
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
