<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/deal_helpers.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM deals WHERE id = ?');
$stmt->execute([$id]);
$deal = $stmt->fetch();
if (!$deal) {
    flash('danger', 'Không tìm thấy deal.');
    redirect('/deals/short.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $declaredPrice = (float)($_POST['invoice_declared_price'] ?? 0);
    $pdo->prepare('UPDATE deals SET invoice_declared_price = ?, issue_invoice = 1, updated_at = ? WHERE id = ?')
        ->execute([$declaredPrice, date('Y-m-d H:i:s'), $id]);
    $deal['invoice_declared_price'] = $declaredPrice;
}

$nights = (int)$deal['nights'];
$realUnit = (float)$deal['price_per_unit'];
$realTotal = $realUnit * $nights;

$hasDeclaredPrice = $deal['invoice_declared_price'] !== null && $deal['invoice_declared_price'] !== '';

$pageTitle = 'Bảng kê xuất hóa đơn ' . $deal['room_code'];

if ($hasDeclaredPrice) {
    $declaredUnit = (float)$deal['invoice_declared_price'];
    $declaredTotal = $declaredUnit * $nights;
    $vatAmount = round($declaredTotal * INVOICE_VAT_PERCENT / 100);
    $invoiceTotal = $declaredTotal + $vatAmount;
    $diff = $declaredTotal - $realTotal;
    $tndnAmount = round($diff * INVOICE_TNDN_PERCENT / 100);
    $totalTax = $vatAmount + $tndnAmount;
    $salePaid = (float)$deal['paid_amount'];
    $refundIfCompanyAccount = $invoiceTotal;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title><?= e($pageTitle) ?></title>
<style>
  body { font-family: Arial, sans-serif; font-size: 13px; color: #000; max-width: 900px; margin: 0 auto; padding: 20px; }
  .toolbar { text-align: center; margin-bottom: 16px; }
  .toolbar button { font-size: 14px; padding: 8px 20px; cursor: pointer; }
  .inv-banner { background: #ffff00; color: #0000cc; text-align: center; font-weight: 800; font-size: 20px; padding: 10px; border: 2px solid #000; }
  table.inv-table { width: 100%; border-collapse: collapse; margin-top: 0; }
  table.inv-table th, table.inv-table td { border: 1px solid #000; padding: 6px 8px; }
  table.inv-table th { background: #ffff00; text-align: center; font-weight: 700; }
  .text-end { text-align: right; }
  .text-center { text-align: center; }
  .peach { background: #fbe0ce; }
  .red-bold { color: #d40000; font-weight: 800; }
  .pink-bold { color: #e619c1; font-weight: 800; }
  .summary-row td { border: none; padding: 4px 8px; }
  .summary-label { text-align: right; font-weight: 700; }
  .summary-value { text-align: right; font-weight: 700; width: 140px; }
  .orange-cell { background: #fbe0ce; }
  .refund-row { background: #ffff00; }
  .refund-row td { border: none; padding: 8px; }
  .refund-label { font-weight: 700; font-style: italic; text-decoration: underline; color: #d40000; }
  .refund-value { color: #d40000; font-weight: 800; font-style: italic; text-decoration: underline; text-align: right; }
  .setup-card { max-width: 480px; margin: 60px auto; padding: 24px; border: 1px solid #ccc; border-radius: 8px; }
  .setup-card label { font-weight: 600; display: block; margin-bottom: 6px; }
  .setup-card input { width: 100%; padding: 8px; font-size: 14px; margin-bottom: 14px; box-sizing: border-box; }
  .setup-card button { padding: 10px 18px; font-size: 14px; cursor: pointer; }
  @media print {
    .toolbar { display: none; }
    body { padding: 0; max-width: 100%; }
  }
</style>
</head>
<body>
<div class="toolbar">
  <button onclick="window.print()">🖨️ In bảng kê</button>
  <button onclick="window.close()">Đóng</button>
</div>

<?php if (!$hasDeclaredPrice): ?>
  <div class="setup-card">
    <h5>Nhập giá kê khai để tính bảng kê xuất hóa đơn</h5>
    <p style="color:#666; font-size:12px;">Deal: <strong><?= e($deal['guest_name']) ?></strong> — Phòng <?= e($deal['room_code']) ?> — Giá thực nhận: <?= money($realUnit) ?>/đêm x <?= $nights ?> đêm = <?= money($realTotal) ?></p>
    <form method="post">
      <?= csrf_field() ?>
      <label>Giá kê khai trên hóa đơn (đ/đêm)</label>
      <input type="number" step="1" name="invoice_declared_price" required autofocus>
      <button type="submit">Tính bảng kê</button>
    </form>
  </div>
<?php else: ?>

  <div class="inv-banner">BẢNG TÍNH PHÍ XUẤT HÓA ĐƠN GIÁ TRỊ GIA TĂNG</div>

  <table class="inv-table">
    <thead>
      <tr>
        <th>STT</th>
        <th>Nội dung</th>
        <th>Check in</th>
        <th>Check out</th>
        <th>Số lượng</th>
        <th>Đơn giá</th>
        <th>Thành tiền</th>
        <th>% Thuế GTGT</th>
        <th>Tiền thuế</th>
        <th>Tổng TT Hóa Đơn</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td class="text-center">1</td>
        <td>Tiền booking</td>
        <td class="text-center peach" rowspan="2"><?= e(vndate($deal['checkin_date'])) ?></td>
        <td class="text-center peach" rowspan="2"><?= e(vndate($deal['checkout_date'])) ?></td>
        <td class="text-center" rowspan="2"><?= $nights ?></td>
        <td class="text-end peach"><?= number_format($realUnit, 0, ',', '.') ?></td>
        <td class="text-end"><?= number_format($realTotal, 0, ',', '.') ?></td>
        <td></td>
        <td></td>
        <td></td>
      </tr>
      <tr>
        <td class="text-center">2</td>
        <td>Phí kê</td>
        <td class="text-end peach"><?= number_format($declaredUnit, 0, ',', '.') ?></td>
        <td class="text-end"><?= number_format($declaredTotal, 0, ',', '.') ?></td>
        <td class="text-center"><?= INVOICE_VAT_PERCENT ?>%</td>
        <td class="text-end pink-bold"><?= number_format($vatAmount, 0, ',', '.') ?></td>
        <td class="text-end"><?= number_format($invoiceTotal, 0, ',', '.') ?></td>
      </tr>
    </tbody>
  </table>

  <table class="inv-table" style="border:none; margin-top:4px;">
    <tr class="summary-row">
      <td colspan="6" style="border:none;"></td>
      <td class="summary-label" style="border:none;">Phí chênh lệch</td>
      <td class="summary-value red-bold" style="border:none;"><?= number_format($diff, 0, ',', '.') ?></td>
      <td colspan="2" style="border:none;"></td>
    </tr>
    <tr class="summary-row">
      <td colspan="6" style="border:none;"></td>
      <td class="summary-label" style="border:none;">Thu <?= INVOICE_TNDN_PERCENT ?>% TNDN</td>
      <td class="summary-value pink-bold" style="border:none;"><?= number_format($tndnAmount, 0, ',', '.') ?></td>
      <td colspan="2" style="border:none;"></td>
    </tr>
    <tr class="summary-row">
      <td colspan="6" style="border:none;"></td>
      <td class="summary-label" style="border:none;">Tổng (VAT+TNDN)</td>
      <td class="summary-value" style="border:none;"><?= number_format($totalTax, 0, ',', '.') ?></td>
      <td colspan="2" style="border:none;"></td>
    </tr>
    <tr class="summary-row">
      <td colspan="6" style="border:none;"></td>
      <td class="summary-label" style="border:none;">Tiền sale đã thanh toán</td>
      <td class="summary-value orange-cell" style="border:none;"><?= number_format($salePaid, 0, ',', '.') ?></td>
      <td colspan="2" style="border:none;"></td>
    </tr>
    <tr class="refund-row">
      <td colspan="6" style="border:none;"></td>
      <td class="refund-label" colspan="2">Nếu CK vào TK CÔNG TY thì còn hoàn sale:</td>
      <td class="refund-value" colspan="2"><?= number_format($refundIfCompanyAccount, 0, ',', '.') ?></td>
    </tr>
  </table>

  <p style="color:#666; font-size:12px; margin-top:16px;">
    Ghi chú: Giá kê khai hiện tại: <?= number_format($declaredUnit, 0, ',', '.') ?> đ/đêm.
    <a href="#" onclick="document.getElementById('editPriceForm').style.display='block'; return false;">Sửa giá kê khai</a>
  </p>
  <form method="post" id="editPriceForm" style="display:none; max-width:400px;">
    <?= csrf_field() ?>
    <div style="display:flex; gap:8px;">
      <input type="number" step="1" name="invoice_declared_price" value="<?= e($deal['invoice_declared_price']) ?>">
      <button type="submit">Lưu</button>
    </div>
  </form>

<?php endif; ?>
</body>
</html>
