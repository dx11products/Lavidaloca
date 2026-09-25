USE lavidaloca;

CREATE TABLE IF NOT EXISTS pm_conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_a INT NOT NULL,
    user_b INT NOT NULL,
    last_message_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_message_by INT NULL,
    UNIQUE KEY pair_unique (user_a, user_b),
    INDEX (user_a),
    INDEX (user_b),
    INDEX (last_message_at)
);

CREATE TABLE IF NOT EXISTS pm_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    body TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    is_deleted_by_sender TINYINT(1) DEFAULT 0,
    is_deleted_by_receiver TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (conversation_id),
    INDEX (sender_id),
    INDEX (receiver_id),
    INDEX (is_read),
    INDEX (created_at)
);

CREATE TABLE IF NOT EXISTS pm_blocks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    blocked_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY block_unique (user_id, blocked_id),
    INDEX (user_id)
);