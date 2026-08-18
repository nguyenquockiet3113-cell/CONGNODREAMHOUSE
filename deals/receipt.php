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

$roomTotal = (float)$deal['nights'] * (float)$deal['price_per_unit'] + (float)$deal['extra_fee'];
$receiverName = current_user()['full_name'] ?? '';
$today = date('d/m/Y');
$paymentMethodDefault = $deal['payment_method'] === 'tien_mat' ? 'tien_mat' : 'chuyen_khoan';

$pageTitle = 'Biên nhận ' . $deal['room_code'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Biên nhận <?= e($deal['room_code']) ?> - <?= e($deal['guest_name']) ?></title>
<style>
  body { font-family: "Times New Roman", Times, serif; font-size: 13pt; color: #000; max-width: 210mm; margin: 0 auto; padding: 16mm 18mm; }
  .toolbar { text-align: center; margin-bottom: 16px; font-family: Arial, sans-serif; }
  .toolbar button { font-size: 14px; padding: 8px 20px; cursor: pointer; }
  .rc-header { text-align: center; margin-bottom: 6px; }
  .rc-header img { height: 56px; }
  .rc-company { font-weight: bold; font-size: 15pt; }
  .rc-address { font-size: 10.5pt; }
  h1 { text-align: center; font-size: 20pt; margin: 10px 0 0; }
  .rc-sub { text-align: center; font-style: italic; font-size: 11pt; margin-bottom: 14px; }
  .rc-field { margin: 4px 0; }
  .rc-field label { font-weight: bold; }
  .rc-field input, .rc-field select { font-family: inherit; font-size: inherit; border: none; border-bottom: 1px dotted #333; background: transparent; }
  table.rc-table { width: 100%; border-collapse: collapse; margin-top: 12px; }
  table.rc-table th, table.rc-table td { border: 1px solid #333; padding: 4px 6px; font-size: 11pt; }
  table.rc-table th { background: #f0f0f0; text-align: center; }
  .text-end { text-align: right; }
  .text-center { text-align: center; }
  .rc-total-row td { font-weight: bold; }
  .rc-cell-input { width: 100%; border: none; background: transparent; font-family: inherit; font-size: inherit; text-align: right; }
  .rc-words { margin-top: 10px; }
  .rc-payment { margin-top: 6px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
  .rc-payment .amount { color: #c0392b; font-weight: bold; font-size: 14pt; }
  .rc-sign-table { width: 100%; margin-top: 40px; border-collapse: collapse; }
  .rc-sign-table td { width: 50%; text-align: center; vertical-align: top; padding-top: 55px; font-weight: bold; }
  .rc-sign-table input { text-align: center; font-weight: bold; border: none; border-bottom: 1px dotted #333; background: transparent; width: 80%; margin-top: 4px; }
  @media print {
    .toolbar { display: none; }
    body { padding: 0; }
    .rc-field input, .rc-field select, .rc-cell-input, .rc-sign-table input { border-bottom: none; }
  }
</style>
</head>
<body>
  <div class="toolbar">
    <button onclick="window.print()">🖨️ In biên nhận</button>
    <button onclick="window.close()">Đóng</button>
  </div>

  <div class="rc-header">
    <img src="<?= url('/assets/img/logo.png') ?>" alt="Logo"><br>
    <span class="rc-company">DREAM'S HOUSE</span><br>
    <span class="rc-address">Địa chỉ/Address: L6-12.01, Landmark 6, 720A Điện Biên Phủ, Phường Thạnh Mỹ Tây, TP.HCM</span>
  </div>
  <h1>BIÊN NHẬN</h1>
  <div class="rc-sub">RECEIPT</div>

  <div class="rc-field">
    <label>Ngày/Date:</label>
    <input type="text" id="rc_date" value="<?= e($today) ?>" size="14">
  </div>
  <div class="rc-field">
    <label>Khách hàng/Customer's Name:</label>
    <input type="text" id="rc_customer" value="<?= e($deal['guest_name']) ?>" size="40">
  </div>
  <div class="rc-field">
    <label>Căn hộ số/Apartment No:</label> <?= e($deal['room_code']) ?>
  </div>

  <table class="rc-table">
    <thead>
      <tr>
        <th style="width:30px;">STT</th>
        <th>Content</th>
        <th>IN</th>
        <th>OUT</th>
        <th>Number of night</th>
        <th>Price</th>
        <th>Surcharge</th>
        <th>Total amount</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td class="text-center">1</td>
        <td>Tiền phòng/ Room charge</td>
        <td class="text-center"><?= e(vndate($deal['checkin_date'])) ?></td>
        <td class="text-center"><?= e(vndate($deal['checkout_date'])) ?></td>
        <td class="text-center"><?= (int)$deal['nights'] ?></td>
        <td class="text-end"><?= number_format((float)$deal['price_per_unit'], 0, ',', '.') ?></td>
        <td class="text-end"><?= $deal['extra_fee'] > 0 ? number_format((float)$deal['extra_fee'], 0, ',', '.') : '' ?></td>
        <td class="text-end rc-line" data-amount="<?= (float)$roomTotal ?>"><?= number_format($roomTotal, 0, ',', '.') ?></td>
      </tr>
      <tr>
        <td class="text-center">2</td>
        <td>Tiền điện/ Electricity bill</td>
        <td></td><td></td><td></td><td></td><td></td>
        <td class="text-end"><input type="number" step="1000" class="rc-cell-input rc-line-input" value="0"></td>
      </tr>
      <tr>
        <td class="text-center">3</td>
        <td>Water and management bill</td>
        <td></td><td></td><td></td><td></td><td></td>
        <td class="text-end"><input type="number" step="1000" class="rc-cell-input rc-line-input" value="0"></td>
      </tr>
      <tr>
        <td class="text-center">4</td>
        <td>Wifi/internet</td>
        <td></td><td></td><td></td><td></td><td></td>
        <td class="text-end"><input type="number" step="1000" class="rc-cell-input rc-line-input" value="0"></td>
      </tr>
      <tr>
        <td class="text-center">5</td>
        <td><input type="text" class="rc-cell-input" style="text-align:left;" placeholder="(khoản khác nếu có)"></td>
        <td></td><td></td><td></td><td></td><td></td>
        <td class="text-end"><input type="number" step="1000" class="rc-cell-input rc-line-input" value="0"></td>
      </tr>
      <tr class="rc-total-row">
        <td colspan="7" class="text-center">TOTAL:</td>
        <td class="text-end" id="rc_total"><?= number_format($roomTotal, 0, ',', '.') ?></td>
      </tr>
    </tbody>
  </table>

  <div class="rc-words">
    <label>Viết bằng chữ/Write in letters:</label>
    <input type="text" id="rc_words" value="<?= e(vn_money_to_words($roomTotal)) ?>" style="width:80%; border:none; border-bottom:1px dotted #333; background:transparent; font-family:inherit; font-size:inherit;">
  </div>

  <div class="rc-payment">
    <div><strong>Payment:</strong> <span class="amount">VND <span id="rc_payment_amount"><?= number_format($roomTotal, 0, ',', '.') ?></span></span></div>
    <div>
      <strong>Payment method:</strong>
      <select id="rc_method">
        <option value="tien_mat" <?= $paymentMethodDefault === 'tien_mat' ? 'selected' : '' ?>>TIỀN MẶT/CASH</option>
        <option value="chuyen_khoan" <?= $paymentMethodDefault === 'chuyen_khoan' ? 'selected' : '' ?>>ỦY NHIỆM CHI/BANK TRANSFER</option>
      </select>
    </div>
  </div>

  <table class="rc-sign-table">
    <tr>
      <td>
        KHÁCH HÀNG<br><span style="font-weight:normal; font-style:italic;">CUSTOMER</span>
        <br><input type="text" id="rc_sign_customer" value="<?= e($deal['guest_name']) ?>">
      </td>
      <td>
        NHÂN VIÊN THU TIỀN<br><span style="font-weight:normal; font-style:italic;">RECEIVER</span>
        <br><input type="text" id="rc_sign_receiver" value="<?= e($receiverName) ?>">
      </td>
    </tr>
  </table>

<script>
function fmtVnd(n) { return new Intl.NumberFormat('vi-VN').format(Math.round(n)); }
function rcRecalc() {
  var total = <?= (float)$roomTotal ?>;
  document.querySelectorAll('.rc-line-input').forEach(function (inp) {
    total += parseFloat(inp.value) || 0;
  });
  document.getElementById('rc_total').textContent = fmtVnd(total);
  document.getElementById('rc_payment_amount').textContent = fmtVnd(total);
}
document.querySelectorAll('.rc-line-input').forEach(function (inp) {
  inp.addEventListener('input', rcRecalc);
});
document.getElementById('rc_customer').addEventListener('input', function () {
  document.getElementById('rc_sign_customer').value = this.value;
});
</script>
</body>
</html>
