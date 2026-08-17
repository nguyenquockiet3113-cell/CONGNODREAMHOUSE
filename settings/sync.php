<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/sync_helpers.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/settings/index.php');
}
verify_csrf();

$action = $_POST['action'] ?? '';
$table = $_POST['table'] ?? '';

try {
    $sheets = get_google_sheets_client($pdo);
    if (!$sheets) {
        flash('danger', 'Chưa cấu hình Google Sheets. Vui lòng lưu Service Account JSON + Spreadsheet ID trước.');
        redirect('/settings/index.php');
    }

    switch ($action) {
        case 'push_all':
            $total = 0;
            foreach (SYNC_TABLES as $t) {
                $total += push_table_to_sheet($pdo, $sheets, $t);
            }
            flash('success', "Đã đẩy $total dòng dữ liệu (tất cả bảng) lên Google Sheet.");
            break;

        case 'pull_all':
            $updated = 0; $inserted = 0;
            foreach (SYNC_TABLES as $t) {
                $r = pull_table_from_sheet($pdo, $sheets, $t);
                $updated += $r['updated'];
                $inserted += $r['inserted'];
            }
            flash('success', "Đã kéo dữ liệu từ Sheet: cập nhật $updated dòng, thêm mới $inserted dòng.");
            break;

        case 'push_one':
            if (!in_array($table, SYNC_TABLES, true)) throw new RuntimeException('Bảng không hợp lệ.');
            $n = push_table_to_sheet($pdo, $sheets, $table);
            flash('success', "Đã đẩy $n dòng của bảng \"$table\" lên Google Sheet.");
            break;

        case 'pull_one':
            if (!in_array($table, SYNC_TABLES, true)) throw new RuntimeException('Bảng không hợp lệ.');
            $r = pull_table_from_sheet($pdo, $sheets, $table);
            flash('success', "Đã kéo bảng \"$table\": cập nhật {$r['updated']} dòng, thêm mới {$r['inserted']} dòng.");
            break;

        default:
            flash('danger', 'Hành động không hợp lệ.');
    }
} catch (Throwable $e) {
    flash('danger', 'Đồng bộ thất bại: ' . $e->getMessage());
}

redirect('/settings/index.php');
