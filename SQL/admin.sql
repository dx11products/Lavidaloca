USE lavidaloca;

-- Admin kolom
ALTER TABLE users ADD COLUMN is_admin TINYINT(1) DEFAULT 0;
ALTER TABLE users ADD COLUMN is_banned TINYINT(1) DEFAULT 0;
ALTER TABLE users ADD COLUMN ban_reason VARCHAR(255) NULL;
ALTER TABLE users ADD COLUMN banned_until TIMESTAMP NULL;
ALTER TABLE users ADD COLUMN notes TEXT NULL;

-- Zet user 1 als admin
UPDATE users SET is_admin = 1 WHERE id = 1;

-- Admin log
CREATE TABLE IF NOT EXISTS admin_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(64) NOT NULL,
    target_type VARCHAR(32),
    target_id INT,
    details TEXT,
    ip VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (admin_id),
    INDEX (action),
    INDEX (created_at)
);

-- Game settings (live aanpasbaar)
CREATE TABLE IF NOT EXISTS game_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(64) NOT NULL UNIQUE,
    value TEXT,
    type ENUM('string','int','float','bool','json') DEFAULT 'string',
    label VARCHAR(128),
    description VARCHAR(255),
    category VARCHAR(32) DEFAULT 'general',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT IGNORE INTO game_settings (`key`, value, type, label, description, category) VALUES
('site_name',          'Vendetta',  'string', 'Site naam',              'De naam van de game', 'general'),
('maintenance_mode',   '0',         'bool',   'Onderhoudsmodus',        'Spelers kunnen niet inloggen', 'general'),
('maintenance_msg',    'We zijn even bezig. Kom snel terug!', 'string', 'Onderhoud bericht', 'Bericht op de onderhoudspagina', 'general'),
('register_enabled',   '1',         'bool',   'Registratie open',       'Nieuwe spelers kunnen zich aanmelden', 'general'),
('energy_regen_sec',   '60',        'int',    'Energie regen (sec)',    'Seconden per 1 energie', 'economy'),
('bank_interest',      '0.02',      'float',  'Bank rente %',           'Dagelijkse rente op banksaldo', 'economy'),
('btc_sell_rate',      '60000',     'int',    'BTC verkoopprijs',       'EUR per BTC', 'economy'),
('crime_level_bonus',  '0.45',      'float',  'Crime rank bonus',       'Bonus per rank level', 'economy'),
('pvp_enabled',        '1',         'bool',   'PvP aanvalsysteem',      'Spelers kunnen elkaar aanvallen', 'pvp'),
('heists_enabled',     '1',         'bool',   'Heists actief',          'Georganiseerde misdaad aan', 'pvp'),
('lootboxes_enabled',  '1',         'bool',   'Lootboxes actief',       'Spelers kunnen lootboxes kopen', 'economy'),
('forum_enabled',      '1',         'bool',   'Forum actief',           'Forum beschikbaar', 'community'),
('pm_enabled',         '1',         'bool',   'Privéberichten actief',  'Spelers kunnen PM sturen', 'community'),
('max_users',          '10000',     'int',    'Max spelers',            'Maximum aantal accounts', 'general'),
('starting_money',     '500',       'int',    'Startgeld',              'Geld bij registratie', 'economy'),
('starting_energy',    '100',       'int',    'Startenergie',           'Energie bij registratie', 'economy');