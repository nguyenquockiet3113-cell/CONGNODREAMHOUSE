<?php
require_once __DIR__ . '/../config/config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/funds/index.php');
}
verify_csrf();
$id = (int)($_POST['id'] ?? 0);
$type = $_POST['type'] ?? 'cash';
$bankId = (int)($_POST['bank_id'] ?? 0);

$pdo->prepare('DELETE FROM fund_ledger WHERE id = ?')->execute([$id]);
flash('success', 'Đã xóa giao dịch.');

redirect('/funds/index.php?type=' . $type . '&bank_id=' . $bankId);
