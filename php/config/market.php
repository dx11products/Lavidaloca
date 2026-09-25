<?php
/**
 * Vendetta — Globale markt + Assets
 * BELANGRIJK: Alleen functies en constanten.
 */

// ============================================================
// ASSETS (definities)
// ============================================================
function getAllMarketAssets(PDO $pdo): array {
    $stmt = $pdo->query("SELECT * FROM market_assets ORDER BY tier ASC, base_price_diamonds ASC");
    return $stmt->fetchAll();
}

function getMarketAsset(PDO $pdo, string $key): ?array {
    $stmt = $pdo->prepare("SELECT * FROM market_assets WHERE `key` = ? LIMIT 1");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ?: null;
}

// ============================================================
// USER ASSETS
// ============================================================
function getUserAssets(PDO $pdo, int $userId, ?string $assetKey = null, ?string $countryKey = null): array {
    $sql = "
        SELECT ua.*, ma.name, ma.icon, ma.category, ma.income_type, ma.income_per_hour,
               ma.color, c.flag, c.name AS country_name
        FROM user_assets ua
        JOIN market_assets ma ON ma.`key` = ua.asset_key
        JOIN countries c ON c.`key` = ua.country_key
        WHERE ua.user_id = ?
    ";
    $params = [$userId];

    if ($assetKey) {
        $sql .= " AND ua.asset_key = ?";
        $params[] = $assetKey;
    }
    if ($countryKey) {
        $sql .= " AND ua.country_key = ?";
        $params[] = $countryKey;
    }
    $sql .= " ORDER BY ma.tier ASC, ua.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function countUserAssets(PDO $pdo, int $userId, string $assetKey): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_assets WHERE user_id = ? AND asset_key = ?");
    $stmt->execute([$userId, $assetKey]);
    return (int)$stmt->fetchColumn();
}

