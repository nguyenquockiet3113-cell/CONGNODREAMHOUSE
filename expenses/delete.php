<?php
require_once __DIR__ . '/../config/config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/expenses/index.php');
}
verify_csrf();
$id = (int)($_POST['id'] ?? 0);

$stmt = $pdo->prepare('DELETE FROM expenses WHERE id = ?');
$stmt->execute([$id]);
flash('success', 'Đã xóa chi phí.');

redirect('/expenses/index.php');
