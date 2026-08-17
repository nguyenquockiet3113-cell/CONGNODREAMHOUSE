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

$pdo->prepare('DELETE FROM deal_payments WHERE id = ? AND deal_id = ?')->execute([$paymentId, $dealId]);
recompute_deal_paid_amount($pdo, $dealId);

flash('success', 'Đã xóa lần thanh toán.');
redirect('/deals/form.php?id=' . $dealId);
