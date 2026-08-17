<?php
/** Danh sach bang du lieu duoc phep dong bo voi Google Sheets */
const SYNC_TABLES = [
    'rooms', 'contracts', 'deals', 'deal_periods', 'deal_payments',
    'expenses', 'cleaning_price_list', 'cleaning_logs', 'fund_ledger', 'reminders', 'bank_accounts',
];

function get_table_columns(PDO $pdo, string $table): array
{
    if (defined('DB_DRIVER') && DB_DRIVER === 'sqlite') {
        $stmt = $pdo->query('PRAGMA table_info(' . $table . ')');
        return array_column($stmt->fetchAll(), 'name');
    }
    $stmt = $pdo->prepare(
        'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION'
    );
    $stmt->execute([$table]);
    return array_column($stmt->fetchAll(), 'COLUMN_NAME');
}

/** Day toan bo du lieu 1 bang len 1 sheet cung ten (ghi de). Tra ve so dong da ghi. */
function push_table_to_sheet(PDO $pdo, GoogleSheets $sheets, string $table): int
{
    $columns = get_table_columns($pdo, $table);
    $rows = [];
    foreach ($pdo->query("SELECT * FROM $table ORDER BY id")->fetchAll() as $row) {
        $rows[] = array_map(fn($c) => (string)($row[$c] ?? ''), $columns);
    }
    $sheets->writeSheet($table, $columns, $rows);
    return count($rows);
}

/**
 * Doc 1 sheet va cap nhat nguoc lai vao bang CSDL tuong ung (upsert theo cot "id").
 * Dong nao co id trung du lieu hien co -> UPDATE; id trong hoac khong ton tai -> INSERT.
 * Tra ve ['updated' => int, 'inserted' => int].
 */
function pull_table_from_sheet(PDO $pdo, GoogleSheets $sheets, string $table): array
{
    $columns = get_table_columns($pdo, $table);
    $values = $sheets->readSheet($table);
    $updated = 0;
    $inserted = 0;

    if (count($values) < 2) {
        return ['updated' => 0, 'inserted' => 0];
    }

    $headers = $values[0];
    foreach (array_slice($values, 1) as $row) {
        $assoc = [];
        foreach ($headers as $i => $h) {
            $h = trim((string)$h);
            if ($h === '' || !in_array($h, $columns, true)) continue;
            $assoc[$h] = $row[$i] ?? '';
        }
        if (!$assoc) continue;

        $id = $assoc['id'] ?? '';
        unset($assoc['id']);
        foreach ($assoc as $k => $v) {
            if ($v === '') $assoc[$k] = null;
        }
        if (!$assoc) continue;

        if ($id !== '' && $id !== null && ctype_digit((string)$id)) {
            $checkStmt = $pdo->prepare("SELECT id FROM $table WHERE id = ?");
            $checkStmt->execute([$id]);
            if ($checkStmt->fetch()) {
                $setSql = implode(', ', array_map(fn($k) => "$k = ?", array_keys($assoc)));
                $params = array_values($assoc);
                $params[] = $id;
                $pdo->prepare("UPDATE $table SET $setSql WHERE id = ?")->execute($params);
                $updated++;
                continue;
            }
        }

        $cols = array_keys($assoc);
        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $pdo->prepare('INSERT INTO ' . $table . ' (' . implode(',', $cols) . ") VALUES ($placeholders)")
            ->execute(array_values($assoc));
        $inserted++;
    }

    return ['updated' => $updated, 'inserted' => $inserted];
}

function get_google_sheets_client(PDO $pdo): ?GoogleSheets
{
    $json = get_setting($pdo, 'google_service_account_json');
    $spreadsheetId = get_setting($pdo, 'google_spreadsheet_id');
    if ($json === '' || $spreadsheetId === '') {
        return null;
    }
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Nội dung Service Account JSON không hợp lệ.');
    }
    require_once __DIR__ . '/GoogleSheets.php';
    return new GoogleSheets($decoded, $spreadsheetId);
}
