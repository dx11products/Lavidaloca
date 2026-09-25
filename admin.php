<?php
/**
 * Vendetta — Admin systeem
 */

// ============================================================
// ADMIN CHECK
// ============================================================
function isAdmin(array $user): bool {
    return !empty($user['is_admin']);
}

function requireAdmin(PDO $pdo): array {
    if (!isLoggedIn()) redirect('login.php');
    $user = currentUser($pdo);
    if (!$user || !isAdmin($user)) {
        redirect('dashboard.php');
    }
    return $user;
}

// ============================================================
// BAN CHECK
// ============================================================
function isBanned(array $user): bool {
    if (empty($user['is_banned'])) return false;
    if (!empty($user['banned_until']) && strtotime($user['banned_until']) < time()) {
        // Ban is verlopen
        return false;
    }
    return true;
}

function banUser(PDO $pdo, int $userId, string $reason, ?int $hours = null): void {
    $until = $hours ? date('Y-m-d H:i:s', time() + ($hours * 3600)) : null;
    $pdo->prepare("
        UPDATE users SET is_banned = 1, ban_reason = ?, banned_until = ?
        WHERE id = ?
    ")->execute([$reason, $until, $userId]);
}

function unbanUser(PDO $pdo, int $userId): void {
    $pdo->prepare("
        UPDATE users SET is_banned = 0, ban_reason = NULL, banned_until = NULL
        WHERE id = ?
    ")->execute([$userId]);
}

// ============================================================
// ADMIN LOG
// ============================================================
function adminLog(PDO $pdo, int $adminId, string $action, ?string $targetType = null, ?int $targetId = null, ?string $details = null): void {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $pdo->prepare("
            INSERT INTO admin_log (admin_id, action, target_type, target_id, details, ip)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([$adminId, $action, $targetType, $targetId, $details, $ip]);
    } catch (Exception $e) {}
}

function getAdminLogs(PDO $pdo, int $limit = 100, ?int $adminId = null): array {
    $sql = "
        SELECT al.*, u.username AS admin_name
        FROM admin_log al
        LEFT JOIN users u ON u.id = al.admin_id
    ";
    $params = [];

    if ($adminId) {
        $sql .= " WHERE al.admin_id = ?";
        $params[] = $adminId;
    }

    $sql .= " ORDER BY al.id DESC LIMIT ?";
    $params[] = $limit;

    $stmt = $pdo->prepare($sql);
    foreach ($params as $i => $p) {
        $stmt->bindValue($i + 1, $p, is_int($p) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    return $stmt->fetchAll();
}

// ============================================================
// SETTINGS
// ============================================================
function getSetting(PDO $pdo, string $key, $default = null) {
    $stmt = $pdo->prepare("SELECT value, type FROM game_settings WHERE `key` = ? LIMIT 1");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    if (!$row) return $default;

    return castSetting($row['value'], $row['type']);
}

function getAllSettings(PDO $pdo, ?string $category = null): array {
    $sql = "SELECT * FROM game_settings";
    $params = [];
    if ($category) {
        $sql .= " WHERE category = ?";
        $params[] = $category;
    }
    $sql .= " ORDER BY category ASC, id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$r) {
        $r['parsed_value'] = castSetting($r['value'], $r['type']);
    }
    return $rows;
}

function castSetting($value, string $type) {
    return match($type) {
        'int'   => (int)$value,
        'float' => (float)$value,
        'bool'  => (bool)(int)$value,
        'json'  => json_decode($value, true),
        default => $value,
    };
}

function updateSetting(PDO $pdo, string $key, $value): void {
    $stmt = $pdo->prepare("SELECT type FROM game_settings WHERE `key` = ? LIMIT 1");
    $stmt->execute([$key]);
    $type = $stmt->fetchColumn();

    if ($type === 'json' && is_array($value)) {
        $value = json_encode($value);
    } elseif ($type === 'bool') {
        $value = $value ? '1' : '0';
    }

    $pdo->prepare("UPDATE game_settings SET value = ? WHERE `key` = ?")
        ->execute([$value, $key]);
}

// ============================================================
// STATISTIEKEN
// ============================================================
function getAdminStats(PDO $pdo): array {
    $stats = [];

    // Users
    $stats['total_users']     = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['active_24h']      = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE last_login > NOW() - INTERVAL 24 HOUR")->fetchColumn();
    $stats['active_7d']       = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE last_login > NOW() - INTERVAL 7 DAY")->fetchColumn();
    $stats['banned']          = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_banned = 1")->fetchColumn();
    $stats['new_today']       = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()")->fetchColumn();

    // Economie
    $stats['total_money']     = (int)$pdo->query("SELECT COALESCE(SUM(money), 0) FROM users")->fetchColumn();
    $stats['total_bank']      = (int)$pdo->query("SELECT COALESCE(SUM(bank_money), 0) FROM users")->fetchColumn();
    $stats['total_btc']       = (float)$pdo->query("SELECT COALESCE(SUM(btc), 0) FROM users")->fetchColumn();
    $stats['total_clicks']    = (int)$pdo->query("SELECT COALESCE(SUM(clicks), 0) FROM users")->fetchColumn();

    // Activiteit
    $stats['crimes_today']    = (int)$pdo->query("SELECT COUNT(*) FROM crime_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn();
    $stats['attacks_today']   = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE DATE(last_attack) = CURDATE()")->fetchColumn();

    // Forum
    $stats['forum_topics']    = (int)$pdo->query("SELECT COUNT(*) FROM forum_topics")->fetchColumn();
    $stats['forum_replies']   = (int)$pdo->query("SELECT COUNT(*) FROM forum_replies WHERE is_deleted = 0")->fetchColumn();

    // Messages
    $stats['pm_messages']     = (int)$pdo->query("SELECT COUNT(*) FROM pm_messages")->fetchColumn();

    // Heists
    $stats['heists_today']    = (int)$pdo->query("SELECT COUNT(*) FROM heist_history WHERE DATE(created_at) = CURDATE()")->fetchColumn();

    // Lootboxes
    $stats['lootboxes_today'] = (int)$pdo->query("SELECT COUNT(*) FROM lootbox_opens WHERE DATE(opened_at) = CURDATE()")->fetchColumn();

    return $stats;
}

// ============================================================
// SPELERSBEHEER
// ============================================================
function searchUsers(PDO $pdo, string $query = '', int $limit = 50, int $offset = 0): array {
    if ($query === '') {
        $stmt = $pdo->prepare("
            SELECT id, username, email, money, btc, clicks, xp, rank_title,
                   is_admin, is_banned, created_at, last_login, health, energy
            FROM users
            ORDER BY id DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    $stmt = $pdo->prepare("
        SELECT id, username, email, money, btc, clicks, xp, rank_title,
               is_admin, is_banned, created_at, last_login, health, energy
        FROM users
        WHERE username LIKE ? OR email LIKE ? OR CAST(id AS CHAR) = ?
        ORDER BY id DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, '%' . $query . '%', PDO::PARAM_STR);
    $stmt->bindValue(2, '%' . $query . '%', PDO::PARAM_STR);
    $stmt->bindValue(3, $query, PDO::PARAM_STR);
    $stmt->bindValue(4, $limit, PDO::PARAM_INT);
    $stmt->bindValue(5, $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function countUsers(PDO $pdo, string $query = ''): int {
    if ($query === '') {
        return (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username LIKE ? OR email LIKE ?");
    $stmt->execute(['%' . $query . '%', '%' . $query . '%']);
    return (int)$stmt->fetchColumn();
}

// ============================================================
// ECONOMIE ACTIES
// ============================================================
function adminAdjustMoney(PDO $pdo, int $userId, int $amount, string $reason): void {
    $pdo->prepare("UPDATE users SET money = GREATEST(0, money + ?) WHERE id = ?")
        ->execute([$amount, $userId]);
    if (function_exists('logActivity')) {
        $sign = $amount >= 0 ? '+' : '';
        logActivity($pdo, $userId, "💼 Admin: {$sign}€" . number_format($amount, 0, ',', '.') . " — {$reason}");
    }
}

function adminAdjustBtc(PDO $pdo, int $userId, float $amount, string $reason): void {
    $pdo->prepare("UPDATE users SET btc = GREATEST(0, btc + ?) WHERE id = ?")
        ->execute([$amount, $userId]);
    if (function_exists('logActivity')) {
        $sign = $amount >= 0 ? '+' : '';
        logActivity($pdo, $userId, "💼 Admin: {$sign}₿" . $amount . " — {$reason}");
    }
}

function adminAdjustClicks(PDO $pdo, int $userId, int $amount, string $reason): void {
    $pdo->prepare("UPDATE users SET clicks = GREATEST(0, clicks + ?) WHERE id = ?")
        ->execute([$amount, $userId]);
    if (function_exists('logActivity')) {
        $sign = $amount >= 0 ? '+' : '';
        logActivity($pdo, $userId, "💼 Admin: {$sign}{$amount} clicks — {$reason}");
    }
}

function adminAdjustXp(PDO $pdo, int $userId, int $amount, string $reason): void {
    $pdo->prepare("UPDATE users SET xp = GREATEST(0, xp + ?) WHERE id = ?")
        ->execute([$amount, $userId]);

    // Update rank
    $stmt = $pdo->prepare("SELECT xp FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $xp = (int)$stmt->fetchColumn();
    $rank = getRankData($xp, getRanksArray());
    $pdo->prepare("UPDATE users SET rank_title = ? WHERE id = ?")
        ->execute([$rank['name'], $userId]);
}

// ============================================================
// BROADCAST
// ============================================================
function broadcastMessage(PDO $pdo, string $message, string $icon = '📢'): int {
    $stmt = $pdo->query("SELECT id FROM users WHERE is_banned = 0");
    $users = $stmt->fetchAll();
    $count = 0;

    foreach ($users as $u) {
        if (function_exists('notify')) {
            notify($pdo, (int)$u['id'], $message, $icon);
            $count++;
        }
    }

    return $count;
}

// ============================================================
// MASS ACTIES
// ============================================================
function massGiveMoney(PDO $pdo, int $amount, int $minRank = 0): int {
    if ($minRank > 0) {
        $stmt = $pdo->prepare("UPDATE users SET money = money + ? WHERE xp >= ?");
        $stmt->execute([$amount, $minRank]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET money = money + ?");
        $stmt->execute([$amount]);
    }
    return $stmt->rowCount();
}

function massGiveBtc(PDO $pdo, float $amount): int {
    $stmt = $pdo->prepare("UPDATE users SET btc = btc + ?, total_btc_earned = total_btc_earned + ?");
    $stmt->execute([$amount, $amount]);
    return $stmt->rowCount();
}

function massGiveClicks(PDO $pdo, int $amount): int {
    $stmt = $pdo->prepare("UPDATE users SET clicks = clicks + ?, total_clicks_earned = total_clicks_earned + ?");
    $stmt->execute([$amount, $amount]);
    return $stmt->rowCount();
}

function massFullEnergy(PDO $pdo): int {
    $stmt = $pdo->prepare("UPDATE users SET energy = max_energy, energy_updated = NOW()");
    $stmt->execute();
    return $stmt->rowCount();
}

function massFullHealth(PDO $pdo): int {
    $stmt = $pdo->prepare("UPDATE users SET health = max_health, hospital_until = NULL");
    $stmt->execute();
    return $stmt->rowCount();
}

function massUnbanAll(PDO $pdo): int {
    $stmt = $pdo->prepare("UPDATE users SET is_banned = 0, ban_reason = NULL, banned_until = NULL WHERE is_banned = 1");
    $stmt->execute();
    return $stmt->rowCount();
}

// ============================================================
// FORUM MODERATIE
// ============================================================
function adminDeleteTopic(PDO $pdo, int $topicId): bool {
    $pdo->prepare("DELETE FROM forum_replies WHERE topic_id = ?")->execute([$topicId]);
    $pdo->prepare("DELETE FROM forum_topics WHERE id = ?")->execute([$topicId]);
    return true;
}

function adminDeleteReply(PDO $pdo, int $replyId): bool {
    $stmt = $pdo->prepare("SELECT topic_id FROM forum_replies WHERE id = ?");
    $stmt->execute([$replyId]);
    $topicId = (int)$stmt->fetchColumn();

    $pdo->prepare("DELETE FROM forum_replies WHERE id = ?")->execute([$replyId]);
    if ($topicId) {
        $pdo->prepare("UPDATE forum_topics SET reply_count = GREATEST(0, reply_count - 1) WHERE id = ?")
            ->execute([$topicId]);
    }
    return true;
}

function adminPinTopic(PDO $pdo, int $topicId, bool $pin): void {
    $pdo->prepare("UPDATE forum_topics SET is_pinned = ? WHERE id = ?")
        ->execute([$pin ? 1 : 0, $topicId]);
}

function adminLockTopic(PDO $pdo, int $topicId, bool $lock): void {
    $pdo->prepare("UPDATE forum_topics SET is_locked = ? WHERE id = ?")
        ->execute([$lock ? 1 : 0, $topicId]);
}