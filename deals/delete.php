<?php
require_once __DIR__ . '/../config/config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/deals/short.php');
}
verify_csrf();
$id = (int)($_POST['id'] ?? 0);

$pdo->prepare('DELETE FROM deal_periods WHERE deal_id = ?')->execute([$id]);
$pdo->prepare('DELETE FROM deals WHERE id = ?')->execute([$id]);
flash('success', 'Đã xóa deal.');

redirect('/deals/short.php');
