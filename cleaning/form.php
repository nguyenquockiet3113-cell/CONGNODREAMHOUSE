<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$log = [
    'id' => 0, 'work_date' => date('Y-m-d'), 'staff_name' => '', 'room_code' => '',
    'bedrooms' => '', 'work_item' => '', 'work_type' => '', 'hours' => '',
    'price' => 0, 'plus' => 0, 'penalty' => 0, 'note' => '',
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

$staffList = $pdo->query(
    "SELECT name FROM cleaning_staff WHERE is_active = 1
     UNION SELECT DISTINCT staff_name FROM cleaning_logs
     ORDER BY name"
)->fetchAll(PDO::FETCH_COLUMN);
$rooms = $pdo->query('SELECT room_code, bedrooms FROM rooms ORDER BY room_code')->fetchAll();
$priceList = $pdo->query('SELECT * FROM cleaning_price_list ORDER BY work_type, unit_price')->fetchAll();
$workTypes = array_values(array_unique(array_column($priceList, 'work_type')));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $log['work_date'] = $_POST['work_date'] ?? '';
    $log['staff_name'] = trim($_POST['staff_name'] ?? '');
    $log['room_code'] = trim($_POST['room_code'] ?? '');
    $log['bedrooms'] = ($_POST['bedrooms'] ?? '') !== '' ? (int)$_POST['bedrooms'] : null;
    $log['work_item'] = trim($_POST['work_item'] ?? '');
    $log['work_type'] = trim($_POST['work_type'] ?? '');
    $log['hours'] = ($_POST['hours'] ?? '') !== '' ? (float)$_POST['hours'] : null;
    $log['price'] = (float)($_POST['price'] ?? 0);
    $log['plus'] = (float)($_POST['plus'] ?? 0);
    $log['penalty'] = (float)($_POST['penalty'] ?? 0);
    $log['note'] = trim($_POST['note'] ?? '');

    if (!$log['work_date']) $errors[] = 'Vui lòng chọn ngày.';
    if ($log['staff_name'] === '') $errors[] = 'Vui lòng nhập tên nhân viên.';

    if (!$errors) {
        if ($id) {
            $stmt = $pdo->prepare('UPDATE cleaning_logs SET work_date=?, staff_name=?, room_code=?, bedrooms=?, work_item=?, work_type=?, hours=?, price=?, plus=?, penalty=?, note=? WHERE id=?');
            $stmt->execute([$log['work_date'], $log['staff_name'], $log['room_code'], $log['bedrooms'], $log['work_item'], $log['work_type'], $log['hours'], $log['price'], $log['plus'], $log['penalty'], $log['note'], $id]);
            flash('success', 'Đã cập nhật.');
        } else {
            $now = date('Y-m-d H:i:s');
            $stmt = $pdo->prepare('INSERT INTO cleaning_logs (work_date, staff_name, room_code, bedrooms, work_item, work_type, hours, price, plus, penalty, note, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$log['work_date'], $log['staff_name'], $log['room_code'], $log['bedrooms'], $log['work_item'], $log['work_type'], $log['hours'], $log['price'], $log['plus'], $log['penalty'], $log['note'], $now]);
            flash('success', 'Đã thêm công việc.');
        }
        redirect('/cleaning/index.php');
    }
}

$pageTitle = $id ? 'Sửa công việc' : 'Thêm công việc';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="mb-0"><i class="bi bi-bucket"></i> <?= $id ? 'Sửa công việc' : 'Thêm công việc vệ sinh' ?></h4>
  <a href="<?= url('/cleaning/prices.php') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-tags"></i> Bảng giá</a>
</div>

<?php foreach ($errors as $err): ?>
  <div class="alert alert-danger py-2"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="card">
  <div class="card-body">
    <form method="post" id="cleanForm">
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

        <div class="col-md-3">
          <label class="form-label">Loại (OUT/LƯU...) *</label>
          <select name="work_type" id="work_type" class="form-select">
            <option value="">-- Chọn --</option>
            <?php foreach ($workTypes as $wt): ?>
              <option value="<?= e($wt) ?>" <?= $log['work_type'] === $wt ? 'selected' : '' ?>><?= e($wt) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="form-text">Tự tính giá theo Số PN ở bảng giá.</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Hạng mục (tùy chọn)</label>
          <select name="work_item" id="work_item" class="form-select">
            <option value="">-- Không chọn (dùng giá theo Số PN) --</option>
            <?php foreach ($priceList as $pl): ?>
              <option value="<?= e($pl['work_item']) ?>" data-type="<?= e($pl['work_type']) ?>" data-unit="<?= e($pl['unit']) ?>" data-price="<?= $pl['unit_price'] ?>" <?= $log['work_item'] === $pl['work_item'] ? 'selected' : '' ?>><?= e($pl['work_item']) ?> (<?= e($pl['work_type']) ?>)</option>
            <?php endforeach; ?>
          </select>
          <div class="form-text">Nếu chọn, giá của hạng mục sẽ ưu tiên hơn giá tự tính theo Số PN.</div>
        </div>
        <div class="col-md-2" id="hoursWrap" style="display:none;">
          <label class="form-label">Số giờ</label>
          <input type="number" step="0.5" name="hours" id="hours" class="form-control" value="<?= e($log['hours']) ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">Price (đ)</label>
          <input type="number" step="1000" name="price" id="price" class="form-control" value="<?= e($log['price']) ?>">
        </div>

        <div class="col-md-2">
          <label class="form-label">Plus - thưởng (đ)</label>
          <input type="number" step="1000" name="plus" class="form-control" value="<?= e($log['plus']) ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">Phạt (đ)</label>
          <input type="number" step="1000" name="penalty" class="form-control" value="<?= e($log['penalty']) ?>">
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
document.getElementById('cl_room_code').addEventListener('input', function () {
  var b = roomBedrooms[this.value];
  var bField = document.getElementById('cl_bedrooms');
  if (b) {
    bField.value = b;
    if (typeof applyAutoPrice === 'function') applyAutoPrice();
  }
});

var workItemSelect = document.getElementById('work_item');
var workTypeSelect = document.getElementById('work_type');
var bedroomsInput = document.getElementById('cl_bedrooms');
var hoursWrap = document.getElementById('hoursWrap');
var hoursInput = document.getElementById('hours');
var priceInput = document.getElementById('price');

// priceMap[work_type][work_item] = {unit, price}
var priceMap = {};
<?php foreach ($priceList as $pl): ?>
priceMap['<?= addslashes($pl['work_type']) ?>'] = priceMap['<?= addslashes($pl['work_type']) ?>'] || {};
priceMap['<?= addslashes($pl['work_type']) ?>']['<?= addslashes($pl['work_item']) ?>'] = { unit: '<?= addslashes($pl['unit']) ?>', price: <?= (float)$pl['unit_price'] ?> };
<?php endforeach; ?>

// Hạng mục cụ thể được chọn -> gia hang muc uu tien
function applyItemPrice() {
  var opt = workItemSelect.options[workItemSelect.selectedIndex];
  if (!opt || !opt.value) return false;
  workTypeSelect.value = opt.getAttribute('data-type');
  var unit = opt.getAttribute('data-unit');
  var unitPrice = parseFloat(opt.getAttribute('data-price')) || 0;
  if (unit === 'gio') {
    hoursWrap.style.display = '';
    if (!hoursInput.value) hoursInput.value = 1;
    priceInput.value = Math.round(unitPrice * (parseFloat(hoursInput.value) || 1));
  } else {
    hoursWrap.style.display = 'none';
    priceInput.value = unitPrice;
  }
  return true;
}

// Chua chon hang muc cu the -> tu tinh gia theo Loai + So PN
function applyAutoPrice() {
  if (applyItemPrice()) return; // hang muc da chon, uu tien gia hang muc
  hoursWrap.style.display = 'none';
  var type = workTypeSelect.value;
  var bedrooms = bedroomsInput.value;
  if (type && bedrooms && priceMap[type] && priceMap[type][bedrooms]) {
    priceInput.value = priceMap[type][bedrooms].price;
  }
}

workTypeSelect.addEventListener('change', applyAutoPrice);
bedroomsInput.addEventListener('input', applyAutoPrice);
workItemSelect.addEventListener('change', applyAutoPrice);
hoursInput.addEventListener('input', function () {
  var opt = workItemSelect.options[workItemSelect.selectedIndex];
  if (opt && opt.getAttribute('data-unit') === 'gio') {
    var unitPrice = parseFloat(opt.getAttribute('data-price')) || 0;
    priceInput.value = Math.round(unitPrice * (parseFloat(hoursInput.value) || 0));
  }
});
<?php if ($log['hours']): ?>hoursWrap.style.display = '';<?php endif; ?>
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
