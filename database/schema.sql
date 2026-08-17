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
-- Bang phong
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_code VARCHAR(50) NOT NULL UNIQUE,
    zone VARCHAR(150) DEFAULT NULL, -- Khu vuc, vd: Vinhomes Central Park
    bedrooms INT NOT NULL DEFAULT 1, -- So phong ngu
    floor VARCHAR(20) DEFAULT NULL,
    room_type VARCHAR(100) DEFAULT NULL,
    area_m2 DECIMAL(8,2) DEFAULT NULL,
    monthly_price DECIMAL(14,0) NOT NULL DEFAULT 0,
    short_term_price DECIMAL(14,0) NOT NULL DEFAULT 0,
    max_occupants INT NOT NULL DEFAULT 1,
    status VARCHAR(20) NOT NULL DEFAULT 'trong', -- trong | dang_thue | bao_tri
    description TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Bang khach thue (dai han)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tenants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,
    id_card_number VARCHAR(50) DEFAULT NULL,
    id_card_address TEXT,
    permanent_address TEXT,
    note TEXT,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Bang hop dong dai han
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS contracts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contract_code VARCHAR(50) NOT NULL UNIQUE,
    room_id INT NOT NULL,
    tenant_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    monthly_rent DECIMAL(14,0) NOT NULL DEFAULT 0,
    deposit_amount DECIMAL(14,0) NOT NULL DEFAULT 0,
    deposit_returned TINYINT(1) NOT NULL DEFAULT 0,
    electricity_price DECIMAL(10,0) NOT NULL DEFAULT 3500,
    water_price DECIMAL(10,0) NOT NULL DEFAULT 20000,
    service_fee DECIMAL(14,0) NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'active', -- active | ended | cancelled
    file_path VARCHAR(255) DEFAULT NULL,
    note TEXT,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_contracts_room FOREIGN KEY (room_id) REFERENCES rooms(id),
    CONSTRAINT fk_contracts_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Nguoi o cung (ngoai nguoi dai dien ky hop dong)
CREATE TABLE IF NOT EXISTS contract_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contract_id INT NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    id_card_number VARCHAR(50) DEFAULT NULL,
    CONSTRAINT fk_members_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Bang tai khoan ngan hang (dung de gan giao dich, doi soat)
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
-- Bang hoa don hang thang - DOANH THU DAI HAN
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contract_id INT NOT NULL,
    room_id INT NOT NULL,
    period_month VARCHAR(7) NOT NULL, -- YYYY-MM
    rent_amount DECIMAL(14,0) NOT NULL DEFAULT 0,
    electricity_old DECIMAL(10,2) NOT NULL DEFAULT 0,
    electricity_new DECIMAL(10,2) NOT NULL DEFAULT 0,
    electricity_price DECIMAL(10,0) NOT NULL DEFAULT 0,
    water_old DECIMAL(10,2) NOT NULL DEFAULT 0,
    water_new DECIMAL(10,2) NOT NULL DEFAULT 0,
    water_price DECIMAL(10,0) NOT NULL DEFAULT 0,
    service_fee DECIMAL(14,0) NOT NULL DEFAULT 0,
    other_fee DECIMAL(14,0) NOT NULL DEFAULT 0,
    other_fee_note VARCHAR(255) DEFAULT NULL,
    total_amount DECIMAL(14,0) NOT NULL DEFAULT 0,
    paid_amount DECIMAL(14,0) NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'unpaid', -- unpaid | partial | paid
    due_date DATE DEFAULT NULL,
    paid_date DATE DEFAULT NULL,
    bank_account_id INT DEFAULT NULL,
    reconciled TINYINT(1) NOT NULL DEFAULT 0,
    note TEXT,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uniq_contract_period (contract_id, period_month),
    CONSTRAINT fk_invoices_contract FOREIGN KEY (contract_id) REFERENCES contracts(id),
    CONSTRAINT fk_invoices_room FOREIGN KEY (room_id) REFERENCES rooms(id),
    CONSTRAINT fk_invoices_bank FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Bang dat phong ngan han - DOANH THU NGAN HAN
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    guest_name VARCHAR(150) NOT NULL,
    guest_phone VARCHAR(30) DEFAULT NULL,
    guest_id_card VARCHAR(50) DEFAULT NULL,
    checkin_date DATE NOT NULL,
    checkout_date DATE NOT NULL,
    price_per_night DECIMAL(14,0) NOT NULL DEFAULT 0,
    deposit_amount DECIMAL(14,0) NOT NULL DEFAULT 0,
    extra_fee DECIMAL(14,0) NOT NULL DEFAULT 0,
    total_amount DECIMAL(14,0) NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'booked', -- booked | checked_in | checked_out | cancelled
    payment_status VARCHAR(20) NOT NULL DEFAULT 'unpaid', -- unpaid | paid
    paid_date DATE DEFAULT NULL,
    bank_account_id INT DEFAULT NULL,
    reconciled TINYINT(1) NOT NULL DEFAULT 0,
    note TEXT,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_bookings_room FOREIGN KEY (room_id) REFERENCES rooms(id),
    CONSTRAINT fk_bookings_bank FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id) ON DELETE SET NULL
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
-- Bang ticket bao tri / sua chua
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    priority VARCHAR(20) NOT NULL DEFAULT 'normal', -- low | normal | high
    status VARCHAR(20) NOT NULL DEFAULT 'open', -- open | in_progress | resolved
    reported_by VARCHAR(150) DEFAULT NULL,
    resolution_note TEXT,
    resolved_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_tickets_room FOREIGN KEY (room_id) REFERENCES rooms(id)
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
