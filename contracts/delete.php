<?php
require_once __DIR__ . '/../config/config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/contracts/index.php');
}
verify_csrf();
$id = (int)($_POST['id'] ?? 0);

$stmt = $pdo->prepare('DELETE FROM contracts WHERE id = ?');
$stmt->execute([$id]);
flash('success', 'Đã xóa hợp đồng.');

redirect('/contracts/index.php');
