<?php
require_once __DIR__ . '/../config/config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/reminders/index.php');
}
verify_csrf();
$id = (int)($_POST['id'] ?? 0);

$stmt = $pdo->prepare('DELETE FROM reminders WHERE id = ?');
$stmt->execute([$id]);
flash('success', 'Đã xóa nhắc nhở.');

redirect('/reminders/index.php');
