<?php
require_once __DIR__ . '/../config/config.php';
require_login();

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="mau-nhap-danh-sach-phong.csv"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
csv_out($out, ['room_code', 'zone', 'bedrooms']);
csv_out($out, ['L6.03.08', 'Vinhomes Central Park', 3]);
csv_out($out, ['L6.12A.08', 'Vinhomes Central Park', 2]);
csv_out($out, ['LP-31.15', 'Vinhomes Grand Park', 2]);
csv_out($out, ['OS5.11.06', 'Vinhomes Ocean Park', 1]);
fclose($out);
exit;
