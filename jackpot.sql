USE lavidaloca;

CREATE TABLE IF NOT EXISTS jackpot_pools (
    id INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(32) NOT NULL UNIQUE,
    name VARCHAR(64) NOT NULL,
    icon VARCHAR(8) DEFAULT '🎰',
    color VARCHAR(16) DEFAULT '#c9a44c',
    min_amount BIGINT NOT NULL,
    current_amount BIGINT NOT NULL,
    growth_per_million BIGINT NOT NULL,
    hit_chance INT NOT NULL,
    min_bet BIGINT NOT NULL,
    max_bet BIGINT NOT NULL,
    last_won_by INT NULL,
    last_won_at TIMESTAMP NULL,
    last_won_amount BIGINT NULL,
    total_won BIGINT DEFAULT 0,
    win_count INT DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS jackpot_wins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jackpot_key VARCHAR(32) NOT NULL,
    user_id INT NOT NULL,
    amount BIGINT NOT NULL,
    game_key VARCHAR(32) NOT NULL,
    bet BIGINT NOT NULL,
    won_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id),
    INDEX (jackpot_key),
    INDEX (won_at)
);

INSERT IGNORE INTO jackpot_pools
    (`key`, name, icon, color, min_amount, current_amount, growth_per_million, hit_chance, min_bet, max_bet)
VALUES
    ('mini',  'Mini Jackpot',  '🥉', '#cd7f32', 1000000,     1000000,     10000,     500,    10000,     1000000),
    ('minor', 'Minor Jackpot', '🥈', '#c0c0c0', 25000000,    25000000,    250000,    5000,   100000,    5000000),
    ('major', 'Major Jackpot', '🥇', '#d4af37', 250000000,   250000000,   2500000,   50000,  500000,    25000000),
    ('grand', 'Grand Jackpot', '👑', '#ffb040', 5000000000,  5000000000,  50000000,  500000, 5000000,   50000000);