<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$errors = [];
$workDate = $_POST['work_date'] ?? date('Y-m-d');
$staffName = trim($_POST['staff_name'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $workDate = $_POST['work_date'] ?? '';
    $staffName = trim($_POST['staff_name'] ?? '');

    if (!$workDate) $errors[] = 'Vui lòng chọn ngày làm việc.';
    if ($staffName === '') $errors[] = 'Vui lòng chọn nhân viên.';

    $roomCodes = $_POST['room_code'] ?? [];
    $bedroomsArr = $_POST['bedrooms'] ?? [];
    $workTypes = $_POST['work_type'] ?? [];
    $workItems = $_POST['work_item'] ?? [];
    $hoursArr = $_POST['hours'] ?? [];
    $prices = $_POST['price'] ?? [];
    $pluses = $_POST['plus'] ?? [];
    $penalties = $_POST['penalty'] ?? [];
    $notes = $_POST['note'] ?? [];

    $validRows = [];
    foreach ($roomCodes as $i => $rc) {
        $rc = trim($rc);
        if ($rc === '') continue;
        $validRows[] = [
            'room_code' => $rc,
            'bedrooms' => ($bedroomsArr[$i] ?? '') !== '' ? (int)$bedroomsArr[$i] : null,
            'work_type' => trim($workTypes[$i] ?? '') !== '' ? trim($workTypes[$i]) : 'OUT',
            'work_item' => trim($workItems[$i] ?? ''),
            'hours' => ($hoursArr[$i] ?? '') !== '' ? (float)$hoursArr[$i] : null,
            'price' => (float)($prices[$i] ?? 0),
            'plus' => (float)($pluses[$i] ?? 0),
            'penalty' => (float)($penalties[$i] ?? 0),
            'note' => trim($notes[$i] ?? ''),
        ];
    }

    if (!$validRows && !$errors) $errors[] = 'Vui lòng nhập ít nhất 1 mã phòng.';

    if (!$errors) {
        $now = date('Y-m-d H:i:s');
        foreach ($validRows as $row) {
            $pdo->prepare(
                'INSERT INTO cleaning_logs (work_date, staff_name, room_code, bedrooms, work_item, work_type, hours, price, plus, penalty, note, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $workDate, $staffName, $row['room_code'], $row['bedrooms'], $row['work_item'], $row['work_type'],
                $row['hours'], $row['price'], $row['plus'], $row['penalty'], $row['note'], $now,
            ]);
        }
        flash('success', 'Đã thêm ' . count($validRows) . ' công việc cho ' . $staffName . ' ngày ' . vndate($workDate) . '.');
        redirect('/cleaning/index.php');
    }
}

$staffList = $pdo->query('SELECT * FROM cleaning_staff WHERE is_active = 1 ORDER BY name')->fetchAll();
$rooms = $pdo->query('SELECT room_code, bedrooms FROM rooms ORDER BY room_code')->fetchAll();
$priceList = $pdo->query('SELECT * FROM cleaning_price_list ORDER BY work_type, unit_price')->fetchAll();
$workTypesList = array_values(array_unique(array_column($priceList, 'work_type')));
$hangMucList = array_values(array_filter($priceList, fn($pl) => $pl['work_type'] !== 'Tổng vệ sinh' && !ctype_digit((string)$pl['work_item'])));

$pageTitle = 'Thêm công việc vệ sinh';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="mb-0"><i class="bi bi-bucket"></i> Thêm công việc vệ sinh</h4>
  <div class="d-flex gap-2">
    <a href="<?= url('/cleaning/staff.php') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-people"></i> Nhân viên</a>
    <a href="<?= url('/cleaning/prices.php') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-tags"></i> Bảng giá</a>
  </div>
</div>

