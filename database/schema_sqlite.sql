-- =====================================================================
-- QUAN LY PHONG - SQLite schema (CHI dung de chay thu tren may local,
-- KHONG dung cho Hostinger). Cau truc bang giong schema.sql (MySQL).
-- =====================================================================

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    full_name VARCHAR(150) NOT NULL,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'staff',
    is_active TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS rooms (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    room_code VARCHAR(50) NOT NULL UNIQUE,
    zone VARCHAR(150),
    bedrooms INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS bank_accounts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    bank_name VARCHAR(150) NOT NULL,
    account_number VARCHAR(50),
    account_holder VARCHAR(150),
    note VARCHAR(255),
    created_at DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS contracts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    contract_code VARCHAR(50) NOT NULL UNIQUE,
    room_code VARCHAR(50) NOT NULL,
    zone VARCHAR(150),

    lessee_name VARCHAR(150) NOT NULL,
    lessee_dob DATE,
    lessee_nationality VARCHAR(100),
    lessee_id_number VARCHAR(50),
    lessee_id_issue_date DATE,
    lessee_id_issue_place VARCHAR(150),
    lessee_address VARCHAR(255),
    lessee_phone VARCHAR(30),
    lessee_email VARCHAR(150),

    lessor_name VARCHAR(150),
    lessor_dob DATE,
    lessor_id_number VARCHAR(50),
    lessor_id_issue_date DATE,
    lessor_id_issue_place VARCHAR(150),
    lessor_address VARCHAR(255),

    monthly_rent DECIMAL(14,0) NOT NULL DEFAULT 0,
    rent_note VARCHAR(255),
    deposit_amount DECIMAL(14,0) NOT NULL DEFAULT 0,

    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    checkin_time VARCHAR(10) DEFAULT '14:00',
    checkout_time VARCHAR(10) DEFAULT '12:00',

    payment_method VARCHAR(20) NOT NULL DEFAULT 'chuyen_khoan',
    receiving_account VARCHAR(100),
    bank_name VARCHAR(100),
    beneficiary_name VARCHAR(150),
    payment_note VARCHAR(255),

    status VARCHAR(20) NOT NULL DEFAULT 'active',
    file_path VARCHAR(255),
    note TEXT,
    created_at DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS deals (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    room_code VARCHAR(50) NOT NULL,
    bedrooms INTEGER,
    zone VARCHAR(150),
    guest_name VARCHAR(150) NOT NULL,
    checkin_date DATE NOT NULL,
    checkout_date DATE NOT NULL,
    nights INTEGER NOT NULL DEFAULT 0,
    deal_type VARCHAR(10) NOT NULL DEFAULT 'ngan_han',
    price_per_unit DECIMAL(14,0) NOT NULL DEFAULT 0,
    deposit_amount DECIMAL(14,0) NOT NULL DEFAULT 0,
    deposit_date DATE,
    extra_fee DECIMAL(14,0) NOT NULL DEFAULT 0,
    total_amount DECIMAL(14,0) NOT NULL DEFAULT 0,
    payment_method VARCHAR(20) NOT NULL DEFAULT 'chuyen_khoan',
    receiving_account VARCHAR(100),
    paid_amount DECIMAL(14,0) NOT NULL DEFAULT 0,
    payment_status VARCHAR(20) NOT NULL DEFAULT 'unpaid',
    reconciled TINYINT NOT NULL DEFAULT 0,
    note TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS deal_periods (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    deal_id INTEGER NOT NULL,
    period_index INTEGER NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    rent_amount DECIMAL(14,0) NOT NULL DEFAULT 0,
    deposit_amount DECIMAL(14,0) NOT NULL DEFAULT 0,
    utilities_amount DECIMAL(14,0) NOT NULL DEFAULT 0,
    paid_amount DECIMAL(14,0) NOT NULL DEFAULT 0,
    reconciled TINYINT NOT NULL DEFAULT 0,
    note VARCHAR(255),
    FOREIGN KEY (deal_id) REFERENCES deals(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS deal_payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    deal_id INTEGER NOT NULL,
    payment_date DATE NOT NULL,
    amount DECIMAL(14,0) NOT NULL DEFAULT 0,
    method VARCHAR(20) NOT NULL DEFAULT 'chuyen_khoan',
    note VARCHAR(255),
    created_at DATETIME NOT NULL,
    FOREIGN KEY (deal_id) REFERENCES deals(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS expenses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    expense_date DATE NOT NULL,
    category VARCHAR(100) NOT NULL,
    room_id INTEGER,
    amount DECIMAL(14,0) NOT NULL DEFAULT 0,
    description VARCHAR(255),
    bank_account_id INTEGER,
    reconciled TINYINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL,
    FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS cleaning_price_list (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    work_type VARCHAR(50) NOT NULL,
    work_item VARCHAR(150) NOT NULL,
    unit VARCHAR(20) NOT NULL DEFAULT 'phong',
    unit_price DECIMAL(14,0) NOT NULL DEFAULT 0,
    note VARCHAR(255),
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS cleaning_staff (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(30),
    note VARCHAR(255),
    is_active TINYINT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS cleaning_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    work_date DATE NOT NULL,
    staff_name VARCHAR(150) NOT NULL,
    room_code VARCHAR(50),
    bedrooms INTEGER,
    work_item VARCHAR(150),
    work_type VARCHAR(100),
    hours DECIMAL(6,2),
    price DECIMAL(14,0) NOT NULL DEFAULT 0,
    plus DECIMAL(14,0) NOT NULL DEFAULT 0,
    penalty DECIMAL(14,0) NOT NULL DEFAULT 0,
    note VARCHAR(255),
    created_at DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS funds (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(150) NOT NULL UNIQUE,
    note VARCHAR(255),
    created_at DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS fund_ledger (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    fund_type VARCHAR(20) NOT NULL,
    bank_account_id INTEGER,
    fund_id INTEGER,
    tx_date DATE NOT NULL,
    zone VARCHAR(150),
    content VARCHAR(255) NOT NULL,
    amount_in DECIMAL(14,0) NOT NULL DEFAULT 0,
    amount_out DECIMAL(14,0) NOT NULL DEFAULT 0,
    note VARCHAR(255),
    is_closing TINYINT NOT NULL DEFAULT 0,
    attachment_path VARCHAR(255),
    created_at DATETIME NOT NULL,
    FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id) ON DELETE SET NULL,
    FOREIGN KEY (fund_id) REFERENCES funds(id)
);

CREATE TABLE IF NOT EXISTS reminders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title VARCHAR(255) NOT NULL,
    due_date DATE NOT NULL,
    note TEXT,
    is_done TINYINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT,
    updated_at DATETIME NOT NULL
);

INSERT OR IGNORE INTO users (id, full_name, username, password_hash, role, is_active, created_at)
VALUES (1, 'Quan tri vien', 'admin', '$2y$12$5DodMIMG6WDRxjD13mXMe.YTx81cjUVnUD/P1IldyrcYkTIMbOtAG', 'admin', 1, datetime('now'));

INSERT INTO cleaning_price_list (work_type, work_item, unit, unit_price, created_at, updated_at) VALUES
('OUT', '1', 'phong', 70000, datetime('now'), datetime('now')),
('OUT', '2', 'phong', 110000, datetime('now'), datetime('now')),
('OUT', '3', 'phong', 150000, datetime('now'), datetime('now')),
('OUT', '4', 'phong', 250000, datetime('now'), datetime('now')),
('OUT', 'Set up 1PN', 'phong', 70000, datetime('now'), datetime('now')),
('OUT', 'Set up 2PN', 'phong', 110000, datetime('now'), datetime('now')),
('OUT', 'Set up 3PN', 'phong', 150000, datetime('now'), datetime('now')),
('LUU', '1', 'phong', 65000, datetime('now'), datetime('now')),
('LUU', '2', 'phong', 100000, datetime('now'), datetime('now')),
('LUU', '3', 'phong', 140000, datetime('now'), datetime('now')),
('LUU', '4', 'phong', 240000, datetime('now'), datetime('now')),
('LUU', 'Set up 1PN', 'phong', 65000, datetime('now'), datetime('now')),
('LUU', 'Set up 2PN', 'phong', 100000, datetime('now'), datetime('now')),
('LUU', 'Set up 3PN', 'phong', 140000, datetime('now'), datetime('now')),
('Tổng vệ sinh', 'Tổng vệ sinh', 'gio', 65000, datetime('now'), datetime('now'));
