USE lavidaloca;

CREATE TABLE IF NOT EXISTS clicks_purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount INT NOT NULL,
    paid_eur BIGINT DEFAULT 0,
    paid_btc DECIMAL(20,8) DEFAULT 0,
    method ENUM('eur','btc','reward') NOT NULL,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id),
    INDEX (method)
);