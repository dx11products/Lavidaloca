<?php
/**
 * Vendetta — Forum
 *
 * BELANGRIJK: Alleen functies en constanten.
 * GEEN require, GEEN redirect.
 */

// ============================================================
// INSTELLINGEN
// ============================================================
const FORUM_TITLE_MIN     = 3;
const FORUM_TITLE_MAX     = 120;
const FORUM_BODY_MIN      = 5;
const FORUM_BODY_MAX      = 5000;
const FORUM_TOPICS_PER_PAGE  = 20;
const FORUM_REPLIES_PER_PAGE = 25;
const FORUM_EDIT_TIME_MIN = 15;   // minuten waarbinnen je mag editen

// ============================================================
// CATEGORIEËN
// ============================================================
function getForumCategories(PDO $pdo): array {
    $stmt = $pdo->query("SELECT * FROM forum_categories ORDER BY sort_order ASC, id ASC");
    return $stmt->fetchAll();
}

function getForumCategory(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare("SELECT * FROM forum_categories WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Statistieken per categorie: aantal topics + laatste topic.
 */
function getCategoryStats(PDO $pdo, int $categoryId): array {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM forum_topics WHERE category_id = ?");
    $stmt->execute([$categoryId]);
    $totalTopics = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(reply_count), 0) FROM forum_topics WHERE category_id = ?");
    $stmt->execute([$categoryId]);
    $totalReplies = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT t.*, u.username
        FROM forum_topics t
        JOIN users u ON u.id = t.user_id
        WHERE t.category_id = ?
        ORDER BY t.last_reply_at DESC, t.id DESC
        LIMIT 1
    ");
    $stmt->execute([$categoryId]);
    $lastTopic = $stmt->fetch();

    return [
        'topics'     => $totalTopics,
        'replies'    => $totalReplies,
        'last_topic' => $lastTopic ?: null,
    ];
}

// ============================================================
// TOPICS
// ============================================================
function getTopicsInCategory(PDO $pdo, int $categoryId, int $limit = 20, int $offset = 0): array {
    $stmt = $pdo->prepare("
        SELECT t.*,
               u.username,
               u.xp,
               u.rank_title,
               lu.username AS last_reply_username
        FROM forum_topics t
        JOIN users u ON u.id = t.user_id
        LEFT JOIN users lu ON lu.id = t.last_reply_user
        WHERE t.category_id = ?
        ORDER BY t.is_pinned DESC, COALESCE(t.last_reply_at, t.created_at) DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $categoryId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function countTopicsInCategory(PDO $pdo, int $categoryId): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM forum_topics WHERE category_id = ?");
    $stmt->execute([$categoryId]);
    return (int)$stmt->fetchColumn();
}

function getForumTopic(PDO $pdo, int $topicId): ?array {
    $stmt = $pdo->prepare("
        SELECT t.*, u.username, u.xp, u.rank_title, u.total_likes_received
        FROM forum_topics t
        JOIN users u ON u.id = t.user_id
        WHERE t.id = ? LIMIT 1
    ");
    $stmt->execute([$topicId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Recente topics voor homepage.
 */
function getRecentTopics(PDO $pdo, int $limit = 10): array {
    $stmt = $pdo->prepare("
        SELECT t.*, u.username, c.name AS category_name, c.icon AS category_icon, c.color AS category_color
        FROM forum_topics t
        JOIN users u ON u.id = t.user_id
        JOIN forum_categories c ON c.id = t.category_id
        ORDER BY COALESCE(t.last_reply_at, t.created_at) DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Populaire topics (meeste replies).
 */
function getPopularTopics(PDO $pdo, int $limit = 5): array {
    $stmt = $pdo->prepare("
        SELECT t.*, u.username, c.name AS category_name, c.icon AS category_icon
        FROM forum_topics t
        JOIN users u ON u.id = t.user_id
        JOIN forum_categories c ON c.id = t.category_id
        ORDER BY t.reply_count DESC, t.views DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function incrementTopicViews(PDO $pdo, int $topicId): void {
    $pdo->prepare("UPDATE forum_topics SET views = views + 1 WHERE id = ?")
        ->execute([$topicId]);
}

// ============================================================
// REPLIES
// ============================================================
function getTopicReplies(PDO $pdo, int $topicId, int $limit = 25, int $offset = 0): array {
    $stmt = $pdo->prepare("
        SELECT r.*, u.username, u.xp, u.rank_title, u.total_likes_received
        FROM forum_replies r
        JOIN users u ON u.id = r.user_id
        WHERE r.topic_id = ? AND r.is_deleted = 0
        ORDER BY r.created_at ASC, r.id ASC
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $topicId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function countTopicReplies(PDO $pdo, int $topicId): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM forum_replies WHERE topic_id = ? AND is_deleted = 0");
    $stmt->execute([$topicId]);
    return (int)$stmt->fetchColumn();
}

// ============================================================
// ACTIES
// ============================================================
function createForumTopic(PDO $pdo, int $userId, int $categoryId, string $title, string $body): array {
    $title = trim($title);
    $body  = trim($body);

    if (mb_strlen($title) < FORUM_TITLE_MIN || mb_strlen($title) > FORUM_TITLE_MAX) {
        return ['error' => 'Titel moet tussen ' . FORUM_TITLE_MIN . ' en ' . FORUM_TITLE_MAX . ' tekens zijn.'];
    }
    if (mb_strlen($body) < FORUM_BODY_MIN || mb_strlen($body) > FORUM_BODY_MAX) {
        return ['error' => 'Bericht moet tussen ' . FORUM_BODY_MIN . ' en ' . FORUM_BODY_MAX . ' tekens zijn.'];
    }

    $category = getForumCategory($pdo, $categoryId);
    if (!$category) {
        return ['error' => 'Categorie niet gevonden.'];
    }
    if ((int)$category['is_locked'] === 1) {
        return ['error' => 'Deze categorie is gesloten.'];
    }

    try {
        $pdo->prepare("
            INSERT INTO forum_topics (category_id, user_id, title, body, last_reply_at, last_reply_user)
            VALUES (?, ?, ?, ?, NOW(), ?)
        ")->execute([$categoryId, $userId, $title, $body, $userId]);

        $topicId = (int)$pdo->lastInsertId();

        if (function_exists('logActivity')) {
            logActivity($pdo, $userId, "📝 Forum topic aangemaakt: {$title}");
        }

        return ['success' => true, 'topic_id' => $topicId];
    } catch (Exception $e) {
        return ['error' => 'Kon topic niet aanmaken.'];
    }
}

function createForumReply(PDO $pdo, int $userId, int $topicId, string $body): array {
    $body = trim($body);
    if (mb_strlen($body) < FORUM_BODY_MIN || mb_strlen($body) > FORUM_BODY_MAX) {
        return ['error' => 'Bericht moet tussen ' . FORUM_BODY_MIN . ' en ' . FORUM_BODY_MAX . ' tekens zijn.'];
    }

    $topic = getForumTopic($pdo, $topicId);
    if (!$topic) {
        return ['error' => 'Topic niet gevonden.'];
    }
    if ((int)$topic['is_locked'] === 1) {
        return ['error' => 'Dit topic is gesloten.'];
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("
            INSERT INTO forum_replies (topic_id, user_id, body)
            VALUES (?, ?, ?)
        ")->execute([$topicId, $userId, $body]);

        $pdo->prepare("
            UPDATE forum_topics
            SET reply_count = reply_count + 1,
                last_reply_at = NOW(),
                last_reply_user = ?
            WHERE id = ?
        ")->execute([$userId, $topicId]);

        if (function_exists('logActivity')) {
            logActivity($pdo, $userId, "💬 Gereageerd op forum topic: {$topic['title']}");
        }

        $pdo->commit();
        return ['success' => true];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['error' => 'Kon reactie niet plaatsen.'];
    }
}

function editForumTopic(PDO $pdo, int $userId, int $topicId, string $title, string $body): array {
    $topic = getForumTopic($pdo, $topicId);
    if (!$topic) return ['error' => 'Topic niet gevonden.'];
    if ((int)$topic['user_id'] !== $userId) return ['error' => 'Niet je eigen topic.'];
    if ((time() - strtotime($topic['created_at'])) > (FORUM_EDIT_TIME_MIN * 60)) {
        return ['error' => 'Je kunt een topic alleen binnen ' . FORUM_EDIT_TIME_MIN . ' minuten bewerken.'];
    }

    $title = trim($title);
    $body  = trim($body);
    if (mb_strlen($title) < FORUM_TITLE_MIN || mb_strlen($title) > FORUM_TITLE_MAX) {
        return ['error' => 'Ongeldige titel.'];
    }
    if (mb_strlen($body) < FORUM_BODY_MIN || mb_strlen($body) > FORUM_BODY_MAX) {
        return ['error' => 'Ongeldig bericht.'];
    }

    $pdo->prepare("UPDATE forum_topics SET title = ?, body = ? WHERE id = ?")
        ->execute([$title, $body, $topicId]);
    return ['success' => true];
}

function deleteForumTopic(PDO $pdo, int $userId, int $topicId): array {
    $topic = getForumTopic($pdo, $topicId);
    if (!$topic) return ['error' => 'Topic niet gevonden.'];

    // Iedereen mag eigen topic verwijderen (of admin)
    if ((int)$topic['user_id'] !== $userId && $userId !== 1) {
        return ['error' => 'Je kunt alleen je eigen topics verwijderen.'];
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM forum_replies WHERE topic_id = ?")->execute([$topicId]);
        $pdo->prepare("DELETE FROM forum_topics WHERE id = ?")->execute([$topicId]);
        $pdo->commit();
        return ['success' => true];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['error' => 'Verwijderen mislukt.'];
    }
}

function deleteForumReply(PDO $pdo, int $userId, int $replyId): array {
    $stmt = $pdo->prepare("SELECT * FROM forum_replies WHERE id = ? LIMIT 1");
    $stmt->execute([$replyId]);
    $reply = $stmt->fetch();
    if (!$reply) return ['error' => 'Reactie niet gevonden.'];

    if ((int)$reply['user_id'] !== $userId && $userId !== 1) {
        return ['error' => 'Je kunt alleen je eigen reacties verwijderen.'];
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE forum_replies SET is_deleted = 1 WHERE id = ?")->execute([$replyId]);
        $pdo->prepare("UPDATE forum_topics SET reply_count = GREATEST(0, reply_count - 1) WHERE id = ?")
            ->execute([$reply['topic_id']]);
        $pdo->commit();
        return ['success' => true];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['error' => 'Verwijderen mislukt.'];
    }
}

// ============================================================
// STATS VOOR HOMEPAGE
// ============================================================
function getForumStats(PDO $pdo): array {
    $topics = (int)$pdo->query("SELECT COUNT(*) FROM forum_topics")->fetchColumn();
    $replies = (int)$pdo->query("SELECT COUNT(*) FROM forum_replies WHERE is_deleted = 0")->fetchColumn();
    $members = (int)$pdo->query("SELECT COUNT(DISTINCT user_id) FROM (SELECT user_id FROM forum_topics UNION SELECT user_id FROM forum_replies) AS f")->fetchColumn();

    return [
        'topics'  => $topics,
        'replies' => $replies,
        'members' => $members,
    ];
}

/**
 * Kort een tekst in voor weergave.
 */
function forumExcerpt(string $text, int $length = 140): string {
    $text = strip_tags($text);
    if (mb_strlen($text) <= $length) return $text;
    return mb_substr($text, 0, $length) . '...';
}

/**
 * Tijd formatting: "zojuist", "5 min geleden", etc.
 */
function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'zojuist';
    if ($diff < 3600) return floor($diff / 60) . ' min geleden';
    if ($diff < 86400) return floor($diff / 3600) . ' uur geleden';
    if ($diff < 604800) return floor($diff / 86400) . ' dagen geleden';
    return date('d M Y', strtotime($datetime));
}

/**
 * Kleur voor gebruiker op basis van rank.
 */
function getUserRankColor(PDO $pdo, int $xp): string {
    if (!function_exists('getRanksArray')) return '#a08d75';
    $ranks = getRanksArray();
    $rank = function_exists('getRankData') ? getRankData($xp, $ranks) : null;
    if (!$rank) return '#a08d75';

    return match($rank['level']) {
        10 => '#ffb040',
        9  => '#e8c877',
        8  => '#c9a44c',
        7  => '#b06aff',
        6  => '#4a9dff',
        5  => '#58e08c',
        4  => '#8ac9ff',
        default => '#a08d75',
    };
}