<?php
/**
 * Vendetta — Clicks & Wapens (bulk aankoop systeem)
 */

// ============================================================
// CLICKS VERDIENEN
// ============================================================
const CLICKS_PER_CRIME_WIN   = 2;
const CLICKS_PER_CRIME_FAIL  = 1;
const CLICKS_PER_ATTACK_WIN  = 5;
const CLICKS_PER_ATTACK_LOSS = 1;
const CLICKS_PER_HEIST_WIN   = 20;
const CLICKS_PER_HEIST_LOSS  = 5;
const CLICKS_PER_LIKE        = 1;

// ============================================================
// CLICKS KOPEN
// ============================================================
const CLICK_PRICE_EUR  = 500;
const CLICK_PRICE_BTC  = 0.0083;
const CLICK_MIN_BUY    = 1;
const CLICK_MAX_BUY    = 10000;

const CLICK_BULK_TIERS = [
    100  => 0.10,
    500  => 0.20,
    1000 => 0.30,
];

// ============================================================
// WAPEN BULK KORTING
// ============================================================
const WEAPON_BULK_TIERS = [
    100  => 0.10,   // 10% korting bij 100+
    500  => 0.20,
    1000 => 0.30,
    5000 => 0.40,
];

const WEAPON_MAX_BUY = 100000; // Max per aankoop

// ============================================================
// WAPENS OPHALEN
// ============================================================
function getAllClickWeapons(PDO $pdo): array {
    $stmt = $pdo->query("SELECT * FROM click_weapons ORDER BY click_cost ASC");
    return $stmt->fetchAll();
}

