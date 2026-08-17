<?php
require_once __DIR__ . '/../config/config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/invoices/index.php');
}
verify_csrf();
$id = (int)($_POST['id'] ?? 0);

$stmt = $pdo->prepare('DELETE FROM invoices WHERE id = ?');
$stmt->execute([$id]);
flash('success', 'Đã xóa hóa đơn.');

redirect('/invoices/index.php');
