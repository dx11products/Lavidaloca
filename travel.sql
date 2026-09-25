USE lavidaloca;

-- ============================================================
-- LANDEN
-- ============================================================
CREATE TABLE IF NOT EXISTS countries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(32) NOT NULL UNIQUE,
    name VARCHAR(64) NOT NULL,
    flag VARCHAR(8) NOT NULL,
    travel_cost INT NOT NULL,
    description VARCHAR(255),
    is_home TINYINT(1) DEFAULT 0
);

-- ============================================================
-- DRUGS MARKT — prijzen per land, per drug
-- ============================================================
CREATE TABLE IF NOT EXISTS drug_prices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    country_key VARCHAR(32) NOT NULL,
    drug_key VARCHAR(32) NOT NULL,
    buy_price INT NOT NULL,
    sell_price INT NOT NULL,
    UNIQUE KEY (country_key, drug_key)
);

-- ============================================================
-- SPELER — huidige locatie + drugs inventory
-- ============================================================
ALTER TABLE users
    ADD COLUMN current_country VARCHAR(32) DEFAULT 'nederland';

CREATE TABLE IF NOT EXISTS user_drugs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    drug_key VARCHAR(32) NOT NULL,
    quantity INT DEFAULT 0,
    UNIQUE KEY (user_id, drug_key),
    INDEX (user_id)
);

-- ============================================================
-- HUIZEN — verkoopbaar per land
-- ============================================================
CREATE TABLE IF NOT EXISTS houses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(32) NOT NULL UNIQUE,
    name VARCHAR(64) NOT NULL,
    description VARCHAR(255),
    price INT NOT NULL,
    grow_slots INT DEFAULT 1,          -- aantal wietplanten
    grow_time_minutes INT DEFAULT 60,  -- hoe lang tot oogst
    yield_per_slot INT DEFAULT 5       -- wiet per plant
);

-- ============================================================
-- SPELER — gekochte huizen per land
-- ============================================================
CREATE TABLE IF NOT EXISTS user_houses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    country_key VARCHAR(32) NOT NULL,
    house_key VARCHAR(32) NOT NULL,
    bought_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (user_id, country_key),
    INDEX (user_id)
);

-- ============================================================
-- WIET PLANTEN — actieve kweek
-- ============================================================
CREATE TABLE IF NOT EXISTS user_plants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    country_key VARCHAR(32) NOT NULL,
    planted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    harvest_at TIMESTAMP NOT NULL,
    harvested TINYINT(1) DEFAULT 0,
    yield_amount INT DEFAULT 0,
    INDEX (user_id, harvested)
);

-- ============================================================
-- SEED DATA — landen
-- ============================================================
INSERT IGNORE INTO countries (`key`, name, flag, travel_cost, description, is_home) VALUES
('nederland', 'Nederland',     '🇳🇱', 0,     'Het thuisland. Rustig en vertrouwd.',           1),
('belgie',    'België',        '🇧🇪', 500,   'Buren met goede connecties.',                   0),
('duitsland', 'Duitsland',     '🇩🇪', 800,   'Groot land, veel vraag naar xtc.',              0),
('frankrijk', 'Frankrijk',     '🇫🇷', 1200,  'Luxe markt, hoge prijzen.',                     0),
('spanje',    'Spanje',        '🇪🇸', 1500,  'Warm weer, perfect voor wiet.',                 0),
('marokko',   'Marokko',       '🇲🇦', 2000,  'Hasj paradijs. Bekend om zijn kwaliteit.',      0),
('colombia',  'Colombia',      '🇨🇴', 3500,  'Cocaïne hoofstad van de wereld.',               0),
('mexico',    'Mexico',        '🇲🇽', 4000,  'Meth laboratoria en kartels.',                  0),
('afghanistan','Afghanistan',  '🇦🇫', 5000,  'Heroïne en opium. Gevaarlijk maar winstgevend.',0);

-- ============================================================
-- SEED DATA — prijzen per land
-- buy_price = wat jij betaalt om te kopen
-- sell_price = wat jij krijgt als je verkoopt
-- ============================================================
INSERT IGNORE INTO drug_prices (country_key, drug_key, buy_price, sell_price) VALUES
-- NEDERLAND
('nederland','wiet',      50,  70),
('nederland','cocaine',   500, 650),
('nederland','meth',      400, 500),
('nederland','xtc',       60,  90),

-- BELGIE
('belgie','wiet',         55,  80),
('belgie','cocaine',      520, 680),
('belgie','meth',         380, 520),
('belgie','xtc',          55,  85),

-- DUITSLAND
('duitsland','wiet',      60,  90),
('duitsland','cocaine',   550, 720),
('duitsland','meth',      350, 480),
('duitsland','xtc',       45,  75),

-- FRANKRIJK
('frankrijk','wiet',      80,  120),
('frankrijk','cocaine',   600, 800),
('frankrijk','meth',      450, 600),
('frankrijk','xtc',       80,  130),

-- SPANJE
('spanje','wiet',         40,  65),
('spanje','cocaine',      520, 700),
('spanje','meth',         420, 550),
('spanje','xtc',          70,  110),

-- MAROKKO
('marokko','wiet',        30,  55),
('marokko','cocaine',     480, 620),
('marokko','meth',        400, 530),
('marokko','xtc',         90,  140),

-- COLOMBIA
('colombia','wiet',       70,  110),
('colombia','cocaine',    250, 400),
('colombia','meth',       450, 600),
('colombia','xtc',        100, 160),

-- MEXICO
('mexico','wiet',         55,  90),
('mexico','cocaine',      380, 550),
('mexico','meth',         200, 350),
('mexico','xtc',          90,  150),

-- AFGHANISTAN
('afghanistan','wiet',    90,  140),
('afghanistan','cocaine', 700, 900),
('afghanistan','meth',    500, 700),
('afghanistan','xtc',     120, 180);

-- ============================================================
-- SEED DATA — huizen
-- ============================================================
INSERT IGNORE INTO houses (`key`, name, description, price, grow_slots, grow_time_minutes, yield_per_slot) VALUES
('flat',        'Flat',           'Een kleine flat. 1 plant.',         2500,   1, 60,  5),
('huis',        'Rijtjeshuis',    'Een simpel rijtjeshuis. 2 planten.',8000,   2, 60,  6),
('villa',       'Villa',          'Ruime villa met tuin. 4 planten.',  25000,  4, 45,  8),
('landgoed',    'Landgoed',       'Groot landgoed. 8 planten.',        75000,  8, 40,  10),
('plantage',    'Wietplantage',   'Een volledige plantage. 15 planten.',250000,15, 30,  12);