function getClickWeapon(PDO $pdo, string $key): ?array {
    $stmt = $pdo->prepare("SELECT * FROM click_weapons WHERE `key` = ? LIMIT 1");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Alle wapens die user bezit (met quantity).
 */
function getUserClickWeapons(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare("
        SELECT uw.*, w.name, w.description, w.icon, w.category,
               w.click_cost, w.attack_bonus
        FROM user_click_weapons uw
        JOIN click_weapons w ON w.`key` = uw.weapon_key
        WHERE uw.user_id = ? AND uw.quantity > 0
        ORDER BY w.attack_bonus DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/**
 * Totaal aantal wapens in bezit.
 */
function getTotalWeaponsOwned(PDO $pdo, int $userId): int {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM user_click_weapons WHERE user_id = ?");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

/**
 * Aantal van een specifiek wapen dat user bezit.
 */
function getUserWeaponQuantity(PDO $pdo, int $userId, string $weaponKey): int {
    $stmt = $pdo->prepare("SELECT quantity FROM user_click_weapons WHERE user_id = ? AND weapon_key = ? LIMIT 1");
    $stmt->execute([$userId, $weaponKey]);
    return (int)($stmt->fetchColumn() ?: 0);
}

/**
 * Totale aanvalskracht uit ALLE wapens in bezit (quantity × attack_bonus).
 */
function getClickWeaponBonus(PDO $pdo, int $userId): int {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(uw.quantity * w.attack_bonus), 0)
        FROM user_click_weapons uw
        JOIN click_weapons w ON w.`key` = uw.weapon_key
        WHERE uw.user_id = ?
    ");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

/**
 * Uitgerust wapen (voor weergave) — het sterkste wapen.
 */
function getEquippedClickWeapon(PDO $pdo, int $userId): ?array {
    $stmt = $pdo->prepare("
        SELECT uw.*, w.name, w.icon, w.attack_bonus, w.category
        FROM user_click_weapons uw
        JOIN click_weapons w ON w.`key` = uw.weapon_key
        WHERE uw.user_id = ? AND uw.quantity > 0
        ORDER BY w.attack_bonus DESC
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Top-3 wapens (voor weergave).
 */
function getTopWeapons(PDO $pdo, int $userId, int $limit = 3): array {
    $stmt = $pdo->prepare("
        SELECT uw.quantity, w.name, w.icon, w.attack_bonus, w.category
        FROM user_click_weapons uw
        JOIN click_weapons w ON w.`key` = uw.weapon_key
        WHERE uw.user_id = ? AND uw.quantity > 0
        ORDER BY (uw.quantity * w.attack_bonus) DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

// ============================================================
// BULK KOPEN
// ============================================================
/**
 * Bereken prijs voor X wapens met bulk korting.
 */
function calculateWeaponPrice(int $quantity, int $unitPrice): array {
    $subtotal = $quantity * $unitPrice;

    $discountPct = 0;
    foreach (WEAPON_BULK_TIERS as $threshold => $pct) {
        if ($quantity >= $threshold) $discountPct = $pct;
    }

    $discountAmount = $subtotal * $discountPct;
    $total = $subtotal - $discountAmount;

    return [
        'unit'            => $unitPrice,
        'subtotal'        => (int)$subtotal,
        'discount_pct'    => $discountPct,
        'discount_amount' => (int)round($discountAmount),
        'total'           => (int)ceil($total),
        'quantity'        => $quantity,
    ];
}

/**
 * Koop X wapens.
 */
function buyWeaponBulk(PDO $pdo, int $userId, string $weaponKey, int $quantity): array {
    if ($quantity < 1) {
        return ['error' => 'Ongeldig aantal.'];
    }
    if ($quantity > WEAPON_MAX_BUY) {
        return ['error' => 'Maximum ' . number_format(WEAPON_MAX_BUY, 0, ',', '.') . ' per aankoop.'];
    }

    $weapon = getClickWeapon($pdo, $weaponKey);
    if (!$weapon) {
        return ['error' => 'Wapen niet gevonden.'];
    }

    // Rank check
    $stmt = $pdo->prepare("SELECT xp, clicks FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    $xp = (int)$row['xp'];
    $clicks = (int)$row['clicks'];

    $rankData = getRankData($xp, getRanksArray());
    if ($rankData['level'] < (int)$weapon['min_rank']) {
        return ['error' => 'Je rank is te laag voor dit wapen.'];
    }

    // Prijs berekenen
    $price = calculateWeaponPrice($quantity, (int)$weapon['click_cost']);
    $totalCost = $price['total'];

    if ($clicks < $totalCost) {
        $tekort = $totalCost - $clicks;
        return ['error' => 'Je hebt ' . number_format($tekort, 0, ',', '.') . ' clicks tekort.'];
    }

    $pdo->beginTransaction();
    try {
        // Trek clicks af
        $pdo->prepare("UPDATE users SET clicks = clicks - ? WHERE id = ?")
            ->execute([$totalCost, $userId]);

        // Voeg wapens toe
        $stmt = $pdo->prepare("SELECT id, quantity FROM user_click_weapons WHERE user_id = ? AND weapon_key = ? LIMIT 1");
        $stmt->execute([$userId, $weaponKey]);
        $existing = $stmt->fetch();

        if ($existing) {
            $pdo->prepare("UPDATE user_click_weapons SET quantity = quantity + ? WHERE id = ?")
                ->execute([$quantity, $existing['id']]);
        } else {
            $pdo->prepare("INSERT INTO user_click_weapons (user_id, weapon_key, quantity) VALUES (?, ?, ?)")
                ->execute([$userId, $weaponKey, $quantity]);
        }

        if (function_exists('logActivity')) {
            logActivity($pdo, $userId,
                "🔫 {$quantity}x {$weapon['name']} gekocht voor " . number_format($totalCost, 0, ',', '.') . " clicks");
        }

        $pdo->commit();

        return [
            'success'  => true,
            'quantity' => $quantity,
            'cost'     => $totalCost,
            'weapon'   => $weapon['name'],
        ];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['error' => 'Aankoop mislukt: ' . $e->getMessage()];
    }
}

// ============================================================
// CLICKS BIJSCHRIJVEN
// ============================================================
function addClicks(PDO $pdo, int $userId, int $amount, string $reason = 'reward'): void {
    if ($amount <= 0) return;

    $pdo->prepare("
        UPDATE users
        SET clicks = clicks + ?, total_clicks_earned = total_clicks_earned + ?
        WHERE id = ?
    ")->execute([$amount, $amount, $userId]);

    try {
        $pdo->prepare("
            INSERT INTO clicks_purchases (user_id, amount, method, description)
            VALUES (?, ?, 'reward', ?)
        ")->execute([$userId, $amount, $reason]);
    } catch (Exception $e) {}
}

// ============================================================
// CLICKS KOPEN (geld/BTC)
// ============================================================
function calculateClickPrice(int $amount, string $method = 'eur'): array {
    $unit = $method === 'eur' ? CLICK_PRICE_EUR : CLICK_PRICE_BTC;
    $subtotal = $amount * $unit;

    $discountPct = 0;
    foreach (CLICK_BULK_TIERS as $threshold => $pct) {
        if ($amount >= $threshold) $discountPct = $pct;
    }

    $discountAmount = $subtotal * $discountPct;
    $total = $subtotal - $discountAmount;

    return [
        'unit'            => $unit,
        'subtotal'        => $subtotal,
        'discount_pct'    => $discountPct,
        'discount_amount' => $discountAmount,
        'total'           => $total,
        'amount'          => $amount,
    ];
}

function buyClicksWithEur(PDO $pdo, int $userId, int $amount): array {
    if ($amount < CLICK_MIN_BUY) return ['error' => 'Minimum ' . CLICK_MIN_BUY . ' click'];
    if ($amount > CLICK_MAX_BUY) return ['error' => 'Maximum ' . CLICK_MAX_BUY . ' clicks per keer'];

    $price = calculateClickPrice($amount, 'eur');
    $totalEur = (int)ceil($price['total']);

    $stmt = $pdo->prepare("SELECT money FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $money = (int)$stmt->fetchColumn();

    if ($money < $totalEur) {
        return ['error' => 'Je hebt niet genoeg geld. Je hebt €' . number_format($totalEur - $money, 0, ',', '.') . ' tekort.'];
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE users SET money = money - ?, clicks = clicks + ? WHERE id = ?")
            ->execute([$totalEur, $amount, $userId]);

        $pdo->prepare("
            INSERT INTO clicks_purchases (user_id, amount, paid_eur, method, description)
            VALUES (?, ?, ?, 'eur', ?)
        ")->execute([$userId, $amount, $totalEur, "Aankoop {$amount} clicks met geld"]);

        if (function_exists('logActivity')) {
            logActivity($pdo, $userId, "🖱️ {$amount} clicks gekocht voor €" . number_format($totalEur, 0, ',', '.'));
        }
        $pdo->commit();

        return ['success' => true, 'amount' => $amount, 'total' => $totalEur];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['error' => 'Aankoop mislukt.'];
    }
}

function buyClicksWithBtc(PDO $pdo, int $userId, int $amount): array {
    if ($amount < CLICK_MIN_BUY) return ['error' => 'Minimum ' . CLICK_MIN_BUY . ' click'];
    if ($amount > CLICK_MAX_BUY) return ['error' => 'Maximum ' . CLICK_MAX_BUY . ' clicks per keer'];

    $price = calculateClickPrice($amount, 'btc');
    $totalBtc = round($price['total'], 8);

    $stmt = $pdo->prepare("SELECT btc FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $btc = (float)$stmt->fetchColumn();

    if ($btc < $totalBtc) {
        $btcFormatted = function_exists('formatBtc') ? formatBtc($totalBtc - $btc) : ($totalBtc - $btc);
        return ['error' => 'Je hebt niet genoeg BTC. Je hebt ₿' . $btcFormatted . ' tekort.'];
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE users SET btc = btc - ?, clicks = clicks + ? WHERE id = ?")
            ->execute([$totalBtc, $amount, $userId]);

        $pdo->prepare("
            INSERT INTO clicks_purchases (user_id, amount, paid_btc, method, description)
            VALUES (?, ?, ?, 'btc', ?)
        ")->execute([$userId, $amount, $totalBtc, "Aankoop {$amount} clicks met BTC"]);

        if (function_exists('logActivity')) {
            $btcFormatted = function_exists('formatBtc') ? formatBtc($totalBtc) : $totalBtc;
            logActivity($pdo, $userId, "🖱️ {$amount} clicks gekocht voor ₿" . $btcFormatted);
        }
        $pdo->commit();

        return ['success' => true, 'amount' => $amount, 'total' => $totalBtc];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['error' => 'Aankoop mislukt.'];
    }
}

// ============================================================
// STATS
// ============================================================
function getClicksPurchases(PDO $pdo, int $userId, int $limit = 15): array {
    $stmt = $pdo->prepare("
        SELECT * FROM clicks_purchases
        WHERE user_id = ?
        ORDER BY id DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function getClicksStats(PDO $pdo, int $userId): array {
    $stats = [
        'clicks_bought_eur' => 0,
        'clicks_bought_btc' => 0,
        'clicks_earned'     => 0,
        'eur_spent'         => 0,
        'btc_spent'         => 0,
    ];

    try {
        $stmt = $pdo->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN method = 'eur' THEN amount ELSE 0 END), 0) AS clicks_bought_eur,
                COALESCE(SUM(CASE WHEN method = 'btc' THEN amount ELSE 0 END), 0) AS clicks_bought_btc,
                COALESCE(SUM(CASE WHEN method = 'reward' THEN amount ELSE 0 END), 0) AS clicks_earned,
                COALESCE(SUM(paid_eur), 0) AS eur_spent,
                COALESCE(SUM(paid_btc), 0) AS btc_spent
            FROM clicks_purchases
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if ($row) {
            $stats = [
                'clicks_bought_eur' => (int)$row['clicks_bought_eur'],
                'clicks_bought_btc' => (int)$row['clicks_bought_btc'],
                'clicks_earned'     => (int)$row['clicks_earned'],
                'eur_spent'         => (int)$row['eur_spent'],
                'btc_spent'         => (float)$row['btc_spent'],
            ];
        }
    } catch (Exception $e) {}

    return $stats;
}