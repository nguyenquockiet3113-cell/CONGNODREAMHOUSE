<?php
require_once __DIR__ . '/../config/config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reconcile_type'], $_POST['reconcile_id'])) {
    verify_csrf();
    $type = $_POST['reconcile_type'];
    $rid = (int)$_POST['reconcile_id'];
    $table = match ($type) {
        'invoice' => 'invoices',
        'booking' => 'bookings',
        'expense' => 'expenses',
        default => null,
    };
    if ($table) {
        $pdo->prepare("UPDATE $table SET reconciled = 1 - reconciled WHERE id = ?")->execute([$rid]);
    }
    $qs = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
    redirect('/reconciliation/index.php' . $qs);
}

$bankAccountId = $_GET['bank_account_id'] ?? '';
$fromDate = $_GET['from'] ?? date('Y-m-01');
$toDate = $_GET['to'] ?? date('Y-m-d');
$reconciledFilter = $_GET['reconciled'] ?? '';

$bankAccounts = $pdo->query('SELECT * FROM bank_accounts ORDER BY bank_name')->fetchAll();

$rows = [];

// Doanh thu dai han da thu (theo paid_date)
$sql = "SELECT i.id, i.paid_date AS tx_date, i.paid_amount AS amount, i.bank_account_id, i.reconciled,
               r.room_code, t.full_name AS party
        FROM invoices i JOIN rooms r ON r.id = i.room_id JOIN contracts c ON c.id = i.contract_id JOIN tenants t ON t.id = c.tenant_id
        WHERE i.paid_amount > 0 AND i.paid_date IS NOT NULL AND i.paid_date BETWEEN ? AND ?";
$params = [$fromDate, $toDate];
if ($bankAccountId !== '') { $sql .= ' AND i.bank_account_id = ?'; $params[] = $bankAccountId; }
if ($reconciledFilter !== '') { $sql .= ' AND i.reconciled = ?'; $params[] = (int)$reconciledFilter; }
$stmt = $pdo->prepare($sql); $stmt->execute($params);
foreach ($stmt->fetchAll() as $r) {
    $rows[] = ['type' => 'invoice', 'label' => 'Thu tiền phòng dài hạn', 'id' => $r['id'], 'date' => $r['tx_date'],
        'party' => $r['room_code'] . ' - ' . $r['party'], 'in' => (float)$r['amount'], 'out' => 0,
        'bank_account_id' => $r['bank_account_id'], 'reconciled' => (int)$r['reconciled']];
}

// Doanh thu ngan han da thu (theo paid_date)
$sql = "SELECT b.id, b.paid_date AS tx_date, b.total_amount AS amount, b.bank_account_id, b.reconciled,
               r.room_code, b.guest_name AS party
        FROM bookings b JOIN rooms r ON r.id = b.room_id
        WHERE b.payment_status = 'paid' AND b.paid_date IS NOT NULL AND b.paid_date BETWEEN ? AND ?";
$params = [$fromDate, $toDate];
if ($bankAccountId !== '') { $sql .= ' AND b.bank_account_id = ?'; $params[] = $bankAccountId; }
if ($reconciledFilter !== '') { $sql .= ' AND b.reconciled = ?'; $params[] = (int)$reconciledFilter; }
$stmt = $pdo->prepare($sql); $stmt->execute($params);
foreach ($stmt->fetchAll() as $r) {
    $rows[] = ['type' => 'booking', 'label' => 'Thu tiền phòng ngắn hạn', 'id' => $r['id'], 'date' => $r['tx_date'],
        'party' => $r['room_code'] . ' - ' . $r['party'], 'in' => (float)$r['amount'], 'out' => 0,
        'bank_account_id' => $r['bank_account_id'], 'reconciled' => (int)$r['reconciled']];
}

// Chi phi
$sql = "SELECT ex.id, ex.expense_date AS tx_date, ex.amount, ex.bank_account_id, ex.reconciled,
               ex.category, r.room_code
        FROM expenses ex LEFT JOIN rooms r ON r.id = ex.room_id
        WHERE ex.expense_date BETWEEN ? AND ?";
$params = [$fromDate, $toDate];
if ($bankAccountId !== '') { $sql .= ' AND ex.bank_account_id = ?'; $params[] = $bankAccountId; }
if ($reconciledFilter !== '') { $sql .= ' AND ex.reconciled = ?'; $params[] = (int)$reconciledFilter; }
$stmt = $pdo->prepare($sql); $stmt->execute($params);
foreach ($stmt->fetchAll() as $r) {
    $rows[] = ['type' => 'expense', 'label' => 'Chi phí - ' . $r['category'], 'id' => $r['id'], 'date' => $r['tx_date'],
        'party' => $r['room_code'] ?? '', 'in' => 0, 'out' => (float)$r['amount'],
        'bank_account_id' => $r['bank_account_id'], 'reconciled' => (int)$r['reconciled']];
}

