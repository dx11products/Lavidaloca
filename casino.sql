USE lavidaloca;

-- Voeg casino-assets toe aan market_assets (per land verkoopbaar)
INSERT IGNORE INTO market_assets (`key`, name, description, icon, category, tier, base_price_diamonds, sell_price_diamonds, max_per_user, min_rank, income_type, income_per_hour, color) VALUES
('blackjack_table', 'Blackjack Tafel', 'Spelers betalen jou bij verlies.', '🃏', 'casino', 3, 750, 300, 5, 7, 'eur', 0, '#4a9dff'),
('poker_table',     'Poker Tafel',     'Poker spelers betalen jou bij verlies.', '♠️', 'casino', 3, 900, 360, 5, 8, 'eur', 0, '#b06aff');

-- Log van casino spellen
CREATE TABLE IF NOT EXISTS casino_game_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    game_key VARCHAR(32) NOT NULL,
    country_key VARCHAR(32) NOT NULL,
    bet BIGINT NOT NULL,
    result ENUM('win','loss','push') NOT NULL,
    payout BIGINT DEFAULT 0,
    net BIGINT DEFAULT 0,
    owner_id INT NULL,
    owner_share BIGINT DEFAULT 0,
    details VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id),
    INDEX (game_key),
    INDEX (country_key),
    INDEX (owner_id),
    INDEX (created_at)
);

-- Casino inkomsten overzicht (per eigenaar)
CREATE TABLE IF NOT EXISTS casino_earnings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    game_key VARCHAR(32) NOT NULL,
    country_key VARCHAR(32) NOT NULL,
    total_bets BIGINT DEFAULT 0,
    total_earnings BIGINT DEFAULT 0,
    total_losses_paid BIGINT DEFAULT 0,
    net_profit BIGINT DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY (owner_id, game_key, country_key),
    INDEX (owner_id)
);