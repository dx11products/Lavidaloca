<?php
/**
 * Vendetta — Rol-niveaus, bonus & achievements voor heists
 *
 * BELANGRIJK: Dit bestand bevat ALLEEN functies en constanten.
 * GEEN require, GEEN redirect, GEEN header() aanroepen.
 */

// ============================================================
// ROL NIVEAUS
// ============================================================
const ROLE_LEVEL_XP = [
    1 => 0,
    2 => 5,
    3 => 15,
    4 => 40,
    5 => 100,
];

const ROLE_LEVEL_NAMES = [
    1 => ['name' => 'Beginner',     'color' => '#8a8a8a', 'icon' => '⚪'],
    2 => ['name' => 'Ervaren',      'color' => '#58e08c', 'icon' => '🟢'],
    3 => ['name' => 'Professional', 'color' => '#4a9dff', 'icon' => '🔵'],
    4 => ['name' => 'Expert',       'color' => '#b06aff', 'icon' => '🟣'],
    5 => ['name' => 'Meester',      'color' => '#ffb040', 'icon' => '🟠'],
];

const ROLE_LEVEL_BONUS = [
    1 => 0.00,
    2 => 0.05,
    3 => 0.10,
    4 => 0.18,
    5 => 0.30,
];

const ROLE_XP_PER_HEIST_WIN  = 2;
const ROLE_XP_PER_HEIST_LOSS = 1;

// ============================================================
// ROL XP FUNCTIES
// ============================================================
function getUserRoleXp(PDO $pdo, int $userId, string $role): array {
    $stmt = $pdo->prepare("SELECT * FROM user_role_xp WHERE user_id = ? AND role = ? LIMIT 1");
    $stmt->execute([$userId, $role]);
    $row = $stmt->fetch();

    if (!$row) {
        return [
            'user_id'     => $userId,
            'role'        => $role,
            'xp'          => 0,
            'heists_done' => 0,
            'heists_won'  => 0,
        ];
    }
    return $row;
}

function getRoleLevel(int $xp): int {
    $level = 1;
    foreach (ROLE_LEVEL_XP as $lvl => $required) {
        if ($xp >= $required) $level = $lvl;
    }
    return $level;
}

function getUserRoleLevels(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare("SELECT * FROM user_role_xp WHERE user_id = ?");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();

    $result = [];
    foreach (HEIST_ROLES as $key => $roleInfo) {
        $row = null;
        foreach ($rows as $r) {
            if ($r['role'] === $key) { $row = $r; break; }
        }

        $xp = $row ? (int)$row['xp'] : 0;
        $level = getRoleLevel($xp);
        $levelInfo = ROLE_LEVEL_NAMES[$level];
        $bonus = ROLE_LEVEL_BONUS[$level];

        $result[$key] = [
            'role'         => $key,
            'role_name'    => $roleInfo['name'],
            'role_icon'    => $roleInfo['icon'],
            'role_bonus'   => $roleInfo['bonus'],
            'xp'           => $xp,
            'level'        => $level,
            'level_name'   => $levelInfo['name'],
            'level_icon'   => $levelInfo['icon'],
            'level_color'  => $levelInfo['color'],
            'level_bonus'  => $bonus,
            'heists_done'  => $row ? (int)$row['heists_done'] : 0,
            'heists_won'   => $row ? (int)$row['heists_won'] : 0,
        ];
    }

    return $result;
}

