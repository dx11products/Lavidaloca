<?php
/**
 * Vendetta — Chat systeem
 * BELANGRIJK: Alleen functies en constanten.
 */

const CHAT_MSG_MAX = 300;
const CHAT_MSG_MIN = 1;
const CHAT_COOLDOWN_SEC = 3;
const CHAT_ONLINE_WINDOW = 120; // 2 minuten
const CHAT_MESSAGES_LIMIT = 50;

// ============================================================
// BERICHTEN OPHALEN
// ============================================================
function getChatMessages(PDO $pdo, string $channel, ?int $familyId, int $afterId = 0): array {
    if ($channel === 'family' && $familyId) {
        if ($afterId > 0) {
            $stmt = $pdo->prepare("
                SELECT cm.*, u.username, u.xp, u.rank_title
                FROM chat_messages cm
                JOIN users u ON u.id = cm.user_id
                WHERE cm.channel = 'family' AND cm.family_id = ?
                  AND cm.id > ? AND cm.is_deleted = 0
                ORDER BY cm.id ASC
            ");
            $stmt->execute([$familyId, $afterId]);
        } else {
            $stmt = $pdo->prepare("
                SELECT cm.*, u.username, u.xp, u.rank_title
                FROM chat_messages cm
                JOIN users u ON u.id = cm.user_id
                WHERE cm.channel = 'family' AND cm.family_id = ?
                  AND cm.is_deleted = 0
                ORDER BY cm.id DESC
                LIMIT ?
            ");
            $stmt->bindValue(1, $familyId, PDO::PARAM_INT);
            $stmt->bindValue(2, CHAT_MESSAGES_LIMIT, PDO::PARAM_INT);
            $stmt->execute();
            return array_reverse($stmt->fetchAll());
        }
        return $stmt->fetchAll();
    }

    // Global
    if ($afterId > 0) {
        $stmt = $pdo->prepare("
            SELECT cm.*, u.username, u.xp, u.rank_title
            FROM chat_messages cm
            JOIN users u ON u.id = cm.user_id
            WHERE cm.channel = 'global'
              AND cm.id > ? AND cm.is_deleted = 0
            ORDER BY cm.id ASC
        ");
        $stmt->execute([$afterId]);
        return $stmt->fetchAll();
    }

    $stmt = $pdo->prepare("
        SELECT cm.*, u.username, u.xp, u.rank_title
        FROM chat_messages cm
        JOIN users u ON u.id = cm.user_id
        WHERE cm.channel = 'global'
          AND cm.is_deleted = 0
        ORDER BY cm.id DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, CHAT_MESSAGES_LIMIT, PDO::PARAM_INT);
    $stmt->execute();
    return array_reverse($stmt->fetchAll());
}

/**
 * Verstuur bericht.
 */
function sendChatMessage(PDO $pdo, int $userId, string $channel, ?int $familyId, string $body): array {
    $body = trim($body);

    if (mb_strlen($body) < CHAT_MSG_MIN) {
        return ['error' => 'Bericht mag niet leeg zijn.'];
    }
    if (mb_strlen($body) > CHAT_MSG_MAX) {
        return ['error' => 'Bericht te lang (max ' . CHAT_MSG_MAX . ' tekens).'];
    }

    // Cooldown check
    $stmt = $pdo->prepare("
        SELECT UNIX_TIMESTAMP(created_at) FROM chat_messages
        WHERE user_id = ?
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$userId]);
    $last = (int)$stmt->fetchColumn();
    if ($last > 0 && (time() - $last) < CHAT_COOLDOWN_SEC) {
        $wait = CHAT_COOLDOWN_SEC - (time() - $last);
        return ['error' => "Wacht nog {$wait}s voor je weer stuurt."];
    }

    // Familie check
    if ($channel === 'family') {
        if (!$familyId) return ['error' => 'Je zit niet in een familie.'];
        $stmt = $pdo->prepare("SELECT id FROM family_members WHERE user_id = ? AND family_id = ? LIMIT 1");
        $stmt->execute([$userId, $familyId]);
        if (!$stmt->fetch()) return ['error' => 'Je bent geen lid van deze familie.'];
    }

    try {
        $pdo->prepare("
            INSERT INTO chat_messages (channel, family_id, user_id, body)
            VALUES (?, ?, ?, ?)
        ")->execute([$channel, $channel === 'family' ? $familyId : null, $userId, $body]);

        $msgId = (int)$pdo->lastInsertId();

        // Update online status
        updateChatOnline($pdo, $userId);

        return ['success' => true, 'id' => $msgId];
    } catch (Exception $e) {
        return ['error' => 'Verzenden mislukt.'];
    }
}

// ============================================================
// ONLINE STATUS
// ============================================================
function updateChatOnline(PDO $pdo, int $userId): void {
    try {
        $pdo->prepare("
            INSERT INTO chat_online (user_id, last_seen)
            VALUES (?, NOW())
            ON DUPLICATE KEY UPDATE last_seen = NOW()
        ")->execute([$userId]);
    } catch (Exception $e) {}
}

function getOnlineUsers(PDO $pdo): array {
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.xp, co.last_seen
        FROM chat_online co
        JOIN users u ON u.id = co.user_id
        WHERE co.last_seen > NOW() - INTERVAL ? SECOND
        ORDER BY u.username ASC
    ");
    $stmt->bindValue(1, CHAT_ONLINE_WINDOW, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function countOnlineUsers(PDO $pdo): int {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM chat_online
        WHERE last_seen > NOW() - INTERVAL ? SECOND
    ");
    $stmt->bindValue(1, CHAT_ONLINE_WINDOW, PDO::PARAM_INT);
    $stmt->execute();
    return (int)$stmt->fetchColumn();
}

// ============================================================
// ADMIN: bericht verwijderen
// ============================================================
function deleteChatMessage(PDO $pdo, int $messageId, int $userId, bool $isAdmin): bool {
    $stmt = $pdo->prepare("SELECT user_id FROM chat_messages WHERE id = ? LIMIT 1");
    $stmt->execute([$messageId]);
    $ownerId = (int)$stmt->fetchColumn();

    if (!$ownerId) return false;
    if ($ownerId !== $userId && !$isAdmin) return false;

    $pdo->prepare("UPDATE chat_messages SET is_deleted = 1 WHERE id = ?")->execute([$messageId]);
    return true;
}

// ============================================================
// FORMATTING
// ============================================================
function chatRankColor(int $xp): string {
    if (!function_exists('getRankData') || !function_exists('getRanksArray')) {
        return '#a08d75';
    }
    $rank = getRankData($xp, getRanksArray());
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

function chatTimeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'nu';
    if ($diff < 3600) return floor($diff / 60) . 'm';
    if ($diff < 86400) return date('H:i', strtotime($datetime));
    return date('d M', strtotime($datetime));
}