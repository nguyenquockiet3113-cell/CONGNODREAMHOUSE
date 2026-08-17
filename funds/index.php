<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$bankAccounts = $pdo->query('SELECT * FROM bank_accounts ORDER BY bank_name')->fetchAll();

$fundType = $_GET['type'] ?? 'cash';
$bankId = (int)($_GET['bank_id'] ?? 0);
if (!in_array($fundType, ['cash', 'bank', 'company'], true)) $fundType = 'cash';
if ($fundType === 'bank' && !$bankId && $bankAccounts) $bankId = (int)$bankAccounts[0]['id'];

$fromDate = $_GET['from'] ?? date('Y-m-01');
$toDate = $_GET['to'] ?? date('Y-m-d');

// So du dau ky (truoc fromDate)
function fund_balance_before(PDO $pdo, string $type, int $bankId, string $beforeDate): float
{
    $sql = 'SELECT COALESCE(SUM(amount_in - amount_out),0) s FROM fund_ledger WHERE fund_type = ? AND tx_date < ?';
    $params = [$type, $beforeDate];
    if ($type === 'bank') { $sql .= ' AND bank_account_id = ?'; $params[] = $bankId; }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (float)$stmt->fetch()['s'];
}

$sql = 'SELECT fl.*, ba.bank_name FROM fund_ledger fl LEFT JOIN bank_accounts ba ON ba.id = fl.bank_account_id WHERE fl.fund_type = ? AND fl.tx_date BETWEEN ? AND ?';
$params = [$fundType, $fromDate, $toDate];
if ($fundType === 'bank') { $sql .= ' AND fl.bank_account_id = ?'; $params[] = $bankId; }
$sql .= ' ORDER BY fl.tx_date ASC, fl.id ASC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$entries = $stmt->fetchAll();

$balance = fund_balance_before($pdo, $fundType, $bankId, $fromDate);
$openingBalance = $balance;
$totalIn = 0; $totalOut = 0;
foreach ($entries as &$en) {
    $balance += (float)$en['amount_in'] - (float)$en['amount_out'];
    $en['running_balance'] = $balance;
    $totalIn += (float)$en['amount_in'];
    $totalOut += (float)$en['amount_out'];
}
unset($en);

$fundLabel = match ($fundType) {
    'cash' => 'Tiền mặt',
    'company' => 'Quỹ công ty',
    'bank' => 'Quỹ ngân hàng',
};

$pageTitle = 'Sổ quỹ';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="mb-0"><i class="bi bi-journal-text"></i> Sổ quỹ</h4>
  <a href="<?= url('/funds/form.php?type=' . $fundType . '&bank_id=' . $bankId) ?>" class="btn btn-success"><i class="bi bi-plus-lg"></i> Thêm giao dịch</a>
</div>

<ul class="nav nav-pills mb-3 flex-wrap">
  <li class="nav-item">
    <a class="nav-link <?= $fundType === 'cash' ? 'active' : '' ?>" href="<?= url('/funds/index.php?type=cash') ?>"><i class="bi bi-cash-stack"></i> Tiền mặt</a>
  </li>
  <?php foreach ($bankAccounts as $ba): ?>
    <li class="nav-item">
      <a class="nav-link <?= $fundType === 'bank' && $bankId === (int)$ba['id'] ? 'active' : '' ?>" href="<?= url('/funds/index.php?type=bank&bank_id=' . $ba['id']) ?>"><i class="bi bi-bank"></i> <?= e($ba['bank_name']) ?></a>
    </li>
  <?php endforeach; ?>
  <li class="nav-item">
    <a class="nav-link <?= $fundType === 'company' ? 'active' : '' ?>" href="<?= url('/funds/index.php?type=company') ?>"><i class="bi bi-building"></i> Quỹ công ty</a>
  </li>
</ul>

<div class="card mb-3">
  <div class="card-body">
    <form class="row g-2 align-items-end">
      <input type="hidden" name="type" value="<?= e($fundType) ?>">
      <input type="hidden" name="bank_id" value="<?= $bankId ?>">
      <div class="col-sm-3">
        <label class="form-label small mb-1">Từ ngày</label>
        <input type="date" name="from" class="form-control" value="<?= e($fromDate) ?>">
      </div>
      <div class="col-sm-3">
        <label class="form-label small mb-1">Đến ngày</label>
        <input type="date" name="to" class="form-control" value="<?= e($toDate) ?>">
      </div>
      <div class="col-sm-2">
        <button class="btn btn-outline-secondary w-100"><i class="bi bi-search"></i> Lọc</button>
      </div>
    </form>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-sm-3">
    <div class="stat-card"><div class="text-muted small">Số dư đầu kỳ</div><div class="stat-value"><?= money($openingBalance) ?></div></div>
  </div>
  <div class="col-sm-3">
    <div class="stat-card"><div class="text-muted small">Thu trong kỳ</div><div class="stat-value text-success"><?= money($totalIn) ?></div></div>
  </div>
  <div class="col-sm-3">
    <div class="stat-card"><div class="text-muted small">Chi trong kỳ</div><div class="stat-value text-danger"><?= money($totalOut) ?></div></div>
  </div>
  <div class="col-sm-3">
    <div class="stat-card"><div class="text-muted small">Số dư cuối kỳ</div><div class="stat-value"><?= money($balance) ?></div></div>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover table-sm mb-0 align-middle">
      <thead>
        <tr>
          <th>Ngày</th>
          <th>Khu vực</th>
          <th>Nội dung</th>
          <th class="text-end">Thu</th>
          <th class="text-end">Chi</th>
          <th class="text-end">Còn tồn</th>
          <th>Đính kèm</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <tr class="table-light">
          <td colspan="5" class="text-end fw-semibold">Số dư đầu kỳ (<?= vndate($fromDate) ?>)</td>
          <td class="text-end fw-semibold"><?= money($openingBalance) ?></td>
          <td colspan="2"></td>
        </tr>
        <?php if (!$entries): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">Không có giao dịch nào trong khoảng lọc.</td></tr>
        <?php endif; ?>
        <?php foreach ($entries as $en): ?>
          <tr class="<?= $en['is_closing'] ? 'table-success' : '' ?>">
            <td><?= vndate($en['tx_date']) ?></td>
            <td><?= e($en['zone']) ?></td>
            <td><?= $en['is_closing'] ? '<i class="bi bi-flag-fill text-success"></i> ' : '' ?><?= e($en['content']) ?><?= $en['note'] ? '<div class="small text-muted">' . e($en['note']) . '</div>' : '' ?></td>
            <td class="text-end text-success"><?= $en['amount_in'] > 0 ? money($en['amount_in']) : '' ?></td>
            <td class="text-end text-danger"><?= $en['amount_out'] > 0 ? money($en['amount_out']) : '' ?></td>
            <td class="text-end fw-semibold"><?= money($en['running_balance']) ?></td>
            <td>
              <?php if ($en['attachment_path']): ?>
                <a href="<?= url('/' . $en['attachment_path']) ?>" target="_blank"><img src="<?= url('/' . $en['attachment_path']) ?>" style="height:32px;border-radius:4px;" alt="attachment"></a>
              <?php endif; ?>
            </td>
            <td class="text-end">
              <a href="<?= url('/funds/form.php?id=' . $en['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
              <form method="post" action="<?= url('/funds/delete.php') ?>" class="d-inline" data-confirm="Xóa giao dịch này?">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $en['id'] ?>">
                <input type="hidden" name="type" value="<?= e($fundType) ?>">
                <input type="hidden" name="bank_id" value="<?= $bankId ?>">
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
