<?php
/**
 * Khoi tao ket noi PDO. Ho tro ca MySQL (production/Hostinger) va SQLite
 * (chay thu local) de code CRUD ben tren dung chung khong can sua doi.
 */

$credentialsFile = __DIR__ . '/db_credentials.php';

if (!file_exists($credentialsFile)) {
    http_response_code(500);
    die(
        'Chua co file cau hinh CSDL. Hay sao chep config/db_credentials.sample.php ' .
        'thanh config/db_credentials.php va dien thong tin ket noi.'
    );
}

require_once $credentialsFile;

try {
    if (DB_DRIVER === 'sqlite') {
        $pdo = new PDO('sqlite:' . DB_SQLITE_PATH);
    } else {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    if (DB_DRIVER === 'sqlite') {
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
} catch (PDOException $e) {
    http_response_code(500);
    die('Loi ket noi co so du lieu: ' . htmlspecialchars($e->getMessage()));
}
