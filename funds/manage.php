<?php
require_once __DIR__ . '/../config/config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (isset($_POST['add_fund'])) {
        $name = trim($_POST['name'] ?? '');
        $note = trim($_POST['note'] ?? '');
        $openingBalance = (float)($_POST['opening_balance'] ?? 0);
        $openingDate = $_POST['opening_date'] ?: date('Y-m-d');
        if ($name === '') {
            flash('danger', 'Vui lòng nhập tên quỹ.');
        } else {
            $dup = $pdo->prepare('SELECT id FROM funds WHERE name = ?');
            $dup->execute([$name]);
            if ($dup->fetch()) {
                flash('danger', 'Tên quỹ này đã tồn tại.');
            } else {
                $now = date('Y-m-d H:i:s');
                $pdo->prepare('INSERT INTO funds (name, note, created_at) VALUES (?,?,?)')
                    ->execute([$name, $note, $now]);
                $newFundId = (int)$pdo->lastInsertId();

                if ($openingBalance != 0) {
                    $pdo->prepare(
                        'INSERT INTO fund_ledger (fund_type, fund_id, tx_date, content, amount_in, amount_out, created_at) VALUES (?,?,?,?,?,?,?)'
                    )->execute([
                        'custom', $newFundId, $openingDate, 'Số dư đầu kỳ',
                        $openingBalance > 0 ? $openingBalance : 0,
                        $openingBalance < 0 ? abs($openingBalance) : 0,
                        $now,
                    ]);
                }
                flash('success', 'Đã thêm sổ quỹ "' . $name . '".');
            }
        }
    } elseif (isset($_POST['rename_fund'])) {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $note = trim($_POST['note'] ?? '');
        if ($name !== '') {
            $pdo->prepare('UPDATE funds SET name=?, note=? WHERE id=?')->execute([$name, $note, $id]);
            flash('success', 'Đã cập nhật sổ quỹ.');
        }
    }
    redirect('/funds/manage.php');
}

$funds = $pdo->query('SELECT * FROM funds ORDER BY name')->fetchAll();

$pageTitle = 'Quản lý sổ quỹ';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="mb-0"><i class="bi bi-journal-plus"></i> Quản lý sổ quỹ tùy chỉnh</h4>
  <a href="<?= url('/funds/index.php') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Về sổ quỹ</a>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header">Danh sách sổ quỹ tùy chỉnh</div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>Tên quỹ</th><th>Ghi chú</th><th class="text-end">Thao tác</th></tr></thead>
          <tbody>
            <?php if (!$funds): ?>
              <tr><td colspan="3" class="text-center text-muted py-4">Chưa có sổ quỹ tùy chỉnh nào. Mặc định đã có sẵn Tiền mặt, Quỹ công ty và các tài khoản ngân hàng.</td></tr>
            <?php endif; ?>
            <?php foreach ($funds as $f): ?>
              <tr>
                <td class="fw-semibold"><?= e($f['name']) ?></td>
                <td class="small text-muted"><?= e($f['note']) ?></td>
                <td class="text-end">
                  <a href="<?= url('/funds/index.php?type=custom&fund_id=' . $f['id']) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                  <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editFund<?= $f['id'] ?>"><i class="bi bi-pencil"></i></button>
                  <form method="post" action="<?= url('/funds/delete_custom.php') ?>" class="d-inline" data-confirm="Xóa sổ quỹ &quot;<?= e($f['name']) ?>&quot;?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $f['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card">
      <div class="card-header">Thêm sổ quỹ mới</div>
      <div class="card-body">
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="add_fund" value="1">
          <div class="mb-3">
            <label class="form-label">Tên sổ quỹ *</label>
            <input type="text" name="name" class="form-control" placeholder="VD: Quỹ dự phòng, Ví Momo..." required>
          </div>
          <div class="mb-3">
            <label class="form-label">Ghi chú</label>
            <input type="text" name="note" class="form-control">
          </div>
          <div class="row g-3 mb-3">
            <div class="col-md-7">
              <label class="form-label">Số dư đầu kỳ (đ)</label>
              <input type="number" step="1000" name="opening_balance" class="form-control" placeholder="0" value="0">
            </div>
            <div class="col-md-5">
              <label class="form-label">Ngày đầu kỳ</label>
              <input type="date" name="opening_date" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
          </div>
          <button type="submit" class="btn btn-success"><i class="bi bi-plus-lg"></i> Thêm sổ quỹ</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php foreach ($funds as $f): ?>
  <div class="modal fade" id="editFund<?= $f['id'] ?>" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="rename_fund" value="1">
          <input type="hidden" name="id" value="<?= $f['id'] ?>">
          <div class="modal-header">
            <h5 class="modal-title">Sửa sổ quỹ</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Tên sổ quỹ *</label>
              <input type="text" name="name" class="form-control" required value="<?= e($f['name']) ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">Ghi chú</label>
              <input type="text" name="note" class="form-control" value="<?= e($f['note']) ?>">
            </div>
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-success">Lưu</button>
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Đóng</button>
          </div>
        </form>
      </div>
    </div>
  </div>
<?php endforeach; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
