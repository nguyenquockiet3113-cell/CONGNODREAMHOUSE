<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/deal_helpers.php';
require_once __DIR__ . '/../includes/xlsx_reader.php';
require_login();

$result = null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (empty($_FILES['csv_file']['name']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Vui lòng chọn file CSV hoặc Excel (.xlsx) để nhập.';
    } else {
        $tmpPath = $_FILES['csv_file']['tmp_name'];
        $ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));

        try {
            $allRows = [];
            if ($ext === 'xlsx') {
                $allRows = read_xlsx_rows($tmpPath);
            } else {
                $handle = fopen($tmpPath, 'r');
                $bom = fread($handle, 3);
                if ($bom !== "\xEF\xBB\xBF") rewind($handle);
                while (($row = fgetcsv($handle)) !== false) $allRows[] = $row;
                fclose($handle);
            }
        } catch (Throwable $e) {
            $errors[] = 'Lỗi đọc file: ' . $e->getMessage();
            $allRows = [];
        }

        $header = $allRows ? array_shift($allRows) : null;
        if (!$header) {
            if (!$errors) $errors[] = 'File rỗng hoặc không đúng định dạng.';
        } else {
            $header = array_map(fn($h) => trim(strtolower((string)$h)), $header);
            $required = ['guest_name', 'room_code', 'checkin_date', 'checkout_date', 'price_per_unit'];
            $missing = array_diff($required, $header);
            if ($missing) {
                $errors[] = 'File thiếu cột bắt buộc: ' . implode(', ', $missing) . '. Cột bắt buộc: ' . implode(', ', $required);
            } else {
                $inserted = 0;
                $skipped = 0;
                $now = date('Y-m-d H:i:s');
                foreach ($allRows as $row) {
                    if (!$row || count(array_filter($row, fn($v) => $v !== '')) === 0) continue;
                    while (count($row) < count($header)) $row[] = '';
                    $r = array_combine($header, array_slice($row, 0, count($header)));

                    $roomCode = trim($r['room_code'] ?? '');
                    $guestName = trim($r['guest_name'] ?? '');
                    $checkin = xlsx_maybe_date(trim($r['checkin_date'] ?? ''));
                    $checkout = xlsx_maybe_date(trim($r['checkout_date'] ?? ''));
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
                        generate_deal_periods($pdo, $newId, $checkin, $checkout, $price);
                    }

                    $inserted++;
                }
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
      Nhận cả file <strong>.xlsx</strong> (Excel) và <strong>.csv</strong> — không cần Save As CSV trước. File cần có dòng tiêu đề (header) với các cột bắt buộc: <code>guest_name, room_code, checkin_date, checkout_date, price_per_unit</code>.
      Có thể thêm các cột tùy chọn: <code>bedrooms, extra_fee, note</code>. Ngày theo định dạng <code>YYYY-MM-DD</code> (nếu dùng Excel với cột định dạng Ngày tháng, hệ thống tự nhận diện).
      Deal có từ 30 đêm trở lên sẽ tự động được phân loại Dài hạn và sinh kỳ thanh toán.
    </p>
    <a href="<?= url('/deals/sample_template.php') ?>" class="btn btn-outline-secondary mb-3"><i class="bi bi-download"></i> Tải file mẫu (CSV)</a>
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="mb-3">
        <input type="file" name="csv_file" accept=".csv,.xlsx" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-success"><i class="bi bi-upload"></i> Nhập dữ liệu</button>
      <a href="<?= url('/deals/short.php') ?>" class="btn btn-outline-secondary">Quay lại</a>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
