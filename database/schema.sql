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
    permissions TEXT DEFAULT NULL, -- danh sach module duoc phep, cach nhau boi dau phay (chi ap dung cho staff)
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
    apply_vat TINYINT(1) NOT NULL DEFAULT 0,
    vat_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'active', -- active | ended | cancelled (Tinh trang)
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
    electricity_amount DECIMAL(14,0) NOT NULL DEFAULT 0,
    water_amount DECIMAL(14,0) NOT NULL DEFAULT 0,
    management_fee_amount DECIMAL(14,0) NOT NULL DEFAULT 0,
    internet_amount DECIMAL(14,0) NOT NULL DEFAULT 0,
    cleaning_fee_amount DECIMAL(14,0) NOT NULL DEFAULT 0, -- Phi ve sinh
    vehicle_fee_amount DECIMAL(14,0) NOT NULL DEFAULT 0, -- Phi xe
    other_fee_amount DECIMAL(14,0) NOT NULL DEFAULT 0,
    utilities_amount DECIMAL(14,0) NOT NULL DEFAULT 0, -- tong cac khoan phi ma CTY thu ho (khong tinh khoan khach tu dong)
    self_paid_items VARCHAR(255) DEFAULT NULL, -- danh sach khoan khach tu thanh toan, vd: electricity,internet
    paid_amount DECIMAL(14,0) NOT NULL DEFAULT 0,
    reconciled TINYINT(1) NOT NULL DEFAULT 0,
    note VARCHAR(255) DEFAULT NULL,
    CONSTRAINT fk_periods_deal FOREIGN KEY (deal_id) REFERENCES deals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Danh sach TK nhan tu quan ly rieng cho Chi phi dai han (billing_entries)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS billing_recipients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    note VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- So giao dich phat sinh dai han (tu do, khong rang buoc vao 1 deal cu the)
