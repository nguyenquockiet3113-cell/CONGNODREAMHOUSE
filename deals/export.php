<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/xlsx_writer.php';
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

$headers = ['Note', 'Sale', 'Code', 'PN', 'TIME', 'Price/unit', 'IN', 'OUT', 'Total', 'Charge', 'TỔNG', 'ĐÃ CK/TM', 'Payment', 'Đã TT', 'TK nhận'];
$rows = [];
foreach ($deals as $d) {
    $total = (float)$d['nights'] * (float)$d['price_per_unit'];
    $grand = $total + (float)$d['extra_fee'];
    $remain = $grand - (float)$d['paid_amount'];
    $rows[] = [
        $d['note'], $d['guest_name'], $d['room_code'], $d['bedrooms'], $d['nights'], (float)$d['price_per_unit'],
        vndate($d['checkin_date']), vndate($d['checkout_date']), $total, (float)$d['extra_fee'], $grand,
        (float)$d['paid_amount'], $remain, $d['payment_status'] === 'paid' ? 'x' : '', $d['receiving_account'],
    ];
}

write_xlsx_and_exit('doanh-thu-ngan-han-' . date('Y-m-d') . '.xlsx', $headers, $rows, [3, 4, 5, 8, 9, 10, 11, 12]);
