<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('APP_NAME', 'Quản Lý Phòng');

// Duong dan goc cua ung dung tren web (vd: '' neu o domain goc,
// hoac '/quanlyphong' neu chay trong thu muc con). Tu dong nhan biet dua
// tren vi tri thuc te cua thu muc goc ung dung so voi document root,
// khong phu thuoc vao script nao dang include file nay.
if (!defined('BASE_URL')) {
    $appRoot = str_replace('\\', '/', realpath(__DIR__ . '/..'));
    $docRoot = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/'));
    $base = ($docRoot !== '' && strpos($appRoot, $docRoot) === 0)
        ? substr($appRoot, strlen($docRoot))
        : '';
    define('BASE_URL', rtrim($base, '/'));
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/functions.php';
