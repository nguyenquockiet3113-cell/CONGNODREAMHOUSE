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

// Xac dinh logo/mau theo khu vuc
$zone = (string)($deal['zone'] ?? '');
if (stripos($zone, 'Central Park') !== false) {
    $theme = 'central';
    $zoneLogo = 'vinhomes-central-park.png';
} elseif (stripos($zone, 'Grand Park') !== false) {
    $theme = 'grand';
    $zoneLogo = 'vinhomes-grand-park.png';
} else {
    $theme = 'default';
    $zoneLogo = null;
}
$zoneLogoPath = __DIR__ . '/../assets/img/' . ($zoneLogo ?? '');
$hasZoneLogo = $zoneLogo && file_exists($zoneLogoPath);

$cin = vndate($period['period_start']);
$cout = vndate($period['period_end']);

$selfPaidSet = array_filter(explode(',', $period['self_paid_items'] ?? ''));

// STT | Nội dung | Check in | Check out | Thành Tiền
$items = [
    ['label' => 'Tiền phòng', 'cin' => '', 'cout' => '', 'amount' => (float)$period['rent_amount'], 'self_paid' => false],
    ['label' => 'Tiền điện', 'cin' => $cin, 'cout' => $cout, 'amount' => (float)$period['electricity_amount'], 'self_paid' => in_array('electricity', $selfPaidSet, true)],
    ['label' => 'Tiền nước', 'cin' => $cin, 'cout' => $cout, 'amount' => (float)$period['water_amount'], 'self_paid' => in_array('water', $selfPaidSet, true)],
    ['label' => 'Phí quản lý', 'cin' => $cin, 'cout' => $cout, 'amount' => (float)$period['management_fee_amount'], 'self_paid' => in_array('management', $selfPaidSet, true)],
    ['label' => 'Phí internet', 'cin' => $cin, 'cout' => $cout, 'amount' => (float)$period['internet_amount'], 'self_paid' => in_array('internet', $selfPaidSet, true)],
    ['label' => 'Phí vệ sinh', 'cin' => '', 'cout' => '', 'amount' => (float)$period['cleaning_fee_amount'], 'self_paid' => in_array('cleaning', $selfPaidSet, true)],
    ['label' => 'Phí xe', 'cin' => '', 'cout' => '', 'amount' => (float)$period['vehicle_fee_amount'], 'self_paid' => in_array('vehicle', $selfPaidSet, true)],
    ['label' => 'Phí khác' . ($period['note'] ? ' (' . $period['note'] . ')' : ''), 'cin' => '', 'cout' => '', 'amount' => (float)$period['other_fee_amount'], 'self_paid' => in_array('other', $selfPaidSet, true)],
];
$subTotal = array_sum(array_map(fn($it) => $it['self_paid'] ? 0 : $it['amount'], $items));
$deposit = (float)$deal['deposit_amount'];
$applyDepositDefault = $isLastPeriod && $deposit > 0;
$settleDefault = $subTotal - ($applyDepositDefault ? $deposit : 0);

