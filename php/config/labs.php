<?php
// Lab & fabriek configuratie

const AMMO_TYPES = [
    'pistool'  => ['name' => 'Pistool kogels',  'icon' => '🔫'],
    'geweer'   => ['name' => 'Geweer kogels',   'icon' => '🎯'],
    'shotgun'  => ['name' => 'Shotgun patronen', 'icon' => '💥'],
];

const AMMO_PER_ATTACK = 5;         // kogels per aanval
const AMMO_DAMAGE_BONUS = 5;       // extra schade als je munitie gebruikt
const AMMO_MAX_PER_TYPE = 100000;

/**
 * Haal alle labs op.
 */
function getAllLabs(PDO $pdo): array {
    $stmt = $pdo->query("SELECT * FROM labs ORDER BY price ASC");
    return $stmt->fetchAll();
}

/**
 * Haal één lab op.
 */
function getLab(PDO $pdo, string $key): ?array {
    $stmt = $pdo->prepare("SELECT * FROM labs WHERE `key` = ? LIMIT 1");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Labs van user in bepaald land.
 */
function getUserLabs(PDO $pdo, int $userId, ?string $countryKey = null): array {
    $sql = "SELECT ul.*, l.name, l.lab_type, l.batch_size, l.process_time_minutes
            FROM user_labs ul
            JOIN labs l ON l.`key` = ul.lab_key
            WHERE ul.user_id = ?";
    $params = [$userId];
    if ($countryKey) {
        $sql .= " AND ul.country_key = ?";
        $params[] = $countryKey;
    }
    $sql .= " ORDER BY ul.bought_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Heeft user een lab van dit type in dit land?
 */
function hasLabInCountry(PDO $pdo, int $userId, string $countryKey, string $labKey): bool {
    $stmt = $pdo->prepare("SELECT id FROM user_labs WHERE user_id = ? AND country_key = ? AND lab_key = ? LIMIT 1");
    $stmt->execute([$userId, $countryKey, $labKey]);
    return (bool)$stmt->fetch();
}

/**
 * Actieve producties van user.
 */
function getActiveProduction(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare("
        SELECT ulp.*, l.name AS lab_name, l.lab_type
        FROM user_lab_production ulp
        JOIN labs l ON l.`key` = ulp.lab_key
        WHERE ulp.user_id = ? AND ulp.collected = 0
        ORDER BY ulp.ready_at ASC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/**
 * Alle kogelfabrieken.
 */
function getAllFactories(PDO $pdo): array {
    $stmt = $pdo->query("SELECT * FROM bullet_factories ORDER BY price ASC");
    return $stmt->fetchAll();
}

/**
 * Kogelfabriek ophalen.
 */
function getFactory(PDO $pdo, string $key): ?array {
    $stmt = $pdo->prepare("SELECT * FROM bullet_factories WHERE `key` = ? LIMIT 1");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Kogelfabrieken van user.
 */
function getUserFactories(PDO $pdo, int $userId, ?string $countryKey = null): array {
    $sql = "SELECT uf.*, bf.name, bf.yield_per_batch, bf.process_time_minutes, bf.material_cost
            FROM user_factories uf
            JOIN bullet_factories bf ON bf.`key` = uf.factory_key
            WHERE uf.user_id = ?";
    $params = [$userId];
    if ($countryKey) {
        $sql .= " AND uf.country_key = ?";
        $params[] = $countryKey;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Munitie van user.
 */
function getUserAmmo(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare("SELECT * FROM user_ammo WHERE user_id = ?");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $out[$r['ammo_key']] = (int)$r['quantity'];
    }
    return $out;
}

/**
 * Voeg munitie toe.
 */
function addAmmo(PDO $pdo, int $userId, string $ammoKey, int $amount): void {
    $pdo->prepare("
        INSERT INTO user_ammo (user_id, ammo_key, quantity)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
    ")->execute([$userId, $ammoKey, $amount]);
}

/**
 * Verwijder munitie (returns true bij succes).
 */
function removeAmmo(PDO $pdo, int $userId, string $ammoKey, int $amount): bool {
    $stmt = $pdo->prepare("SELECT quantity FROM user_ammo WHERE user_id = ? AND ammo_key = ?");
    $stmt->execute([$userId, $ammoKey]);
    $have = (int)($stmt->fetchColumn() ?: 0);
    if ($have < $amount) return false;
    $pdo->prepare("UPDATE user_ammo SET quantity = quantity - ? WHERE user_id = ? AND ammo_key = ?")
        ->execute([$amount, $userId, $ammoKey]);
    return true;
}

/**
 * Totaal aantal kogels van user.
 */
function getTotalAmmo(array $ammo): int {
    return array_sum($ammo);
}

/**
 * Actieve kogel-producties.
 */
function getActiveBulletProduction(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare("
        SELECT ubp.*, bf.name AS factory_name
        FROM user_bullet_production ubp
        JOIN bullet_factories bf ON bf.`key` = ubp.factory_key
        WHERE ubp.user_id = ? AND ubp.collected = 0
        ORDER BY ubp.ready_at ASC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}