USE lavidaloca;

-- ============================================================
-- LABS — verwerkingsplekken voor drugs
-- ============================================================
CREATE TABLE IF NOT EXISTS labs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(32) NOT NULL UNIQUE,
    name VARCHAR(64) NOT NULL,
    description VARCHAR(255),
    lab_type ENUM('cocaine','meth','xtc') NOT NULL,
    price INT NOT NULL,
    batch_size INT DEFAULT 10,          -- aantal producten per productie
    process_time_minutes INT DEFAULT 15 -- hoe lang per batch
);

-- ============================================================
-- SPELER — gekochte labs per land
-- ============================================================
CREATE TABLE IF NOT EXISTS user_labs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    country_key VARCHAR(32) NOT NULL,
    lab_key VARCHAR(32) NOT NULL,
    bought_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (user_id, country_key, lab_key),
    INDEX (user_id)
);

-- ============================================================
-- PRODUCTIE — actieve drug batches
-- ============================================================
CREATE TABLE IF NOT EXISTS user_lab_production (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    lab_key VARCHAR(32) NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ready_at TIMESTAMP NOT NULL,
    batch_amount INT NOT NULL,
    collected TINYINT(1) DEFAULT 0,
    INDEX (user_id, collected)
);

-- ============================================================
-- KOGELFABRIEK
-- ============================================================
CREATE TABLE IF NOT EXISTS bullet_factories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(32) NOT NULL UNIQUE,
    name VARCHAR(64) NOT NULL,
    description VARCHAR(255),
    price INT NOT NULL,
    yield_per_batch INT DEFAULT 50,       -- kogels per productie
    process_time_minutes INT DEFAULT 10,  -- tijd per batch
    material_cost INT DEFAULT 200         -- materiaalkosten per batch
);

CREATE TABLE IF NOT EXISTS user_factories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    country_key VARCHAR(32) NOT NULL,
    factory_key VARCHAR(32) NOT NULL,
    bought_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (user_id, country_key, factory_key),
    INDEX (user_id)
);

-- ============================================================
-- MUNITIE INVENTORY
-- ============================================================
CREATE TABLE IF NOT EXISTS user_ammo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    ammo_key VARCHAR(32) NOT NULL,
    quantity INT DEFAULT 0,
    UNIQUE KEY (user_id, ammo_key),
    INDEX (user_id)
);

CREATE TABLE IF NOT EXISTS user_bullet_production (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    factory_key VARCHAR(32) NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ready_at TIMESTAMP NOT NULL,
    batch_amount INT NOT NULL,
    collected TINYINT(1) DEFAULT 0,
    INDEX (user_id, collected)
);

-- ============================================================
-- SEED — labs
-- ============================================================
INSERT IGNORE INTO labs (`key`, name, description, lab_type, price, batch_size, process_time_minutes) VALUES
('coke_lab',    'Cocaïne lab',    'Verwerk cocaïnebladeren tot zuivere cocaïne.',    'cocaine', 50000, 10, 20),
('meth_lab',    'Meth lab',       'Chemische productie van crystal meth.',            'meth',    40000, 10, 15),
('xtc_lab',     'XTC fabriek',    'Productie van XTC pillen in bulk.',                'xtc',     25000, 20, 10);

-- ============================================================
-- SEED — kogelfabrieken
-- ============================================================
INSERT IGNORE INTO bullet_factories (`key`, name, description, price, yield_per_batch, process_time_minutes, material_cost) VALUES
('klein',   'Kleine werkplaats',   'Kleine productie voor eigen gebruik.',  15000,  50,  5, 200),
('middel',  'Middelgrote fabriek', 'Productie voor de hele bende.',         60000,  200, 10, 600),
('groot',   'Grote fabriek',       'Industriële productie — massa munitie.',250000, 1000, 15, 2500);