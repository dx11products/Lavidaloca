USE lavidaloca;

CREATE TABLE IF NOT EXISTS items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(64) NOT NULL UNIQUE,
    name VARCHAR(128) NOT NULL,
    description VARCHAR(255) NOT NULL,
    category ENUM('weapon','armor','vehicle','misc') NOT NULL,
    price INT NOT NULL,
    attack_bonus INT DEFAULT 0,
    defense_bonus INT DEFAULT 0,
    min_rank INT DEFAULT 1
);

CREATE TABLE IF NOT EXISTS user_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    item_key VARCHAR(64) NOT NULL,
    quantity INT DEFAULT 1,
    equipped TINYINT(1) DEFAULT 0,
    bought_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id),
    INDEX (item_key)
);

-- Items invoegen (alleen als ze nog niet bestaan)
INSERT IGNORE INTO items (`key`, name, description, category, price, attack_bonus, defense_bonus, min_rank) VALUES
('knuppel',        'Knuppel',           'Een simpele houten knuppel. Beter dan niets.',              'weapon',  150,   3,  0, 1),
('zakmes',         'Zakmes',            'Klein maar effectief in een gevecht.',                      'weapon',  400,   6,  0, 2),
('honkbalknuppel', 'Honkbalknuppel',    'Klassieker uit de Amerikaanse straten.',                    'weapon',  900,  11,  0, 3),
('pistool',        'Pistool',           'Een 9mm pistool. Klein, dodelijk.',                         'weapon', 2500,  20,  0, 4),
('uzi',            'Uzi',               'Snelvuurwapen voor de echte gangster.',                     'weapon', 6000,  35,  0, 5),
('shotgun',        'Shotgun',           'Één schot en het is voorbij.',                              'weapon',12000,  55,  0, 6),
('ak47',           'AK-47',             'Het wapen van legendes. Iedereen is bang.',                 'weapon',30000,  90,  0, 8),

('leren_jas',      'Leren jack',        'Biedt wat bescherming tegen klappen.',                      'armor',   300,   0,  4, 1),
('kogelvrij_vest', 'Kogelvrij vest',    'Beschermt je tegen pistoolschoten.',                        'armor',  3000,   0, 12, 3),
('gevechtsvest',   'Gevechtsvest',      'Militair vest. Bijna ondoordringbaar.',                     'armor', 15000,   0, 25, 6),

('fiets',          'Fiets',             'Simpele stadsfiets. Sneller dan lopen.',                    'vehicle', 250,   0,  0, 1),
('scooter',        'Scooter',           'Vespa'tje voor de snelle ontsnapping.',                     'vehicle',1200,   0,  0, 2),
('motor',          'Motorfiets',        'Snel en wendbaar in het verkeer.',                          'vehicle',4500,   0,  0, 4),
('bmw',            'BMW',               'Duitse klasse. Iedereen kijkt om.',                         'vehicle',25000,   0,  0, 6),
('ferrari',        'Ferrari',           'Rood, snel, en jaloersmakend.',                             'vehicle',90000,   0,  0, 9);