$pageTitle = 'Bill ' . $deal['room_code'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Bill <?= e($deal['room_code']) ?> - <?= e(deal_period_label((int)$period['period_index'], $period['period_start'])) ?></title>
<style>
  body { font-family: Arial, sans-serif; font-size: 13px; color: #222; max-width: 980px; margin: 0 auto; padding: 20px; }
  .toolbar { text-align: center; margin-bottom: 16px; }
  .toolbar button { font-size: 14px; padding: 8px 20px; cursor: pointer; }
  table.bill { width: 100%; border-collapse: collapse; }
  table.bill td, table.bill th { border: 1px solid #999; padding: 5px 8px; }
  .header-logo { text-align: center; vertical-align: middle; }
  .header-logo img { max-height: 64px; max-width: 100px; }
  .header-name { font-size: 18px; font-weight: bold; text-align: center; vertical-align: middle; }
  .header-room { font-size: 16px; font-weight: bold; text-align: center; vertical-align: middle; }
  .header-note { background: #f4d6d6; font-weight: bold; text-align: center; vertical-align: top; }
  .theme-central .header-row { background: #f6ddb8; }
  .theme-grand .header-row { background: #d9ecd3; }
  .theme-default .header-row { background: #ececec; }
  .col-head { background: #eba33f; font-weight: bold; text-align: center; }
  .text-end { text-align: right; }
  .text-center { text-align: center; }
  .amount-cell { color: #d32f2f; font-weight: bold; }
  .deposit-cell { background: #f6ddc0; color: #17348c; font-weight: bold; text-align: right; }
  .total-label { background: #eac9c6; font-weight: bold; text-align: center; font-size: 15px; }
  .total-value { background: #ffe600; font-weight: bold; text-align: right; font-size: 15px; }
  .settle-label { background: #f2c99a; font-weight: bold; text-align: center; font-size: 15px; }
  .settle-value { background: #5fe0e0; font-weight: bold; text-align: right; font-size: 15px; }
  .deposit-toggle { font-size: 12px; padding: 6px 8px; }
  @media print {
    .toolbar, .deposit-toggle { display: none; }
    body { padding: 0; max-width: none; }
  }
</style>
</head>
<body>
  <div class="toolbar">
    <button onclick="window.print()">🖨️ In bill</button>
    <button onclick="window.close()">Đóng</button>
  </div>

  <table class="bill theme-<?= $theme ?>">
    <tr class="header-row">
      <td class="header-logo">
        <?php if ($hasZoneLogo): ?>
          <img src="<?= url('/assets/img/' . $zoneLogo) ?>" alt="Logo">
        <?php else: ?>
          <img src="<?= url('/assets/img/logo.png') ?>" alt="Logo">
        <?php endif; ?>
      </td>
      <td class="header-name" colspan="3"><?= e(mb_strtoupper($deal['guest_name'])) ?></td>
      <td class="header-room" colspan="2"><?= e($deal['room_code']) ?></td>
      <td colspan="4"></td>
      <td class="header-note" rowspan="13">Note</td>
    </tr>
    <tr class="col-head">
      <td>STT</td>
      <td>Nội dung</td>
      <td>Check in</td>
      <td>Check out</td>
      <td>Số lượng</td>
      <td>Đơn giá</td>
      <td>Thành Tiền</td>
      <td>Tiền Cọc</td>
      <td>Đã Thanh toán</td>
      <td>Tổng cộng</td>
    </tr>
    <?php foreach ($items as $i => $it): ?>
      <tr<?= $it['self_paid'] ? ' style="opacity:.6;"' : '' ?>>
        <td class="text-center"><?= $i + 1 ?></td>
        <td><?= e($it['label']) ?><?= $it['self_paid'] ? ' <span style="font-style:italic; color:#888;">(khách tự đóng)</span>' : '' ?></td>
        <td class="text-center"><?= e($it['cin']) ?></td>
        <td class="text-center"><?= e($it['cout']) ?></td>
        <td class="text-center">1,00</td>
        <td></td>
        <td class="text-end<?= $it['self_paid'] ? '' : ' amount-cell' ?>"><?= number_format($it['amount'], 0, ',', '.') ?></td>
        <?php if ($i === 0): ?>
          <td class="deposit-cell" rowspan="8"><?= number_format($deposit, 0, ',', '.') ?></td>
        <?php endif; ?>
        <td></td>
        <td class="text-end<?= $it['self_paid'] ? '' : ' amount-cell' ?>"><?= $it['self_paid'] ? '—' : number_format($it['amount'], 0, ',', '.') ?></td>
      </tr>
    <?php endforeach; ?>
    <tr>
      <td class="text-center">9</td>
      <td>Tiền cọc</td>
      <td colspan="3"></td>
      <td></td>
      <td></td>
      <td></td>
      <td></td>
      <td></td>
    </tr>
    <tr>
      <td colspan="7" class="total-label">TỔNG CỘNG</td>
      <td colspan="3" class="total-value" id="subTotalCell"><?= number_format($subTotal, 0, ',', '.') ?></td>
    </tr>
    <tr>
      <td colspan="7" class="settle-label" id="settleLabel">HOÀN LẠI CHO SALE</td>
      <td colspan="3" class="settle-value" id="settleAmount"><?= number_format(abs($settleDefault), 0, ',', '.') ?></td>
    </tr>
  </table>

  <?php if ($selfPaidSet): ?>
    <div style="font-size:11px; color:#888; margin-top:4px; font-style:italic;">* Khoản đánh dấu "khách tự đóng" không tính vào Tổng cộng công ty cần thu.</div>
  <?php endif; ?>

  <label class="deposit-toggle d-print-none" style="display:block; margin-top:10px;">
    <input type="checkbox" id="applyDeposit" <?= $applyDepositDefault ? 'checked' : '' ?> <?= $deposit > 0 ? '' : 'disabled' ?>>
    Trừ tiền cọc đã thu (<?= number_format($deposit, 0, ',', '.') ?> đ) vào kỳ này<?= $isLastPeriod ? ' — kỳ cuối, gợi ý trừ cọc' : '' ?>
  </label>

<script>
var subTotal = <?= (float)$subTotal ?>;
var deposit = <?= (float)$deposit ?>;
function fmt(n) { return new Intl.NumberFormat('vi-VN').format(Math.round(Math.abs(n))); }
function recalcSettle() {
  var applied = document.getElementById('applyDeposit').checked ? deposit : 0;
  var settle = subTotal - applied;
  var labelEl = document.getElementById('settleLabel');
  var amountEl = document.getElementById('settleAmount');
  labelEl.textContent = settle >= 0 ? 'THANH TOÁN CHO HOST' : 'HOÀN LẠI CHO SALE';
  amountEl.textContent = fmt(settle);
}
document.getElementById('applyDeposit').addEventListener('change', recalcSettle);
recalcSettle();
</script>
</body>
</html>
