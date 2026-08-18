<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$rooms = $pdo->query('SELECT room_code, zone, bedrooms FROM rooms ORDER BY zone, room_code')->fetchAll();

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="danh-sach-phong-' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
csv_out($out, ['room_code', 'zone', 'bedrooms']);
foreach ($rooms as $r) {
    csv_out($out, [$r['room_code'], $r['zone'], $r['bedrooms']]);
}
fclose($out);
exit;
