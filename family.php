<?php
/**
 * Vendetta — Familie systeem + Notificaties + Daily Rewards
 * BELANGRIJK: Alleen functies en constanten.
 */

// ============================================================
// INSTELLINGEN
// ============================================================
const FAMILY_CREATE_COST = 5000;
const FAMILY_MAX_MEMBERS = 20;
const FAMILY_NAME_MIN    = 3;
const FAMILY_NAME_MAX    = 30;

// ============================================================
// FAMILIE FUNCTIES
// ============================================================
/**
 * Haal de familie op van een user (of null).
 */
function getUserFamily(PDO $pdo, int $userId): ?array {
    $stmt = $pdo->prepare("
        SELECT f.*, fm.rank AS member_rank
        FROM family_members fm
        JOIN families f ON f.id = fm.family_id
        WHERE fm.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Haal alle leden van een familie op.
 */
function getFamilyMembers(PDO $pdo, int $familyId): array {
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.xp, u.money, u.rank_title,
               fm.rank, fm.joined_at
        FROM family_members fm
        JOIN users u ON u.id = fm.user_id
        WHERE fm.family_id = ?
        ORDER BY
            FIELD(fm.rank, 'baas', 'onderbaas', 'capo', 'lid'),
            fm.joined_at ASC
    ");
    $stmt->execute([$familyId]);
    return $stmt->fetchAll();
}

// ============================================================
// NOTIFICATIES
// ============================================================
/**
 * Aantal ongelezen notificaties.
 */
function unreadNotifications(PDO $pdo, int $userId): int {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Verstuur notificatie naar user.
 */
function notify(PDO $pdo, int $userId, string $message, string $icon = '🔔'): void {
    try {
        $pdo->prepare("INSERT INTO notifications (user_id, message, icon) VALUES (?, ?, ?)")
            ->execute([$userId, $message, $icon]);
    } catch (Exception $e) {}
}

// ============================================================
// DAGELIJKSE BELONING
// ============================================================
/**
 * Dagelijkse beloning ophalen.
 */
function getDailyReward(PDO $pdo, int $userId): ?array {
    try {
        $stmt = $pdo->prepare("SELECT * FROM daily_rewards WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Kan user vandaag zijn dagelijkse beloning ophalen?
 */
function canClaimDaily(?array $daily): bool {
    if (!$daily) return true;
    return $daily['last_claim'] !== date('Y-m-d');
}

/**
 * Geld beloning per streak dag.
 * Curve: €500K → €1M → €1.5M → €2M → €2.5M → €3M → €5M
 */
function dailyRewardAmount(int $streak): int {
    $streak = max(1, min(7, $streak));

    $rewards = [
        1 => 500000,
        2 => 1000000,
        3 => 1500000,
        4 => 2000000,
        5 => 2500000,
        6 => 3000000,
        7 => 5000000,
    ];

    return $rewards[$streak] ?? 500000;
}

/**
 * Diamant beloning per streak dag.
 * Curve: 10 → 25 → 50 → 100 → 200 → 500 → 1750
 */
function dailyDiamondReward(int $streak): int {
    $streak = max(1, min(7, $streak));

    $rewards = [
        1 => 10,
        2 => 25,
        3 => 50,
        4 => 100,
        5 => 200,
        6 => 500,
        7 => 1750,
    ];

    return $rewards[$streak] ?? 10;
}

/**
 * XP beloning per streak dag.
 */
function dailyXpReward(int $streak): int {
    $streak = max(1, min(7, $streak));
    return $streak * 10;
}