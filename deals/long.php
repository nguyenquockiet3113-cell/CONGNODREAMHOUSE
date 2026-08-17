<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/deal_helpers.php';
require_login();

$search = trim($_GET['q'] ?? '');
$month = trim($_GET['month'] ?? '');

$sql = "SELECT * FROM deals WHERE deal_type = 'dai_han'";
$params = [];
if ($search !== '') {
    $sql .= ' AND (guest_name LIKE ? OR room_code LIKE ?)';
    $params = array_merge($params, ["%$search%", "%$search%"]);
}
$sql .= ' ORDER BY checkin_date DESC, id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$deals = $stmt->fetchAll();

// Lay toan bo ky cua cac deal nay, gom nhom theo deal_id
$periodsByDeal = [];
if ($deals) {
    $dealIds = array_column($deals, 'id');
    $placeholders = implode(',', array_fill(0, count($dealIds), '?'));
    $pStmt = $pdo->prepare("SELECT * FROM deal_periods WHERE deal_id IN ($placeholders) ORDER BY period_index");
    $pStmt->execute($dealIds);
    foreach ($pStmt->fetchAll() as $p) {
        $periodsByDeal[$p['deal_id']][] = $p;
    }
}

// Neu co loc theo thang, chi giu lai cac deal co it nhat 1 ky trong thang do
if ($month !== '') {
    $deals = array_filter($deals, function ($d) use ($periodsByDeal, $month) {
        foreach ($periodsByDeal[$d['id']] ?? [] as $p) {
            if ($month >= substr($p['period_start'], 0, 7) && $month <= substr($p['period_end'], 0, 7)) return true;
        }
        return false;
    });
}

$sumTotal = 0; $sumPaid = 0; $totalPeriods = 0;
$dealStats = [];
foreach ($deals as $d) {
    $periods = $periodsByDeal[$d['id']] ?? [];
    $total = 0; $paid = 0;
    foreach ($periods as $p) {
        $total += (float)$p['rent_amount'] + (float)$p['deposit_amount'] + (float)$p['utilities_amount'];
        $paid += (float)$p['paid_amount'];
    }
    $dealStats[$d['id']] = ['total' => $total, 'paid' => $paid, 'count' => count($periods)];
    $sumTotal += $total; $sumPaid += $paid; $totalPeriods += count($periods);
}

$pageTitle = 'Doanh thu dài hạn';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="mb-0"><i class="bi bi-receipt"></i> Doanh thu dài hạn</h4>
  <a href="<?= url('/deals/form.php') ?>" class="btn btn-success"><i class="bi bi-plus-lg"></i> Thêm deal</a>
</div>

<div class="row g-3 mb-3">
  <div class="col-sm-3">
    <div class="stat-card"><div class="text-muted small">Tổng cộng (theo bộ lọc)</div><div class="stat-value"><?= money($sumTotal) ?></div></div>
  </div>
  <div class="col-sm-3">
    <div class="stat-card"><div class="text-muted small">Đã thu</div><div class="stat-value text-success"><?= money($sumPaid) ?></div></div>
  </div>
  <div class="col-sm-3">
    <div class="stat-card"><div class="text-muted small">Còn phải thu</div><div class="stat-value text-danger"><?= money($sumTotal - $sumPaid) ?></div></div>
  </div>
  <div class="col-sm-3">
    <div class="stat-card"><div class="text-muted small">Số deal / kỳ</div><div class="stat-value"><?= count($deals) ?> / <?= $totalPeriods ?></div></div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form class="row g-2 align-items-end">
      <div class="col-sm-4">
        <input type="text" name="q" class="form-control" placeholder="Tên khách, mã phòng..." value="<?= e($search) ?>">
      </div>
      <div class="col-sm-3">
        <input type="month" name="month" class="form-control" value="<?= e($month) ?>" title="Chỉ hiện deal có kỳ rơi vào tháng này">
      </div>
      <div class="col-sm-2">
        <button class="btn btn-outline-secondary w-100"><i class="bi bi-search"></i> Lọc</button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead>
        <tr>
          <th>Phòng</th>
          <th>Khách</th>
          <th>Thời hạn</th>
          <th>Số kỳ</th>
          <th class="text-end">Tổng cộng</th>
          <th class="text-end">Đã thu</th>
          <th class="text-end">Còn lại</th>
          <th class="text-end">Thao tác</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$deals): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">Chưa có deal dài hạn nào.</td></tr>
        <?php endif; ?>
        <?php foreach ($deals as $d): ?>
          <?php $stat = $dealStats[$d['id']]; $remain = $stat['total'] - $stat['paid']; ?>
          <tr>
            <td class="fw-semibold"><?= e($d['room_code']) ?><?= $d['zone'] ? '<div class="small text-muted">' . e($d['zone']) . '</div>' : '' ?></td>
            <td><?= e($d['guest_name']) ?></td>
            <td><?= vndate($d['checkin_date']) ?> - <?= vndate($d['checkout_date']) ?></td>
            <td><?= $stat['count'] ?> kỳ</td>
            <td class="text-end"><?= money($stat['total']) ?></td>
            <td class="text-end text-success"><?= money($stat['paid']) ?></td>
            <td class="text-end <?= $remain > 0 ? 'text-danger' : '' ?>"><?= money($remain) ?></td>
            <td class="text-end">
              <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#periodModal<?= $d['id'] ?>"><i class="bi bi-eye"></i> Chi tiết</button>
              <a href="<?= url('/deals/form.php?id=' . $d['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php foreach ($deals as $d): ?>
  <?php $periods = $periodsByDeal[$d['id']] ?? []; ?>
  <div class="modal fade" id="periodModal<?= $d['id'] ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><?= e($d['room_code']) ?> - <?= e($d['guest_name']) ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <table class="table table-sm mb-0">
            <thead>
              <tr><th>Kỳ</th><th>Từ - đến</th><th class="text-end">Thuê</th><th class="text-end">Cọc</th><th class="text-end">Điện/Nước</th><th class="text-end">Đã TT</th><th class="text-end">Còn lại</th></tr>
            </thead>
            <tbody>
              <?php foreach ($periods as $p): ?>
                <?php $pt = (float)$p['rent_amount'] + (float)$p['deposit_amount'] + (float)$p['utilities_amount']; $pr = $pt - (float)$p['paid_amount']; ?>
                <tr>
                  <td><?= e(deal_period_label((int)$p['period_index'], $p['period_start'])) ?></td>
                  <td><?= vndate($p['period_start']) ?> - <?= vndate($p['period_end']) ?></td>
                  <td class="text-end"><?= money($p['rent_amount']) ?></td>
                  <td class="text-end"><?= $p['deposit_amount'] > 0 ? money($p['deposit_amount']) : '' ?></td>
                  <td class="text-end"><?= money($p['utilities_amount']) ?></td>
                  <td class="text-end text-success"><?= money($p['paid_amount']) ?></td>
                  <td class="text-end <?= $pr > 0 ? 'text-danger' : '' ?>"><?= money($pr) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="modal-footer">
          <a href="<?= url('/deals/form.php?id=' . $d['id']) ?>" class="btn btn-primary btn-sm"><i class="bi bi-pencil"></i> Sửa chi tiết từng kỳ</a>
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Đóng</button>
        </div>
      </div>
    </div>
  </div>
<?php endforeach; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
