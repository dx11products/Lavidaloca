<?php
/**
 * Vendetta — Racing & Tuning
 */

// ============================================================
// RACE INSTELLINGEN
// ============================================================
const RACE_ENERGY_COST    = 30;         // energie per race
const RACE_MIN_ENTRY      = 500;        // minimum inleg
const RACE_MAX_ENTRY      = 50000;      // maximum inleg
const RACE_MIN_PLAYERS    = 2;          // min spelers om te starten
const RACE_MAX_PLAYERS    = 4;          // max spelers per race
const RACE_RAKE_PERCENT   = 10;         // % van de pot voor het huis
const RACE_XP_WIN         = 50;         // XP voor winnaar
const RACE_XP_LOSS        = 10;         // XP voor verliezer

// ============================================================
// UPGRADE INSTELLINGEN
// ============================================================
const UPGRADE_MAX_LEVEL   = 5;          // max level per upgrade

// ============================================================
// AUTO PRESTATIES BEREKENEN
// ============================================================
/**
 * Bereken de totale prestatie-score van een auto.
 * Basis: waarde/1000 + upgrades.
 */
function calculateCarPerformance(array $car, array $upgrades = []): array {
    $baseSpeed    = (int)floor(($car['current_value'] ?? 0) / 1000);
    $baseHandling = (int)floor(($car['current_value'] ?? 0) / 1500);

    $speed    = $baseSpeed;
    $handling = $baseHandling;
    $nitro    = 0;
    $armor    = 0;

    foreach ($upgrades as $u) {
        $lvl = (int)($car[$u['key'] . '_level'] ?? 0);
        if ($lvl <= 0) continue;
        $speed    += $lvl * (int)$u['speed_bonus_per_level'];
        $handling += $lvl * (int)$u['handling_bonus_per_level'];
        $nitro    += $lvl * (int)$u['nitro_bonus_per_level'];
    }

    // Rarity bonus
    $rarityBonus = match($car['rarity'] ?? 'common') {
        'uncommon'  => 5,
        'rare'      => 15,
        'epic'      => 30,
        'legendary' => 50,
        default     => 0,
    };

    $total = $speed + $handling + (int)floor($nitro / 2) + $rarityBonus;

    return [
        'speed'    => $speed,
        'handling' => $handling,
        'nitro'    => $nitro,
        'armor'    => $armor,
        'rarity'   => $rarityBonus,
        'total'    => $total,
    ];
}

// ============================================================
// UPGRADE KOSTEN BEREKENEN
// ============================================================
/**
 * Bereken kosten voor een upgrade op een bepaald niveau.
 * Kost = base_cost * (multiplier ^ huidig niveau)
 */
function upgradeCost(array $upgrade, int $currentLevel): int {
    if ($currentLevel >= (int)$upgrade['max_level']) return -1;
    return (int)round((int)$upgrade['base_cost'] * pow((float)$upgrade['cost_multiplier'], $currentLevel));
}

// ============================================================
// RACE SIMULATIE
// ============================================================
/**
 * Simuleer een race voor alle deelnemers.
 * Retourneert een array gesorteerd op finish_time.
 */
function simulateRace(array $participants): array {
    $results = [];

    foreach ($participants as $p) {
        // Elke auto heeft een prestatie-score
        $perf = (int)$p['total_perf'];

        // Random factor 90-110%
        $randomFactor = random_int(90, 110) / 100;

        // Basis racetijd: hoe hoger de prestatie, hoe lager de tijd
        // 10.000 prestatie = 30 seconden, 1.000 prestatie = 60 seconden
        $baseTime = max(20, 90 - ($perf / 200));

        // Random fluctuatie
        $finishTime = (int)round($baseTime * 1000 * (2 - $randomFactor));

        $results[] = [
            'user_id'     => $p['user_id'],
            'garage_id'   => $p['garage_id'],
            'username'    => $p['username'],
            'car_name'    => $p['car_name'],
            'car_icon'    => $p['car_icon'],
            'performance' => $perf,
            'time_ms'     => $finishTime,
        ];
    }

    // Sorteer op tijd (laagste eerst = winnaar)
    usort($results, fn($a, $b) => $a['time_ms'] <=> $b['time_ms']);

    // Voeg posities toe
    foreach ($results as $i => &$r) {
        $r['position'] = $i + 1;
    }

    return $results;
}

// ============================================================
// HULPFUNCTIES
// ============================================================
/**
 * Haal alle actieve races op in een land.
 */
function getOpenRaces(PDO $pdo, string $countryKey): array {
    $stmt = $pdo->prepare("
        SELECT r.*,
               u.username AS host_name,
               (SELECT COUNT(*) FROM race_participants WHERE race_id = r.id) AS player_count
        FROM races r
        JOIN users u ON u.id = r.host_id
        WHERE r.country_key = ? AND r.status = 'waiting'
        ORDER BY r.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$countryKey]);
    return $stmt->fetchAll();
}

/**
 * Deelnemers van een race.
 */
function getRaceParticipants(PDO $pdo, int $raceId): array {
    $stmt = $pdo->prepare("
        SELECT rp.*, u.username, ug.car_key,
               ct.name AS car_name, ct.icon AS car_icon, ct.rarity
        FROM race_participants rp
        JOIN users u ON u.id = rp.user_id
        JOIN user_garage ug ON ug.id = rp.garage_id
        JOIN car_types ct ON ct.`key` = ug.car_key
        WHERE rp.race_id = ?
        ORDER BY rp.joined_at ASC
    ");
    $stmt->execute([$raceId]);
    return $stmt->fetchAll();
}

/**
 * Is user al deelnemer aan een race?
 */
function isInRace(PDO $pdo, int $userId, int $raceId): bool {
    $stmt = $pdo->prepare("SELECT id FROM race_participants WHERE race_id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$raceId, $userId]);
    return (bool)$stmt->fetch();
}

/**
 * Race geschiedenis van user.
 */
function getUserRaceHistory(PDO $pdo, int $userId, int $limit = 10): array {
    $stmt = $pdo->prepare("
        SELECT * FROM race_history
        WHERE user_id = ?
        ORDER BY id DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Alle upgrades (globale lijst).
 */
function getAllUpgrades(PDO $pdo): array {
    $stmt = $pdo->query("SELECT * FROM car_upgrades ORDER BY id ASC");
    return $stmt->fetchAll();
}