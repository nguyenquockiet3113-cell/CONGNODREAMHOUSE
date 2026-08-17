<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM contracts WHERE id = ?');
$stmt->execute([$id]);
$c = $stmt->fetch();
if (!$c) {
    flash('danger', 'Không tìm thấy hợp đồng.');
    redirect('/contracts/index.php');
}

function pv(?string $v): string { return $v !== null && $v !== '' ? e($v) : '……………………'; }
$today = date('d/m/Y');
$signDay = date('j', strtotime($c['created_at'] ?? 'now'));
$signMonth = date('n', strtotime($c['created_at'] ?? 'now'));
$signYear = date('Y', strtotime($c['created_at'] ?? 'now'));
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>In hợp đồng <?= e($c['contract_code']) ?></title>
<style>
  body { font-family: "Times New Roman", Times, serif; font-size: 13pt; color: #000; max-width: 210mm; margin: 0 auto; padding: 20mm 18mm; line-height: 1.45; }
  .toolbar { text-align: center; margin-bottom: 16px; }
  .toolbar button { font-family: Arial, sans-serif; font-size: 14px; padding: 8px 20px; cursor: pointer; }
  h1, h2 { text-align: center; margin: 4px 0; }
  h1 { font-size: 15pt; }
  .sub { text-align: center; font-style: italic; font-size: 11pt; margin-bottom: 4px; }
  .right { text-align: right; }
  .center { text-align: center; }
  .bold { font-weight: bold; }
  .article-title { font-weight: bold; margin-top: 14px; margin-bottom: 4px; }
  .clause { margin: 4px 0; text-align: justify; }
  .en { font-style: italic; color: #333; }
  table.parties { width: 100%; border-collapse: collapse; margin: 10px 0; }
  table.parties td { vertical-align: top; padding: 2px 4px; }
  .sign-table { width: 100%; margin-top: 40px; border-collapse: collapse; }
  .sign-table td { width: 50%; text-align: center; vertical-align: top; padding-top: 60px; font-weight: bold; }
  hr { border: none; border-top: 1px solid #000; margin: 14px 0; }
  @media print {
    .toolbar { display: none; }
    body { padding: 0; }
  }
</style>
</head>
<body>
  <div class="toolbar">
    <button onclick="window.print()">🖨️ In hợp đồng</button>
    <button onclick="window.close()">Đóng</button>
  </div>

  <h1>HỢP ĐỒNG THUÊ CĂN HỘ</h1>
  <div class="sub">APARTMENT LEASE AGREEMENT</div>
  <p class="center bold">CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM<br>Độc Lập – Tự Do – Hạnh Phúc</p>
  <p class="center en">The Socialist Republic of Vietnam — Independence – Freedom – Happiness</p>
  <p>Số/No: <strong><?= pv($c['contract_code']) ?></strong></p>
  <p>Thành phố Hồ Chí Minh, ngày <?= $signDay ?> tháng <?= $signMonth ?> năm <?= $signYear ?><br>
  <span class="en">Ho Chi Minh City, <?= $signDay ?>/<?= $signMonth ?>/<?= $signYear ?></span></p>

  <p>GIỮA / <span class="en">BETWEEN</span></p>

  <p class="bold">BÊN CHO THUÊ / <span class="en">THE LESSOR</span>:</p>
  <table class="parties">
    <tr><td style="width:30%">Tên / <span class="en">Name</span>:</td><td class="bold"><?= pv($c['lessor_name']) ?></td>
        <td style="width:20%">Ngày sinh:</td><td><?= vndate($c['lessor_dob']) ?: '……………' ?></td></tr>
    <tr><td>Hộ chiếu/CCCD số:</td><td><?= pv($c['lessor_id_number']) ?></td>
        <td>Cấp ngày / Tại:</td><td><?= vndate($c['lessor_id_issue_date']) ?: '……' ?> / <?= pv($c['lessor_id_issue_place']) ?></td></tr>
    <tr><td>Địa chỉ liên lạc:</td><td colspan="3"><?= pv($c['lessor_address']) ?></td></tr>
  </table>
  <p>Chủ sở hữu hợp pháp căn hộ số / <span class="en">Legal owner of the apartment No</span>: <strong><?= pv($c['room_code']) ?></strong><br>
  Dưới đây được gọi là Bên cho thuê / <span class="en">Hereinafter called "the Lessor"</span></p>

  <p class="bold">BÊN THUÊ / <span class="en">THE LESSEE</span>:</p>
  <table class="parties">
    <tr><td style="width:30%">Tên / <span class="en">Name</span>:</td><td class="bold"><?= pv($c['lessee_name']) ?></td>
        <td style="width:20%">Ngày sinh:</td><td><?= vndate($c['lessee_dob']) ?: '……………' ?></td></tr>
    <tr><td>Quốc tịch:</td><td><?= pv($c['lessee_nationality']) ?></td>
        <td>SĐT / Email:</td><td><?= pv($c['lessee_phone']) ?> / <?= pv($c['lessee_email']) ?></td></tr>
    <tr><td>Hộ chiếu/CCCD số:</td><td><?= pv($c['lessee_id_number']) ?></td>
        <td>Cấp ngày / Tại:</td><td><?= vndate($c['lessee_id_issue_date']) ?: '……' ?> / <?= pv($c['lessee_id_issue_place']) ?></td></tr>
    <tr><td>Địa chỉ liên lạc:</td><td colspan="3"><?= pv($c['lessee_address']) ?></td></tr>
  </table>
  <p>Dưới đây được gọi là Bên Thuê / <span class="en">Hereinafter called "the Lessee"</span></p>

  <hr>

  <div class="article-title">Điều/Article 1: Đối tượng hợp đồng / Object of the contract</div>
  <p class="clause">Bên Thuê đồng ý thuê và Bên Cho Thuê đồng ý cho thuê căn hộ: <strong><?= pv($c['room_code']) ?></strong> tại <strong><?= pv($c['zone']) ?></strong>.<br>
  <span class="en">The Lessee agrees to rent and the Lessor agrees to lease the apartment No: <?= pv($c['room_code']) ?> at <?= pv($c['zone']) ?>.</span></p>

  <div class="article-title">Điều/Article 2: Mục đích thuê / Purpose of use</div>
  <p class="clause">2.1: Mục đích thuê là để ở. <span class="en">Purpose for using the premises for personal residence.</span></p>
  <p class="clause">2.2: Trang thiết bị nội thất và các tiện ích: đầy đủ. <span class="en">Furniture and appliances: full.</span></p>

  <div class="article-title">Điều/Article 3: Tiền thuê / Apartment rental</div>
  <p class="clause">3.1: Tiền thuê nhà là: <strong><?= money($c['monthly_rent']) ?></strong>/tháng. <span class="en">The apartment rental shall be: <?= money($c['monthly_rent']) ?>/month.</span></p>
  <?php if ($c['rent_note']): ?>
  <p class="clause">3.2: <?= e($c['rent_note']) ?>.</p>
  <?php endif; ?>
  <p class="clause">3.3: Tiền thuê nhà sẽ được cố định trong thời hạn Hợp đồng thuê. <span class="en">The Rental price shall be fixed during the term of the Lease.</span></p>
  <p class="clause">3.4: Tiền đặt cọc / <span class="en">Security deposit</span>: Ngay sau khi ký kết hợp đồng này, Bên Thuê nhà sẽ đặt cọc là: <strong><?= money($c['deposit_amount']) ?></strong>. Khoản đặt cọc sẽ được hoàn trả lại ngay sau khi kết thúc hợp đồng theo đúng thỏa thuận hai bên.</p>

  <div class="article-title">Điều/Article 4: Thời hạn thuê / Duration of the lease</div>
  <p class="clause">4.1: Thời hạn thuê bắt đầu từ <?= pv($c['checkin_time']) ?> ngày <strong><?= vndate($c['start_date']) ?></strong> đến <?= pv($c['checkout_time']) ?> ngày <strong><?= vndate($c['end_date']) ?></strong>.<br>
  <span class="en">The lease term shall commence at <?= pv($c['checkin_time']) ?> on <?= vndate($c['start_date']) ?> and expire at <?= pv($c['checkout_time']) ?> on <?= vndate($c['end_date']) ?>.</span></p>

  <div class="article-title">Điều/Article 5: Thanh toán / Method of payment</div>
  <p class="clause">5.1: Tiền thuê nhà sẽ được thanh toán bằng hình thức: <strong><?= $c['payment_method'] === 'tien_mat' ? 'Tiền mặt' : 'Chuyển khoản' ?></strong>.<br>
  Số tài khoản: <?= pv($c['receiving_account']) ?> — Ngân hàng: <?= pv($c['bank_name']) ?> — Người thụ hưởng: <?= pv($c['beneficiary_name']) ?></p>
  <?php if ($c['payment_note']): ?>
  <p class="clause">5.2: <?= e($c['payment_note']) ?></p>
  <?php endif; ?>

  <div class="article-title">Điều/Article 6: Nghĩa vụ của hai bên / Both parties responsibilities</div>
  <p class="clause">6.1: Bên Cho Thuê có trách nhiệm bàn giao căn hộ đúng hiện trạng, đảm bảo quyền sử dụng riêng biệt cho Bên Thuê trong suốt thời gian thuê, và nhanh chóng sửa chữa các hư hỏng thuộc phần xây dựng của căn hộ.</p>
  <p class="clause">6.2: Bên Thuê có trách nhiệm thanh toán đúng hẹn, sử dụng căn hộ đúng mục đích, bàn giao lại căn hộ với đầy đủ trang thiết bị trong tình trạng tốt khi hết hạn hợp đồng, và chịu trách nhiệm với các hư hỏng do mình gây ra.</p>

  <div class="article-title">Điều/Article 7: Kết thúc hợp đồng / Termination of this lease agreement</div>
  <p class="clause">Hợp đồng kết thúc khi hết hạn hoặc khi hai bên thỏa thuận chấm dứt trước hạn. Trường hợp Bên Thuê đơn phương chấm dứt trước hạn, tiền cọc và phần tiền thuê đã trả trước cho thời gian chưa sử dụng (nếu có) sẽ không được hoàn trả, trừ khi hai bên có thỏa thuận khác.</p>

  <div class="article-title">Điều/Article 8: Điều khoản chung / General Provisions</div>
  <p class="clause">8.1: Hợp đồng này được thực hiện đầy đủ bởi hai bên. Mọi điều chỉnh, bổ sung phải được sự đồng ý bằng văn bản của hai bên. Nếu có tranh chấp, hai bên ưu tiên giải quyết qua hòa giải, thương lượng.</p>
  <p class="clause">8.2: Hợp đồng này được lập thành hai (02) bản có giá trị như nhau; mỗi bên giữ một bản.</p>

  <?php if ($c['note']): ?>
  <div class="article-title">Ghi chú thêm</div>
  <p class="clause"><?= nl2br(e($c['note'])) ?></p>
  <?php endif; ?>

  <table class="sign-table">
    <tr>
      <td>BÊN CHO THUÊ<br><span class="en" style="font-weight:normal;">THE LESSOR</span><br><br><br><br><?= e($c['lessor_name']) ?></td>
      <td>BÊN THUÊ<br><span class="en" style="font-weight:normal;">THE LESSEE</span><br><br><br><br><?= e($c['lessee_name']) ?></td>
    </tr>
  </table>
</body>
</html>
