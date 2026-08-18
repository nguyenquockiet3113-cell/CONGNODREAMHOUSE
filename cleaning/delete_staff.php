<?php
require_once __DIR__ . '/../config/config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/cleaning/staff.php');
}
verify_csrf();
$id = (int)($_POST['id'] ?? 0);

$pdo->prepare('DELETE FROM cleaning_staff WHERE id = ?')->execute([$id]);
flash('success', 'Đã xóa nhân viên.');

redirect('/cleaning/staff.php');
