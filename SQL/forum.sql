USE lavidaloca;

-- ============================================================
-- FORUM CATEGORIEËN
-- ============================================================
CREATE TABLE IF NOT EXISTS forum_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(64) NOT NULL,
    description VARCHAR(255),
    icon VARCHAR(8) DEFAULT '💬',
    color VARCHAR(16) DEFAULT '#c9a44c',
    sort_order INT DEFAULT 0,
    min_rank INT DEFAULT 1,
    is_locked TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- FORUM TOPICS
-- ============================================================
CREATE TABLE IF NOT EXISTS forum_topics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    is_pinned TINYINT(1) DEFAULT 0,
    is_locked TINYINT(1) DEFAULT 0,
    views INT DEFAULT 0,
    reply_count INT DEFAULT 0,
    last_reply_at TIMESTAMP NULL,
    last_reply_user INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (category_id),
    INDEX (user_id),
    INDEX (is_pinned),
    INDEX (last_reply_at)
);

-- ============================================================
-- FORUM REPLIES
-- ============================================================
CREATE TABLE IF NOT EXISTS forum_replies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    topic_id INT NOT NULL,
    user_id INT NOT NULL,
    body TEXT NOT NULL,
    is_deleted TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (topic_id),
    INDEX (user_id)
);

-- ============================================================
-- SEED — Categorieën
-- ============================================================
INSERT IGNORE INTO forum_categories (id, name, description, icon, color, sort_order, min_rank) VALUES
(1, 'Aankondigingen',    'Officiële updates en aankondigingen van het team.',       '📢', '#c9a44c', 1, 1),
(2, 'Algemeen',          'Praten over alles wat met Vendetta te maken heeft.',       '💬', '#4a9dff', 2, 1),
(3, 'Strategie & Tips',  'Deel je beste strategieën en tips met andere spelers.',    '🧠', '#58e08c', 3, 1),
(4, 'Bendes & Families', 'Zoek leden, sluit allianties, of daag anderen uit.',       '👥', '#b06aff', 4, 1),
(5, 'Bugs & Suggesties', 'Meld bugs of stel nieuwe features voor.',                  '🐛', '#ff5c5c', 5, 1),
(6, 'Off-topic',         'Alles wat nergens anders past.',                          '🎲', '#8a8a8a', 6, 1);