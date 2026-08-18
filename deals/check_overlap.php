<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/deal_helpers.php';
require_login();
header('Content-Type: application/json; charset=UTF-8');

$roomCode = trim($_GET['room_code'] ?? '');
$checkin = $_GET['checkin'] ?? '';
$checkout = $_GET['checkout'] ?? '';
$excludeId = (int)($_GET['exclude_id'] ?? 0);

$conflicts = find_overlapping_deals($pdo, $roomCode, $checkin, $checkout, $excludeId);

echo json_encode([
    'conflicts' => array_map(fn($d) => [
        'guest_name' => $d['guest_name'],
        'checkin' => vndate($d['checkin_date']),
        'checkout' => vndate($d['checkout_date']),
        'deal_type' => $d['deal_type'] === 'dai_han' ? 'Dài hạn' : 'Ngắn hạn',
    ], $conflicts),
]);
