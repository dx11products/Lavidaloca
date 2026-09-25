<?php
/**
 * Vendetta — Crimes (timer-systeem, geen energie)
 * Cooldown ALLEEN bij een boete (gepakt worden of mislukken)
 * Cooldowns zijn in SECONDEN
 */

$CRIMES_V2 = [
    // ===== TIER 1 — 15 sec =====
    'zakkenrollen' => [
        'name'         => 'Zakkenrollen',
        'desc'         => 'Steel een portemonnee van een toerist op het strand.',
        'tier'         => 1,
        'min_rank'     => 1,
        'min_reward'   => 500,
        'max_reward'   => 1500,
        'xp_reward'    => 5,
        'success_rate' => 90,
        'cooldown'     => 15,
        'busted_risk'  => 5,
        'fine_min'     => 100,
        'fine_max'     => 500,
    ],
    'fietsen_jatten' => [
        'name'         => 'Fietsen jatten',
        'desc'         => 'Jat een dure fiets uit een studentenstad.',
        'tier'         => 1,
        'min_rank'     => 1,
        'min_reward'   => 800,
        'max_reward'   => 2200,
        'xp_reward'    => 7,
        'success_rate' => 85,
        'cooldown'     => 15,
        'busted_risk'  => 6,
        'fine_min'     => 200,
        'fine_max'     => 700,
    ],

    // ===== TIER 2 — 30 sec =====
    'winkeldiefstal' => [
        'name'         => 'Winkeldiefstal',
        'desc'         => 'Jat wat spullen uit een drukke supermarkt.',
        'tier'         => 2,
        'min_rank'     => 2,
        'min_reward'   => 2000,
        'max_reward'   => 5000,
        'xp_reward'    => 12,
        'success_rate' => 80,
        'cooldown'     => 30,
        'busted_risk'  => 8,
        'fine_min'     => 500,
        'fine_max'     => 1500,
    ],
    'inbraak_huis' => [
        'name'         => 'Inbraak in huis',
        'desc'         => 'Breek in bij een rijtjeshuis in een rijke buurt.',
        'tier'         => 2,
        'min_rank'     => 3,
        'min_reward'   => 4000,
        'max_reward'   => 9000,
        'xp_reward'    => 18,
        'success_rate' => 72,
        'cooldown'     => 30,
        'busted_risk'  => 12,
        'fine_min'     => 1000,
        'fine_max'     => 3000,
    ],

    // ===== TIER 3 — 45 sec =====
    'autodiefstal' => [
        'name'         => 'Autodiefstal',
        'desc'         => 'Kraak een auto open in de parkeergarage.',
        'tier'         => 3,
        'min_rank'     => 4,
        'min_reward'   => 8000,
        'max_reward'   => 18000,
        'xp_reward'    => 28,
        'success_rate' => 68,
        'cooldown'     => 45,
        'busted_risk'  => 15,
        'fine_min'     => 2000,
        'fine_max'     => 6000,
    ],
    'juwelier_kraken' => [
        'name'         => 'Juwelier kraken',
        'desc'         => 'Breek in bij een juwelier in het centrum.',
        'tier'         => 3,
        'min_rank'     => 5,
        'min_reward'   => 15000,
        'max_reward'   => 35000,
        'xp_reward'    => 45,
        'success_rate' => 60,
        'cooldown'     => 45,
        'busted_risk'  => 20,
        'fine_min'     => 4000,
        'fine_max'     => 10000,
    ],
    'drugs_dealen' => [
        'name'         => 'Drugs dealen',
        'desc'         => 'Verkoop wat wiet aan een stel toeristen in de club.',
        'tier'         => 3,
        'min_rank'     => 4,
        'min_reward'   => 10000,
        'max_reward'   => 22000,
        'xp_reward'    => 32,
        'success_rate' => 70,
        'cooldown'     => 45,
        'busted_risk'  => 18,
        'fine_min'     => 2500,
        'fine_max'     => 8000,
    ],

    // ===== TIER 4 — 60 sec =====
    'overval_tankstation' => [
        'name'         => 'Overval tankstation',
        'desc'         => 'Bewapende overval op een tankstation aan de snelweg.',
        'tier'         => 4,
        'min_rank'     => 6,
        'min_reward'   => 40000,
        'max_reward'   => 80000,
        'xp_reward'    => 80,
        'success_rate' => 55,
        'cooldown'     => 60,
        'busted_risk'  => 25,
        'fine_min'     => 8000,
        'fine_max'     => 20000,
    ],
    'geldtransport' => [
        'name'         => 'Geldtransport overval',
        'desc'         => 'Overval een geldtransport met een vals wegblokkade.',
        'tier'         => 4,
        'min_rank'     => 7,
        'min_reward'   => 70000,
        'max_reward'   => 140000,
        'xp_reward'    => 110,
        'success_rate' => 48,
        'cooldown'     => 60,
        'busted_risk'  => 30,
        'fine_min'     => 12000,
        'fine_max'     => 30000,
    ],
    'kunst_diefstal' => [
        'name'         => 'Kunstdiefstal',
        'desc'         => 'Steel een duur schilderij uit een museum.',
        'tier'         => 4,
        'min_rank'     => 6,
        'min_reward'   => 60000,
        'max_reward'   => 120000,
        'xp_reward'    => 100,
        'success_rate' => 50,
        'cooldown'     => 60,
        'busted_risk'  => 28,
        'fine_min'     => 10000,
        'fine_max'     => 25000,
    ],

    // ===== TIER 5 — 90 sec =====
    'bankoverval' => [
        'name'         => 'Bankoverval',
        'desc'         => 'De grote klapper — een bank op het industriegebied.',
        'tier'         => 5,
        'min_rank'     => 8,
        'min_reward'   => 180000,
        'max_reward'   => 350000,
        'xp_reward'    => 250,
        'success_rate' => 38,
        'cooldown'     => 90,
        'busted_risk'  => 40,
        'fine_min'     => 30000,
        'fine_max'     => 80000,
    ],
    'diamant_roof' => [
        'name'         => 'Diamantroof',
        'desc'         => 'Een gewaagde roof op een diamantbeurs in Antwerpen.',
        'tier'         => 5,
        'min_rank'     => 9,
        'min_reward'   => 350000,
        'max_reward'   => 700000,
        'xp_reward'    => 400,
        'success_rate' => 30,
        'cooldown'     => 90,
        'busted_risk'  => 50,
        'fine_min'     => 60000,
        'fine_max'     => 150000,
    ],
    'gouden_kas' => [
        'name'         => 'Gouden kluis kraken',
        'desc'         => 'Kraak een kluis in een Zwitserse bank.',
        'tier'         => 5,
        'min_rank'     => 10,
        'min_reward'   => 600000,
        'max_reward'   => 1200000,
        'xp_reward'    => 650,
        'success_rate' => 25,
        'cooldown'     => 90,
        'busted_risk'  => 55,
        'fine_min'     => 100000,
        'fine_max'     => 250000,
    ],
];

