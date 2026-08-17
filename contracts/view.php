<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT c.*, r.room_code, t.full_name AS tenant_name, t.phone AS tenant_phone, t.email AS tenant_email, t.id_card_number
                        FROM contracts c JOIN rooms r ON r.id = c.room_id JOIN tenants t ON t.id = c.tenant_id
                        WHERE c.id = ?');
$stmt->execute([$id]);
$contract = $stmt->fetch();
if (!$contract) {
    flash('danger', 'Không tìm thấy hợp đồng.');
    redirect('/contracts/index.php');
}

$mStmt = $pdo->prepare('SELECT * FROM contract_members WHERE contract_id = ?');
$mStmt->execute([$id]);
$members = $mStmt->fetchAll();

$invStmt = $pdo->prepare('SELECT * FROM invoices WHERE contract_id = ? ORDER BY period_month DESC');
$invStmt->execute([$id]);
$invoices = $invStmt->fetchAll();

$pageTitle = 'Hợp đồng ' . $contract['contract_code'];
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="mb-0"><i class="bi bi-file-earmark-text"></i> Hợp đồng <?= e($contract['contract_code']) ?>
    <span class="badge bg-<?= badge_class($contract['status']) ?>"><?= e(CONTRACT_STATUS_LABELS[$contract['status']] ?? '') ?></span>
  </h4>
  <div class="d-flex gap-2">
    <a href="<?= url('/invoices/form.php?contract_id=' . $contract['id']) ?>" class="btn btn-success"><i class="bi bi-receipt"></i> Tạo hóa đơn tháng</a>
    <a href="<?= url('/contracts/form.php?id=' . $contract['id']) ?>" class="btn btn-outline-primary"><i class="bi bi-pencil"></i> Sửa</a>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header">Thông tin hợp đồng</div>
      <div class="card-body">
        <table class="table table-sm mb-0">
          <tr><th style="width:40%">Phòng</th><td><?= e($contract['room_code']) ?></td></tr>
          <tr><th>Thời hạn</th><td><?= vndate($contract['start_date']) ?> — <?= vndate($contract['end_date']) ?></td></tr>
          <tr><th>Tiền thuê/tháng</th><td><?= money($contract['monthly_rent']) ?></td></tr>
          <tr><th>Tiền đặt cọc</th><td><?= money($contract['deposit_amount']) ?> <?= $contract['deposit_returned'] ? '<span class="badge bg-secondary">Đã hoàn trả</span>' : '' ?></td></tr>
          <tr><th>Đơn giá điện/nước</th><td><?= money($contract['electricity_price']) ?>/kWh · <?= money($contract['water_price']) ?>/m³</td></tr>
          <tr><th>Phí dịch vụ/tháng</th><td><?= money($contract['service_fee']) ?></td></tr>
          <?php if ($contract['file_path']): ?>
          <tr><th>File hợp đồng</th><td><a href="<?= url('/' . $contract['file_path']) ?>" target="_blank"><i class="bi bi-paperclip"></i> Xem file</a></td></tr>
          <?php endif; ?>
          <tr><th>Ghi chú</th><td><?= nl2br(e($contract['note'])) ?></td></tr>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header">Khách thuê đại diện</div>
      <div class="card-body">
        <table class="table table-sm mb-3">
          <tr><th style="width:40%">Họ tên</th><td><?= e($contract['tenant_name']) ?></td></tr>
          <tr><th>SĐT</th><td><?= e($contract['tenant_phone']) ?></td></tr>
          <tr><th>Email</th><td><?= e($contract['tenant_email']) ?></td></tr>
          <tr><th>CCCD/CMND</th><td><?= e($contract['id_card_number']) ?></td></tr>
        </table>
        <div class="fw-semibold small mb-2">Người ở cùng</div>
        <?php if (!$members): ?>
          <div class="text-muted small">Không có.</div>
        <?php else: ?>
          <ul class="list-unstyled small mb-0">
            <?php foreach ($members as $m): ?>
              <li class="mb-1"><i class="bi bi-person"></i> <?= e($m['full_name']) ?> <?= $m['phone'] ? '- ' . e($m['phone']) : '' ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="card mt-3">
  <div class="card-header">Lịch sử hóa đơn</div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead><tr><th>Kỳ</th><th>Tổng tiền</th><th>Đã thu</th><th>Trạng thái</th><th></th></tr></thead>
      <tbody>
        <?php if (!$invoices): ?>
          <tr><td colspan="5" class="text-center text-muted py-3">Chưa có hóa đơn nào.</td></tr>
        <?php endif; ?>
        <?php foreach ($invoices as $inv): ?>
          <tr>
            <td><?= e($inv['period_month']) ?></td>
            <td><?= money($inv['total_amount']) ?></td>
            <td><?= money($inv['paid_amount']) ?></td>
            <td><span class="badge bg-<?= badge_class($inv['status']) ?>"><?= e(INVOICE_STATUS_LABELS[$inv['status']] ?? '') ?></span></td>
            <td class="text-end"><a href="<?= url('/invoices/form.php?id=' . $inv['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="mt-3">
  <a href="<?= url('/contracts/index.php') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Quay lại danh sách</a>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
