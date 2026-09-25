<?php
/**
 * Vendetta — Landen, Drugs, Huizen & Planten
 * BELANGRIJK: Alleen functies en constanten.
 */

// ============================================================
// DRUGS
// ============================================================
const DRUGS = [
    'wiet'    => ['name' => 'Wiet',     'icon' => '🌿', 'unit' => 'gram'],
    'cocaine' => ['name' => 'Cocaïne',  'icon' => '❄️', 'unit' => 'gram'],
    'meth'    => ['name' => 'Meth',     'icon' => '💎', 'unit' => 'gram'],
    'xtc'     => ['name' => 'XTC',      'icon' => '💊', 'unit' => 'pil'],
];

// ============================================================
// LANDEN
// ============================================================
function getCountries(PDO $pdo): array {
    $stmt = $pdo->query("SELECT * FROM countries ORDER BY travel_cost ASC");
    return $stmt->fetchAll();
}

function getCountry(PDO $pdo, string $key): ?array {
    $stmt = $pdo->prepare("SELECT * FROM countries WHERE `key` = ? LIMIT 1");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ?: null;
}

// ============================================================
// DRUGS PRIJZEN
// ============================================================
function getDrugPrices(PDO $pdo, string $countryKey): array {
    $stmt = $pdo->prepare("SELECT * FROM drug_prices WHERE country_key = ?");
    $stmt->execute([$countryKey]);
    $rows = $stmt->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $out[$r['drug_key']] = $r;
    }
    return $out;
}

function getUserDrugs(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare("SELECT * FROM user_drugs WHERE user_id = ? AND quantity > 0");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $out[$r['drug_key']] = (int)$r['quantity'];
    }
    return $out;
}

function getTotalDrugCount(array $userDrugs): int {
    return array_sum($userDrugs);
}

function maxDrugCapacity(): int {
    return 1000;
}

/**
 * Voeg drugs toe aan user inventory.
 */
