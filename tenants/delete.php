<?php
require_once __DIR__ . '/../config/config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/tenants/index.php');
}
verify_csrf();
$id = (int)($_POST['id'] ?? 0);

try {
    $stmt = $pdo->prepare('DELETE FROM tenants WHERE id = ?');
    $stmt->execute([$id]);
    flash('success', 'Đã xóa khách thuê.');
} catch (PDOException $e) {
    flash('danger', 'Không thể xóa khách thuê này vì đang có hợp đồng liên quan.');
}

redirect('/tenants/index.php');
