<?php
/**
 * Vendetta — Heists (timer-systeem, geen energie)
 * BELANGRIJK: Alleen functies en constanten.
 */

const HEIST_ROLES = [
    'generiek'        => ['name' => 'Algemeen',     'icon' => '👤', 'bonus' => 1.00, 'desc' => 'Doet mee aan de overval.'],
    'breker'          => ['name' => 'Breker',       'icon' => '🔨', 'bonus' => 1.15, 'desc' => 'Forceert deuren en kluizen.'],
    'chauffeur'       => ['name' => 'Chauffeur',    'icon' => '🚗', 'bonus' => 1.20, 'desc' => 'Zorgt voor snelle ontsnapping.'],
    'scherpschutter'  => ['name' => 'Sniper',       'icon' => '🎯', 'bonus' => 1.25, 'desc' => 'Dekt het team vanaf afstand.'],
    'hacker'          => ['name' => 'Hacker',       'icon' => '💻', 'bonus' => 1.30, 'desc' => 'Schakelt beveiliging uit.'],
    'meesterbrein'    => ['name' => 'Meesterbrein', 'icon' => '🧠', 'bonus' => 1.40, 'desc' => 'Leidt de operatie en plant alles.'],
];

const HEIST_LOBBY_TIMEOUT            = 15;
const HEIST_TEAM_BONUS               = 0.05;
const HEIST_FAIL_XP                  = 20;
const HEIST_FAIL_FINE_PER            = 0.05;
const HEIST_COMPLETION_COOLDOWN_MIN  = 5;
const HEIST_LEVEL_BONUS              = 0.55;

// ============================================================
// OPHAALFUNCTIES
// ============================================================
function getAllHeists(PDO $pdo): array {
    $stmt = $pdo->query("SELECT * FROM heists ORDER BY tier ASC, min_rank ASC");
    return $stmt->fetchAll();
}

function getHeist(PDO $pdo, string $key): ?array {
    $stmt = $pdo->prepare("SELECT * FROM heists WHERE `key` = ? LIMIT 1");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function getAvailableHeists(PDO $pdo, int $rankLevel): array {
    $stmt = $pdo->prepare("SELECT * FROM heists WHERE min_rank <= ? ORDER BY tier ASC, min_rank ASC");
    $stmt->execute([$rankLevel]);
    return $stmt->fetchAll();
}

// ============================================================
// LEVEL & COOLDOWN
// ============================================================
function getHeistLevelMultiplier(int $rankLevel): float {
    return 1 + (($rankLevel - 1) * HEIST_LEVEL_BONUS);
}

function getHeistCooldown(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare("SELECT last_heist_at FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $last = $stmt->fetchColumn();

    if (!$last) return ['ok' => true, 'wait' => 0];

    $elapsed = time() - strtotime($last);
    $cooldown = HEIST_COMPLETION_COOLDOWN_MIN * 60;
    $wait = $cooldown - $elapsed;

    if ($wait <= 0) return ['ok' => true, 'wait' => 0];
    return ['ok' => false, 'wait' => $wait];
}

function setHeistCooldown(PDO $pdo, int $userId): void {
    $pdo->prepare("UPDATE users SET last_heist_at = NOW() WHERE id = ?")->execute([$userId]);
}

// ============================================================
// LOBBY
// ============================================================
function getOpenHeistLobbies(PDO $pdo, string $countryKey): array {
    $stmt = $pdo->prepare("
        SELECT hl.*, h.name AS heist_name, h.icon, h.max_players, h.min_players,
               h.min_rank, h.duration_minutes, h.base_reward_min, h.base_reward_max,
               u.username AS host_name,
               (SELECT COUNT(*) FROM heist_members WHERE lobby_id = hl.id) AS member_count
        FROM heist_lobbies hl
        JOIN heists h ON h.`key` = hl.heist_key
        JOIN users u ON u.id = hl.host_id
        WHERE hl.status = 'waiting' AND hl.country_key = ?
        ORDER BY hl.started_at DESC
        LIMIT 20
    ");
    $stmt->execute([$countryKey]);
    return $stmt->fetchAll();
}

function getHeistLobby(PDO $pdo, int $lobbyId): ?array {
    $stmt = $pdo->prepare("
        SELECT hl.*, h.name AS heist_name, h.description AS heist_description, h.icon,
               h.max_players, h.min_players, h.min_rank,
               h.base_reward_min, h.base_reward_max, h.xp_reward, h.success_rate,
               h.tier, h.risk_level, h.duration_minutes,
               u.username AS host_name
        FROM heist_lobbies hl
        JOIN heists h ON h.`key` = hl.heist_key
        JOIN users u ON u.id = hl.host_id
        WHERE hl.id = ? LIMIT 1
    ");
    $stmt->execute([$lobbyId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function getHeistMembers(PDO $pdo, int $lobbyId): array {
    $stmt = $pdo->prepare("
        SELECT hm.*, u.username, u.xp, u.rank_title
        FROM heist_members hm
        JOIN users u ON u.id = hm.user_id
        WHERE hm.lobby_id = ?
        ORDER BY hm.joined_at ASC
    ");
    $stmt->execute([$lobbyId]);
    return $stmt->fetchAll();
}

function isHeistMember(PDO $pdo, int $lobbyId, int $userId): bool {
    $stmt = $pdo->prepare("SELECT id FROM heist_members WHERE lobby_id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$lobbyId, $userId]);
    return (bool)$stmt->fetch();
}

function getUserActiveLobby(PDO $pdo, int $userId): ?array {
    $stmt = $pdo->prepare("
        SELECT hl.*
        FROM heist_lobbies hl
        JOIN heist_members hm ON hm.lobby_id = hl.id
        WHERE hm.user_id = ?
          AND hl.status IN ('waiting', 'ready', 'in_progress')
        ORDER BY hl.id DESC LIMIT 1
    ");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

// ============================================================
// UITVOERING
// ============================================================
function calculateHeistSuccess(array $heist, array $members): int {
    $baseChance = (int)$heist['success_rate'];
    $extraPlayers = max(0, count($members) - (int)$heist['min_players']);
    $teamBonus = $extraPlayers * 8;

    $roleBonus = 0;
    foreach ($members as $m) {
        $role = HEIST_ROLES[$m['role']] ?? HEIST_ROLES['generiek'];
        $roleBonus += (int)round(($role['bonus'] - 1) * 100);
    }
    $roleBonus = (int)round($roleBonus / max(1, count($members)));

    $chance = $baseChance + $teamBonus + $roleBonus;
    return max(10, min(95, $chance));
}

function calculateHeistReward(array $heist, array $members): int {
    $baseReward = random_int((int)$heist['base_reward_min'], (int)$heist['base_reward_max']);
    $playerCount = count($members);
    $teamBonus = 1 + (($playerCount - 1) * HEIST_TEAM_BONUS);

    $roleBonus = 0;
    foreach ($members as $m) {
        $role = HEIST_ROLES[$m['role']] ?? HEIST_ROLES['generiek'];
        $roleBonus += $role['bonus'];
    }
    $avgRoleBonus = $roleBonus / max(1, $playerCount);

    return (int)floor($baseReward * $teamBonus * $avgRoleBonus);
}

function distributeReward(int $totalReward, array $members): array {
    $count = count($members);
    if ($count === 0) return [];

    $share = (int)floor($totalReward / $count);
    $remainder = $totalReward - ($share * $count);

    $distribution = [];
    foreach ($members as $i => $m) {
        $extra = $i === 0 ? $remainder : 0;
        $distribution[$m['user_id']] = $share + $extra;
    }
    return $distribution;
}