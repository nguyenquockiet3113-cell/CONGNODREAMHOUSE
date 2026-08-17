<?php
require_once __DIR__ . '/../config/config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reconcile_type'], $_POST['reconcile_id'])) {
    verify_csrf();
    $type = $_POST['reconcile_type'];
    $rid = (int)$_POST['reconcile_id'];
    $table = match ($type) {
        'deal' => 'deals',
        'period' => 'deal_periods',
        'expense' => 'expenses',
        default => null,
    };
    if ($table) {
        $pdo->prepare("UPDATE $table SET reconciled = 1 - reconciled WHERE id = ?")->execute([$rid]);
    }
    $qs = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
    redirect('/reconciliation/index.php' . $qs);
}

$bankKeyword = trim($_GET['bank'] ?? '');
$fromDate = $_GET['from'] ?? date('Y-m-01');
$toDate = $_GET['to'] ?? date('Y-m-d');
$reconciledFilter = $_GET['reconciled'] ?? '';

$bankAccounts = $pdo->query('SELECT * FROM bank_accounts ORDER BY bank_name')->fetchAll();

$rows = [];

// Thu ngan han (theo ngay check-in)
$sql = "SELECT id, checkin_date AS tx_date, paid_amount AS amount, receiving_account, reconciled, room_code, guest_name
        FROM deals WHERE deal_type = 'ngan_han' AND paid_amount > 0 AND checkin_date BETWEEN ? AND ?";
$params = [$fromDate, $toDate];
if ($bankKeyword !== '') { $sql .= ' AND receiving_account LIKE ?'; $params[] = "%$bankKeyword%"; }
if ($reconciledFilter !== '') { $sql .= ' AND reconciled = ?'; $params[] = (int)$reconciledFilter; }
$stmt = $pdo->prepare($sql); $stmt->execute($params);
foreach ($stmt->fetchAll() as $r) {
    $rows[] = ['type' => 'deal', 'label' => 'Thu ngắn hạn', 'id' => $r['id'], 'date' => $r['tx_date'],
        'party' => $r['room_code'] . ' - ' . $r['guest_name'], 'account' => $r['receiving_account'],
        'in' => (float)$r['amount'], 'out' => 0, 'reconciled' => (int)$r['reconciled']];
}

// Thu dai han (theo ky, period_start)
$sql = "SELECT dp.id, dp.period_start AS tx_date, dp.paid_amount AS amount, d.receiving_account, dp.reconciled,
               d.room_code, d.guest_name
        FROM deal_periods dp JOIN deals d ON d.id = dp.deal_id
        WHERE d.deal_type = 'dai_han' AND dp.paid_amount > 0 AND dp.period_start BETWEEN ? AND ?";
$params = [$fromDate, $toDate];
if ($bankKeyword !== '') { $sql .= ' AND d.receiving_account LIKE ?'; $params[] = "%$bankKeyword%"; }
if ($reconciledFilter !== '') { $sql .= ' AND dp.reconciled = ?'; $params[] = (int)$reconciledFilter; }
$stmt = $pdo->prepare($sql); $stmt->execute($params);
foreach ($stmt->fetchAll() as $r) {
    $rows[] = ['type' => 'period', 'label' => 'Thu dài hạn', 'id' => $r['id'], 'date' => $r['tx_date'],
        'party' => $r['room_code'] . ' - ' . $r['guest_name'], 'account' => $r['receiving_account'],
        'in' => (float)$r['amount'], 'out' => 0, 'reconciled' => (int)$r['reconciled']];
}

// Chi phi
$sql = "SELECT ex.id, ex.expense_date AS tx_date, ex.amount, ex.reconciled, ex.category, r.room_code, ba.bank_name, ba.account_number
        FROM expenses ex LEFT JOIN rooms r ON r.id = ex.room_id LEFT JOIN bank_accounts ba ON ba.id = ex.bank_account_id
        WHERE ex.expense_date BETWEEN ? AND ?";
$params = [$fromDate, $toDate];
if ($reconciledFilter !== '') { $sql .= ' AND ex.reconciled = ?'; $params[] = (int)$reconciledFilter; }
$stmt = $pdo->prepare($sql); $stmt->execute($params);
foreach ($stmt->fetchAll() as $r) {
    $accountLabel = trim(($r['bank_name'] ?? '') . ' ' . ($r['account_number'] ?? ''));
    if ($bankKeyword !== '' && stripos($accountLabel, $bankKeyword) === false) continue;
    $rows[] = ['type' => 'expense', 'label' => 'Chi phí - ' . $r['category'], 'id' => $r['id'], 'date' => $r['tx_date'],
        'party' => $r['room_code'] ?? '', 'account' => $accountLabel,
        'in' => 0, 'out' => (float)$r['amount'], 'reconciled' => (int)$r['reconciled']];
}

usort($rows, fn($a, $b) => strcmp($b['date'], $a['date']));

$totalIn = array_sum(array_column($rows, 'in'));
$totalOut = array_sum(array_column($rows, 'out'));
$unreconciledCount = count(array_filter($rows, fn($r) => !$r['reconciled']));

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
        <label class="form-label small mb-1">Ngân hàng / Số TK</label>
        <input type="text" name="bank" class="form-control" list="bankHints" placeholder="VD: Vietcombank" value="<?= e($bankKeyword) ?>">
        <datalist id="bankHints">
          <?php foreach ($bankAccounts as $ba): ?><option value="<?= e($ba['bank_name']) ?>"><?php endforeach; ?>
        </datalist>
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
            <td><?= $r['account'] ? e($r['account']) : '<span class="text-muted">Tiền mặt</span>' ?></td>
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
