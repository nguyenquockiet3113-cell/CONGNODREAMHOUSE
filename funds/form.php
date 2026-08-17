<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$entry = [
    'id' => 0, 'fund_type' => $_GET['type'] ?? 'cash', 'bank_account_id' => (int)($_GET['bank_id'] ?? 0),
    'tx_date' => date('Y-m-d'), 'zone' => '', 'content' => '', 'amount_in' => '', 'amount_out' => '',
    'note' => '', 'is_closing' => 0, 'attachment_path' => '',
];
$errors = [];

if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM fund_ledger WHERE id = ?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found) {
        flash('danger', 'Không tìm thấy giao dịch.');
        redirect('/funds/index.php');
    }
    $entry = $found;
}

$bankAccounts = $pdo->query('SELECT * FROM bank_accounts ORDER BY bank_name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $entry['fund_type'] = $_POST['fund_type'] ?? 'cash';
    $entry['bank_account_id'] = $entry['fund_type'] === 'bank' ? (int)($_POST['bank_account_id'] ?? 0) : null;
    $entry['tx_date'] = $_POST['tx_date'] ?? '';
    $entry['zone'] = trim($_POST['zone'] ?? '');
    $entry['content'] = trim($_POST['content'] ?? '');
    $entry['amount_in'] = (float)($_POST['amount_in'] ?? 0);
    $entry['amount_out'] = (float)($_POST['amount_out'] ?? 0);
    $entry['note'] = trim($_POST['note'] ?? '');
    $entry['is_closing'] = isset($_POST['is_closing']) ? 1 : 0;

    if (!in_array($entry['fund_type'], ['cash', 'bank', 'company'], true)) $errors[] = 'Loại quỹ không hợp lệ.';
    if ($entry['fund_type'] === 'bank' && !$entry['bank_account_id']) $errors[] = 'Vui lòng chọn tài khoản ngân hàng.';
    if (!$entry['tx_date']) $errors[] = 'Vui lòng chọn ngày.';
    if ($entry['content'] === '') $errors[] = 'Vui lòng nhập nội dung.';
    if ($entry['amount_in'] <= 0 && $entry['amount_out'] <= 0 && !$entry['is_closing']) {
        $errors[] = 'Vui lòng nhập số tiền Thu hoặc Chi (hoặc đánh dấu là dòng chốt quỹ).';
    }

    $attachmentPath = $entry['attachment_path'];
    if (!empty($_FILES['attachment']['name'])) {
        $file = $_FILES['attachment'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExt, true)) {
                $errors[] = 'Ảnh đính kèm chỉ chấp nhận JPG, PNG, WEBP.';
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $errors[] = 'Ảnh đính kèm tối đa 5MB.';
            } else {
                $uploadDir = __DIR__ . '/../uploads/fund_ledger/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $newName = 'fl_' . bin2hex(random_bytes(8)) . '.' . $ext;
                if (move_uploaded_file($file['tmp_name'], $uploadDir . $newName)) {
                    $attachmentPath = 'uploads/fund_ledger/' . $newName;
                } else {
                    $errors[] = 'Tải ảnh lên thất bại.';
                }
            }
        }
    }

    if (!$errors) {
        $now = date('Y-m-d H:i:s');
        if ($id) {
            $stmt = $pdo->prepare(
                'UPDATE fund_ledger SET fund_type=?, bank_account_id=?, tx_date=?, zone=?, content=?, amount_in=?, amount_out=?, note=?, is_closing=?, attachment_path=? WHERE id=?'
            );
            $stmt->execute([
                $entry['fund_type'], $entry['bank_account_id'], $entry['tx_date'], $entry['zone'], $entry['content'],
                $entry['amount_in'], $entry['amount_out'], $entry['note'], $entry['is_closing'], $attachmentPath, $id,
            ]);
            flash('success', 'Đã cập nhật giao dịch.');
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO fund_ledger (fund_type, bank_account_id, tx_date, zone, content, amount_in, amount_out, note, is_closing, attachment_path, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)'
            );
            $stmt->execute([
                $entry['fund_type'], $entry['bank_account_id'], $entry['tx_date'], $entry['zone'], $entry['content'],
                $entry['amount_in'], $entry['amount_out'], $entry['note'], $entry['is_closing'], $attachmentPath, $now,
            ]);
            flash('success', 'Đã thêm giao dịch.');
        }
        redirect('/funds/index.php?type=' . $entry['fund_type'] . '&bank_id=' . $entry['bank_account_id']);
    }
}

