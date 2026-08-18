<?php
/** Quy uoc: >= 30 dem la Dai han */
const DEAL_PERIOD_DAYS = 30;
const DEAL_LONG_TERM_THRESHOLD_NIGHTS = 30;

/** Anh xa key khoan phi -> ten field POST tuong ung, dung chung cho form sua ky va logic khach tu dong. */
const DEAL_FEE_KEYS = [
    'electricity' => 'period_electricity',
    'water' => 'period_water',
    'management' => 'period_management',
    'internet' => 'period_internet',
    'cleaning' => 'period_cleaning',
    'vehicle' => 'period_vehicle',
    'other' => 'period_other',
];
const DEAL_FEE_LABELS = [
    'electricity' => 'Điện',
    'water' => 'Nước',
    'management' => 'Phí QL',
    'internet' => 'Internet',
    'cleaning' => 'Vệ sinh',
    'vehicle' => 'Xe',
    'other' => 'Phí khác',
];

function deal_classify(int $nights): string
{
    return $nights >= DEAL_LONG_TERM_THRESHOLD_NIGHTS ? 'dai_han' : 'ngan_han';
}

function deal_nights(string $checkin, string $checkout): int
{
    $diff = (strtotime($checkout) - strtotime($checkin)) / 86400;
    return max(0, (int)$diff);
}

/** So thang tron (30 ngay) va so ngay le con lai. */
function deal_months_breakdown(int $nights): array
{
    $fullMonths = intdiv($nights, DEAL_PERIOD_DAYS);
    $remainderDays = $nights % DEAL_PERIOD_DAYS;
    return [$fullMonths, $remainderDays];
}

/**
 * Tien thue truoc VAT/coc/phu phi.
 * Ngan han: price_per_unit la gia/dem -> nights * price.
 * Dai han: price_per_unit la gia/thang (30 ngay) -> so thang tron * price + ti le ngay le,
 * giong het cach generate_deal_periods() chia ky de 2 con so nay luon khop nhau.
 */
function deal_rent_total(int $nights, float $pricePerUnit, string $dealType): float
{
    if ($dealType !== 'dai_han') {
        return $nights * $pricePerUnit;
    }
    [$fullMonths, $remainderDays] = deal_months_breakdown($nights);
    $total = $fullMonths * $pricePerUnit;
    if ($remainderDays > 0) {
        $total += round($pricePerUnit * $remainderDays / DEAL_PERIOD_DAYS);
    }
    return $total;
}

/** Sinh cac ky 30 ngay cho 1 deal dai han. Chi goi khi deal chua co ky nao. */
function generate_deal_periods(PDO $pdo, int $dealId, string $checkin, string $checkout, float $pricePerUnit, float $deposit): void
{
    $current = $checkin;
    $index = 1;
    while (strtotime($current) < strtotime($checkout)) {
        $periodEndExclusive = date('Y-m-d', strtotime($current . ' +' . DEAL_PERIOD_DAYS . ' days'));
        $periodEnd = min($periodEndExclusive, $checkout);
        $periodEndInclusive = date('Y-m-d', strtotime($periodEnd . ' -1 day'));
        $periodDays = (strtotime($periodEnd) - strtotime($current)) / 86400;
        // Ky du 30 ngay tinh dung gia; ky cuoi (neu ngan hon) tinh ti le theo so ngay thuc te
        $rentAmount = $periodDays >= DEAL_PERIOD_DAYS ? $pricePerUnit : round($pricePerUnit * $periodDays / DEAL_PERIOD_DAYS);

        $pdo->prepare(
            'INSERT INTO deal_periods (deal_id, period_index, period_start, period_end, rent_amount, deposit_amount, electricity_amount, water_amount, management_fee_amount, internet_amount, cleaning_fee_amount, vehicle_fee_amount, other_fee_amount, utilities_amount, paid_amount) VALUES (?,?,?,?,?,?,0,0,0,0,0,0,0,0,0)'
        )->execute([$dealId, $index, $current, $periodEndInclusive, $rentAmount, $index === 1 ? $deposit : 0]);

        $current = $periodEnd;
        $index++;
    }
}

/**
 * Tim cac deal khac cung phong bi trung lich (chong lan ngay o) voi khoang [checkin, checkout).
 * Bo qua deal da huy (status = cancelled) va deal dang sua (excludeId).
 */
function find_overlapping_deals(PDO $pdo, string $roomCode, string $checkin, string $checkout, int $excludeId = 0): array
{
    if ($roomCode === '' || !$checkin || !$checkout) return [];
    $sql = "SELECT id, guest_name, checkin_date, checkout_date, deal_type FROM deals
            WHERE room_code = ? AND checkin_date < ? AND checkout_date > ? AND status != 'cancelled'";
    $params = [$roomCode, $checkout, $checkin];
    if ($excludeId) {
        $sql .= ' AND id != ?';
        $params[] = $excludeId;
    }
    $sql .= ' ORDER BY checkin_date ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function deal_period_label(int $index, string $start): string
{
    return 'Kỳ ' . $index . ' (Tháng ' . date('n/Y', strtotime($start)) . ')';
}

/** Tinh lai tong da thanh toan (paid_amount) va payment_status cua 1 deal tu bang deal_payments. */
function recompute_deal_paid_amount(PDO $pdo, int $dealId): void
{
    $sumStmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) s FROM deal_payments WHERE deal_id = ?');
    $sumStmt->execute([$dealId]);
    $paid = (float)$sumStmt->fetch()['s'];

    $dealStmt = $pdo->prepare('SELECT total_amount FROM deals WHERE id = ?');
    $dealStmt->execute([$dealId]);
    $total = (float)($dealStmt->fetch()['total_amount'] ?? 0);

    $status = $paid >= $total && $total > 0 ? 'paid' : 'unpaid';
    $pdo->prepare('UPDATE deals SET paid_amount = ?, payment_status = ? WHERE id = ?')->execute([$paid, $status, $dealId]);
}
