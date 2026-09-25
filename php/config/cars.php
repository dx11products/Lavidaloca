<?php
/**
 * Vendetta — Auto's & Garage (timer-systeem)
 */

const CAR_STEAL_COOLDOWN  = 30;      // 30 seconden tussen steels
const CAR_STEAL_XP        = 25;
const CAR_SELL_PERCENT    = 70;
const CAR_GARAGE_LIMIT    = 10;
const CAR_LEVEL_BONUS     = 0.40;

function getCarLevelMultiplier(int $rankLevel): float {
    return 1 + (($rankLevel - 1) * CAR_LEVEL_BONUS);
}

function getAllCarTypes(PDO $pdo): array {
    $stmt = $pdo->query("SELECT * FROM car_types ORDER BY base_value ASC");
    return $stmt->fetchAll();
}

function getCarsInCountry(PDO $pdo, string $countryKey): array {
    $stmt = $pdo->prepare("
        SELECT ct.*, cp.value, cp.steal_chance
        FROM car_types ct
        JOIN car_prices cp ON cp.car_key = ct.`key`
        WHERE cp.country_key = ?
        ORDER BY cp.value ASC
    ");
    $stmt->execute([$countryKey]);
    return $stmt->fetchAll();
}

function getCarType(PDO $pdo, string $key): ?array {
    $stmt = $pdo->prepare("SELECT * FROM car_types WHERE `key` = ? LIMIT 1");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function getCarValueInCountry(PDO $pdo, string $carKey, string $countryKey): ?array {
    $stmt = $pdo->prepare("SELECT value, steal_chance FROM car_prices WHERE car_key = ? AND country_key = ? LIMIT 1");
    $stmt->execute([$carKey, $countryKey]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function userHasHouseInCountry(PDO $pdo, int $userId, string $countryKey): bool {
    $stmt = $pdo->prepare("SELECT id FROM user_houses WHERE user_id = ? AND country_key = ? LIMIT 1");
    $stmt->execute([$userId, $countryKey]);
    return (bool)$stmt->fetch();
}

function countGarageCars(PDO $pdo, int $userId, string $countryKey): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_garage WHERE user_id = ? AND country_key = ?");
    $stmt->execute([$userId, $countryKey]);
    return (int)$stmt->fetchColumn();
}

function getUserGarage(PDO $pdo, int $userId, ?string $countryKey = null): array {
    $sql = "
        SELECT ug.*, ct.name, ct.brand, ct.icon, ct.rarity,
               cp.value AS current_value,
               c.flag, c.name AS country_name
        FROM user_garage ug
        JOIN car_types ct ON ct.`key` = ug.car_key
        LEFT JOIN car_prices cp ON cp.car_key = ug.car_key AND cp.country_key = ug.country_key
        JOIN countries c ON c.`key` = ug.country_key
        WHERE ug.user_id = ?
    ";
    $params = [$userId];
    if ($countryKey) {
        $sql .= " AND ug.country_key = ?";
        $params[] = $countryKey;
    }
    $sql .= " ORDER BY cp.value DESC, ug.stolen_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function addCarToGarage(PDO $pdo, int $userId, string $countryKey, string $carKey): void {
    $pdo->prepare("INSERT INTO user_garage (user_id, country_key, car_key) VALUES (?, ?, ?)")
        ->execute([$userId, $countryKey, $carKey]);
}

function getLastSteal(PDO $pdo, int $userId): ?array {
    $stmt = $pdo->prepare("SELECT * FROM user_car_steal WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function canStealCar(PDO $pdo, int $userId): array {
    $last = getLastSteal($pdo, $userId);
    if (!$last) return ['ok' => true, 'wait' => 0];
    $elapsed = time() - strtotime($last['last_steal']);
    $wait = CAR_STEAL_COOLDOWN - $elapsed;
    if ($wait <= 0) return ['ok' => true, 'wait' => 0];
    return ['ok' => false, 'wait' => $wait];
}

function recordSteal(PDO $pdo, int $userId): void {
    $stmt = $pdo->prepare("SELECT id FROM user_car_steal WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    if ($stmt->fetch()) {
        $pdo->prepare("UPDATE user_car_steal SET last_steal = NOW(), total_stolen = total_stolen + 1 WHERE user_id = ?")
            ->execute([$userId]);
    } else {
        $pdo->prepare("INSERT INTO user_car_steal (user_id, total_stolen) VALUES (?, 1)")
            ->execute([$userId]);
    }
}

function rarityColor(string $rarity): string {
    return match($rarity) {
        'common'    => '#a08d75',
        'uncommon'  => '#58e08c',
        'rare'      => '#4a9dff',
        'epic'      => '#b06aff',
        'legendary' => '#ffb040',
        default     => '#a08d75',
    };
}

function rarityLabel(string $rarity): string {
    return match($rarity) {
        'common'    => 'Gewoon',
        'uncommon'  => 'Ongewoon',
        'rare'      => 'Zeldzaam',
        'epic'      => 'Episch',
        'legendary' => 'Legendarisch',
        default     => 'Onbekend',
    };
}