<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/deal_helpers.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/deals/short.php');
}
verify_csrf();

$paymentId = (int)($_POST['payment_id'] ?? 0);
$dealId = (int)($_POST['deal_id'] ?? 0);
$paymentDate = $_POST['payment_date'] ?? '';
$amount = (float)($_POST['amount'] ?? 0);
$method = $_POST['method'] ?? 'chuyen_khoan';
$receivingAccount = trim($_POST['receiving_account'] ?? '');
$note = trim($_POST['note'] ?? '');

if (!$paymentDate || $amount <= 0) {
    flash('danger', 'Vui lòng nhập đầy đủ ngày và số tiền hợp lệ.');
    redirect('/deals/form.php?id=' . $dealId);
}

$pdo->prepare('UPDATE deal_payments SET payment_date=?, amount=?, method=?, receiving_account=?, note=? WHERE id=? AND deal_id=?')
    ->execute([$paymentDate, $amount, $method, $receivingAccount, $note, $paymentId, $dealId]);

recompute_deal_paid_amount($pdo, $dealId);

flash('success', 'Đã cập nhật lần thanh toán.');
redirect('/deals/form.php?id=' . $dealId);
