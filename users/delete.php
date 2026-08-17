<?php
require_once __DIR__ . '/../config/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/users/index.php');
}
verify_csrf();
$id = (int)($_POST['id'] ?? 0);

if ($id === (int)current_user()['id']) {
    flash('danger', 'Không thể tự xóa tài khoản đang đăng nhập.');
    redirect('/users/index.php');
}

$stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
$stmt->execute([$id]);
flash('success', 'Đã xóa tài khoản.');

redirect('/users/index.php');
