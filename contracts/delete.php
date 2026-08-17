<?php
require_once __DIR__ . '/../config/config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/contracts/index.php');
}
verify_csrf();
$id = (int)($_POST['id'] ?? 0);

try {
    $pdo->prepare('DELETE FROM contract_members WHERE contract_id = ?')->execute([$id]);
    $stmt = $pdo->prepare('DELETE FROM contracts WHERE id = ?');
    $stmt->execute([$id]);
    flash('success', 'Đã xóa hợp đồng.');
} catch (PDOException $e) {
    flash('danger', 'Không thể xóa hợp đồng này vì đang có hóa đơn liên quan.');
}

redirect('/contracts/index.php');
