<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/deal_helpers.php';
require_login();

$result = null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (empty($_FILES['csv_file']['name']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Vui lòng chọn file CSV để nhập.';
    } else {
        $tmpPath = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($tmpPath, 'r');
        // Bo qua BOM UTF-8 neu co
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") rewind($handle);

        $header = fgetcsv($handle);
        if (!$header) {
            $errors[] = 'File CSV rỗng hoặc không đúng định dạng.';
        } else {
            $header = array_map(fn($h) => trim(strtolower($h)), $header);
            $required = ['guest_name', 'room_code', 'checkin_date', 'checkout_date', 'price_per_unit'];
            $missing = array_diff($required, $header);
            if ($missing) {
                $errors[] = 'File thiếu cột bắt buộc: ' . implode(', ', $missing) . '. Cột bắt buộc: ' . implode(', ', $required);
            } else {
                $inserted = 0;
                $skipped = 0;
                $now = date('Y-m-d H:i:s');
                while (($row = fgetcsv($handle)) !== false) {
                    if (count($row) < count($header)) continue;
                    $r = array_combine($header, $row);

                    $roomCode = trim($r['room_code'] ?? '');
                    $guestName = trim($r['guest_name'] ?? '');
                    $checkin = trim($r['checkin_date'] ?? '');
                    $checkout = trim($r['checkout_date'] ?? '');
                    $price = (float)($r['price_per_unit'] ?? 0);
                    $extra = (float)($r['extra_fee'] ?? 0);
                    $bedrooms = isset($r['bedrooms']) && $r['bedrooms'] !== '' ? (int)$r['bedrooms'] : null;
                    $note = trim($r['note'] ?? '');

                    if ($roomCode === '' || $guestName === '' || !strtotime($checkin) || !strtotime($checkout) || $checkout <= $checkin) {
                        $skipped++;
                        continue;
                    }

                    $nights = deal_nights($checkin, $checkout);
                    $dealType = deal_classify($nights);
                    $total = $nights * $price + $extra;

                    $pdo->prepare(
                        'INSERT INTO deals (room_code, bedrooms, zone, guest_name, checkin_date, checkout_date, nights, deal_type, price_per_unit, deposit_amount, extra_fee, total_amount, payment_method, note, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,0,?,?,?,?,?,?)'
                    )->execute([$roomCode, $bedrooms, null, $guestName, $checkin, $checkout, $nights, $dealType, $price, $extra, $total, 'chuyen_khoan', $note, $now, $now]);
                    $newId = (int)$pdo->lastInsertId();

                    if ($dealType === 'dai_han') {
                        generate_deal_periods($pdo, $newId, $checkin, $checkout, $price, 0);
                    }

                    $inserted++;
                }
                fclose($handle);
                $result = ['inserted' => $inserted, 'skipped' => $skipped];
            }
        }
    }
}

$pageTitle = 'Nhập dữ liệu từ Excel/CSV';
require_once __DIR__ . '/../includes/header.php';
?>
<h4 class="mb-3"><i class="bi bi-file-earmark-arrow-up"></i> Nhập deal từ Excel/CSV</h4>

<?php foreach ($errors as $err): ?>
  <div class="alert alert-danger py-2"><?= e($err) ?></div>
<?php endforeach; ?>

<?php if ($result): ?>
  <div class="alert alert-success">Đã nhập thành công <strong><?= $result['inserted'] ?></strong> deal<?= $result['skipped'] > 0 ? ', bỏ qua ' . $result['skipped'] . ' dòng thiếu dữ liệu/không hợp lệ' : '' ?>.</div>
<?php endif; ?>

<div class="card">
  <div class="card-body">
    <p class="small text-muted">
      File CSV cần có dòng tiêu đề (header) với các cột bắt buộc: <code>guest_name, room_code, checkin_date, checkout_date, price_per_unit</code>.
      Có thể thêm các cột tùy chọn: <code>bedrooms, extra_fee, note</code>. Ngày theo định dạng <code>YYYY-MM-DD</code>.
      Deal có từ 30 đêm trở lên sẽ tự động được phân loại Dài hạn và sinh kỳ thanh toán.
    </p>
    <a href="<?= url('/deals/sample_template.php') ?>" class="btn btn-outline-secondary mb-3"><i class="bi bi-download"></i> Tải file mẫu (CSV)</a>
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="mb-3">
        <input type="file" name="csv_file" accept=".csv" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-success"><i class="bi bi-upload"></i> Nhập dữ liệu</button>
      <a href="<?= url('/deals/short.php') ?>" class="btn btn-outline-secondary">Quay lại</a>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
