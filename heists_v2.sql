USE lavidaloca;

-- Rol XP per user per rol
CREATE TABLE IF NOT EXISTS user_role_xp (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    role VARCHAR(32) NOT NULL,
    xp INT DEFAULT 0,
    heists_done INT DEFAULT 0,
    heists_won INT DEFAULT 0,
    UNIQUE KEY (user_id, role),
    INDEX (user_id)
);

-- Heist lobby: bende-only
ALTER TABLE heist_lobbies
    ADD COLUMN family_id INT DEFAULT NULL;

ALTER TABLE heist_lobbies
    ADD COLUMN is_family_heist TINYINT(1) DEFAULT 0;

ALTER TABLE heist_lobbies
    ADD INDEX idx_family_id (family_id);

-- Heist history: rol XP
ALTER TABLE heist_history
    ADD COLUMN role_xp_gained INT DEFAULT 0;

ALTER TABLE heist_history
    ADD COLUMN bonus_percent INT DEFAULT 0;