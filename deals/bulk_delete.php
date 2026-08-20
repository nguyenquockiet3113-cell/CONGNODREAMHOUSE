<?php
require_once __DIR__ . '/../config/config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/deals/short.php');
}
verify_csrf();

$ids = array_filter(array_map('intval', $_POST['ids'] ?? []));
$backTo = ($_POST['back_to'] ?? 'short') === 'long' ? 'long' : 'short';

if ($ids) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $pdo->prepare("DELETE FROM deal_periods WHERE deal_id IN ($placeholders)")->execute($ids);
    $pdo->prepare("DELETE FROM deals WHERE id IN ($placeholders)")->execute($ids);
    flash('success', 'Đã xóa ' . count($ids) . ' deal.');
} else {
    flash('warning', 'Chưa chọn deal nào để xóa.');
}

redirect('/deals/' . $backTo . '.php');
