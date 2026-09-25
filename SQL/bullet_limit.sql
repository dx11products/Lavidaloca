USE lavidaloca;

-- Kolommen op users
ALTER TABLE users ADD COLUMN bullet_limit_level INT DEFAULT 1;
ALTER TABLE users ADD COLUMN bullet_used_today BIGINT DEFAULT 0;
ALTER TABLE users ADD COLUMN bullet_last_reset DATE NULL;

-- Kolom op families
ALTER TABLE families ADD COLUMN bullet_limit_level INT DEFAULT 1;

-- Log
CREATE TABLE IF NOT EXISTS bullet_production_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount BIGINT NOT NULL,
    cost BIGINT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id),
    INDEX (created_at)
);