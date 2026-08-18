<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$search = trim($_GET['q'] ?? '');
$fromDate = $_GET['from'] ?? '';
$toDate = $_GET['to'] ?? '';
$paidFilter = $_GET['paid'] ?? '';

$sql = "SELECT * FROM deals WHERE deal_type = 'ngan_han'";
$params = [];
if ($search !== '') {
    $sql .= ' AND (guest_name LIKE ? OR room_code LIKE ? OR note LIKE ?)';
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}
if ($fromDate !== '') { $sql .= ' AND checkin_date >= ?'; $params[] = $fromDate; }
if ($toDate !== '') { $sql .= ' AND checkin_date <= ?'; $params[] = $toDate; }
if ($paidFilter !== '') { $sql .= ' AND payment_status = ?'; $params[] = $paidFilter; }
$sql .= ' ORDER BY checkin_date DESC, id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$deals = $stmt->fetchAll();

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="doanh-thu-ngan-han-' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM de Excel doc dung UTF-8
csv_out($out, ['note', 'guest_name', 'room_code', 'bedrooms', 'nights', 'price_per_unit', 'checkin_date', 'checkout_date', 'extra_fee', 'total_amount', 'paid_amount', 'payment_status']);
foreach ($deals as $d) {
    csv_out($out, [
        $d['note'], $d['guest_name'], $d['room_code'], $d['bedrooms'], $d['nights'], $d['price_per_unit'],
        $d['checkin_date'], $d['checkout_date'], $d['extra_fee'], $d['total_amount'], $d['paid_amount'], $d['payment_status'],
    ]);
}
fclose($out);
exit;
