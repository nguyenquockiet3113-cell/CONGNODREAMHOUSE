<?php
/** Quy uoc: >= 30 dem la Dai han */
const DEAL_PERIOD_DAYS = 30;
const DEAL_LONG_TERM_THRESHOLD_NIGHTS = 30;

function deal_classify(int $nights): string
{
    return $nights >= DEAL_LONG_TERM_THRESHOLD_NIGHTS ? 'dai_han' : 'ngan_han';
}

function deal_nights(string $checkin, string $checkout): int
{
    $diff = (strtotime($checkout) - strtotime($checkin)) / 86400;
    return max(0, (int)$diff);
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
            'INSERT INTO deal_periods (deal_id, period_index, period_start, period_end, rent_amount, deposit_amount, utilities_amount, paid_amount) VALUES (?,?,?,?,?,?,0,0)'
        )->execute([$dealId, $index, $current, $periodEndInclusive, $rentAmount, $index === 1 ? $deposit : 0]);

        $current = $periodEnd;
        $index++;
    }
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
