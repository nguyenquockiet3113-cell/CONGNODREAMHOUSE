<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$errors = [];
$entry = [
    'work_date' => date('Y-m-d'), 'staff_name' => '', 'room_code' => '',
    'penalty' => '', 'note' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $entry['work_date'] = $_POST['work_date'] ?? '';
    $entry['staff_name'] = trim($_POST['staff_name'] ?? '');
    $entry['room_code'] = trim($_POST['room_code'] ?? '');
    $entry['penalty'] = (float)($_POST['penalty'] ?? 0);
    $entry['note'] = trim($_POST['note'] ?? '');

    if (!$entry['work_date']) $errors[] = 'Vui lòng chọn ngày.';
    if ($entry['staff_name'] === '') $errors[] = 'Vui lòng chọn nhân viên.';
    if ($entry['penalty'] <= 0) $errors[] = 'Vui lòng nhập số tiền phạt lớn hơn 0.';
    if ($entry['note'] === '') $errors[] = 'Vui lòng nhập lý do phạt.';

    if (!$errors) {
        $now = date('Y-m-d H:i:s');
        $pdo->prepare(
            'INSERT INTO cleaning_logs (work_date, staff_name, room_code, price, plus, penalty, note, created_at) VALUES (?,?,?,0,0,?,?,?)'
        )->execute([$entry['work_date'], $entry['staff_name'], $entry['room_code'], $entry['penalty'], $entry['note'], $now]);

        flash('success', 'Đã ghi phạt ' . money($entry['penalty']) . ' cho ' . $entry['staff_name'] . ' ngày ' . vndate($entry['work_date']) . ' — sẽ tự động trừ vào lương kỳ này.');
        redirect('/cleaning/index.php');
    }
}

$staffList = $pdo->query(
    "SELECT name FROM cleaning_staff WHERE is_active = 1
     UNION SELECT DISTINCT staff_name FROM cleaning_logs
     ORDER BY name"
)->fetchAll(PDO::FETCH_COLUMN);
$rooms = $pdo->query('SELECT room_code FROM rooms ORDER BY room_code')->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Ghi phạt nhanh';
require_once __DIR__ . '/../includes/header.php';
?>
<h4 class="mb-3"><i class="bi bi-exclamation-triangle"></i> Ghi phạt nhanh</h4>

<?php foreach ($errors as $err): ?>
  <div class="alert alert-danger py-2"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="card" style="max-width:600px;">
  <div class="card-body">
    <p class="small text-muted">
      Dùng để ghi nhanh một khoản phạt (không gắn với công việc cụ thể). Số tiền sẽ <strong>tự động trừ</strong> vào tổng lương của nhân viên trong kỳ tính lương chứa ngày này.
    </p>
    <form method="post">
      <?= csrf_field() ?>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Ngày *</label>
          <input type="date" name="work_date" class="form-control" required value="<?= e($entry['work_date']) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Nhân viên *</label>
          <select name="staff_name" class="form-select" required>
            <option value="">-- Chọn nhân viên --</option>
            <?php foreach ($staffList as $s): ?>
              <option value="<?= e($s) ?>" <?= $entry['staff_name'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Mã phòng (nếu có)</label>
          <input type="text" name="room_code" class="form-control" list="roomList" value="<?= e($entry['room_code']) ?>">
          <datalist id="roomList">
            <?php foreach ($rooms as $r): ?><option value="<?= e($r) ?>"><?php endforeach; ?>
          </datalist>
        </div>
        <div class="col-md-6">
          <label class="form-label">Số tiền phạt (đ) *</label>
          <input type="number" step="1000" name="penalty" class="form-control" required value="<?= e($entry['penalty']) ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Lý do *</label>
          <input type="text" name="note" class="form-control" required placeholder="VD: Dọn vệ sinh dơ" value="<?= e($entry['note']) ?>">
        </div>
      </div>
      <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-danger"><i class="bi bi-check-lg"></i> Ghi phạt</button>
        <a href="<?= url('/cleaning/index.php') ?>" class="btn btn-outline-secondary">Hủy</a>
      </div>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