function addDrug(PDO $pdo, int $userId, string $drugKey, int $amount): void {
    $pdo->prepare("
        INSERT INTO user_drugs (user_id, drug_key, quantity)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
    ")->execute([$userId, $drugKey, $amount]);
}

/**
 * Verwijder drugs uit user inventory.
 */
function removeDrug(PDO $pdo, int $userId, string $drugKey, int $amount): bool {
    $stmt = $pdo->prepare("SELECT quantity FROM user_drugs WHERE user_id = ? AND drug_key = ?");
    $stmt->execute([$userId, $drugKey]);
    $have = (int)($stmt->fetchColumn() ?: 0);
    if ($have < $amount) return false;
    $pdo->prepare("UPDATE user_drugs SET quantity = quantity - ? WHERE user_id = ? AND drug_key = ?")
        ->execute([$amount, $userId, $drugKey]);
    return true;
}

// ============================================================
// HUIZEN
// ============================================================
function getAllHouses(PDO $pdo): array {
    $stmt = $pdo->query("SELECT * FROM houses ORDER BY price ASC");
    return $stmt->fetchAll();
}

function getUserHouse(PDO $pdo, int $userId, string $countryKey): ?array {
    $stmt = $pdo->prepare("
        SELECT h.*, uh.bought_at
        FROM user_houses uh
        JOIN houses h ON h.`key` = uh.house_key
        WHERE uh.user_id = ? AND uh.country_key = ?
        LIMIT 1
    ");
    $stmt->execute([$userId, $countryKey]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function getUserHouses(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare("
        SELECT uh.*, h.name, h.grow_slots, h.grow_time_minutes, h.yield_per_slot
        FROM user_houses uh
        JOIN houses h ON h.`key` = uh.house_key
        WHERE uh.user_id = ?
        ORDER BY uh.bought_at DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

// ============================================================
// PLANTEN — NIEUW SYSTEEM MET WATER
// ============================================================
const PLANT_WATER_MAX        = 4;    // 4x water per plant
const PLANT_WATER_INTERVAL   = 900;  // 15 min tussen water (in seconden)
const PLANT_TOTAL_GROW_TIME  = 3600; // 1 uur totaal (in seconden)

/**
 * Actieve planten in een land.
 */
function getActivePlants(PDO $pdo, int $userId, ?string $countryKey = null): array {
    $sql = "SELECT * FROM user_plants WHERE user_id = ? AND harvested = 0";
    $params = [$userId];
    if ($countryKey) {
        $sql .= " AND country_key = ?";
        $params[] = $countryKey;
    }
    $sql .= " ORDER BY id ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Aantal actieve planten in een land.
 */
function countActivePlants(PDO $pdo, int $userId, string $countryKey): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_plants WHERE user_id = ? AND country_key = ? AND harvested = 0");
    $stmt->execute([$userId, $countryKey]);
    return (int)$stmt->fetchColumn();
}

/**
 * Status van een plant (water-count, klaar, dood, etc.).
 */
function getPlantStatus(array $plant): array {
    $waterCount = (int)($plant['water_count'] ?? 0);

    // Bepaal wanneer de plant geplant is
    // We gebruiken harvest_at als "geplant + PLANT_TOTAL_GROW_TIME"
    // Dus: plantedAt = harvest_at - PLANT_TOTAL_GROW_TIME
    $harvestTime = strtotime($plant['harvest_at']);
    $plantedAt   = $harvestTime - PLANT_TOTAL_GROW_TIME;

    // Als de plant al geplant is vóór het nieuwe systeem
    if ($plantedAt > time()) {
        $plantedAt = time(); // fallback
    }

    $lastWater = $plant['last_water_at'] ? strtotime($plant['last_water_at']) : $plantedAt;

    // Wanneer mag je weer water geven?
    $canWaterIn = max(0, ($lastWater + PLANT_WATER_INTERVAL) - time());

    // Wanneer verdroogt de plant?
    $deadline = $plantedAt + PLANT_TOTAL_GROW_TIME;
    $secondsLeft = max(0, $deadline - time());

    $isReady = ($waterCount >= PLANT_WATER_MAX);
    $isDead  = (!$isReady && $secondsLeft <= 0);
    $canWater = (!$isReady && !$isDead && $canWaterIn <= 0);

    $progress = $isReady ? 100 : min(99, round(($waterCount / PLANT_WATER_MAX) * 100));

    return [
        'water_count'  => $waterCount,
        'can_water'    => $canWater,
        'can_water_in' => $canWaterIn,
        'is_ready'     => $isReady,
        'is_dead'      => $isDead,
        'seconds_left' => $secondsLeft,
        'progress'     => $progress,
    ];
}

/**
 * Geef water aan 1 plant.
 */
function waterPlant(PDO $pdo, int $userId, int $plantId): array {
    $stmt = $pdo->prepare("
        SELECT * FROM user_plants
        WHERE id = ? AND user_id = ? AND harvested = 0
        LIMIT 1
    ");
    $stmt->execute([$plantId, $userId]);
    $plant = $stmt->fetch();

    if (!$plant) return ['error' => 'Plant niet gevonden.'];

    $status = getPlantStatus($plant);

    if ($status['is_dead'])    return ['error' => 'Deze plant is verdroogd.'];
    if ($status['is_ready'])   return ['error' => 'Deze plant is al klaar.'];
    if (!$status['can_water']) {
        return ['error' => 'Wacht nog ' . $status['can_water_in'] . 's voor je water kunt geven.'];
    }

    $newCount = $status['water_count'] + 1;

    $pdo->prepare("
        UPDATE user_plants
        SET water_count = ?, last_water_at = NOW()
        WHERE id = ?
    ")->execute([$newCount, $plantId]);

    return [
        'success'   => true,
        'new_count' => $newCount,
        'is_ready'  => $newCount >= PLANT_WATER_MAX,
    ];
}

/**
 * Geef water aan alle planten die water nodig hebben.
 */
function waterAllPlants(PDO $pdo, int $userId, string $countryKey): array {
    $plants = getActivePlants($pdo, $userId, $countryKey);
    $watered = 0;
    $skipped = 0;

    foreach ($plants as $plant) {
        $status = getPlantStatus($plant);
        if ($status['can_water']) {
            $pdo->prepare("
                UPDATE user_plants
                SET water_count = water_count + 1, last_water_at = NOW()
                WHERE id = ?
            ")->execute([$plant['id']]);
            $watered++;
        } else {
            $skipped++;
        }
    }

    return ['watered' => $watered, 'skipped' => $skipped];
}

/**
 * Plant X planten in één keer.
 */
function plantBulk(PDO $pdo, int $userId, string $countryKey, int $count, int $growMinutes, int $yieldPerPlant): array {
    if ($count < 1) return ['error' => 'Minimum 1 plant.'];

    $costPerPlant = getPlantCost($pdo, $countryKey, $yieldPerPlant);
    $totalCost = $costPerPlant * $count;

    // Check geld
    $stmt = $pdo->prepare("SELECT money FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $money = (int)$stmt->fetchColumn();

    if ($money < $totalCost) {
        return ['error' => 'Je hebt €' . number_format($totalCost, 0, ',', '.') .
                          ' nodig. Je hebt €' . number_format($money, 0, ',', '.') . '.'];
    }

    $readyAt = date('Y-m-d H:i:s', time() + PLANT_TOTAL_GROW_TIME);

    $pdo->beginTransaction();
    try {
        // Geld af
        $pdo->prepare("UPDATE users SET money = money - ? WHERE id = ?")
            ->execute([$totalCost, $userId]);

        // Planten aanmaken
        $stmt = $pdo->prepare("
            INSERT INTO user_plants
            (user_id, country_key, harvest_at, yield_amount, water_count, last_water_at)
            VALUES (?, ?, ?, ?, 0, NULL)
        ");
        for ($i = 0; $i < $count; $i++) {
            $stmt->execute([$userId, $countryKey, $readyAt, $yieldPerPlant]);
        }
        $pdo->commit();

        return [
            'success'    => true,
            'count'      => $count,
            'cost'       => $totalCost,
            'cost_per'   => $costPerPlant,
        ];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['error' => 'Planten mislukt: ' . $e->getMessage()];
    }
}
/**
 * Oogst 1 plant.
 */
function harvestPlant(PDO $pdo, int $userId, int $plantId): array {
    $stmt = $pdo->prepare("
        SELECT * FROM user_plants
        WHERE id = ? AND user_id = ? AND harvested = 0
        LIMIT 1
    ");
    $stmt->execute([$plantId, $userId]);
    $plant = $stmt->fetch();

    if (!$plant) return ['error' => 'Plant niet gevonden.'];

    $status = getPlantStatus($plant);

    if ($status['is_dead'])   return ['error' => 'Deze plant is verdroogd.'];
    if (!$status['is_ready']) return ['error' => 'Deze plant is nog niet klaar (water: ' . $status['water_count'] . '/4).'];

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE user_plants SET harvested = 1 WHERE id = ?")->execute([$plantId]);
        addDrug($pdo, $userId, 'wiet', (int)$plant['yield_amount']);
        $pdo->commit();

        return ['success' => true, 'yield' => (int)$plant['yield_amount']];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['error' => 'Oogsten mislukt.'];
    }
}

/**
 * Oogst alle klaar-zijnde planten.
 */
function harvestAllPlants(PDO $pdo, int $userId, string $countryKey): array {
    $plants = getActivePlants($pdo, $userId, $countryKey);
    $totalYield = 0;
    $count = 0;

    $pdo->beginTransaction();
    try {
        foreach ($plants as $plant) {
            $status = getPlantStatus($plant);
            if ($status['is_ready']) {
                $pdo->prepare("UPDATE user_plants SET harvested = 1 WHERE id = ?")->execute([$plant['id']]);
                $totalYield += (int)$plant['yield_amount'];
                $count++;
            }
        }
        if ($totalYield > 0) {
            addDrug($pdo, $userId, 'wiet', $totalYield);
        }
        $pdo->commit();

        return ['success' => true, 'count' => $count, 'total_yield' => $totalYield];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['error' => 'Bulk oogsten mislukt.'];
    }
}

/**
 * Verwijder alle dode (verdroogde) planten.
 */
function clearDeadPlants(PDO $pdo, int $userId, string $countryKey): array {
    $plants = getActivePlants($pdo, $userId, $countryKey);
    $cleared = 0;

    foreach ($plants as $plant) {
        $status = getPlantStatus($plant);
        if ($status['is_dead']) {
            $pdo->prepare("UPDATE user_plants SET harvested = 1 WHERE id = ?")->execute([$plant['id']]);
            $cleared++;
        }
    }

    return ['cleared' => $cleared];
}

// ============================================================
// PLANT KOSTEN
// ============================================================

/**
 * Bereken de kost om 1 plant te planten in een bepaald land.
 * Formule: (yield × verkoopprijs) / 4
 * Resultaat: 4x winst bij succes.
 */
function getPlantCost(PDO $pdo, string $countryKey, int $yieldPerPlant): int {
    // Haal wiet verkoopprijs op in dit land
    $stmt = $pdo->prepare("
        SELECT sell_price FROM drug_prices
        WHERE country_key = ? AND drug_key = 'wiet'
        LIMIT 1
    ");
    $stmt->execute([$countryKey]);
    $sellPrice = (int)$stmt->fetchColumn();

    if ($sellPrice <= 0) return 50; // fallback

    // Kost = (opbrengst × verkoopprijs) / 4
    $cost = ($yieldPerPlant * $sellPrice) / 4;

    return max(10, (int)round($cost));
}

// ============================================================
// HULPFUNCTIES
// ============================================================
/**
 * Leesbaar formaat van tijd tot oogst.
 */
function timeUntil(string $datetime): string {
    $diff = strtotime($datetime) - time();
    if ($diff <= 0) return 'Klaar';
    $min = floor($diff / 60);
    $sec = $diff % 60;
    if ($min > 0) return "{$min}m {$sec}s";
    return "{$sec}s";
}