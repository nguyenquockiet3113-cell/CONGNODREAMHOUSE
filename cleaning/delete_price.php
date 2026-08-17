<?php
require_once __DIR__ . '/../config/config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/cleaning/prices.php');
}
verify_csrf();
$id = (int)($_POST['id'] ?? 0);

$pdo->prepare('DELETE FROM cleaning_price_list WHERE id = ?')->execute([$id]);
flash('success', 'Đã xóa mục giá.');

redirect('/cleaning/prices.php');