<?php foreach ($errors as $err): ?>
  <div class="alert alert-danger py-2"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="card mb-3">
  <div class="card-body">
    <div class="alert alert-info small mb-3">
      Chọn <strong>1 ngày</strong> và <strong>1 nhân viên</strong>, sau đó thêm nhiều dòng mã phòng bên dưới — phù hợp khi một nhân viên làm nhiều căn trong cùng một ngày. Giá tự tính theo Loại + Số PN, chọn thêm Hạng mục nếu muốn dùng giá riêng.
    </div>
    <form method="post" id="batchForm">
      <?= csrf_field() ?>
      <div class="row g-3 mb-3">
        <div class="col-md-4">
          <label class="form-label">Ngày làm việc *</label>
          <input type="date" name="work_date" class="form-control" required value="<?= e($workDate) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Nhân viên *</label>
          <select name="staff_name" class="form-select" required>
            <option value="">-- Chọn nhân viên --</option>
            <?php foreach ($staffList as $s): ?>
              <option value="<?= e($s['name']) ?>" <?= $staffName === $s['name'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if (!$staffList): ?>
            <div class="form-text">Chưa có nhân viên nào. <a href="<?= url('/cleaning/staff.php') ?>">Thêm ngay</a>.</div>
          <?php endif; ?>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-sm align-middle" id="rowsTable">
          <thead>
            <tr>
              <th style="min-width:140px;">Mã phòng</th>
              <th style="width:80px;">Số PN</th>
              <th style="min-width:110px;">Loại</th>
              <th style="min-width:170px;">Hạng mục (tùy chọn)</th>
              <th style="width:80px;">Số giờ</th>
              <th style="width:120px;">Price</th>
              <th style="width:100px;">Plus</th>
              <th style="width:100px;">Phạt</th>
              <th>Note</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="rowsBody"></tbody>
        </table>
      </div>
      <button type="button" id="addRowBtn" class="btn btn-outline-secondary btn-sm mb-3"><i class="bi bi-plus-lg"></i> Thêm dòng phòng</button>

      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-success"><i class="bi bi-check-lg"></i> Lưu tất cả</button>
        <a href="<?= url('/cleaning/index.php') ?>" class="btn btn-outline-secondary">Hủy</a>
      </div>
    </form>
  </div>
</div>

<template id="rowTemplate">
  <tr class="work-row">
    <td>
      <input type="text" name="room_code[]" class="form-control form-control-sm room-code-input" list="roomList">
    </td>
    <td><input type="number" min="0" name="bedrooms[]" class="form-control form-control-sm bedrooms-input"></td>
    <td>
      <select name="work_type[]" class="form-select form-select-sm work-type-input">
        <option value="">--</option>
        <?php foreach ($workTypesList as $wt): ?><option value="<?= e($wt) ?>" <?= $wt === 'OUT' ? 'selected' : '' ?>><?= e($wt) ?></option><?php endforeach; ?>
      </select>
    </td>
    <td>
      <select name="work_item[]" class="form-select form-select-sm work-item-input">
        <option value="">-- Không chọn --</option>
        <?php foreach ($hangMucList as $pl): ?>
          <option value="<?= e($pl['work_item']) ?>" data-type="<?= e($pl['work_type']) ?>" data-unit="<?= e($pl['unit']) ?>" data-price="<?= $pl['unit_price'] ?>"><?= e($pl['work_item']) ?> (<?= e($pl['work_type']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </td>
    <td><input type="number" step="0.5" name="hours[]" class="form-control form-control-sm hours-input" style="display:none;"></td>
    <td><input type="number" step="1000" name="price[]" class="form-control form-control-sm price-input"></td>
    <td><input type="number" step="1000" name="plus[]" class="form-control form-control-sm" value="0"></td>
    <td><input type="number" step="1000" name="penalty[]" class="form-control form-control-sm" value="0"></td>
    <td><input type="text" name="note[]" class="form-control form-control-sm"></td>
    <td><button type="button" class="btn btn-sm btn-outline-danger remove-row-btn"><i class="bi bi-x"></i></button></td>
  </tr>
</template>

<datalist id="roomList">
  <?php foreach ($rooms as $r): ?><option value="<?= e($r['room_code']) ?>"><?php endforeach; ?>
</datalist>

<script>
var roomBedrooms = <?= json_encode(array_column($rooms, 'bedrooms', 'room_code')) ?>;
var priceMap = {};
<?php foreach ($priceList as $pl): ?>
priceMap['<?= addslashes($pl['work_type']) ?>'] = priceMap['<?= addslashes($pl['work_type']) ?>'] || {};
priceMap['<?= addslashes($pl['work_type']) ?>']['<?= addslashes($pl['work_item']) ?>'] = <?= (float)$pl['unit_price'] ?>;
<?php endforeach; ?>

var rowsBody = document.getElementById('rowsBody');
var rowTemplate = document.getElementById('rowTemplate');
var HOURLY_TYPE = 'Tổng vệ sinh';

function addRow() {
  var clone = rowTemplate.content.cloneNode(true);
  rowsBody.appendChild(clone);
}
document.getElementById('addRowBtn').addEventListener('click', addRow);

function computeRowPrice(row) {
  var itemSelect = row.querySelector('.work-item-input');
  var typeSelect = row.querySelector('.work-type-input');
  var priceInput = row.querySelector('.price-input');
  var bedroomsInput = row.querySelector('.bedrooms-input');
  var hoursInput = row.querySelector('.hours-input');
  var type = typeSelect.value;

  if (type === HOURLY_TYPE) {
    itemSelect.value = '';
    itemSelect.style.display = 'none';
    hoursInput.style.display = '';
    if (!hoursInput.value) hoursInput.value = 1;
    var hourly = (priceMap[HOURLY_TYPE] && priceMap[HOURLY_TYPE][HOURLY_TYPE] !== undefined) ? priceMap[HOURLY_TYPE][HOURLY_TYPE] : 0;
    priceInput.value = Math.round(hourly * (parseFloat(hoursInput.value) || 0));
    return;
  }
  itemSelect.style.display = '';
  hoursInput.style.display = 'none';

  var itemOpt = itemSelect.options[itemSelect.selectedIndex];
  if (itemOpt && itemOpt.value) {
    priceInput.value = itemOpt.getAttribute('data-price');
    return;
  }
  var bedrooms = bedroomsInput.value;
  if (type && bedrooms && priceMap[type] && priceMap[type][bedrooms] !== undefined) {
    priceInput.value = priceMap[type][bedrooms];
  }
}

rowsBody.addEventListener('input', function (e) {
  var row = e.target.closest('.work-row');
  if (!row) return;
  if (e.target.classList.contains('room-code-input')) {
    var b = roomBedrooms[e.target.value];
    if (b) row.querySelector('.bedrooms-input').value = b;
  }
  computeRowPrice(row);
});
rowsBody.addEventListener('change', function (e) {
  var row = e.target.closest('.work-row');
  if (!row) return;
  computeRowPrice(row);
});
rowsBody.addEventListener('click', function (e) {
  var btn = e.target.closest('.remove-row-btn');
  if (btn) {
    var rows = rowsBody.querySelectorAll('.work-row');
    if (rows.length > 1) btn.closest('.work-row').remove();
  }
});

// Bat dau voi 3 dong trong
addRow(); addRow(); addRow();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
