<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$contractIdParam = (int)($_GET['contract_id'] ?? 0);

$invoice = [
    'id' => 0, 'contract_id' => $contractIdParam, 'room_id' => 0,
    'period_month' => date('Y-m'), 'rent_amount' => 0,
    'electricity_old' => 0, 'electricity_new' => 0, 'electricity_price' => 3500,
    'water_old' => 0, 'water_new' => 0, 'water_price' => 20000,
    'service_fee' => 0, 'other_fee' => 0, 'other_fee_note' => '',
    'paid_amount' => 0, 'due_date' => date('Y-m-d', strtotime('+10 days')), 'paid_date' => '',
    'note' => '',
];
$errors = [];

$contracts = $pdo->query(
    "SELECT c.id, c.contract_code, c.monthly_rent, c.electricity_price, c.water_price, c.service_fee,
            r.id AS room_id, r.room_code, t.full_name AS tenant_name
     FROM contracts c JOIN rooms r ON r.id = c.room_id JOIN tenants t ON t.id = c.tenant_id
     WHERE c.status = 'active' ORDER BY r.room_code"
)->fetchAll();

function last_electricity_water(PDO $pdo, int $contractId): array
{
    $stmt = $pdo->prepare('SELECT electricity_new, water_new FROM invoices WHERE contract_id = ? ORDER BY period_month DESC LIMIT 1');
    $stmt->execute([$contractId]);
    $row = $stmt->fetch();
    return $row ? [(float)$row['electricity_new'], (float)$row['water_new']] : [0, 0];
}

