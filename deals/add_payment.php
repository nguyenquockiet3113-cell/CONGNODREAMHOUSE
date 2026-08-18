<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/deal_helpers.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/deals/short.php');
}
verify_csrf();

$dealId = (int)($_POST['deal_id'] ?? 0);
$paymentDate = $_POST['payment_date'] ?? '';
$amount = (float)($_POST['amount'] ?? 0);
$method = $_POST['method'] ?? 'chuyen_khoan';
$receivingAccount = trim($_POST['receiving_account'] ?? '');
$note = trim($_POST['note'] ?? '');

$dealStmt = $pdo->prepare('SELECT id, receiving_account FROM deals WHERE id = ?');
$dealStmt->execute([$dealId]);
$deal = $dealStmt->fetch();
if (!$deal) {
    flash('danger', 'Không tìm thấy deal.');
    redirect('/deals/short.php');
}

if (!$paymentDate || $amount <= 0) {
    flash('danger', 'Vui lòng nhập đầy đủ ngày và số tiền hợp lệ.');
    redirect('/deals/form.php?id=' . $dealId);
}

if ($receivingAccount === '') $receivingAccount = $deal['receiving_account'];

$now = date('Y-m-d H:i:s');
$pdo->prepare('INSERT INTO deal_payments (deal_id, payment_date, amount, method, receiving_account, note, created_at) VALUES (?,?,?,?,?,?,?)')
    ->execute([$dealId, $paymentDate, $amount, $method, $receivingAccount, $note, $now]);

recompute_deal_paid_amount($pdo, $dealId);

flash('success', 'Đã ghi nhận thanh toán.');
redirect('/deals/form.php?id=' . $dealId);
