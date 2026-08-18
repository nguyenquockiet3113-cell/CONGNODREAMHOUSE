<?php
require_once __DIR__ . '/../config/config.php';
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
            if (!in_array('room_code', $header, true)) {
                $errors[] = 'File thiếu cột bắt buộc: room_code. Các cột hỗ trợ: room_code, zone, bedrooms.';
            } else {
                $inserted = 0; $updated = 0; $skipped = 0;
                $now = date('Y-m-d H:i:s');
                foreach ($allRows as $row) {
                    if (!$row || count(array_filter($row, fn($v) => $v !== '')) === 0) continue;
                    while (count($row) < count($header)) $row[] = '';
                    $r = array_combine($header, array_slice($row, 0, count($header)));
                    $roomCode = trim($r['room_code'] ?? '');
                    if ($roomCode === '') { $skipped++; continue; }
                    $zone = trim($r['zone'] ?? '');
                    $bedrooms = isset($r['bedrooms']) && $r['bedrooms'] !== '' ? (int)$r['bedrooms'] : 1;

                    $existing = $pdo->prepare('SELECT id FROM rooms WHERE room_code = ?');
                    $existing->execute([$roomCode]);
                    $row2 = $existing->fetch();

                    if ($row2) {
                        $pdo->prepare('UPDATE rooms SET zone=?, bedrooms=?, updated_at=? WHERE id=?')
                            ->execute([$zone, $bedrooms, $now, $row2['id']]);
                        $updated++;
                    } else {
                        $pdo->prepare('INSERT INTO rooms (room_code, zone, bedrooms, created_at, updated_at) VALUES (?,?,?,?,?)')
                            ->execute([$roomCode, $zone, $bedrooms, $now, $now]);
                        $inserted++;
                    }
                }
                $result = ['inserted' => $inserted, 'updated' => $updated, 'skipped' => $skipped];
            }
        }
    }
}

$pageTitle = 'Nhập phòng từ Excel/CSV';
require_once __DIR__ . '/../includes/header.php';
?>
<h4 class="mb-3"><i class="bi bi-file-earmark-arrow-up"></i> Nhập danh sách phòng từ Excel/CSV</h4>

<?php foreach ($errors as $err): ?>
  <div class="alert alert-danger py-2"><?= e($err) ?></div>
<?php endforeach; ?>

<?php if ($result): ?>
  <div class="alert alert-success">
    Đã thêm mới <strong><?= $result['inserted'] ?></strong> phòng, cập nhật <strong><?= $result['updated'] ?></strong> phòng
    <?= $result['skipped'] > 0 ? ', bỏ qua ' . $result['skipped'] . ' dòng thiếu mã phòng' : '' ?>.
  </div>
<?php endif; ?>

<div class="card">
  <div class="card-body">
    <p class="small text-muted">
      Nhận cả file <strong>.xlsx</strong> (Excel) và <strong>.csv</strong> — không cần Save As CSV trước. File cần có dòng tiêu đề (header) với cột bắt buộc: <code>room_code</code>. Có thể thêm cột tùy chọn: <code>zone, bedrooms</code>.
      Phòng có mã trùng với phòng đã có sẽ được <strong>cập nhật</strong> (khu vực, số phòng ngủ), mã chưa có sẽ được <strong>thêm mới</strong>.
    </p>
    <a href="<?= url('/rooms/sample_template.php') ?>" class="btn btn-outline-secondary mb-3"><i class="bi bi-download"></i> Tải file mẫu (CSV)</a>
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="mb-3">
        <input type="file" name="csv_file" accept=".csv,.xlsx" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-success"><i class="bi bi-upload"></i> Nhập dữ liệu</button>
      <a href="<?= url('/rooms/index.php') ?>" class="btn btn-outline-secondary">Quay lại</a>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