usort($rows, fn($a, $b) => strcmp($b['date'], $a['date']));

$totalIn = array_sum(array_column($rows, 'in'));
$totalOut = array_sum(array_column($rows, 'out'));
$unreconciledCount = count(array_filter($rows, fn($r) => !$r['reconciled']));

$bankAccountsById = array_column($bankAccounts, null, 'id');

$pageTitle = 'Đối soát ngân hàng';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="mb-0"><i class="bi bi-bank"></i> Đối soát ngân hàng</h4>
  <a href="<?= url('/bank_accounts/index.php') ?>" class="btn btn-outline-secondary"><i class="bi bi-gear"></i> Quản lý tài khoản ngân hàng</a>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form class="row g-2 align-items-end">
      <div class="col-sm-3">
        <label class="form-label small mb-1">Tài khoản ngân hàng</label>
        <select name="bank_account_id" class="form-select">
          <option value="">-- Tất cả --</option>
          <?php foreach ($bankAccounts as $ba): ?>
            <option value="<?= $ba['id'] ?>" <?= (string)$bankAccountId === (string)$ba['id'] ? 'selected' : '' ?>><?= e($ba['bank_name']) ?><?= $ba['account_number'] ? ' - ' . e($ba['account_number']) : '' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-2">
        <label class="form-label small mb-1">Từ ngày</label>
        <input type="date" name="from" class="form-control" value="<?= e($fromDate) ?>">
      </div>
      <div class="col-sm-2">
        <label class="form-label small mb-1">Đến ngày</label>
        <input type="date" name="to" class="form-control" value="<?= e($toDate) ?>">
      </div>
      <div class="col-sm-2">
        <label class="form-label small mb-1">Đối soát</label>
        <select name="reconciled" class="form-select">
          <option value="">-- Tất cả --</option>
          <option value="0" <?= $reconciledFilter === '0' ? 'selected' : '' ?>>Chưa đối soát</option>
          <option value="1" <?= $reconciledFilter === '1' ? 'selected' : '' ?>>Đã đối soát</option>
        </select>
      </div>
      <div class="col-sm-2">
        <button class="btn btn-outline-secondary w-100"><i class="bi bi-search"></i> Lọc</button>
      </div>
    </form>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-sm-4">
    <div class="stat-card"><div class="text-muted small">Tổng tiền vào</div><div class="stat-value text-success"><?= money($totalIn) ?></div></div>
  </div>
  <div class="col-sm-4">
    <div class="stat-card"><div class="text-muted small">Tổng tiền ra</div><div class="stat-value text-danger"><?= money($totalOut) ?></div></div>
  </div>
  <div class="col-sm-4">
    <div class="stat-card"><div class="text-muted small">Chưa đối soát</div><div class="stat-value <?= $unreconciledCount > 0 ? 'text-warning' : '' ?>"><?= $unreconciledCount ?> giao dịch</div></div>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Ngày</th>
          <th>Nội dung</th>
          <th>Đối tượng</th>
          <th>Tài khoản NH</th>
          <th class="text-end">Tiền vào</th>
          <th class="text-end">Tiền ra</th>
          <th>Đối soát</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">Không có giao dịch nào trong khoảng lọc.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= vndate($r['date']) ?></td>
            <td><?= e($r['label']) ?></td>
            <td><?= e($r['party']) ?></td>
            <td><?= $r['bank_account_id'] && isset($bankAccountsById[$r['bank_account_id']]) ? e($bankAccountsById[$r['bank_account_id']]['bank_name']) : '<span class="text-muted">Tiền mặt</span>' ?></td>
            <td class="text-end text-success"><?= $r['in'] > 0 ? money($r['in']) : '' ?></td>
            <td class="text-end text-danger"><?= $r['out'] > 0 ? money($r['out']) : '' ?></td>
            <td>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="reconcile_type" value="<?= e($r['type']) ?>">
                <input type="hidden" name="reconcile_id" value="<?= $r['id'] ?>">
                <button class="btn btn-sm <?= $r['reconciled'] ? 'btn-success' : 'btn-outline-secondary' ?>">
                  <i class="bi bi-<?= $r['reconciled'] ? 'check-circle-fill' : 'circle'; ?>"></i> <?= $r['reconciled'] ? 'Đã đối soát' : 'Đánh dấu' ?>
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
