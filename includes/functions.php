<?php
/** Escape chuoi HTML de chong XSS khi in ra trang. */
function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/** Dinh dang so tien VND, vd: 3500000 -> "3.500.000 đ" */
function money($amount): string
{
    return number_format((float)$amount, 0, ',', '.') . ' đ';
}

/** Dinh dang ngay kieu Viet Nam dd/mm/yyyy tu chuoi yyyy-mm-dd */
function vndate(?string $isoDate): string
{
    if (empty($isoDate)) {
        return '';
    }
    $ts = strtotime($isoDate);
    return $ts ? date('d/m/Y', $ts) : '';
}

function redirect(string $path): void
{
    header('Location: ' . BASE_URL . $path);
    exit;
}

function url(string $path): string
{
    return BASE_URL . $path;
}

/** Tao/tra ve CSRF token cho session hien tai */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(400);
        die('Yeu cau khong hop le (CSRF token sai). Vui long tai lai trang va thu lai.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (empty($_SESSION['user'])) {
        redirect('/login.php');
    }
}

function require_admin(): void
{
    require_login();
    if (($_SESSION['user']['role'] ?? '') !== 'admin') {
        http_response_code(403);
        die('Ban khong co quyen truy cap trang nay.');
    }
}

function is_admin(): bool
{
    return ($_SESSION['user']['role'] ?? '') === 'admin';
}

/** Sinh ma tu dong theo tien to va so thu tu, vd: HD, 2026, 3 -> HD202600003 */
function generate_code(string $prefix, int $nextNumber): string
{
    return $prefix . date('Y') . str_pad((string)$nextNumber, 4, '0', STR_PAD_LEFT);
}

const ROOM_STATUS_LABELS = [
    'trong' => 'Còn trống',
    'dang_thue' => 'Đang thuê',
    'bao_tri' => 'Bảo trì',
];

const CONTRACT_STATUS_LABELS = [
    'active' => 'Đang hiệu lực',
    'ended' => 'Đã kết thúc',
    'cancelled' => 'Đã hủy',
];

const INVOICE_STATUS_LABELS = [
    'unpaid' => 'Chưa thanh toán',
    'partial' => 'Thanh toán 1 phần',
    'paid' => 'Đã thanh toán',
];

const BOOKING_STATUS_LABELS = [
    'booked' => 'Đã đặt',
    'checked_in' => 'Đang ở',
    'checked_out' => 'Đã trả phòng',
    'cancelled' => 'Đã hủy',
];

const EXPENSE_CATEGORIES = [
    'Điện', 'Nước', 'Internet', 'Sửa chữa - Bảo trì', 'Vệ sinh',
    'Lương nhân viên', 'Thuế - Phí nhà nước', 'Marketing', 'Khác',
];

function badge_class(string $status): string
{
    return match ($status) {
        'trong', 'active', 'paid', 'checked_out' => 'success',
        'dang_thue', 'checked_in' => 'primary',
        'bao_tri', 'partial', 'booked' => 'warning',
        'ended', 'cancelled', 'unpaid' => 'secondary',
        default => 'secondary',
    };
}
