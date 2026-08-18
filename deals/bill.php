<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/deal_helpers.php';
require_login();

$dealId = (int)($_GET['deal_id'] ?? 0);
$periodId = (int)($_GET['period_id'] ?? 0);

$dealStmt = $pdo->prepare('SELECT * FROM deals WHERE id = ?');
$dealStmt->execute([$dealId]);
$deal = $dealStmt->fetch();
if (!$deal) {
    flash('danger', 'Không tìm thấy deal.');
    redirect('/deals/long.php');
}

$periodStmt = $pdo->prepare('SELECT * FROM deal_periods WHERE id = ? AND deal_id = ?');
$periodStmt->execute([$periodId, $dealId]);
$period = $periodStmt->fetch();
if (!$period) {
    flash('danger', 'Không tìm thấy kỳ thanh toán.');
    redirect('/deals/long.php');
}

$maxIndexStmt = $pdo->prepare('SELECT MAX(period_index) FROM deal_periods WHERE deal_id = ?');
$maxIndexStmt->execute([$dealId]);
$isLastPeriod = (int)$period['period_index'] === (int)$maxIndexStmt->fetchColumn();

// Xac dinh mau bill theo khu vuc
$zone = (string)($deal['zone'] ?? '');
if (stripos($zone, 'Central Park') !== false) {
    $theme = 'central';
    $zoneLabel = 'VINHOMES CENTRAL PARK';
} elseif (stripos($zone, 'Grand Park') !== false) {
    $theme = 'grand';
    $zoneLabel = 'VINHOMES GRAND PARK';
} else {
    $theme = 'default';
    $zoneLabel = $zone !== '' ? mb_strtoupper($zone) : 'DREAM\'S HOUSE';
}

$items = [
    ['label' => 'Tiền phòng', 'amount' => (float)$period['rent_amount']],
    ['label' => 'Tiền điện', 'amount' => (float)$period['electricity_amount']],
    ['label' => 'Tiền nước', 'amount' => (float)$period['water_amount']],
    ['label' => 'Phí quản lý', 'amount' => (float)$period['management_fee_amount']],
    ['label' => 'Phí khác' . ($period['note'] ? ' (' . $period['note'] . ')' : ''), 'amount' => (float)$period['other_fee_amount']],
];
$subTotal = array_sum(array_column($items, 'amount'));
$deposit = (float)$deal['deposit_amount'];

