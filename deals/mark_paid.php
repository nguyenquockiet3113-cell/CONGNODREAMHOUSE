<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/deal_helpers.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/deals/short.php');
}
verify_csrf();

$dealId = (int)($_POST['deal_id'] ?? 0);
$backTo = ($_POST['back_to'] ?? '') === 'long' ? '/deals/long.php' : '/deals/short.php';

$stmt = $pdo->prepare('SELECT * FROM deals WHERE id = ?');
$stmt->execute([$dealId]);
$deal = $stmt->fetch();
if (!$deal) {
    flash('danger', 'Không tìm thấy deal.');
    redirect($backTo);
}

$remain = (float)$deal['total_amount'] - (float)$deal['paid_amount'];
if ($remain > 0) {
    $now = date('Y-m-d H:i:s');
    $pdo->prepare('INSERT INTO deal_payments (deal_id, payment_date, amount, method, note, created_at) VALUES (?,?,?,?,?,?)')
        ->execute([$dealId, date('Y-m-d'), $remain, $deal['payment_method'], 'Đánh dấu đã thu đủ', $now]);
    recompute_deal_paid_amount($pdo, $dealId);
    flash('success', 'Đã đánh dấu thu đủ cho ' . $deal['guest_name'] . '.');
} else {
    flash('success', 'Deal này đã thu đủ từ trước.');
}

redirect($backTo);