-- Giong so tay "TINH TIEN DAI HAN": moi dong la 1 khoan thu bat ky
-- (dien nuoc thang, phi lam the, mat the, tien nha...) cho 1 khach/phong.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS billing_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bill_date DATE NOT NULL,
    guest_name VARCHAR(150) NOT NULL,
    room_code VARCHAR(50),
    content VARCHAR(255),
    quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
    electricity_amount DECIMAL(14,0) NOT NULL DEFAULT 0,
    water_amount DECIMAL(14,0) NOT NULL DEFAULT 0,
    management_fee_amount DECIMAL(14,0) NOT NULL DEFAULT 0,
    internet_amount DECIMAL(14,0) NOT NULL DEFAULT 0,
    vehicle_fee_amount DECIMAL(14,0) NOT NULL DEFAULT 0,
    other_fee_amount DECIMAL(14,0) NOT NULL DEFAULT 0,
    card_fee_amount DECIMAL(14,0) NOT NULL DEFAULT 0, -- The nha, co the am
    total_amount DECIMAL(14,0) NOT NULL DEFAULT 0, -- tong 7 khoan tren
    deposit_used DECIMAL(14,0) NOT NULL DEFAULT 0, -- Tien coc tru vao giao dich nay
    settle_amount DECIMAL(14,0) NOT NULL DEFAULT 0, -- total_amount - deposit_used, co the am (hoan lai)
    is_done TINYINT(1) NOT NULL DEFAULT 0, -- Tinh trang
    customer_paid_date DATE DEFAULT NULL, -- Khach TT
    receiving_account VARCHAR(100) DEFAULT NULL, -- TK NHAN
    note VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Lich su thanh toan cua Deal ngan han (ho tro thanh toan nhieu lan)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS deal_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    deal_id INT NOT NULL,
    payment_date DATE NOT NULL,
    amount DECIMAL(14,0) NOT NULL DEFAULT 0,
    method VARCHAR(20) NOT NULL DEFAULT 'chuyen_khoan', -- tien_mat | chuyen_khoan
    receiving_account VARCHAR(100) DEFAULT NULL,
    note VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_deal_payments_deal FOREIGN KEY (deal_id) REFERENCES deals(id) ON DELETE CASCADE
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
-- Bang gia ve sinh (co the sua khi gia thay doi)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cleaning_price_list (
    id INT AUTO_INCREMENT PRIMARY KEY,
    work_type VARCHAR(50) NOT NULL, -- OUT | LUU | Tổng vệ sinh
    work_item VARCHAR(150) NOT NULL, -- vd: "1", "2", "Set up 1PN", "Tong ve sinh"
    unit VARCHAR(20) NOT NULL DEFAULT 'phong', -- phong | gio
    unit_price DECIMAL(14,0) NOT NULL DEFAULT 0,
    note VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uniq_price_item (work_type, work_item)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Danh sach nhan vien ve sinh
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cleaning_staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(30) DEFAULT NULL,
    note VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL
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
    work_type VARCHAR(100) DEFAULT NULL, -- loai cong viec: OUT | LUU | Tổng vệ sinh
    hours DECIMAL(6,2) DEFAULT NULL, -- so gio (chi dung cho hang muc tinh theo gio)
    price DECIMAL(14,0) NOT NULL DEFAULT 0,
    plus DECIMAL(14,0) NOT NULL DEFAULT 0,
    penalty DECIMAL(14,0) NOT NULL DEFAULT 0, -- phat
    note VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Danh sach cac quy tuy chinh do nguoi dung tu dat ten them
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS funds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    note VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- So quy: Tien mat / Quy ngan hang (theo tung TK) / Quy cong ty / Quy tuy chinh
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS fund_ledger (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fund_type VARCHAR(20) NOT NULL, -- cash | bank | company | custom
    bank_account_id INT DEFAULT NULL, -- chi dung khi fund_type = bank
    fund_id INT DEFAULT NULL, -- chi dung khi fund_type = custom
    tx_date DATE NOT NULL,
    zone VARCHAR(150) DEFAULT NULL,
    content VARCHAR(255) NOT NULL,
    amount_in DECIMAL(14,0) NOT NULL DEFAULT 0,
    amount_out DECIMAL(14,0) NOT NULL DEFAULT 0,
    note VARCHAR(255) DEFAULT NULL,
    is_closing TINYINT(1) NOT NULL DEFAULT 0, -- dong "CHOT QUY"
    attachment_path VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_fund_bank FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id) ON DELETE SET NULL,
    CONSTRAINT fk_fund_custom FOREIGN KEY (fund_id) REFERENCES funds(id)
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

-- ---------------------------------------------------------------------
-- Bang gia ve sinh mac dinh (co the sua trong man hinh Cai dat bang gia)
-- ---------------------------------------------------------------------
INSERT INTO cleaning_price_list (work_type, work_item, unit, unit_price, created_at, updated_at) VALUES
('OUT', '1', 'phong', 70000, NOW(), NOW()),
('OUT', '2', 'phong', 110000, NOW(), NOW()),
('OUT', '3', 'phong', 150000, NOW(), NOW()),
('OUT', '4', 'phong', 250000, NOW(), NOW()),
('OUT', 'Set up 1PN', 'phong', 70000, NOW(), NOW()),
('OUT', 'Set up 2PN', 'phong', 110000, NOW(), NOW()),
('OUT', 'Set up 3PN', 'phong', 150000, NOW(), NOW()),
('LUU', '1', 'phong', 65000, NOW(), NOW()),
('LUU', '2', 'phong', 100000, NOW(), NOW()),
('LUU', '3', 'phong', 140000, NOW(), NOW()),
('LUU', '4', 'phong', 240000, NOW(), NOW()),
('LUU', 'Set up 1PN', 'phong', 65000, NOW(), NOW()),
('LUU', 'Set up 2PN', 'phong', 100000, NOW(), NOW()),
('LUU', 'Set up 3PN', 'phong', 140000, NOW(), NOW()),
('Tổng vệ sinh', 'Tổng vệ sinh', 'gio', 65000, NOW(), NOW())
ON DUPLICATE KEY UPDATE work_type = work_type;
