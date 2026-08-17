<?php
require_once __DIR__ . '/../config/config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/rooms/index.php');
}
verify_csrf();
$id = (int)($_POST['id'] ?? 0);

try {
    $stmt = $pdo->prepare('DELETE FROM rooms WHERE id = ?');
    $stmt->execute([$id]);
    flash('success', 'Đã xóa phòng.');
} catch (PDOException $e) {
    flash('danger', 'Không thể xóa phòng này vì đang có dữ liệu liên quan (hợp đồng, hóa đơn, đặt phòng...). Hãy chuyển trạng thái sang "Bảo trì" thay vì xóa.');
}

redirect('/rooms/index.php');
