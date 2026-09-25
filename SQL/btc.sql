USE lavidaloca;

-- ============================================================
-- BTC BALANS OP USERS
-- ============================================================
ALTER TABLE users ADD COLUMN btc DECIMAL(20,8) DEFAULT 0;
ALTER TABLE users ADD COLUMN total_btc_earned DECIMAL(20,8) DEFAULT 0;
ALTER TABLE users ADD COLUMN total_btc_sold DECIMAL(20,8) DEFAULT 0;

-- ============================================================
-- MINER TYPES
-- ============================================================
CREATE TABLE IF NOT EXISTS btc_miners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(32) NOT NULL UNIQUE,
    name VARCHAR(64) NOT NULL,
    description VARCHAR(255),
    icon VARCHAR(8) DEFAULT '💻',
    tier INT DEFAULT 1,
    base_price INT NOT NULL,
    base_btc_per_hour DECIMAL(20,8) NOT NULL,
    min_rank INT DEFAULT 1
);

-- ============================================================
-- USER MINERS — 1 per huis
-- ============================================================
CREATE TABLE IF NOT EXISTS user_btc_miners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    house_id INT NOT NULL,
    miner_key VARCHAR(32) NOT NULL,
    level INT DEFAULT 1,
    last_production_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total_btc_mined DECIMAL(20,8) DEFAULT 0,
    bought_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (house_id),
    INDEX (user_id)
);

-- ============================================================
-- BTC TRANSACTIES
-- ============================================================
CREATE TABLE IF NOT EXISTS btc_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(20,8) NOT NULL,
    type VARCHAR(32) NOT NULL,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id),
    INDEX (type)
);

-- ============================================================
-- SEED — Miners
-- ============================================================
INSERT IGNORE INTO btc_miners (`key`, name, description, icon, tier, base_price, base_btc_per_hour, min_rank) VALUES
('basic',    'Basis miner',      'Een simpele GPU miner voor thuis.',          '💻', 1, 25000,   0.000010,  3),
('pro',      'Pro miner',        'Krachtige ASIC miner, betere opbrengst.',    '🖥️', 2, 100000,  0.000050,  5),
('industrial','Industriële rig', 'Een volledige mining farm.',                 '🏭', 3, 500000,  0.000250,  7),
('quantum',  'Quantum miner',    'Geavanceerde quantum computer miner.',       '⚛️', 4, 2500000, 0.001500,  9);