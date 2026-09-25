<?php
/**
 * Vendetta — Privéberichten (PM)
 */

const PM_BODY_MIN     = 1;
const PM_BODY_MAX     = 2000;
const PM_COOLDOWN_SEC = 3;      // Seconden tussen berichten
const PM_MESSAGES_PER_PAGE = 50;

/**
 * Normaliseer user paar — altijd (kleinste, grootste).
 */
function pmPair(int $a, int $b): array {
    return $a < $b ? [$a, $b] : [$b, $a];
}

/**
 * Zoek of maak een conversatie tussen twee users.
 */
function getOrCreateConversation(PDO $pdo, int $userA, int $userB): int {
    [$a, $b] = pmPair($userA, $userB);

    $stmt = $pdo->prepare("SELECT id FROM pm_conversations WHERE user_a = ? AND user_b = ? LIMIT 1");
    $stmt->execute([$a, $b]);
    $id = $stmt->fetchColumn();

    if ($id) return (int)$id;

    $pdo->prepare("INSERT INTO pm_conversations (user_a, user_b) VALUES (?, ?)")
        ->execute([$a, $b]);

    return (int)$pdo->lastInsertId();
}

/**
 * Haal conversatie op.
 */
function getConversation(PDO $pdo, int $conversationId): ?array {
    $stmt = $pdo->prepare("SELECT * FROM pm_conversations WHERE id = ? LIMIT 1");
    $stmt->execute([$conversationId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Bepaal andere user in de conversatie.
 */
function getOtherUser(array $conversation, int $myId): int {
    return (int)$conversation['user_a'] === $myId
        ? (int)$conversation['user_b']
        : (int)$conversation['user_a'];
}

/**
 * Alle conversaties van een user (met laatste bericht + ongelezen).
 */
function getUserConversations(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare("
        SELECT c.*,
               CASE WHEN c.user_a = ? THEN c.user_b ELSE c.user_a END AS other_id,
               (SELECT COUNT(*) FROM pm_messages m
                WHERE m.conversation_id = c.id
                  AND m.receiver_id = ?
                  AND m.is_read = 0
                  AND m.is_deleted_by_receiver = 0) AS unread
        FROM pm_conversations c
        WHERE c.user_a = ? OR c.user_b = ?
        ORDER BY c.last_message_at DESC
    ");
    $stmt->execute([$userId, $userId, $userId, $userId]);
    $rows = $stmt->fetchAll();

    // Voeg user info toe
    foreach ($rows as &$row) {
        $stmt2 = $pdo->prepare("SELECT id, username, xp, rank_title FROM users WHERE id = ? LIMIT 1");
        $stmt2->execute([(int)$row['other_id']]);
        $u = $stmt2->fetch();
        $row['other_username']  = $u['username'] ?? 'Onbekend';
        $row['other_xp']        = (int)($u['xp'] ?? 0);
        $row['other_rank']      = $u['rank_title'] ?? '';

        // Laatste bericht
        $stmt2 = $pdo->prepare("
            SELECT body, sender_id, created_at
            FROM pm_messages
            WHERE conversation_id = ?
              AND NOT (is_deleted_by_sender = 1 AND is_deleted_by_receiver = 1)
            ORDER BY id DESC LIMIT 1
        ");
        $stmt2->execute([(int)$row['id']]);
        $last = $stmt2->fetch();
        $row['last_body']      = $last['body'] ?? '';
        $row['last_sender_id'] = (int)($last['sender_id'] ?? 0);
        $row['last_at']        = $last['created_at'] ?? $row['last_message_at'];
    }

    return $rows;
}

/**
 * Aantal ongelezen berichten totaal.
 */
function countUnreadMessages(PDO $pdo, int $userId): int {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM pm_messages
        WHERE receiver_id = ? AND is_read = 0 AND is_deleted_by_receiver = 0
    ");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

/**
 * Berichten in een conversatie.
 */
function getConversationMessages(PDO $pdo, int $conversationId, int $userId, int $limit = 50, int $beforeId = 0): array {
    if ($beforeId > 0) {
        $stmt = $pdo->prepare("
            SELECT m.*, u.username AS sender_name
            FROM pm_messages m
            JOIN users u ON u.id = m.sender_id
            WHERE m.conversation_id = ?
              AND m.id < ?
              AND NOT (m.sender_id = ? AND m.is_deleted_by_sender = 1)
              AND NOT (m.receiver_id = ? AND m.is_deleted_by_receiver = 1)
            ORDER BY m.id DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $conversationId, PDO::PARAM_INT);
        $stmt->bindValue(2, $beforeId, PDO::PARAM_INT);
        $stmt->bindValue(3, $userId, PDO::PARAM_INT);
        $stmt->bindValue(4, $userId, PDO::PARAM_INT);
        $stmt->bindValue(5, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = array_reverse($stmt->fetchAll());
        return $rows;
    }

    $stmt = $pdo->prepare("
        SELECT m.*, u.username AS sender_name
        FROM pm_messages m
        JOIN users u ON u.id = m.sender_id
        WHERE m.conversation_id = ?
          AND NOT (m.sender_id = ? AND m.is_deleted_by_sender = 1)
          AND NOT (m.receiver_id = ? AND m.is_deleted_by_receiver = 1)
        ORDER BY m.id DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, $conversationId, PDO::PARAM_INT);
    $stmt->bindValue(2, $userId, PDO::PARAM_INT);
    $stmt->bindValue(3, $userId, PDO::PARAM_INT);
    $stmt->bindValue(4, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = array_reverse($stmt->fetchAll());
    return $rows;
}

/**
 * Nieuwe berichten sinds X id (voor live polling).
 */
function getNewMessages(PDO $pdo, int $conversationId, int $userId, int $afterId): array {
    $stmt = $pdo->prepare("
        SELECT m.*, u.username AS sender_name
        FROM pm_messages m
        JOIN users u ON u.id = m.sender_id
        WHERE m.conversation_id = ?
          AND m.id > ?
          AND NOT (m.sender_id = ? AND m.is_deleted_by_sender = 1)
          AND NOT (m.receiver_id = ? AND m.is_deleted_by_receiver = 1)
        ORDER BY m.id ASC
    ");
    $stmt->execute([$conversationId, $afterId, $userId, $userId]);
    return $stmt->fetchAll();
}

/**
 * Markeer alle berichten in een gesprek als gelezen (die naar jou gestuurd zijn).
 */
function markConversationRead(PDO $pdo, int $conversationId, int $userId): void {
    $pdo->prepare("
        UPDATE pm_messages
        SET is_read = 1
        WHERE conversation_id = ? AND receiver_id = ? AND is_read = 0
    ")->execute([$conversationId, $userId]);
}

/**
 * Is user geblokkeerd door andere user?
 */
function isBlocked(PDO $pdo, int $userId, int $targetId): bool {
    $stmt = $pdo->prepare("SELECT id FROM pm_blocks WHERE user_id = ? AND blocked_id = ? LIMIT 1");
    $stmt->execute([$userId, $targetId]);
    if ($stmt->fetch()) return true;

    // Of omgekeerd?
    $stmt = $pdo->prepare("SELECT id FROM pm_blocks WHERE user_id = ? AND blocked_id = ? LIMIT 1");
    $stmt->execute([$targetId, $userId]);
    return (bool)$stmt->fetch();
}

/**
 * Blokkeer of deblokkeer.
 */
function toggleBlock(PDO $pdo, int $userId, int $targetId): string {
    $stmt = $pdo->prepare("SELECT id FROM pm_blocks WHERE user_id = ? AND blocked_id = ? LIMIT 1");
    $stmt->execute([$userId, $targetId]);

    if ($stmt->fetch()) {
        $pdo->prepare("DELETE FROM pm_blocks WHERE user_id = ? AND blocked_id = ?")
            ->execute([$userId, $targetId]);
        return 'unblocked';
    } else {
        $pdo->prepare("INSERT INTO pm_blocks (user_id, blocked_id) VALUES (?, ?)")
            ->execute([$userId, $targetId]);
        return 'blocked';
    }
}

/**
 * Laatste bericht tijd van user (voor cooldown).
 */
function getLastMessageTime(PDO $pdo, int $userId): int {
    $stmt = $pdo->prepare("
        SELECT UNIX_TIMESTAMP(created_at) FROM pm_messages
        WHERE sender_id = ?
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

/**
 * Verstuur een bericht.
 */
function sendPrivateMessage(PDO $pdo, int $senderId, int $receiverId, string $body): array {
    $body = trim($body);

    if ($senderId === $receiverId) {
        return ['error' => 'Je kunt jezelf geen bericht sturen.'];
    }
    if (mb_strlen($body) < PM_BODY_MIN || mb_strlen($body) > PM_BODY_MAX) {
        return ['error' => 'Bericht moet tussen ' . PM_BODY_MIN . ' en ' . PM_BODY_MAX . ' tekens zijn.'];
    }

    // Cooldown
    $last = getLastMessageTime($pdo, $senderId);
    if ($last > 0 && (time() - $last) < PM_COOLDOWN_SEC) {
        return ['error' => 'Wacht even voor je weer stuurt.'];
    }

    // Block check
    if (isBlocked($pdo, $senderId, $receiverId)) {
        return ['error' => 'Je kunt deze speler geen bericht sturen.'];
    }

    // Bestaat receiver?
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$receiverId]);
    if (!$stmt->fetch()) {
        return ['error' => 'Speler niet gevonden.'];
    }

    $conversationId = getOrCreateConversation($pdo, $senderId, $receiverId);

    $pdo->beginTransaction();
    try {
        $pdo->prepare("
            INSERT INTO pm_messages (conversation_id, sender_id, receiver_id, body)
            VALUES (?, ?, ?, ?)
        ")->execute([$conversationId, $senderId, $receiverId, $body]);

        $msgId = (int)$pdo->lastInsertId();

        $pdo->prepare("
            UPDATE pm_conversations
            SET last_message_at = NOW(), last_message_by = ?
            WHERE id = ?
        ")->execute([$senderId, $conversationId]);

        // Notify receiver
        if (function_exists('notify')) {
            $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$senderId]);
            $senderName = $stmt->fetchColumn();
            notify($pdo, $receiverId, "💌 Nieuw privébericht van {$senderName}", '💌');
        }

        $pdo->commit();

        return [
            'success'         => true,
            'message_id'      => $msgId,
            'conversation_id' => $conversationId,
        ];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['error' => 'Verzenden mislukt.'];
    }
}

/**
 * Zoek spelers op naam.
 */
function searchPlayers(PDO $pdo, string $query, int $excludeId, int $limit = 20): array {
    $query = trim($query);
    if ($query === '') return [];

    $stmt = $pdo->prepare("
        SELECT id, username, xp, rank_title
        FROM users
        WHERE id != ?
          AND username LIKE ?
        ORDER BY xp DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, $excludeId, PDO::PARAM_INT);
    $stmt->bindValue(2, '%' . $query . '%', PDO::PARAM_STR);
    $stmt->bindValue(3, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Delete een bericht voor user (soft delete).
 */
function deleteMessageForUser(PDO $pdo, int $messageId, int $userId): bool {
    $stmt = $pdo->prepare("SELECT * FROM pm_messages WHERE id = ? LIMIT 1");
    $stmt->execute([$messageId]);
    $msg = $stmt->fetch();
    if (!$msg) return false;

    if ((int)$msg['sender_id'] === $userId) {
        $pdo->prepare("UPDATE pm_messages SET is_deleted_by_sender = 1 WHERE id = ?")->execute([$messageId]);
        return true;
    }
    if ((int)$msg['receiver_id'] === $userId) {
        $pdo->prepare("UPDATE pm_messages SET is_deleted_by_receiver = 1 WHERE id = ?")->execute([$messageId]);
        return true;
    }
    return false;
}

/**
 * Verwijder hele conversatie voor user.
 */
function deleteConversationForUser(PDO $pdo, int $conversationId, int $userId): bool {
    $conv = getConversation($pdo, $conversationId);
    if (!$conv) return false;

    // Markeer alle berichten als verwijderd voor deze user
    $pdo->prepare("UPDATE pm_messages SET is_deleted_by_sender = 1 WHERE conversation_id = ? AND sender_id = ?")
        ->execute([$conversationId, $userId]);
    $pdo->prepare("UPDATE pm_messages SET is_deleted_by_receiver = 1 WHERE conversation_id = ? AND receiver_id = ?")
        ->execute([$conversationId, $userId]);

    // Als beide partijen alles verwijderd hebben, wis conversatie
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM pm_messages
        WHERE conversation_id = ?
          AND NOT (is_deleted_by_sender = 1 AND is_deleted_by_receiver = 1)
    ");
    $stmt->execute([$conversationId]);
    if ((int)$stmt->fetchColumn() === 0) {
        $pdo->prepare("DELETE FROM pm_messages WHERE conversation_id = ?")->execute([$conversationId]);
        $pdo->prepare("DELETE FROM pm_conversations WHERE id = ?")->execute([$conversationId]);
    }

    return true;
}

/**
 * Tijd formatting.
 */
function pmTimeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'zojuist';
    if ($diff < 3600) return floor($diff / 60) . 'm';
    if ($diff < 86400) return floor($diff / 3600) . 'u';
    if ($diff < 604800) return floor($diff / 86400) . 'd';
    return date('d M', strtotime($datetime));
}

/**
 * Rank-kleur.
 */
function pmRankColor(int $xp): string {
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