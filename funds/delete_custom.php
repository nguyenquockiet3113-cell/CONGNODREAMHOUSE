<?php
require_once __DIR__ . '/../config/config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/funds/manage.php');
}
verify_csrf();
$id = (int)($_POST['id'] ?? 0);

try {
    $pdo->prepare('DELETE FROM funds WHERE id = ?')->execute([$id]);
    flash('success', 'Đã xóa sổ quỹ.');
} catch (PDOException $e) {
    flash('danger', 'Không thể xóa sổ quỹ này vì đang có giao dịch. Hãy xóa hết giao dịch của sổ quỹ trước.');
}

redirect('/funds/manage.php');