function buyAssetFromSystem(PDO $pdo, int $userId, string $assetKey, string $countryKey): array {
    $asset = getMarketAsset($pdo, $assetKey);
    if (!$asset) return ['error' => 'Asset niet gevonden.'];

    $stmt = $pdo->prepare("SELECT xp FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $rank = getRankData((int)$stmt->fetchColumn(), getRanksArray());
    if ($rank['level'] < (int)$asset['min_rank']) {
        return ['error' => 'Je rank is te laag.'];
    }

    $owned = countUserAssets($pdo, $userId, $assetKey);
    if ($owned >= (int)$asset['max_per_user']) {
        return ['error' => 'Maximum bereikt voor dit type asset.'];
    }

    $price = (int)$asset['base_price_diamonds'];
    if (getUserDiamonds($pdo, $userId) < $price) {
        return ['error' => 'Niet genoeg diamanten. Je hebt er ' . ($price - getUserDiamonds($pdo, $userId)) . ' tekort.'];
    }

    $pdo->beginTransaction();
    try {
        if (!removeDiamonds($pdo, $userId, $price, 'buy_asset', "{$asset['name']} in {$countryKey}")) {
            throw new Exception('Betaling mislukt');
        }

        $pdo->prepare("
            INSERT INTO user_assets (user_id, asset_key, country_key)
            VALUES (?, ?, ?)
        ")->execute([$userId, $assetKey, $countryKey]);

        if (function_exists('logActivity')) {
            logActivity($pdo, $userId, "💎 {$asset['name']} gekocht in {$countryKey} voor {$price} diamanten");
        }

        $pdo->commit();
        return ['success' => true, 'asset' => $asset, 'price' => $price];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['error' => 'Aankoop mislukt'];
    }
}

function sellAssetToSystem(PDO $pdo, int $userId, int $userAssetId): array {
    $stmt = $pdo->prepare("
        SELECT ua.*, ma.name, ma.sell_price_diamonds
        FROM user_assets ua
        JOIN market_assets ma ON ma.`key` = ua.asset_key
        WHERE ua.id = ? AND ua.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$userAssetId, $userId]);
    $ua = $stmt->fetch();

    if (!$ua) return ['error' => 'Asset niet gevonden.'];

    $sellPrice = (int)$ua['sell_price_diamonds'];

    $pdo->beginTransaction();
    try {
        addDiamonds($pdo, $userId, $sellPrice, 'sell_asset', "Verkocht aan systeem: {$ua['name']}");
        $pdo->prepare("DELETE FROM user_assets WHERE id = ?")->execute([$userAssetId]);

        if (function_exists('logActivity')) {
            logActivity($pdo, $userId, "💎 {$ua['name']} verkocht aan systeem voor {$sellPrice} diamanten");
        }

        $pdo->commit();
        return ['success' => true, 'received' => $sellPrice, 'asset' => $ua['name']];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['error' => 'Verkoop mislukt'];
    }
}

// ============================================================
// MARKET LISTINGS
// ============================================================
function getMarketListings(PDO $pdo, ?string $assetKey = null, ?string $sort = 'newest', int $limit = 50): array {
    $sql = "
        SELECT ml.*, ma.name, ma.icon, ma.category, ma.income_type, ma.income_per_hour,
               ma.color, ma.base_price_diamonds, u.username AS seller_name,
               c.flag, c.name AS country_name
        FROM market_listings ml
        JOIN market_assets ma ON ma.`key` = ml.asset_key
        JOIN users u ON u.id = ml.seller_id
        JOIN countries c ON c.`key` = ml.country_key
        WHERE ml.status = 'active'
    ";
    $params = [];

    if ($assetKey) {
        $sql .= " AND ml.asset_key = ?";
        $params[] = $assetKey;
    }

    $orderBy = match($sort) {
        'price_low'  => 'ml.price_diamonds ASC',
        'price_high' => 'ml.price_diamonds DESC',
        default      => 'ml.listed_at DESC',
    };
    $sql .= " ORDER BY $orderBy LIMIT ?";
    $params[] = $limit;

    $stmt = $pdo->prepare($sql);
    foreach ($params as $i => $p) {
        $stmt->bindValue($i + 1, $p, is_int($p) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    return $stmt->fetchAll();
}

function getMarketListing(PDO $pdo, int $listingId): ?array {
    $stmt = $pdo->prepare("
        SELECT ml.*, ma.name, ma.icon, ma.category, ma.income_type, ma.income_per_hour,
               ma.color, ma.base_price_diamonds, u.username AS seller_name,
               c.flag, c.name AS country_name
        FROM market_listings ml
        JOIN market_assets ma ON ma.`key` = ml.asset_key
        JOIN users u ON u.id = ml.seller_id
        JOIN countries c ON c.`key` = ml.country_key
        WHERE ml.id = ? LIMIT 1
    ");
    $stmt->execute([$listingId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function listAssetOnMarket(PDO $pdo, int $userId, int $userAssetId, int $price): array {
    if ($price < 1) return ['error' => 'Prijs moet minimaal 1 diamant zijn.'];
    if ($price > 999999999) return ['error' => 'Prijs te hoog.'];

    $stmt = $pdo->prepare("SELECT * FROM user_assets WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$userAssetId, $userId]);
    $ua = $stmt->fetch();
    if (!$ua) return ['error' => 'Asset niet gevonden.'];

    $pdo->beginTransaction();
    try {
        $pdo->prepare("
            INSERT INTO market_listings (seller_id, asset_key, country_key, level, price_diamonds, status)
            VALUES (?, ?, ?, ?, ?, 'active')
        ")->execute([$userId, $ua['asset_key'], $ua['country_key'], $ua['level'], $price]);

        $pdo->prepare("DELETE FROM user_assets WHERE id = ?")->execute([$userAssetId]);

        $asset = getMarketAsset($pdo, $ua['asset_key']);
        if (function_exists('logActivity')) {
            logActivity($pdo, $userId, "💎 {$asset['name']} te koop gezet voor {$price} diamanten");
        }

        $pdo->commit();
        return ['success' => true, 'price' => $price];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['error' => 'Listings mislukt'];
    }
}

function buyMarketListing(PDO $pdo, int $buyerId, int $listingId): array {
    $listing = getMarketListing($pdo, $listingId);
    if (!$listing) return ['error' => 'Listing niet gevonden.'];
    if ((int)$listing['seller_id'] === $buyerId) return ['error' => 'Je kunt je eigen listing niet kopen.'];
    if ($listing['status'] !== 'active') return ['error' => 'Deze listing is niet meer beschikbaar.'];

    $price = (int)$listing['price_diamonds'];
    $have = getUserDiamonds($pdo, $buyerId);
    if ($have < $price) return ['error' => 'Niet genoeg diamanten.'];

    $pdo->beginTransaction();
    try {
        if (!removeDiamonds($pdo, $buyerId, $price, 'market_buy', "Koop: {$listing['name']}")) {
            throw new Exception('Betaling mislukt');
        }

        addDiamonds($pdo, (int)$listing['seller_id'], $price, 'market_sell', "Verkocht: {$listing['name']}");

        $pdo->prepare("
            INSERT INTO user_assets (user_id, asset_key, country_key, level)
            VALUES (?, ?, ?, ?)
        ")->execute([$buyerId, $listing['asset_key'], $listing['country_key'], $listing['level']]);

        $pdo->prepare("
            UPDATE market_listings
            SET status = 'sold', buyer_id = ?, sold_at = NOW()
            WHERE id = ?
        ")->execute([$buyerId, $listingId]);

        $pdo->prepare("
            INSERT INTO market_sales (seller_id, buyer_id, asset_key, price_diamonds)
            VALUES (?, ?, ?, ?)
        ")->execute([(int)$listing['seller_id'], $buyerId, $listing['asset_key'], $price]);

        if (function_exists('notify')) {
            $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$buyerId]);
            $buyerName = $stmt->fetchColumn();
            notify($pdo, (int)$listing['seller_id'],
                "💰 Je {$listing['name']} is verkocht aan {$buyerName} voor {$price} 💎!", '💎');
        }

        if (function_exists('logActivity')) {
            logActivity($pdo, $buyerId, "💎 {$listing['name']} gekocht van {$listing['seller_name']} voor {$price} diamanten");
        }

        $pdo->commit();
        return ['success' => true, 'asset' => $listing, 'price' => $price];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['error' => 'Aankoop mislukt'];
    }
}

function cancelListing(PDO $pdo, int $userId, int $listingId): array {
    $stmt = $pdo->prepare("SELECT * FROM market_listings WHERE id = ? AND seller_id = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$listingId, $userId]);
    $listing = $stmt->fetch();
    if (!$listing) return ['error' => 'Listing niet gevonden.'];

    $pdo->beginTransaction();
    try {
        $pdo->prepare("
            INSERT INTO user_assets (user_id, asset_key, country_key, level)
            VALUES (?, ?, ?, ?)
        ")->execute([$userId, $listing['asset_key'], $listing['country_key'], $listing['level']]);

        $pdo->prepare("UPDATE market_listings SET status = 'cancelled' WHERE id = ?")
            ->execute([$listingId]);

        $pdo->commit();
        return ['success' => true];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['error' => 'Annuleren mislukt'];
    }
}

function getUserListings(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare("
        SELECT ml.*, ma.name, ma.icon, ma.category, ma.color,
               c.flag, c.name AS country_name
        FROM market_listings ml
        JOIN market_assets ma ON ma.`key` = ml.asset_key
        JOIN countries c ON c.`key` = ml.country_key
        WHERE ml.seller_id = ? AND ml.status = 'active'
        ORDER BY ml.listed_at DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function getRecentMarketSales(PDO $pdo, int $limit = 15): array {
    $stmt = $pdo->prepare("
        SELECT ms.*, ma.name, ma.icon, ma.color,
               us.username AS seller_name, ub.username AS buyer_name
        FROM market_sales ms
        JOIN market_assets ma ON ma.`key` = ms.asset_key
        JOIN users us ON us.id = ms.seller_id
        JOIN users ub ON ub.id = ms.buyer_id
        ORDER BY ms.id DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function getMarketStats(PDO $pdo): array {
    return [
        'total_listings' => (int)$pdo->query("SELECT COUNT(*) FROM market_listings WHERE status = 'active'")->fetchColumn(),
        'total_sales'    => (int)$pdo->query("SELECT COUNT(*) FROM market_sales")->fetchColumn(),
        'total_volume'   => (int)$pdo->query("SELECT COALESCE(SUM(price_diamonds), 0) FROM market_sales")->fetchColumn(),
        'lowest_price'   => (int)$pdo->query("SELECT COALESCE(MIN(price_diamonds), 0) FROM market_listings WHERE status = 'active'")->fetchColumn(),
    ];
}

function collectAssetIncome(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare("
        SELECT ua.*, ma.name, ma.income_type, ma.income_per_hour, ma.icon
        FROM user_assets ua
        JOIN market_assets ma ON ma.`key` = ua.asset_key
        WHERE ua.user_id = ?
    ");
    $stmt->execute([$userId]);
    $assets = $stmt->fetchAll();

    $gains = ['eur' => 0, 'btc' => 0.0, 'clicks' => 0, 'diamonds' => 0, 'bullets' => 0];
    $collected = 0;

    foreach ($assets as $a) {
        $hoursSince = (time() - strtotime($a['last_collected_at'])) / 3600;
        if ($hoursSince < 0.0167) continue;

        $income = (float)$a['income_per_hour'] * $hoursSince;
        if ($income <= 0) continue;

        $gains[$a['income_type']] += $income;
        $collected++;

        $pdo->prepare("UPDATE user_assets SET last_collected_at = NOW(), total_earned = total_earned + ? WHERE id = ?")
            ->execute([(int)$income, $a['id']]);
    }

    if ($gains['eur'] > 0) {
        $pdo->prepare("UPDATE users SET money = money + ? WHERE id = ?")->execute([(int)$gains['eur'], $userId]);
    }
    if ($gains['btc'] > 0) {
        $pdo->prepare("UPDATE users SET btc = btc + ?, total_btc_earned = total_btc_earned + ? WHERE id = ?")
            ->execute([$gains['btc'], $gains['btc'], $userId]);
    }
    if ($gains['clicks'] > 0) {
        $pdo->prepare("UPDATE users SET clicks = clicks + ?, total_clicks_earned = total_clicks_earned + ? WHERE id = ?")
            ->execute([(int)$gains['clicks'], (int)$gains['clicks'], $userId]);
    }
    if ($gains['diamonds'] > 0) {
        addDiamonds($pdo, $userId, (int)$gains['diamonds'], 'asset_income', 'Diamantmijn opbrengst');
    }

    return ['collected' => $collected, 'gains' => $gains];
}