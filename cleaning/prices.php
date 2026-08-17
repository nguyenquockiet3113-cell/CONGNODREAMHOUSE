<?php
require_once __DIR__ . '/../config/config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (isset($_POST['add_price'])) {
        $workType = trim($_POST['new_work_type'] ?? '');
        $workItem = trim($_POST['new_work_item'] ?? '');
        $unit = $_POST['new_unit'] ?? 'phong';
        $unitPrice = (float)($_POST['new_unit_price'] ?? 0);
        if ($workType !== '' && $workItem !== '') {
            $now = date('Y-m-d H:i:s');
            $pdo->prepare('INSERT INTO cleaning_price_list (work_type, work_item, unit, unit_price, created_at, updated_at) VALUES (?,?,?,?,?,?)')
                ->execute([$workType, $workItem, $unit, $unitPrice, $now, $now]);
            flash('success', 'Đã thêm mục giá mới.');
        } else {
            flash('danger', 'Vui lòng nhập đủ Loại và Hạng mục.');
        }
    } elseif (isset($_POST['save_all'])) {
        $ids = $_POST['price_id'] ?? [];
        $now = date('Y-m-d H:i:s');
        foreach ($ids as $i => $pid) {
            $pdo->prepare('UPDATE cleaning_price_list SET work_type=?, work_item=?, unit=?, unit_price=?, updated_at=? WHERE id=?')
                ->execute([
                    trim($_POST['work_type'][$i] ?? ''),
                    trim($_POST['work_item'][$i] ?? ''),
                    $_POST['unit'][$i] ?? 'phong',
                    (float)($_POST['unit_price'][$i] ?? 0),
                    $now, (int)$pid,
                ]);
        }
        flash('success', 'Đã lưu bảng giá.');
    }
    redirect('/cleaning/prices.php');
}

$prices = $pdo->query('SELECT * FROM cleaning_price_list ORDER BY work_type, unit_price')->fetchAll();

$pageTitle = 'Bảng giá vệ sinh';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="mb-0"><i class="bi bi-tags"></i> Bảng giá vệ sinh</h4>
  <a href="<?= url('/cleaning/index.php') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Về bảng lương</a>
</div>

<form method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="save_all" value="1">
  <div class="card mb-3">
    <div class="card-body">
      <table class="table table-sm align-middle">
        <thead><tr><th>Loại</th><th>Hạng mục</th><th>Đơn vị</th><th>Đơn giá (đ)</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($prices as $p): ?>
            <tr>
              <input type="hidden" name="price_id[]" value="<?= $p['id'] ?>">
              <td><input type="text" name="work_type[]" class="form-control form-control-sm" value="<?= e($p['work_type']) ?>"></td>
              <td><input type="text" name="work_item[]" class="form-control form-control-sm" value="<?= e($p['work_item']) ?>"></td>
              <td>
                <select name="unit[]" class="form-select form-select-sm">
                  <option value="phong" <?= $p['unit'] === 'phong' ? 'selected' : '' ?>>/ phòng</option>
                  <option value="gio" <?= $p['unit'] === 'gio' ? 'selected' : '' ?>>/ giờ</option>
                </select>
              </td>
              <td><input type="number" step="1000" name="unit_price[]" class="form-control form-control-sm" value="<?= e($p['unit_price']) ?>"></td>
              <td>
                <button type="submit" form="deleteForm<?= $p['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Xóa mục giá này?')"><i class="bi bi-trash"></i></button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <button type="submit" class="btn btn-success mb-4"><i class="bi bi-check-lg"></i> Lưu bảng giá</button>
</form>

<?php foreach ($prices as $p): ?>
  <form method="post" id="deleteForm<?= $p['id'] ?>" action="<?= url('/cleaning/delete_price.php') ?>" class="d-none">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $p['id'] ?>">
  </form>
<?php endforeach; ?>

<div class="card">
  <div class="card-header">Thêm mục giá mới</div>
  <div class="card-body">
    <form method="post" class="row g-2 align-items-end">
      <?= csrf_field() ?>
      <input type="hidden" name="add_price" value="1">
      <div class="col-md-3">
        <label class="form-label small mb-1">Loại</label>
        <input type="text" name="new_work_type" class="form-control" placeholder="VD: OUT, LƯU, Khác" required>
      </div>
      <div class="col-md-3">
        <label class="form-label small mb-1">Hạng mục</label>
        <input type="text" name="new_work_item" class="form-control" placeholder="VD: Set up 1PN" required>
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">Đơn vị</label>
        <select name="new_unit" class="form-select">
          <option value="phong">/ phòng</option>
          <option value="gio">/ giờ</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">Đơn giá (đ)</label>
        <input type="number" step="1000" name="new_unit_price" class="form-control" required>
      </div>
      <div class="col-md-2">
        <button class="btn btn-success w-100"><i class="bi bi-plus-lg"></i> Thêm</button>
      </div>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
