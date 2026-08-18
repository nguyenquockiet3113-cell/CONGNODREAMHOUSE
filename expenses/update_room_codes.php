<?php
require_once __DIR__ . '/../config/config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/expenses/room_codes.php');
}
verify_csrf();

$roomIds = $_POST['room_id'] ?? [];
$elecCodes = $_POST['electricity_code'] ?? [];
$waterCodes = $_POST['water_code'] ?? [];
$now = date('Y-m-d H:i:s');

$stmt = $pdo->prepare('UPDATE rooms SET electricity_code=?, water_code=?, updated_at=? WHERE id=?');
foreach ($roomIds as $i => $rid) {
    $stmt->execute([
        trim($elecCodes[$i] ?? ''),
        trim($waterCodes[$i] ?? ''),
        $now,
        (int)$rid,
    ]);
}

flash('success', 'Đã cập nhật mã điện/nước theo phòng.');
$qs = trim($_POST['return_qs'] ?? '');
redirect('/expenses/room_codes.php' . ($qs !== '' ? '?' . $qs : ''));
