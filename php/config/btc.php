<?php
/**
 * Vendetta — Bitcoin & Miners
 * BELANGRIJK: Alleen functies en constanten.
 */

// ============================================================
// INSTELLINGEN
// ============================================================
const BTC_SELL_RATE         = 60000;
const BTC_MAX_LEVEL         = 10;
const BTC_UPGRADE_MULTIPLIER = 1.80;   // Kosten vermenigvuldigen per level
const BTC_LEVEL_BONUS       = 1.00;    // +100% per level (verdubbelt!)

// ============================================================
// MINERS OPHALEN
// ============================================================
function getAllBtcMiners(PDO $pdo): array {
    $stmt = $pdo->query("SELECT * FROM btc_miners ORDER BY tier ASC");
    return $stmt->fetchAll();
}

function getBtcMiner(PDO $pdo, string $key): ?array {
    $stmt = $pdo->prepare("SELECT * FROM btc_miners WHERE `key` = ? LIMIT 1");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ?: null;
}

// ============================================================
// USER MINERS
// ============================================================
function getMinerForHouse(PDO $pdo, int $houseId): ?array {
    $stmt = $pdo->prepare("
        SELECT um.*, m.name, m.icon, m.base_btc_per_hour, m.tier, m.base_price, m.description
        FROM user_btc_miners um
        JOIN btc_miners m ON m.`key` = um.miner_key
        WHERE um.house_id = ?
        LIMIT 1
    ");
    $stmt->execute([$houseId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function getUserMiners(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare("
        SELECT um.*, m.name, m.icon, m.base_btc_per_hour, m.tier, m.base_price, m.description,
               h.name AS house_name, uh.country_key
        FROM user_btc_miners um
        JOIN btc_miners m ON m.`key` = um.miner_key
        JOIN user_houses uh ON uh.id = um.house_id
        JOIN houses h ON h.`key` = uh.house_key
        WHERE um.user_id = ?
        ORDER BY um.total_btc_mined DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

// ============================================================
// PRODUCTIE BEREKENEN
// ============================================================
/**
 * BTC per uur voor een miner op bepaald level.
 * Elke level verdubbelt de opbrengst (+100%).
 *
 * Level 1  = ×1
 * Level 3  = ×4
 * Level 5  = ×16
 * Level 7  = ×64
 * Level 10 = ×512
 */
function minerHourlyRate(array $miner): float {
    $base  = (float)$miner['base_btc_per_hour'];
    $level = max(1, (int)$miner['level']);

    // Verdubbel per level: 2^(level-1)
    $multiplier = pow(2, $level - 1);

    return $base * $multiplier;
}

/**
 * BTC per dag voor een miner.
 */
function minerDailyRate(array $miner): float {
    return minerHourlyRate($miner) * 24;
}

/**
 * BTC per week voor een miner.
 */
function minerWeeklyRate(array $miner): float {
    return minerHourlyRate($miner) * 24 * 7;
}

/**
 * BTC per maand voor een miner (30 dagen).
 */
function minerMonthlyRate(array $miner): float {
    return minerHourlyRate($miner) * 24 * 30;
}

/**
 * Multiplier voor weergave.
 */
function minerLevelMultiplier(int $level): int {
    return (int)pow(2, max(1, $level) - 1);
}

/**
 * Update alle miners van user — accumuleer productie sinds last_production_at.
 */
function updateUserBtcProduction(PDO $pdo, int $userId): float {
    $stmt = $pdo->prepare("
        SELECT um.id, um.level, um.last_production_at,
               m.base_btc_per_hour
        FROM user_btc_miners um
        JOIN btc_miners m ON m.`key` = um.miner_key
        WHERE um.user_id = ?
    ");
    $stmt->execute([$userId]);
    $miners = $stmt->fetchAll();

    if (empty($miners)) return 0.0;

    $totalGained = 0.0;
    $now = time();

    foreach ($miners as $m) {
        $last = strtotime($m['last_production_at']);
        $seconds = $now - $last;
        if ($seconds < 60) continue;

        $hours = $seconds / 3600;
        $multiplier = pow(2, max(1, (int)$m['level']) - 1);
        $rate = (float)$m['base_btc_per_hour'] * $multiplier;
        $gained = $rate * $hours;

        if ($gained <= 0) continue;

        $totalGained += $gained;

        $pdo->prepare("
            UPDATE user_btc_miners
            SET last_production_at = NOW(),
                total_btc_mined = total_btc_mined + ?
            WHERE id = ?
        ")->execute([$gained, $m['id']]);
    }

    if ($totalGained > 0) {
        $pdo->prepare("
            UPDATE users
            SET btc = btc + ?, total_btc_earned = total_btc_earned + ?
            WHERE id = ?
        ")->execute([$totalGained, $totalGained, $userId]);
    }

    return $totalGained;
}

// ============================================================
// KOPEN & UPGRADEN
// ============================================================
function getUpgradeCost(array $miner): int {
    $base = (int)$miner['base_price'];
    $level = max(1, (int)$miner['level']);
    return (int)round($base * 0.8 * pow(BTC_UPGRADE_MULTIPLIER, $level));
}

function addBtcTransaction(PDO $pdo, int $userId, float $amount, string $type, string $description): void {
    $pdo->prepare("
        INSERT INTO btc_transactions (user_id, amount, type, description)
        VALUES (?, ?, ?, ?)
    ")->execute([$userId, $amount, $type, $description]);
}

function getBtcTransactions(PDO $pdo, int $userId, int $limit = 20): array {
    $stmt = $pdo->prepare("
        SELECT * FROM btc_transactions
        WHERE user_id = ?
        ORDER BY id DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

// ============================================================
// FORMATTEREN
// ============================================================
function formatBtc(float $amount): string {
    $str = number_format($amount, 8, '.', '');
    $str = rtrim(rtrim($str, '0'), '.');
    if ($str === '' || $str === '-') $str = '0';
    return $str;
}

function btcToEur(float $btc): int {
    return (int)floor($btc * BTC_SELL_RATE);
}