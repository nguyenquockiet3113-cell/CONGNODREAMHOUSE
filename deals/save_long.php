<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/deal_helpers.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/deals/long.php');
}
verify_csrf();

$roomCode = trim($_POST['room_code'] ?? '');
$bedrooms = ($_POST['bedrooms'] ?? '') !== '' ? (int)$_POST['bedrooms'] : null;
$zone = trim($_POST['zone'] ?? '');
$guestName = trim($_POST['guest_name'] ?? '');
$checkin = $_POST['checkin_date'] ?? '';
$checkout = $_POST['checkout_date'] ?? '';
$price = (float)($_POST['price_per_unit'] ?? 0);
$deposit = (float)($_POST['deposit_amount'] ?? 0);
$applyVat = isset($_POST['apply_vat']) ? 1 : 0;
$vatPercent = $applyVat ? (float)($_POST['vat_percent'] ?? 0) : 0;
$note = trim($_POST['note'] ?? '');
$status = in_array($_POST['status'] ?? '', array_keys(DEAL_STATUS_LABELS), true) ? $_POST['status'] : 'active';

$errors = [];
if ($roomCode === '') $errors[] = 'Vui lòng nhập mã căn hộ.';
if ($guestName === '') $errors[] = 'Vui lòng nhập tên sale.';
if (!$checkin || !$checkout) $errors[] = 'Vui lòng nhập ngày check-in/check-out.';
if ($checkin && $checkout && $checkout <= $checkin) $errors[] = 'Check-out phải sau check-in.';

if ($errors) {
    flash('danger', implode(' ', $errors));
    redirect('/deals/long.php');
}

$conflicts = find_overlapping_deals($pdo, $roomCode, $checkin, $checkout);
if ($conflicts) {
    $conflictText = implode('; ', array_map(fn($c) => $c['guest_name'] . ' (' . vndate($c['checkin_date']) . ' - ' . vndate($c['checkout_date']) . ')', $conflicts));
    flash('warning', '⚠️ Trùng lịch phòng ' . $roomCode . ' với: ' . $conflictText);
}

$nights = deal_nights($checkin, $checkout);
$dealType = deal_classify($nights);
$rentTotal = deal_rent_total($nights, $price, $dealType);
$vatAmount = $applyVat ? round($rentTotal * $vatPercent / 100) : 0;
$total = $rentTotal + $vatAmount;
$now = date('Y-m-d H:i:s');

$cols = [
    'room_code', 'bedrooms', 'zone', 'guest_name', 'checkin_date', 'checkout_date',
    'nights', 'deal_type', 'price_per_unit', 'deposit_amount', 'deposit_date', 'extra_fee',
    'total_amount', 'payment_method', 'receiving_account', 'apply_vat', 'vat_percent', 'status',
    'note', 'created_at', 'updated_at',
];
$values = [
    $roomCode, $bedrooms, $zone, $guestName, $checkin, $checkout,
    $nights, $dealType, $price, $deposit, $deposit > 0 ? date('Y-m-d') : null, 0,
    $total, 'chuyen_khoan', '', $applyVat, $vatPercent, $status,
    $note, $now, $now,
];
$placeholders = implode(',', array_fill(0, count($cols), '?'));
$pdo->prepare('INSERT INTO deals (' . implode(',', $cols) . ") VALUES ($placeholders)")->execute($values);
$dealId = (int)$pdo->lastInsertId();

if ($dealType === 'dai_han') {
    // Tien coc chi de giu cho (phong hoi khi tra phong), khong tinh vao tien nha
    // can thu va khong tu dong ghi nhan la da thanh toan.
    generate_deal_periods($pdo, $dealId, $checkin, $checkout, $price, $deposit);
}

recompute_deal_paid_amount($pdo, $dealId);

flash('success', 'Đã thêm deal dài hạn cho ' . $guestName . ' (' . $roomCode . ').');
redirect($dealType === 'dai_han' ? '/deals/long.php' : '/deals/short.php');