$pageTitle = $id ? 'Sửa giao dịch quỹ' : 'Thêm giao dịch quỹ';
require_once __DIR__ . '/../includes/header.php';
?>
<h4 class="mb-3"><i class="bi bi-journal-text"></i> <?= $id ? 'Sửa giao dịch quỹ' : 'Thêm giao dịch quỹ' ?></h4>

<?php foreach ($errors as $err): ?>
  <div class="alert alert-danger py-2"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="card">
  <div class="card-body">
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Sổ quỹ *</label>
          <select name="fund_type" id="fund_type" class="form-select" required>
            <option value="cash" <?= $entry['fund_type'] === 'cash' ? 'selected' : '' ?>>Tiền mặt</option>
            <option value="bank" <?= $entry['fund_type'] === 'bank' ? 'selected' : '' ?>>Quỹ ngân hàng</option>
            <option value="company" <?= $entry['fund_type'] === 'company' ? 'selected' : '' ?>>Quỹ công ty</option>
          </select>
        </div>
        <div class="col-md-4" id="bankWrap" style="<?= $entry['fund_type'] === 'bank' ? '' : 'display:none;' ?>">
          <label class="form-label">Tài khoản ngân hàng</label>
          <select name="bank_account_id" class="form-select">
            <option value="">-- Chọn --</option>
            <?php foreach ($bankAccounts as $ba): ?>
              <option value="<?= $ba['id'] ?>" <?= (int)$entry['bank_account_id'] === (int)$ba['id'] ? 'selected' : '' ?>><?= e($ba['bank_name']) ?><?= $ba['account_number'] ? ' - ' . e($ba['account_number']) : '' ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Ngày *</label>
          <input type="date" name="tx_date" class="form-control" required value="<?= e($entry['tx_date']) ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label">Khu vực</label>
          <input type="text" name="zone" class="form-control" value="<?= e($entry['zone']) ?>">
        </div>
        <div class="col-md-8">
          <label class="form-label">Nội dung *</label>
          <input type="text" name="content" class="form-control" required value="<?= e($entry['content']) ?>">
        </div>

        <div class="col-md-3">
          <label class="form-label">Thu (đ)</label>
          <input type="number" step="1000" name="amount_in" class="form-control" value="<?= e($entry['amount_in']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Chi (đ)</label>
          <input type="number" step="1000" name="amount_out" class="form-control" value="<?= e($entry['amount_out']) ?>">
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <div class="form-check">
            <input type="checkbox" name="is_closing" id="is_closing" class="form-check-input" <?= $entry['is_closing'] ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_closing">Dòng chốt quỹ</label>
          </div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Ảnh đính kèm</label>
          <input type="file" name="attachment" class="form-control" accept=".jpg,.jpeg,.png,.webp">
          <?php if ($entry['attachment_path']): ?>
            <div class="small mt-1"><a href="<?= url('/' . $entry['attachment_path']) ?>" target="_blank">Xem ảnh hiện tại</a></div>
          <?php endif; ?>
        </div>

        <div class="col-12">
          <label class="form-label">Ghi chú</label>
          <input type="text" name="note" class="form-control" value="<?= e($entry['note']) ?>">
        </div>
      </div>
      <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-success"><i class="bi bi-check-lg"></i> Lưu</button>
        <a href="<?= url('/funds/index.php') ?>" class="btn btn-outline-secondary">Hủy</a>
      </div>
    </form>
  </div>
</div>

<script>
document.getElementById('fund_type').addEventListener('change', function () {
  document.getElementById('bankWrap').style.display = this.value === 'bank' ? '' : 'none';
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