function addRoleXp(PDO $pdo, int $userId, string $role, int $xp, bool $won): void {
    $stmt = $pdo->prepare("SELECT id FROM user_role_xp WHERE user_id = ? AND role = ? LIMIT 1");
    $stmt->execute([$userId, $role]);

    if ($stmt->fetch()) {
        $pdo->prepare("
            UPDATE user_role_xp
            SET xp = xp + ?, heists_done = heists_done + 1, heists_won = heists_won + ?
            WHERE user_id = ? AND role = ?
        ")->execute([$xp, $won ? 1 : 0, $userId, $role]);
    } else {
        $pdo->prepare("
            INSERT INTO user_role_xp (user_id, role, xp, heists_done, heists_won)
            VALUES (?, ?, ?, 1, ?)
        ")->execute([$userId, $role, $xp, $won ? 1 : 0]);
    }
}

function getTotalRoleBonus(PDO $pdo, int $userId, string $role): float {
    $roleInfo = HEIST_ROLES[$role] ?? HEIST_ROLES['generiek'];
    $baseBonus = $roleInfo['bonus'];

    $roleXp = getUserRoleXp($pdo, $userId, $role);
    $level = getRoleLevel((int)$roleXp['xp']);
    $levelBonus = ROLE_LEVEL_BONUS[$level];

    return $baseBonus + $levelBonus;
}

// ============================================================
// HEIST SUCCESS + REWARD (V2 — met rol-niveaus)
// ============================================================
function calculateHeistSuccessV2(PDO $pdo, array $heist, array $members): int {
    $baseChance = (int)$heist['success_rate'];

    $extraPlayers = max(0, count($members) - (int)$heist['min_players']);
    $teamBonus = $extraPlayers * 8;

    $roleBonus = 0;
    foreach ($members as $m) {
        $bonus = getTotalRoleBonus($pdo, (int)$m['user_id'], $m['role']);
        $roleBonus += (int)round(($bonus - 1) * 100);
    }
    $roleBonus = (int)round($roleBonus / max(1, count($members)));

    $chance = $baseChance + $teamBonus + $roleBonus;
    return max(10, min(95, $chance));
}

function calculateHeistRewardV2(PDO $pdo, array $heist, array $members): int {
    $baseReward = random_int((int)$heist['base_reward_min'], (int)$heist['base_reward_max']);

    $playerCount = count($members);
    $teamBonus = 1 + (($playerCount - 1) * HEIST_TEAM_BONUS);

    $roleBonus = 0;
    foreach ($members as $m) {
        $roleBonus += getTotalRoleBonus($pdo, (int)$m['user_id'], $m['role']);
    }
    $avgRoleBonus = $roleBonus / max(1, $playerCount);

    return (int)floor($baseReward * $teamBonus * $avgRoleBonus);
}

// ============================================================
// FAMILIE-HEISTS
// ============================================================
function getFamilyHeistLobbies(PDO $pdo, int $familyId, string $countryKey): array {
    $stmt = $pdo->prepare("
        SELECT hl.*, h.name AS heist_name, h.icon, h.max_players, h.min_players,
               h.min_rank, h.duration_minutes, h.base_reward_min, h.base_reward_max,
               u.username AS host_name,
               (SELECT COUNT(*) FROM heist_members WHERE lobby_id = hl.id) AS member_count
        FROM heist_lobbies hl
        JOIN heists h ON h.`key` = hl.heist_key
        JOIN users u ON u.id = hl.host_id
        WHERE hl.status = 'waiting'
          AND hl.family_id = ?
          AND hl.country_key = ?
        ORDER BY hl.started_at DESC
        LIMIT 10
    ");
    $stmt->execute([$familyId, $countryKey]);
    return $stmt->fetchAll();
}

function isFamilyHeist(array $lobby): bool {
    return !empty($lobby['is_family_heist']);
}

function canJoinLobby(PDO $pdo, array $lobby, array $user): array {
    if (empty($lobby['is_family_heist'])) {
        return ['ok' => true];
    }

    $myFamily = getUserFamily($pdo, $user['id']);
    if (!$myFamily) {
        return ['ok' => false, 'error' => 'Deze heist is alleen voor familieleden.'];
    }
    if ((int)$myFamily['id'] !== (int)$lobby['family_id']) {
        return ['ok' => false, 'error' => 'Deze heist is voor een andere familie.'];
    }
    return ['ok' => true];
}