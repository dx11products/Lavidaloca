USE lavidaloca;

CREATE TABLE IF NOT EXISTS chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    channel ENUM('global','family') DEFAULT 'global',
    family_id INT NULL,
    user_id INT NOT NULL,
    body TEXT NOT NULL,
    is_deleted TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (channel),
    INDEX (family_id),
    INDEX (created_at)
);

CREATE TABLE IF NOT EXISTS chat_online (
    user_id INT NOT NULL PRIMARY KEY,
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (last_seen)
);