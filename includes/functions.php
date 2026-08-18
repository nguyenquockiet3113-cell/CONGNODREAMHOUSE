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

/** Doc 1 nhom 3 chu so ra chu (dung noi bo cho vn_money_to_words). */
function vn_read_group(int $n, bool $isFirstGroup): string
{
    $ones = ['', 'một', 'hai', 'ba', 'bốn', 'năm', 'sáu', 'bảy', 'tám', 'chín'];
    $hundred = intdiv($n, 100);
    $ten = intdiv($n % 100, 10);
    $unit = $n % 10;
    $parts = [];

    if (!($isFirstGroup && $hundred === 0)) {
        $parts[] = ($hundred === 0 ? 'không' : $ones[$hundred]) . ' trăm';
    }

    if ($ten >= 2) {
        $parts[] = $ones[$ten] . ' mươi';
        if ($unit === 1) $parts[] = 'mốt';
        elseif ($unit === 5) $parts[] = 'lăm';
        elseif ($unit === 4) $parts[] = 'tư';
        elseif ($unit > 0) $parts[] = $ones[$unit];
    } elseif ($ten === 1) {
        $parts[] = 'mười';
        if ($unit === 1) $parts[] = 'một';
        elseif ($unit === 5) $parts[] = 'lăm';
        elseif ($unit > 0) $parts[] = $ones[$unit];
    } elseif ($unit > 0) {
        $parts[] = $parts ? 'lẻ ' . $ones[$unit] : $ones[$unit];
    }

    return trim(implode(' ', $parts));
}

/** Doc so tien VND ra chu, vd: 4200000 -> "Bốn triệu hai trăm nghìn đồng". */
function vn_money_to_words(float $amount): string
{
    $number = (int)round($amount);
    if ($number === 0) return 'Không đồng';
    $neg = $number < 0;
    $number = abs($number);

    $groupNames = ['', 'nghìn', 'triệu', 'tỷ', 'nghìn tỷ'];
    $groups = [];
    $n = $number;
    while ($n > 0) {
        $groups[] = $n % 1000;
        $n = intdiv($n, 1000);
    }

    $strParts = [];
    for ($i = count($groups) - 1; $i >= 0; $i--) {
        $g = $groups[$i];
        if ($g === 0) continue;
        $words = vn_read_group($g, empty($strParts));
        if ($words === '') continue;
        $suffix = $groupNames[$i] ?? '';
        $strParts[] = trim($words . ($suffix ? ' ' . $suffix : ''));
    }

    $result = preg_replace('/\s+/', ' ', trim(implode(' ', $strParts)));
    $result = mb_strtoupper(mb_substr($result, 0, 1)) . mb_substr($result, 1);
    return ($neg ? 'Âm ' : '') . $result . ' đồng';
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

/** Danh sach module co the phan quyen rieng cho tung tai khoan staff (key trung ten thu muc). */
const APP_MODULES = [
    'rooms' => 'Khu & Phòng',
    'contracts' => 'Hợp đồng',
    'deals' => 'Doanh thu ngắn hạn & dài hạn',
    'billing' => 'Chi phí khác',
    'expenses' => 'Chi phí',
    'cleaning' => 'Tiền lương vệ sinh',
    'funds' => 'Sổ quỹ',
    'reconciliation' => 'Đối soát ngân hàng',
    'reports' => 'Báo cáo',
    'reminders' => 'Nhắc nhở',
];

// Thu muc con thuoc ve 1 module cha (khong co muc rieng trong APP_MODULES nhung can gate theo module cha)
const MODULE_ALIASES = [
    'bank_accounts' => 'reconciliation',
];

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_admin(): bool
{
    return ($_SESSION['user']['role'] ?? '') === 'admin';
}

/** Module hien tai suy ra tu duong dan script (ten thu muc dau tien), quy doi qua module cha neu la alias. */
function current_module(): string
{
    $module = basename(dirname($_SERVER['SCRIPT_NAME']));
    return MODULE_ALIASES[$module] ?? $module;
}

function has_permission(string $module): bool
{
    if (is_admin()) return true;
    $perms = array_filter(explode(',', $_SESSION['user']['permissions'] ?? ''));
    return in_array($module, $perms, true);
}

function require_login(): void
{
    if (empty($_SESSION['user'])) {
        redirect('/login.php');
    }
    $module = current_module();
    if (array_key_exists($module, APP_MODULES) && !has_permission($module)) {
        flash('danger', 'Bạn không có quyền truy cập mục này. Liên hệ quản trị viên để được cấp quyền.');
        redirect('/dashboard.php');
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

/** Sinh ma tu dong theo tien to va so thu tu, vd: HD, 2026, 3 -> HD202600003 */
function generate_code(string $prefix, int $nextNumber): string
{
    return $prefix . date('Y') . str_pad((string)$nextNumber, 4, '0', STR_PAD_LEFT);
}

const CONTRACT_STATUS_LABELS = [
    'active' => 'Đang hiệu lực',
    'ended' => 'Đã kết thúc',
    'cancelled' => 'Đã hủy',
];

const DEAL_STATUS_LABELS = [
    'active' => 'Đang thuê',
    'ended' => 'Đã trả phòng',
    'cancelled' => 'Đã hủy',
];

const EXPENSE_CATEGORIES = [
    'Điện', 'Nước + Phí quản lý', 'Thẻ xe', 'Phí thẻ/ngân hàng', 'Internet',
    'Thuế - Mặt bằng', 'Lương', 'Sửa chữa', 'Rác', 'Vật tư', 'Phát sinh khác',
];

/** Ghi 1 dong CSV, tuong thich PHP 8.4+ (bat buoc truyen $escape de tranh canh bao deprecated). */
function csv_out($handle, array $fields): void
{
    fputcsv($handle, $fields, ',', '"', '\\');
}

function get_setting(PDO $pdo, string $key, string $default = ''): string
{
    $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? (string)$row['setting_value'] : $default;
}

function set_setting(PDO $pdo, string $key, string $value): void
{
    $now = date('Y-m-d H:i:s');
    $existing = $pdo->prepare('SELECT setting_key FROM settings WHERE setting_key = ?');
    $existing->execute([$key]);
    if ($existing->fetch()) {
        $pdo->prepare('UPDATE settings SET setting_value = ?, updated_at = ? WHERE setting_key = ?')->execute([$value, $now, $key]);
    } else {
        $pdo->prepare('INSERT INTO settings (setting_key, setting_value, updated_at) VALUES (?,?,?)')->execute([$key, $value, $now]);
    }
}

function badge_class(string $status): string
{
    return match ($status) {
        'active', 'paid' => 'success',
        'ended', 'cancelled', 'unpaid' => 'secondary',
        default => 'secondary',
    };
}
