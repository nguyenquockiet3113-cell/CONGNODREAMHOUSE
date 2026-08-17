<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/deal_helpers.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/deals/short.php');
}
verify_csrf();
$id = (int)($_POST['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM deals WHERE id = ?');
$stmt->execute([$id]);
$deal = $stmt->fetch();

if ($deal) {
    $pdo->prepare('DELETE FROM deal_periods WHERE deal_id = ?')->execute([$id]);
    if ($deal['deal_type'] === 'dai_han') {
        generate_deal_periods($pdo, $id, $deal['checkin_date'], $deal['checkout_date'], $deal['price_per_unit'], $deal['deposit_amount']);
    }
    flash('success', 'Đã tạo lại các kỳ thanh toán.');
} else {
    flash('danger', 'Không tìm thấy deal.');
}

redirect('/deals/form.php?id=' . $id);