// ============================================================
// REWARD SCALING
// ============================================================
const CRIME_LEVEL_BONUS = 0.45;

function getLevelMultiplier(int $rankLevel): float {
    return 1 + (($rankLevel - 1) * CRIME_LEVEL_BONUS);
}

function getEffectiveReward(array $crime, int $rankLevel): array {
    $mult = getLevelMultiplier($rankLevel);
    return [
        'min'        => (int)floor($crime['min_reward'] * $mult),
        'max'        => (int)floor($crime['max_reward'] * $mult),
        'multiplier' => $mult,
    ];
}

// ============================================================
// CHAINS
// ============================================================
const CHAIN_BONUSES = [
    5  => ['multiplier' => 1.10, 'name' => 'Warm'],
    10 => ['multiplier' => 1.25, 'name' => 'Heet'],
    20 => ['multiplier' => 1.50, 'name' => 'Vurig'],
    50 => ['multiplier' => 2.00, 'name' => 'Legendarisch'],
];

const CHAIN_TIMEOUT_MINUTES = 15;

function getUserChain(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare("SELECT * FROM user_chains WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();

    if (!$row) {
        $pdo->prepare("INSERT INTO user_chains (user_id) VALUES (?)")->execute([$userId]);
        return ['user_id' => $userId, 'current_chain' => 0, 'best_chain' => 0, 'last_crime_at' => null, 'total_chains_completed' => 0];
    }

    if ($row['last_crime_at'] && $row['current_chain'] > 0) {
        $elapsed = (time() - strtotime($row['last_crime_at'])) / 60;
        if ($elapsed > CHAIN_TIMEOUT_MINUTES) {
            $pdo->prepare("UPDATE user_chains SET current_chain = 0, last_crime_at = NULL WHERE user_id = ?")->execute([$userId]);
            $row['current_chain'] = 0;
            $row['last_crime_at'] = null;
        }
    }
    return $row;
}

function getChainMultiplier(int $chain): float {
    $multiplier = 1.0;
    foreach (CHAIN_BONUSES as $threshold => $bonus) {
        if ($chain >= $threshold) $multiplier = $bonus['multiplier'];
    }
    return $multiplier;
}

function getChainLabel(int $chain): ?string {
    $label = null;
    foreach (CHAIN_BONUSES as $threshold => $bonus) {
        if ($chain >= $threshold) $label = $bonus['name'];
    }
    return $label;
}

function getNextChainMilestone(int $chain): ?int {
    foreach (array_keys(CHAIN_BONUSES) as $threshold) {
        if ($chain < $threshold) return $threshold;
    }
    return null;
}

function updateChain(PDO $pdo, int $userId, bool $success): void {
    $chain = getUserChain($pdo, $userId);
    if ($success) {
        $newChain = (int)$chain['current_chain'] + 1;
        $bestChain = max((int)$chain['best_chain'], $newChain);
        $pdo->prepare("UPDATE user_chains SET current_chain = ?, best_chain = ?, last_crime_at = NOW() WHERE user_id = ?")
            ->execute([$newChain, $bestChain, $userId]);
    } else {
        $pdo->prepare("UPDATE user_chains SET current_chain = 0, last_crime_at = NULL WHERE user_id = ?")
            ->execute([$userId]);
    }
}

// ============================================================
// COOLDOWNS — in SECONDEN
// ============================================================
function canDoCrime(PDO $pdo, int $userId, string $crimeKey): array {
    $stmt = $pdo->prepare("SELECT available_at FROM crime_cooldowns WHERE user_id = ? AND crime_key = ? LIMIT 1");
    $stmt->execute([$userId, $crimeKey]);
    $availableAt = $stmt->fetchColumn();

    if (!$availableAt) return ['ok' => true, 'wait' => 0];
    $wait = strtotime($availableAt) - time();
    if ($wait <= 0) return ['ok' => true, 'wait' => 0];
    return ['ok' => false, 'wait' => $wait];
}

function setCrimeCooldown(PDO $pdo, int $userId, string $crimeKey, int $seconds): void {
    if ($seconds <= 0) return;
    $availableAt = date('Y-m-d H:i:s', time() + $seconds);
    $pdo->prepare("
        INSERT INTO crime_cooldowns (user_id, crime_key, available_at)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE available_at = VALUES(available_at)
    ")->execute([$userId, $crimeKey, $availableAt]);
}

// ============================================================
// BUSTED
// ============================================================
function getBustedInfo(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare("SELECT * FROM user_busted WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row) {
        $pdo->prepare("INSERT INTO user_busted (user_id) VALUES (?)")->execute([$userId]);
        return ['user_id' => $userId, 'busted_count' => 0, 'last_busted' => null];
    }
    return $row;
}

function recordBusted(PDO $pdo, int $userId): void {
    $pdo->prepare("
        INSERT INTO user_busted (user_id, busted_count, last_busted)
        VALUES (?, 1, NOW())
        ON DUPLICATE KEY UPDATE busted_count = busted_count + 1, last_busted = NOW()
    ")->execute([$userId]);
}

// ============================================================
// STATS
// ============================================================
function getRecentCrimes(PDO $pdo, int $userId, int $limit = 10): array {
    $stmt = $pdo->prepare("SELECT * FROM crime_logs WHERE user_id = ? ORDER BY id DESC LIMIT ?");
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function getUserCrimeStats(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS total,
               SUM(success = 1) AS successes,
               SUM(success = 0) AS failures,
               COALESCE(SUM(reward), 0) AS total_earned,
               COALESCE(SUM(fine), 0) AS total_fines
        FROM crime_logs WHERE user_id = ?
    ");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}