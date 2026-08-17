<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$statusFilter = $_GET['status'] ?? '';
$search = trim($_GET['q'] ?? '');

$sql = 'SELECT b.*, r.room_code FROM bookings b JOIN rooms r ON r.id = b.room_id WHERE 1=1';
$params = [];
if ($statusFilter !== '') {
    $sql .= ' AND b.status = ?';
    $params[] = $statusFilter;
}
if ($search !== '') {
    $sql .= ' AND (b.guest_name LIKE ? OR b.guest_phone LIKE ? OR r.room_code LIKE ?)';
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}
$sql .= ' ORDER BY b.checkin_date DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

$pageTitle = 'Doanh thu ngắn hạn';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="mb-0"><i class="bi bi-calendar-check"></i> Doanh thu ngắn hạn (đặt phòng theo ngày)</h4>
  <a href="<?= url('/bookings/form.php') ?>" class="btn btn-success"><i class="bi bi-plus-lg"></i> Thêm đặt phòng</a>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form class="row g-2 align-items-end">
      <div class="col-sm-4">
        <input type="text" name="q" class="form-control" placeholder="Tên khách, SĐT, mã phòng..." value="<?= e($search) ?>">
      </div>
      <div class="col-sm-3">
        <select name="status" class="form-select">
          <option value="">-- Tất cả trạng thái --</option>
          <?php foreach (BOOKING_STATUS_LABELS as $k => $v): ?>
            <option value="<?= e($k) ?>" <?= $statusFilter === $k ? 'selected' : '' ?>><?= e($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-2">
        <button class="btn btn-outline-secondary w-100"><i class="bi bi-search"></i> Lọc</button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Phòng</th>
          <th>Khách</th>
          <th>Nhận phòng</th>
          <th>Trả phòng</th>
          <th>Tổng tiền</th>
          <th>Trạng thái</th>
          <th>Thanh toán</th>
          <th class="text-end">Thao tác</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$bookings): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">Chưa có đặt phòng nào.</td></tr>
        <?php endif; ?>
        <?php foreach ($bookings as $b): ?>
          <tr>
            <td class="fw-semibold"><?= e($b['room_code']) ?></td>
            <td><?= e($b['guest_name']) ?><div class="small text-muted"><?= e($b['guest_phone']) ?></div></td>
            <td><?= vndate($b['checkin_date']) ?></td>
            <td><?= vndate($b['checkout_date']) ?></td>
            <td><?= money($b['total_amount']) ?></td>
            <td><span class="badge bg-<?= badge_class($b['status']) ?>"><?= e(BOOKING_STATUS_LABELS[$b['status']] ?? '') ?></span></td>
            <td><span class="badge bg-<?= $b['payment_status'] === 'paid' ? 'success' : 'secondary' ?>"><?= $b['payment_status'] === 'paid' ? 'Đã thu' : 'Chưa thu' ?></span></td>
            <td class="text-end">
              <a href="<?= url('/bookings/form.php?id=' . $b['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
              <form method="post" action="<?= url('/bookings/delete.php') ?>" class="d-inline" data-confirm="Xóa đặt phòng của <?= e($b['guest_name']) ?>?">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $b['id'] ?>">
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
