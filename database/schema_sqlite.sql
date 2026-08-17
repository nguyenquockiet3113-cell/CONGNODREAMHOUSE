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
    floor VARCHAR(20),
    room_type VARCHAR(100),
    area_m2 DECIMAL(8,2),
    monthly_price DECIMAL(14,0) NOT NULL DEFAULT 0,
    short_term_price DECIMAL(14,0) NOT NULL DEFAULT 0,
    max_occupants INTEGER NOT NULL DEFAULT 1,
    status VARCHAR(20) NOT NULL DEFAULT 'trong',
    description TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS tenants (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    full_name VARCHAR(150) NOT NULL,
    phone VARCHAR(30),
    email VARCHAR(150),
    id_card_number VARCHAR(50),
    id_card_address TEXT,
    permanent_address TEXT,
    note TEXT,
    created_at DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS contracts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    contract_code VARCHAR(50) NOT NULL UNIQUE,
    room_id INTEGER NOT NULL,
    tenant_id INTEGER NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    monthly_rent DECIMAL(14,0) NOT NULL DEFAULT 0,
    deposit_amount DECIMAL(14,0) NOT NULL DEFAULT 0,
    deposit_returned TINYINT NOT NULL DEFAULT 0,
    electricity_price DECIMAL(10,0) NOT NULL DEFAULT 3500,
    water_price DECIMAL(10,0) NOT NULL DEFAULT 20000,
    service_fee DECIMAL(14,0) NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    file_path VARCHAR(255),
    note TEXT,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (room_id) REFERENCES rooms(id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);

CREATE TABLE IF NOT EXISTS contract_members (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    contract_id INTEGER NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    phone VARCHAR(30),
    id_card_number VARCHAR(50),
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS invoices (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    contract_id INTEGER NOT NULL,
    room_id INTEGER NOT NULL,
    period_month VARCHAR(7) NOT NULL,
    rent_amount DECIMAL(14,0) NOT NULL DEFAULT 0,
    electricity_old DECIMAL(10,2) NOT NULL DEFAULT 0,
    electricity_new DECIMAL(10,2) NOT NULL DEFAULT 0,
    electricity_price DECIMAL(10,0) NOT NULL DEFAULT 0,
    water_old DECIMAL(10,2) NOT NULL DEFAULT 0,
    water_new DECIMAL(10,2) NOT NULL DEFAULT 0,
    water_price DECIMAL(10,0) NOT NULL DEFAULT 0,
    service_fee DECIMAL(14,0) NOT NULL DEFAULT 0,
    other_fee DECIMAL(14,0) NOT NULL DEFAULT 0,
    other_fee_note VARCHAR(255),
    total_amount DECIMAL(14,0) NOT NULL DEFAULT 0,
    paid_amount DECIMAL(14,0) NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'unpaid',
    due_date DATE,
    paid_date DATE,
    note TEXT,
    created_at DATETIME NOT NULL,
    UNIQUE (contract_id, period_month),
    FOREIGN KEY (contract_id) REFERENCES contracts(id),
    FOREIGN KEY (room_id) REFERENCES rooms(id)
);

CREATE TABLE IF NOT EXISTS bookings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    room_id INTEGER NOT NULL,
    guest_name VARCHAR(150) NOT NULL,
    guest_phone VARCHAR(30),
    guest_id_card VARCHAR(50),
    checkin_date DATE NOT NULL,
    checkout_date DATE NOT NULL,
    price_per_night DECIMAL(14,0) NOT NULL DEFAULT 0,
    deposit_amount DECIMAL(14,0) NOT NULL DEFAULT 0,
    extra_fee DECIMAL(14,0) NOT NULL DEFAULT 0,
    total_amount DECIMAL(14,0) NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'booked',
    payment_status VARCHAR(20) NOT NULL DEFAULT 'unpaid',
    note TEXT,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (room_id) REFERENCES rooms(id)
);

CREATE TABLE IF NOT EXISTS expenses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    expense_date DATE NOT NULL,
    category VARCHAR(100) NOT NULL,
    room_id INTEGER,
    amount DECIMAL(14,0) NOT NULL DEFAULT 0,
    description VARCHAR(255),
    created_at DATETIME NOT NULL,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL
);

INSERT OR IGNORE INTO users (id, full_name, username, password_hash, role, is_active, created_at)
VALUES (1, 'Quan tri vien', 'admin', '$2y$12$5DodMIMG6WDRxjD13mXMe.YTx81cjUVnUD/P1IldyrcYkTIMbOtAG', 'admin', 1, datetime('now'));
