<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM contracts WHERE id = ?');
$stmt->execute([$id]);
$contract = $stmt->fetch();
if (!$contract) {
    flash('danger', 'Không tìm thấy hợp đồng.');
    redirect('/contracts/index.php');
}

$pageTitle = 'Hợp đồng ' . $contract['contract_code'];
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="mb-0"><i class="bi bi-file-earmark-text"></i> Hợp đồng <?= e($contract['contract_code']) ?>
    <span class="badge bg-<?= badge_class($contract['status']) ?>"><?= e(CONTRACT_STATUS_LABELS[$contract['status']] ?? '') ?></span>
  </h4>
  <a href="<?= url('/contracts/form.php?id=' . $contract['id']) ?>" class="btn btn-outline-primary"><i class="bi bi-pencil"></i> Sửa</a>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header">Thông tin hợp đồng</div>
      <div class="card-body">
        <table class="table table-sm mb-0">
          <tr><th style="width:40%">Phòng</th><td><?= e($contract['room_code']) ?><?= $contract['zone'] ? ' - ' . e($contract['zone']) : '' ?></td></tr>
          <tr><th>Thời hạn</th><td><?= vndate($contract['start_date']) ?> (<?= e($contract['checkin_time']) ?>) — <?= vndate($contract['end_date']) ?> (<?= e($contract['checkout_time']) ?>)</td></tr>
          <tr><th>Tiền thuê/tháng</th><td><?= money($contract['monthly_rent']) ?></td></tr>
          <tr><th>Ghi chú giá thuê</th><td><?= e($contract['rent_note']) ?></td></tr>
          <tr><th>Tiền đặt cọc</th><td><?= money($contract['deposit_amount']) ?></td></tr>
          <tr><th>Thanh toán</th><td><?= $contract['payment_method'] === 'tien_mat' ? 'Tiền mặt' : 'Chuyển khoản' ?><?= $contract['bank_name'] ? ' - ' . e($contract['bank_name']) : '' ?><?= $contract['receiving_account'] ? ' - ' . e($contract['receiving_account']) : '' ?></td></tr>
          <tr><th>Người thụ hưởng</th><td><?= e($contract['beneficiary_name']) ?></td></tr>
          <tr><th>Ghi chú thanh toán</th><td><?= e($contract['payment_note']) ?></td></tr>
          <?php if ($contract['file_path']): ?>
          <tr><th>File hợp đồng</th><td><a href="<?= url('/' . $contract['file_path']) ?>" target="_blank"><i class="bi bi-paperclip"></i> Xem file</a></td></tr>
          <?php endif; ?>
          <tr><th>Ghi chú</th><td><?= nl2br(e($contract['note'])) ?></td></tr>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card h-100 mb-3">
      <div class="card-header">Bên thuê</div>
      <div class="card-body">
        <table class="table table-sm mb-0">
          <tr><th style="width:40%">Họ tên</th><td><?= e($contract['lessee_name']) ?></td></tr>
          <tr><th>Ngày sinh</th><td><?= vndate($contract['lessee_dob']) ?></td></tr>
          <tr><th>Quốc tịch</th><td><?= e($contract['lessee_nationality']) ?></td></tr>
          <tr><th>SĐT</th><td><?= e($contract['lessee_phone']) ?></td></tr>
          <tr><th>Email</th><td><?= e($contract['lessee_email']) ?></td></tr>
          <tr><th>Hộ chiếu/CCCD</th><td><?= e($contract['lessee_id_number']) ?> <?= $contract['lessee_id_issue_date'] ? '(' . vndate($contract['lessee_id_issue_date']) . ($contract['lessee_id_issue_place'] ? ' - ' . e($contract['lessee_id_issue_place']) : '') . ')' : '' ?></td></tr>
          <tr><th>Địa chỉ</th><td><?= e($contract['lessee_address']) ?></td></tr>
        </table>
      </div>
    </div>

    <div class="card h-100">
      <div class="card-header">Bên cho thuê</div>
      <div class="card-body">
        <table class="table table-sm mb-0">
          <tr><th style="width:40%">Họ tên</th><td><?= e($contract['lessor_name']) ?></td></tr>
          <tr><th>Ngày sinh</th><td><?= vndate($contract['lessor_dob']) ?></td></tr>
          <tr><th>Hộ chiếu/CCCD</th><td><?= e($contract['lessor_id_number']) ?> <?= $contract['lessor_id_issue_date'] ? '(' . vndate($contract['lessor_id_issue_date']) . ($contract['lessor_id_issue_place'] ? ' - ' . e($contract['lessor_id_issue_place']) : '') . ')' : '' ?></td></tr>
          <tr><th>Địa chỉ</th><td><?= e($contract['lessor_address']) ?></td></tr>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="mt-3">
  <a href="<?= url('/contracts/index.php') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Quay lại danh sách</a>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
