<?php
/**
 * Sao chep file nay thanh "db_credentials.php" (cung thu muc) roi dien
 * thong tin that. File db_credentials.php KHONG duoc day len Git/GitHub
 * (da khai bao trong .gitignore) vi chua thong tin dang nhap CSDL.
 *
 * Tren Hostinger: hPanel > Databases > MySQL Databases de tao CSDL,
 * user va mat khau, roi dien vao ben duoi.
 */

// 'mysql' khi chay tren Hostinger/production, 'sqlite' khi chay thu local
define('DB_DRIVER', 'mysql');

// --- Cau hinh khi DB_DRIVER = 'mysql' ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'ten_csdl_hostinger');
define('DB_USER', 'user_csdl');
define('DB_PASS', 'mat_khau_csdl');
define('DB_CHARSET', 'utf8mb4');

// --- Cau hinh khi DB_DRIVER = 'sqlite' (chi dung khi chay thu local) ---
define('DB_SQLITE_PATH', __DIR__ . '/../database/local.sqlite');
