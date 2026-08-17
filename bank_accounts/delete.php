<?php
require_once __DIR__ . '/../config/config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/bank_accounts/index.php');
}
verify_csrf();
$id = (int)($_POST['id'] ?? 0);

try {
    $stmt = $pdo->prepare('DELETE FROM bank_accounts WHERE id = ?');
    $stmt->execute([$id]);
    flash('success', 'Đã xóa tài khoản ngân hàng.');
} catch (PDOException $e) {
    flash('danger', 'Không thể xóa vì tài khoản này đang được gắn với giao dịch.');
}

redirect('/bank_accounts/index.php');
