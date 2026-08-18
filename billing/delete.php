<?php
require_once __DIR__ . '/../config/config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/billing/index.php');
}
verify_csrf();
$id = (int)($_POST['id'] ?? 0);

$pdo->prepare('DELETE FROM billing_entries WHERE id = ?')->execute([$id]);
flash('success', 'Đã xóa giao dịch.');

redirect('/billing/index.php');
