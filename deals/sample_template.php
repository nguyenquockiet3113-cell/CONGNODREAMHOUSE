<?php
require_once __DIR__ . '/../config/config.php';
require_login();

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="mau-nhap-doanh-thu-ngan-han.csv"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
csv_out($out, ['note', 'guest_name', 'room_code', 'bedrooms', 'checkin_date', 'checkout_date', 'price_per_unit', 'extra_fee']);
csv_out($out, ['', 'Nguyễn Văn A', 'L6.03.08', 3, '2026-08-20', '2026-08-23', 700000, 0]);
csv_out($out, ['GH', 'Trần Thị B', 'LP-31.15', 2, '2026-08-15', '2026-08-18', 900000, 200000]);
fclose($out);
exit;