$pageTitle = 'Bill ' . $deal['room_code'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Bill <?= e($deal['room_code']) ?> - <?= e(deal_period_label((int)$period['period_index'], $period['period_start'])) ?></title>
<style>
  body { font-family: Arial, sans-serif; font-size: 13px; color: #222; max-width: 760px; margin: 0 auto; padding: 20px; }
  .toolbar { text-align: center; margin-bottom: 16px; }
  .toolbar button { font-size: 14px; padding: 8px 20px; cursor: pointer; }
  .bill-box { border: 1px solid #ccc; border-radius: 6px; overflow: hidden; }
  .bill-header { display: flex; align-items: center; gap: 16px; padding: 14px 18px; }
  .theme-central .bill-header { background: #f3e2c7; color: #7a4a1e; }
  .theme-grand .bill-header { background: #dcefdc; color: #205c2e; }
  .theme-default .bill-header { background: #e9e9e9; color: #333; }
  .zone-badge { font-weight: bold; font-size: 16px; letter-spacing: .5px; }
  .header-info { margin-left: auto; text-align: right; font-size: 13px; }
  .header-info .room-code { font-size: 18px; font-weight: bold; }
  table.items { width: 100%; border-collapse: collapse; }
  table.items th, table.items td { border: 1px solid #ddd; padding: 8px 10px; }
  table.items th { background: #fafafa; text-align: left; }
  .text-end { text-align: right; }
  .total-row td { font-weight: bold; background: #fafafa; }
  .settle-box { padding: 14px 18px; }
  .settle-row { display: flex; justify-content: space-between; padding: 4px 0; }
  .settle-final { font-size: 16px; font-weight: bold; border-top: 2px solid #333; margin-top: 6px; padding-top: 8px; }
  .positive { color: #1a7a1a; }
  .negative { color: #c0392b; }
  @media print {
    .toolbar { display: none; }
    body { padding: 0; max-width: none; }
  }
</style>
</head>
<body>
  <div class="toolbar">
    <button onclick="window.print()">🖨️ In bill</button>
    <button onclick="window.close()">Đóng</button>
  </div>

  <div class="bill-box theme-<?= $theme ?>">
    <div class="bill-header">
      <img src="<?= url('/assets/img/logo.png') ?>" alt="Logo" style="height:40px;">
      <div class="zone-badge"><?= e($zoneLabel) ?></div>
      <div class="header-info">
        <div class="room-code"><?= e($deal['room_code']) ?></div>
        <div><?= e($deal['guest_name']) ?></div>
        <div><?= e(deal_period_label((int)$period['period_index'], $period['period_start'])) ?> — Từ <?= vndate($period['period_start']) ?> đến <?= vndate($period['period_end']) ?></div>
      </div>
    </div>

    <table class="items">
      <thead>
        <tr><th style="width:40px;">STT</th><th>Nội dung</th><th class="text-end" style="width:160px;">Số tiền</th></tr>
      </thead>
      <tbody>
        <?php foreach ($items as $i => $it): ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td><?= e($it['label']) ?></td>
            <td class="text-end"><?= money($it['amount']) ?></td>
          </tr>
        <?php endforeach; ?>
        <tr class="total-row">
          <td colspan="2">TỔNG CỘNG</td>
          <td class="text-end" id="subTotalCell"><?= money($subTotal) ?></td>
        </tr>
      </tbody>
    </table>

    <div class="settle-box">
      <label style="display:flex; align-items:center; gap:8px;">
        <input type="checkbox" id="applyDeposit" <?= $isLastPeriod && $deposit > 0 ? 'checked' : '' ?> <?= $deposit > 0 ? '' : 'disabled' ?>>
        Trừ tiền cọc đã thu (<?= money($deposit) ?>) vào kỳ này<?= $isLastPeriod ? ' <span style="color:#888;">— kỳ cuối, gợi ý trừ cọc</span>' : '' ?>
      </label>
      <div class="settle-row"><span>Tổng cộng chi phí kỳ này</span><span id="lineSubTotal"><?= money($subTotal) ?></span></div>
      <div class="settle-row"><span>Trừ tiền cọc</span><span id="lineDeposit">- <?= money($isLastPeriod && $deposit > 0 ? $deposit : 0) ?></span></div>
      <div class="settle-row settle-final">
        <span id="settleLabel"></span><span id="settleAmount"></span>
      </div>
    </div>
  </div>

<script>
var subTotal = <?= (float)$subTotal ?>;
var deposit = <?= (float)$deposit ?>;
function fmt(n) { return new Intl.NumberFormat('vi-VN').format(Math.round(Math.abs(n))) + ' đ'; }
function recalcSettle() {
  var applied = document.getElementById('applyDeposit').checked ? deposit : 0;
  document.getElementById('lineDeposit').textContent = '- ' + fmt(applied);
  var settle = subTotal - applied;
  var labelEl = document.getElementById('settleLabel');
  var amountEl = document.getElementById('settleAmount');
  if (settle >= 0) {
    labelEl.textContent = 'THANH TOÁN CHO HOST';
    amountEl.textContent = fmt(settle);
    amountEl.className = 'positive';
  } else {
    labelEl.textContent = 'HOÀN LẠI CHO SALE';
    amountEl.textContent = fmt(settle);
    amountEl.className = 'negative';
  }
}
document.getElementById('applyDeposit').addEventListener('change', recalcSettle);
recalcSettle();
</script>
</body>
</html>