if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM invoices WHERE id = ?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found) {
        flash('danger', 'Không tìm thấy hóa đơn.');
        redirect('/invoices/index.php');
    }
    $invoice = $found;
} elseif ($contractIdParam) {
    $c = array_filter($contracts, fn($x) => (int)$x['id'] === $contractIdParam);
    $c = reset($c);
    if ($c) {
        $invoice['room_id'] = $c['room_id'];
        $invoice['rent_amount'] = $c['monthly_rent'];
        $invoice['electricity_price'] = $c['electricity_price'];
        $invoice['water_price'] = $c['water_price'];
        $invoice['service_fee'] = $c['service_fee'];
        [$invoice['electricity_old'], $invoice['water_old']] = last_electricity_water($pdo, $contractIdParam);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $invoice['contract_id'] = (int)($_POST['contract_id'] ?? 0);
    $invoice['period_month'] = trim($_POST['period_month'] ?? '');
    $invoice['rent_amount'] = (float)($_POST['rent_amount'] ?? 0);
    $invoice['electricity_old'] = (float)($_POST['electricity_old'] ?? 0);
    $invoice['electricity_new'] = (float)($_POST['electricity_new'] ?? 0);
    $invoice['electricity_price'] = (float)($_POST['electricity_price'] ?? 0);
    $invoice['water_old'] = (float)($_POST['water_old'] ?? 0);
    $invoice['water_new'] = (float)($_POST['water_new'] ?? 0);
    $invoice['water_price'] = (float)($_POST['water_price'] ?? 0);
    $invoice['service_fee'] = (float)($_POST['service_fee'] ?? 0);
    $invoice['other_fee'] = (float)($_POST['other_fee'] ?? 0);
    $invoice['other_fee_note'] = trim($_POST['other_fee_note'] ?? '');
    $invoice['paid_amount'] = (float)($_POST['paid_amount'] ?? 0);
    $invoice['due_date'] = $_POST['due_date'] ?: null;
    $invoice['paid_date'] = $_POST['paid_date'] ?: null;
    $invoice['note'] = trim($_POST['note'] ?? '');

    if (!$invoice['contract_id']) $errors[] = 'Vui lòng chọn hợp đồng.';
    if (!preg_match('/^\d{4}-\d{2}$/', $invoice['period_month'])) $errors[] = 'Vui lòng chọn kỳ hóa đơn (tháng).';
    if ($invoice['electricity_new'] < $invoice['electricity_old']) $errors[] = 'Chỉ số điện mới phải >= chỉ số cũ.';
    if ($invoice['water_new'] < $invoice['water_old']) $errors[] = 'Chỉ số nước mới phải >= chỉ số cũ.';

    $contractRow = null;
    if ($invoice['contract_id']) {
        $cStmt = $pdo->prepare('SELECT * FROM contracts WHERE id = ?');
        $cStmt->execute([$invoice['contract_id']]);
        $contractRow = $cStmt->fetch();
        if (!$contractRow) $errors[] = 'Hợp đồng không tồn tại.';
    }

    if (!$errors) {
        $invoice['room_id'] = $contractRow['room_id'];
        $elecAmount = ($invoice['electricity_new'] - $invoice['electricity_old']) * $invoice['electricity_price'];
        $waterAmount = ($invoice['water_new'] - $invoice['water_old']) * $invoice['water_price'];
        $total = $invoice['rent_amount'] + $elecAmount + $waterAmount + $invoice['service_fee'] + $invoice['other_fee'];

        $status = 'unpaid';
        if ($invoice['paid_amount'] >= $total && $total > 0) $status = 'paid';
        elseif ($invoice['paid_amount'] > 0) $status = 'partial';

        try {
            if ($id) {
                $stmt = $pdo->prepare(
                    'UPDATE invoices SET contract_id=?, room_id=?, period_month=?, rent_amount=?, electricity_old=?, electricity_new=?, electricity_price=?, water_old=?, water_new=?, water_price=?, service_fee=?, other_fee=?, other_fee_note=?, total_amount=?, paid_amount=?, status=?, due_date=?, paid_date=?, note=? WHERE id=?'
                );
                $stmt->execute([
                    $invoice['contract_id'], $invoice['room_id'], $invoice['period_month'], $invoice['rent_amount'],
                    $invoice['electricity_old'], $invoice['electricity_new'], $invoice['electricity_price'],
                    $invoice['water_old'], $invoice['water_new'], $invoice['water_price'],
                    $invoice['service_fee'], $invoice['other_fee'], $invoice['other_fee_note'],
                    $total, $invoice['paid_amount'], $status, $invoice['due_date'], $invoice['paid_date'], $invoice['note'], $id,
                ]);
                flash('success', 'Đã cập nhật hóa đơn.');
            } else {
                $now = date('Y-m-d H:i:s');
                $stmt = $pdo->prepare(
                    'INSERT INTO invoices (contract_id, room_id, period_month, rent_amount, electricity_old, electricity_new, electricity_price, water_old, water_new, water_price, service_fee, other_fee, other_fee_note, total_amount, paid_amount, status, due_date, paid_date, note, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                );
                $stmt->execute([
                    $invoice['contract_id'], $invoice['room_id'], $invoice['period_month'], $invoice['rent_amount'],
                    $invoice['electricity_old'], $invoice['electricity_new'], $invoice['electricity_price'],
                    $invoice['water_old'], $invoice['water_new'], $invoice['water_price'],
                    $invoice['service_fee'], $invoice['other_fee'], $invoice['other_fee_note'],
                    $total, $invoice['paid_amount'], $status, $invoice['due_date'], $invoice['paid_date'], $invoice['note'], $now,
                ]);
                flash('success', 'Đã lập hóa đơn.');
            }
            redirect('/invoices/index.php');
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'uniq_contract_period') || str_contains(strtolower($e->getMessage()), 'unique')) {
                $errors[] = 'Hợp đồng này đã có hóa đơn cho kỳ ' . $invoice['period_month'] . '.';
            } else {
                $errors[] = 'Lỗi khi lưu hóa đơn: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = $id ? 'Sửa hóa đơn' : 'Lập hóa đơn';
require_once __DIR__ . '/../includes/header.php';
?>
<h4 class="mb-3"><i class="bi bi-receipt"></i> <?= $id ? 'Sửa hóa đơn' : 'Lập hóa đơn hàng tháng' ?></h4>

<?php foreach ($errors as $err): ?>
  <div class="alert alert-danger py-2"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="card">
  <div class="card-body">
    <form method="post" id="invoiceForm">
      <?= csrf_field() ?>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Hợp đồng *</label>
          <select name="contract_id" id="contract_id" class="form-select" required <?= $id ? 'disabled' : '' ?>>
            <option value="">-- Chọn hợp đồng --</option>
            <?php foreach ($contracts as $c): ?>
              <option value="<?= $c['id'] ?>"
                data-rent="<?= $c['monthly_rent'] ?>" data-eprice="<?= $c['electricity_price'] ?>"
                data-wprice="<?= $c['water_price'] ?>" data-service="<?= $c['service_fee'] ?>"
                <?= (int)$invoice['contract_id'] === (int)$c['id'] ? 'selected' : '' ?>>
                <?= e($c['room_code']) ?> - <?= e($c['tenant_name']) ?> (<?= e($c['contract_code']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <?php if ($id): ?><input type="hidden" name="contract_id" value="<?= $invoice['contract_id'] ?>"><?php endif; ?>
        </div>
        <div class="col-md-3">
          <label class="form-label">Kỳ hóa đơn (tháng) *</label>
          <input type="month" name="period_month" class="form-control" required value="<?= e($invoice['period_month']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Hạn thanh toán</label>
          <input type="date" name="due_date" class="form-control" value="<?= e($invoice['due_date']) ?>">
        </div>

        <div class="col-md-3">
          <label class="form-label">Tiền thuê phòng (đ)</label>
          <input type="number" step="1000" id="rent_amount" name="rent_amount" class="form-control calc" value="<?= e($invoice['rent_amount']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Phí dịch vụ (đ)</label>
          <input type="number" step="1000" id="service_fee" name="service_fee" class="form-control calc" value="<?= e($invoice['service_fee']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Phụ phí khác (đ)</label>
          <input type="number" step="1000" id="other_fee" name="other_fee" class="form-control calc" value="<?= e($invoice['other_fee']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Ghi chú phụ phí</label>
          <input type="text" name="other_fee_note" class="form-control" value="<?= e($invoice['other_fee_note']) ?>">
        </div>

        <div class="col-12"><hr><div class="fw-semibold small mb-1">Điện</div></div>
        <div class="col-md-3">
          <label class="form-label">Chỉ số cũ (kWh)</label>
          <input type="number" step="0.1" id="electricity_old" name="electricity_old" class="form-control calc" value="<?= e($invoice['electricity_old']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Chỉ số mới (kWh)</label>
          <input type="number" step="0.1" id="electricity_new" name="electricity_new" class="form-control calc" value="<?= e($invoice['electricity_new']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Đơn giá (đ/kWh)</label>
          <input type="number" step="100" id="electricity_price" name="electricity_price" class="form-control calc" value="<?= e($invoice['electricity_price']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Thành tiền điện</label>
          <input type="text" id="electricity_amount_display" class="form-control" disabled>
        </div>

        <div class="col-12"><div class="fw-semibold small mb-1">Nước</div></div>
        <div class="col-md-3">
          <label class="form-label">Chỉ số cũ (m³)</label>
          <input type="number" step="0.1" id="water_old" name="water_old" class="form-control calc" value="<?= e($invoice['water_old']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Chỉ số mới (m³)</label>
          <input type="number" step="0.1" id="water_new" name="water_new" class="form-control calc" value="<?= e($invoice['water_new']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Đơn giá (đ/m³)</label>
          <input type="number" step="100" id="water_price" name="water_price" class="form-control calc" value="<?= e($invoice['water_price']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Thành tiền nước</label>
          <input type="text" id="water_amount_display" class="form-control" disabled>
        </div>

        <div class="col-12">
          <hr>
          <div class="alert alert-info d-flex justify-content-between align-items-center mb-0">
            <span>Tổng cộng hóa đơn</span>
            <strong id="totalDisplay" style="font-size:1.3rem;">0 đ</strong>
          </div>
        </div>

        <div class="col-md-4">
          <label class="form-label">Số tiền đã thu (đ)</label>
          <input type="number" step="1000" name="paid_amount" class="form-control" value="<?= e($invoice['paid_amount']) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Ngày thanh toán</label>
          <input type="date" name="paid_date" class="form-control" value="<?= e($invoice['paid_date']) ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Ghi chú</label>
          <textarea name="note" class="form-control" rows="2"><?= e($invoice['note']) ?></textarea>
        </div>
      </div>

      <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-success"><i class="bi bi-check-lg"></i> Lưu hóa đơn</button>
        <a href="<?= url('/invoices/index.php') ?>" class="btn btn-outline-secondary">Hủy</a>
      </div>
    </form>
  </div>
</div>

<script>
var contractSelect = document.getElementById('contract_id');
if (contractSelect && !contractSelect.disabled) {
  contractSelect.addEventListener('change', function () {
    var opt = this.options[this.selectedIndex];
    if (!opt || !opt.value) return;
    document.getElementById('rent_amount').value = opt.getAttribute('data-rent') || 0;
    document.getElementById('electricity_price').value = opt.getAttribute('data-eprice') || 0;
    document.getElementById('water_price').value = opt.getAttribute('data-wprice') || 0;
    document.getElementById('service_fee').value = opt.getAttribute('data-service') || 0;
    recalc();
  });
}

function fmt(n) {
  return new Intl.NumberFormat('vi-VN').format(Math.round(n)) + ' đ';
}

function recalc() {
  var rent = parseFloat(document.getElementById('rent_amount').value) || 0;
  var service = parseFloat(document.getElementById('service_fee').value) || 0;
  var other = parseFloat(document.getElementById('other_fee').value) || 0;
  var eOld = parseFloat(document.getElementById('electricity_old').value) || 0;
  var eNew = parseFloat(document.getElementById('electricity_new').value) || 0;
  var ePrice = parseFloat(document.getElementById('electricity_price').value) || 0;
  var wOld = parseFloat(document.getElementById('water_old').value) || 0;
  var wNew = parseFloat(document.getElementById('water_new').value) || 0;
  var wPrice = parseFloat(document.getElementById('water_price').value) || 0;

  var eAmount = Math.max(0, eNew - eOld) * ePrice;
  var wAmount = Math.max(0, wNew - wOld) * wPrice;
  document.getElementById('electricity_amount_display').value = fmt(eAmount);
  document.getElementById('water_amount_display').value = fmt(wAmount);
  document.getElementById('totalDisplay').textContent = fmt(rent + service + other + eAmount + wAmount);
}

document.querySelectorAll('.calc').forEach(function (el) {
  el.addEventListener('input', recalc);
});
recalc();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
