-- =====================================================================
-- QUAN LY PHONG - MySQL schema (dung cho production / Hostinger)
-- Import file nay qua phpMyAdmin hoac: mysql -u user -p ten_csdl < schema.sql
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Bang tai khoan dang nhap
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'staff', -- admin | staff
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Bang phong (danh muc tham khao - khong bat buoc phai co truoc khi tao deal)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_code VARCHAR(50) NOT NULL UNIQUE,
    zone VARCHAR(150) DEFAULT NULL, -- Khu vuc, vd: Vinhomes Central Park
    bedrooms INT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Bang tai khoan ngan hang (dung de goi y "Tai khoan nhan", doi soat)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bank_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bank_name VARCHAR(150) NOT NULL,
    account_number VARCHAR(50) DEFAULT NULL,
    account_holder VARCHAR(150) DEFAULT NULL,
    note VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Bang hop dong thue (ho so phap ly, dien theo mau hop dong thuc te)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS contracts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contract_code VARCHAR(50) NOT NULL UNIQUE, -- So hop dong, vd: LP-31.15
    room_code VARCHAR(50) NOT NULL,
    zone VARCHAR(150) DEFAULT NULL,

    lessee_name VARCHAR(150) NOT NULL, -- Ben thue
    lessee_dob DATE DEFAULT NULL,
    lessee_nationality VARCHAR(100) DEFAULT NULL,
    lessee_id_number VARCHAR(50) DEFAULT NULL,
    lessee_id_issue_date DATE DEFAULT NULL,
    lessee_id_issue_place VARCHAR(150) DEFAULT NULL,
    lessee_address VARCHAR(255) DEFAULT NULL,
    lessee_phone VARCHAR(30) DEFAULT NULL,
    lessee_email VARCHAR(150) DEFAULT NULL,

    lessor_name VARCHAR(150) DEFAULT NULL, -- Ben cho thue (chu nha)
    lessor_dob DATE DEFAULT NULL,
    lessor_id_number VARCHAR(50) DEFAULT NULL,
    lessor_id_issue_date DATE DEFAULT NULL,
    lessor_id_issue_place VARCHAR(150) DEFAULT NULL,
    lessor_address VARCHAR(255) DEFAULT NULL,

    monthly_rent DECIMAL(14,0) NOT NULL DEFAULT 0,
    rent_note VARCHAR(255) DEFAULT NULL, -- vd: "Khong bao gom phi quan ly, internet"
    deposit_amount DECIMAL(14,0) NOT NULL DEFAULT 0,

    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    checkin_time VARCHAR(10) DEFAULT '14:00',
    checkout_time VARCHAR(10) DEFAULT '12:00',

    payment_method VARCHAR(20) NOT NULL DEFAULT 'chuyen_khoan', -- tien_mat | chuyen_khoan
    receiving_account VARCHAR(100) DEFAULT NULL,
    bank_name VARCHAR(100) DEFAULT NULL,
    beneficiary_name VARCHAR(150) DEFAULT NULL,
    payment_note VARCHAR(255) DEFAULT NULL,

    status VARCHAR(20) NOT NULL DEFAULT 'active', -- active | ended | cancelled
    file_path VARCHAR(255) DEFAULT NULL,
    note TEXT,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Bang Deal (dat phong) - hop nhat doanh thu ngan han va dai han.
-- Quy uoc: >= 30 dem duoc coi la Dai han va tu dong sinh cac ky
-- thanh toan 30 ngay trong bang deal_periods.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS deals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_code VARCHAR(50) NOT NULL,
    bedrooms INT DEFAULT NULL,
    zone VARCHAR(150) DEFAULT NULL,
    guest_name VARCHAR(150) NOT NULL,
    checkin_date DATE NOT NULL,
    checkout_date DATE NOT NULL,
    nights INT NOT NULL DEFAULT 0,
    deal_type VARCHAR(10) NOT NULL DEFAULT 'ngan_han', -- ngan_han | dai_han
    price_per_unit DECIMAL(14,0) NOT NULL DEFAULT 0, -- gia/dem (ngan han) hoac gia/ky 30 ngay (dai han)
    deposit_amount DECIMAL(14,0) NOT NULL DEFAULT 0,
    deposit_date DATE DEFAULT NULL,
    extra_fee DECIMAL(14,0) NOT NULL DEFAULT 0, -- Charge
    total_amount DECIMAL(14,0) NOT NULL DEFAULT 0,
    payment_method VARCHAR(20) NOT NULL DEFAULT 'chuyen_khoan',
    receiving_account VARCHAR(100) DEFAULT NULL,
    paid_amount DECIMAL(14,0) NOT NULL DEFAULT 0, -- Da CK/TM (chi dung cho ngan han)
    payment_status VARCHAR(20) NOT NULL DEFAULT 'unpaid', -- unpaid | paid (cot "Da TT")
    reconciled TINYINT(1) NOT NULL DEFAULT 0,
    note TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Cac ky cong no hang thang cua Deal dai han (vong doi 30 ngay/ky)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS deal_periods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    deal_id INT NOT NULL,
    period_index INT NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    rent_amount DECIMAL(14,0) NOT NULL DEFAULT 0,
    deposit_amount DECIMAL(14,0) NOT NULL DEFAULT 0,
    utilities_amount DECIMAL(14,0) NOT NULL DEFAULT 0,
    paid_amount DECIMAL(14,0) NOT NULL DEFAULT 0,
    reconciled TINYINT(1) NOT NULL DEFAULT 0,
    note VARCHAR(255) DEFAULT NULL,
    CONSTRAINT fk_periods_deal FOREIGN KEY (deal_id) REFERENCES deals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Bang chi phi
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    expense_date DATE NOT NULL,
    category VARCHAR(100) NOT NULL,
    room_id INT DEFAULT NULL,
    amount DECIMAL(14,0) NOT NULL DEFAULT 0,
    description VARCHAR(255) DEFAULT NULL,
    bank_account_id INT DEFAULT NULL,
    reconciled TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_expenses_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL,
    CONSTRAINT fk_expenses_bank FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Bang cong nhat ky lam viec ve sinh (tinh luong)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cleaning_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    work_date DATE NOT NULL,
    staff_name VARCHAR(150) NOT NULL,
    room_code VARCHAR(50) DEFAULT NULL,
    bedrooms INT DEFAULT NULL,
    work_item VARCHAR(150) DEFAULT NULL, -- hang muc, vd: "Set up 1PN"
    work_type VARCHAR(100) DEFAULT NULL, -- loai cong viec
    price DECIMAL(14,0) NOT NULL DEFAULT 0,
    plus DECIMAL(14,0) NOT NULL DEFAULT 0,
    note VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Bang nhac nho
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS reminders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    due_date DATE NOT NULL,
    note TEXT,
    is_done TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Bang cau hinh he thong (key-value), dung cho tich hop Google Sheets...
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value LONGTEXT,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- Tai khoan admin mac dinh: username = admin / mat khau = admin123
-- (Doi mat khau ngay sau khi dang nhap lan dau!)
-- ---------------------------------------------------------------------
INSERT INTO users (full_name, username, password_hash, role, is_active, created_at)
VALUES ('Quan tri vien', 'admin', '$2y$12$5DodMIMG6WDRxjD13mXMe.YTx81cjUVnUD/P1IldyrcYkTIMbOtAG', 'admin', 1, NOW())
ON DUPLICATE KEY UPDATE username = username;
