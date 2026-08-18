<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$staffFilter = trim($_GET['staff'] ?? '');
$fromDate = $_GET['from'] ?? date('Y-m-01');
$toDate = $_GET['to'] ?? date('Y-m-d');

$sql = 'SELECT * FROM cleaning_logs WHERE work_date BETWEEN ? AND ?';
$params = [$fromDate, $toDate];
if ($staffFilter !== '') {
    $sql .= ' AND staff_name = ?';
    $params[] = $staffFilter;
}
$sql .= ' ORDER BY work_date ASC, id ASC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="luong-ve-sinh-' . $fromDate . '_' . $toDate . '.csv"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
csv_out($out, ['work_date', 'staff_name', 'room_code', 'bedrooms', 'work_item', 'work_type', 'hours', 'price', 'plus', 'penalty', 'total', 'note']);
$grandTotal = 0;
foreach ($logs as $l) {
    $total = (float)$l['price'] + (float)$l['plus'] - (float)$l['penalty'];
    $grandTotal += $total;
    csv_out($out, [
        $l['work_date'], $l['staff_name'], $l['room_code'], $l['bedrooms'], $l['work_item'], $l['work_type'],
        $l['hours'], $l['price'], $l['plus'], $l['penalty'], $total, $l['note'],
    ]);
}
csv_out($out, []);
csv_out($out, ['', '', '', '', '', '', '', '', '', 'TỔNG CỘNG', $grandTotal, '']);
fclose($out);
exit;
