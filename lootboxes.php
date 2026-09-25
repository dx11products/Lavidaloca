<?php
/**
 * Vendetta — Loot Boxes (Extreme Edition)
 * 8 tiers, 7 rarities, pity system, multi-drop
 */

// ============================================================
// RARITY KANSEN
// ============================================================
const LOOTBOX_RARITIES = [
    'common'    => ['weight' => 50,  'label' => 'Gewoon',        'color' => '#8a8a8a', 'icon' => '⚪'],
    'uncommon'  => ['weight' => 28,  'label' => 'Ongewoon',       'color' => '#58e08c', 'icon' => '🟢'],
    'rare'      => ['weight' => 13,  'label' => 'Zeldzaam',       'color' => '#4a9dff', 'icon' => '🔵'],
    'epic'      => ['weight' => 6,   'label' => 'Episch',         'color' => '#b06aff', 'icon' => '🟣'],
    'legendary' => ['weight' => 2,   'label' => 'Legendarisch',   'color' => '#ffb040', 'icon' => '🟠'],
    'mythic'    => ['weight' => 0.8, 'label' => 'Mythisch',       'color' => '#ff4444', 'icon' => '🔴'],
    'divine'    => ['weight' => 0.2, 'label' => 'Goddelijk',      'color' => '#ffffff', 'icon' => '✨'],
];

// ============================================================
// PITY SYSTEEM
// ============================================================
const LOOTBOX_PITY_THRESHOLD  = 50;  // Na 50 opens zonder legendary+
const LOOTBOX_PITY_RARITY     = 'legendary';

// ============================================================
// MULTI-DROP KANS
// ============================================================
const LOOTBOX_MULTI_DROP = [
    1 => 95,   // 95% kans op 1 item
    2 => 4,    // 4% kans op 2 items
    3 => 1,    // 1% kans op 3 items
];

// ============================================================
// REWARD POOLS — 8 TIERS
// ============================================================
const LOOTBOX_POOLS = [
    1 => [ // WOODEN — €10K
        'common'    => [['type'=>'eur','min'=>5000,'max'=>25000,'label'=>'Geld'], ['type'=>'clicks','min'=>5,'max'=>20,'label'=>'Clicks']],
        'uncommon'  => [['type'=>'eur','min'=>25000,'max'=>75000,'label'=>'Geld'], ['type'=>'clicks','min'=>20,'max'=>60,'label'=>'Clicks']],
        'rare'      => [['type'=>'eur','min'=>100000,'max'=>300000,'label'=>'Geld'], ['type'=>'clicks','min'=>100,'max'=>300,'label'=>'Clicks']],
        'epic'      => [['type'=>'btc','min'=>0.005,'max'=>0.02,'label'=>'BTC']],
        'legendary' => [['type'=>'btc','min'=>0.05,'max'=>0.2,'label'=>'BTC']],
        'mythic'    => [['type'=>'eur','min'=>5000000,'max'=>15000000,'label'=>'Jackpot']],
        'divine'    => [['type'=>'btc','min'=>1,'max'=>3,'label'=>'Goddelijke BTC']],
    ],

    2 => [ // IRON — €100K
        'common'    => [['type'=>'eur','min'=>50000,'max'=>200000,'label'=>'Geld'], ['type'=>'clicks','min'=>30,'max'=>100,'label'=>'Clicks']],
        'uncommon'  => [['type'=>'eur','min'=>200000,'max'=>600000,'label'=>'Geld'], ['type'=>'clicks','min'=>100,'max'=>300,'label'=>'Clicks']],
        'rare'      => [['type'=>'eur','min'=>1000000,'max'=>3000000,'label'=>'Geld'], ['type'=>'weapon','key'=>'mes','qty_min'=>50,'qty_max'=>200,'label'=>'Messen']],
        'epic'      => [['type'=>'btc','min'=>0.05,'max'=>0.15,'label'=>'BTC']],
        'legendary' => [['type'=>'btc','min'=>0.5,'max'=>1.5,'label'=>'Grote BTC']],
        'mythic'    => [['type'=>'eur','min'=>25000000,'max'=>75000000,'label'=>'Grote Jackpot']],
        'divine'    => [['type'=>'btc','min'=>5,'max'=>15,'label'=>'Goddelijke BTC']],
    ],

    3 => [ // BRONZE — €1M
        'common'    => [['type'=>'eur','min'=>500000,'max'=>2000000,'label'=>'Geld'], ['type'=>'clicks','min'=>200,'max'=>800,'label'=>'Clicks']],
        'uncommon'  => [['type'=>'eur','min'=>2000000,'max'=>6000000,'label'=>'Geld'], ['type'=>'clicks','min'=>800,'max'=>2500,'label'=>'Clicks']],
        'rare'      => [['type'=>'eur','min'=>10000000,'max'=>30000000,'label'=>'Geld'], ['type'=>'weapon','key'=>'pistool','qty_min'=>200,'qty_max'=>1000,'label'=>'Pistolen']],
        'epic'      => [['type'=>'btc','min'=>0.5,'max'=>2,'label'=>'BTC'], ['type'=>'clicks','min'=>5000,'max'=>15000,'label'=>'Clicks']],
        'legendary' => [['type'=>'btc','min'=>5,'max'=>15,'label'=>'Grote BTC']],
        'mythic'    => [['type'=>'eur','min'=>100000000,'max'=>300000000,'label'=>'Massale Jackpot']],
        'divine'    => [['type'=>'btc','min'=>50,'max'=>150,'label'=>'Goddelijk vermogen']],
    ],

    4 => [ // SILVER — €10M
        'common'    => [['type'=>'eur','min'=>5000000,'max'=>20000000,'label'=>'Geld'], ['type'=>'clicks','min'=>2000,'max'=>8000,'label'=>'Clicks']],
        'uncommon'  => [['type'=>'eur','min'=>20000000,'max'=>60000000,'label'=>'Geld'], ['type'=>'clicks','min'=>8000,'max'=>25000,'label'=>'Clicks']],
        'rare'      => [['type'=>'eur','min'=>100000000,'max'=>300000000,'label'=>'Geld'], ['type'=>'weapon','key'=>'uzi','qty_min'=>1000,'qty_max'=>5000,'label'=>'Uzis']],
        'epic'      => [['type'=>'btc','min'=>5,'max'=>20,'label'=>'BTC'], ['type'=>'clicks','min'=>50000,'max'=>150000,'label'=>'Clicks']],
        'legendary' => [['type'=>'btc','min'=>50,'max'=>150,'label'=>'Enorme BTC']],
        'mythic'    => [['type'=>'eur','min'=>1000000000,'max'=>3000000000,'label'=>'Miljard Jackpot']],
        'divine'    => [['type'=>'btc','min'=>500,'max'=>1500,'label'=>'Goddelijk vermogen']],
    ],

    5 => [ // GOLD — €100M
        'common'    => [['type'=>'eur','min'=>50000000,'max'=>200000000,'label'=>'Geld'], ['type'=>'clicks','min'=>20000,'max'=>80000,'label'=>'Clicks']],
        'uncommon'  => [['type'=>'eur','min'=>200000000,'max'=>600000000,'label'=>'Geld'], ['type'=>'clicks','min'=>80000,'max'=>250000,'label'=>'Clicks']],
        'rare'      => [['type'=>'eur','min'=>1000000000,'max'=>3000000000,'label'=>'Miljarden'], ['type'=>'weapon','key'=>'shotgun','qty_min'=>5000,'qty_max'=>20000,'label'=>'Shotguns']],
        'epic'      => [['type'=>'btc','min'=>50,'max'=>200,'label'=>'BTC'], ['type'=>'clicks','min'=>500000,'max'=>1500000,'label'=>'Clicks']],
        'legendary' => [['type'=>'btc','min'=>500,'max'=>1500,'label'=>'Enorme BTC']],
        'mythic'    => [['type'=>'eur','min'=>10000000000,'max'=>30000000000,'label'=>'Miljard Jackpot']],
        'divine'    => [['type'=>'btc','min'=>5000,'max'=>15000,'label'=>'Goddelijk vermogen']],
    ],

    6 => [ // PLATINUM — €1 MILJARD
        'common'    => [['type'=>'eur','min'=>500000000,'max'=>2000000000,'label'=>'Geld'], ['type'=>'clicks','min'=>200000,'max'=>800000,'label'=>'Clicks']],
        'uncommon'  => [['type'=>'eur','min'=>2000000000,'max'=>6000000000,'label'=>'Miljarden'], ['type'=>'clicks','min'=>800000,'max'=>2500000,'label'=>'Clicks']],
        'rare'      => [['type'=>'eur','min'=>10000000000,'max'=>30000000000,'label'=>'Massale miljarden'], ['type'=>'weapon','key'=>'ak47','qty_min'=>20000,'qty_max'=>100000,'label'=>'AK-47s']],
        'epic'      => [['type'=>'btc','min'=>500,'max'=>2000,'label'=>'Gigantische BTC'], ['type'=>'clicks','min'=>5000000,'max'=>15000000,'label'=>'Miljoenen clicks']],
        'legendary' => [['type'=>'btc','min'=>5000,'max'=>15000,'label'=>'Kolossale BTC']],
        'mythic'    => [['type'=>'eur','min'=>100000000000,'max'=>300000000000,'label'=>'100 Miljard Jackpot']],
        'divine'    => [['type'=>'btc','min'=>50000,'max'=>150000,'label'=>'Goddelijk vermogen']],
    ],

    7 => [ // DIAMOND — €50 MILJARD
        'common'    => [['type'=>'eur','min'=>25000000000,'max'=>100000000000,'label'=>'Miljarden'], ['type'=>'clicks','min'=>10000000,'max'=>40000000,'label'=>'Clicks']],
        'uncommon'  => [['type'=>'eur','min'=>100000000000,'max'=>300000000000,'label'=>'100+ Miljard'], ['type'=>'clicks','min'=>40000000,'max'=>120000000,'label'=>'Clicks']],
        'rare'      => [['type'=>'eur','min'=>500000000000,'max'=>1500000000000,'label'=>'Biljoen'], ['type'=>'weapon','key'=>'sniper','qty_min'=>100000,'qty_max'=>500000,'label'=>'Snipers']],
        'epic'      => [['type'=>'btc','min'=>5000,'max'=>20000,'label'=>'BTC'], ['type'=>'clicks','min'=>250000000,'max'=>750000000,'label'=>'Clicks']],
        'legendary' => [['type'=>'btc','min'=>50000,'max'=>150000,'label'=>'Onvoorstelbaar BTC']],
        'mythic'    => [['type'=>'eur','min'=>5000000000000,'max'=>15000000000000,'label'=>'Biljoen Jackpot']],
        'divine'    => [['type'=>'btc','min'=>500000,'max'=>1500000,'label'=>'Hemels vermogen']],
    ],

    8 => [ // CELESTIAL — €1 BILJOEN
        'common'    => [['type'=>'eur','min'=>500000000000,'max'=>2000000000000,'label'=>'Biljoen'], ['type'=>'clicks','min'=>500000000,'max'=>2000000000,'label'=>'Miljard clicks']],
        'uncommon'  => [['type'=>'eur','min'=>2000000000000,'max'=>6000000000000,'label'=>'Biljoenen'], ['type'=>'clicks','min'=>2000000000,'max'=>6000000000,'label'=>'Clicks']],
        'rare'      => [['type'=>'eur','min'=>10000000000000,'max'=>30000000000000,'label'=>'10+ Biljoen'], ['type'=>'weapon','key'=>'rpg','qty_min'=>500000,'qty_max'=>2000000,'label'=>'RPG-7s']],
        'epic'      => [['type'=>'btc','min'=>500000,'max'=>2000000,'label'=>'Miljoenen BTC']],
        'legendary' => [['type'=>'btc','min'=>5000000,'max'=>15000000,'label'=>'Onbevattelijk BTC']],
        'mythic'    => [['type'=>'eur','min'=>100000000000000,'max'=>500000000000000,'label'=>'Biljoen Jackpot']],
        'divine'    => [['type'=>'btc','min'=>50000000,'max'=>200000000,'label'=>'Hemels vermogen']],
    ],
];

// ============================================================
// FUNCTIES
// ============================================================
function getAllLootboxes(PDO $pdo): array {
    $stmt = $pdo->query("SELECT * FROM lootbox_types ORDER BY tier ASC");
    return $stmt->fetchAll();
}

function getLootbox(PDO $pdo, string $key): ?array {
    $stmt = $pdo->prepare("SELECT * FROM lootbox_types WHERE `key` = ? LIMIT 1");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Roll rarity met pity systeem.
 */
function rollLootboxRarity(PDO $pdo, int $userId): string {
    $stmt = $pdo->prepare("SELECT lootbox_pity FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $pity = (int)$stmt->fetchColumn();

    // Pity trigger
    if ($pity >= LOOTBOX_PITY_THRESHOLD) {
        return LOOTBOX_PITY_RARITY;
    }

    $totalWeight = 0;
    foreach (LOOTBOX_RARITIES as $r) {
        $totalWeight += $r['weight'] * 100;
    }

    $roll = random_int(1, (int)$totalWeight);
    $current = 0;

    foreach (LOOTBOX_RARITIES as $key => $info) {
        $current += $info['weight'] * 100;
        if ($roll <= $current) return $key;
    }

    return 'common';
}

/**
 * Kies reward uit pool.
 */
function rollLootboxReward(int $tier, string $rarity): ?array {
    if (!isset(LOOTBOX_POOLS[$tier][$rarity])) return null;

    $pool = LOOTBOX_POOLS[$tier][$rarity];
    if (empty($pool)) return null;

    $reward = $pool[array_rand($pool)];

    if (in_array($reward['type'], ['eur', 'clicks'], true)) {
        $value = random_int($reward['min'], $reward['max']);
    } elseif ($reward['type'] === 'btc') {
        $min = (float)$reward['min'];
        $max = (float)$reward['max'];
        $value = $min + (mt_rand() / mt_getrandmax()) * ($max - $min);
        $value = round($value, 8);
    } elseif ($reward['type'] === 'weapon') {
        $value = random_int($reward['qty_min'], $reward['qty_max']);
    } else {
        $value = 0;
    }

    return [
        'type'   => $reward['type'],
        'value'  => $value,
        'label'  => $reward['label'] ?? '',
        'weapon' => $reward['key'] ?? null,
        'rarity' => $rarity,
    ];
}

/**
 * Bepaal hoeveel items uit de box komen.
 */
function rollLootboxDropCount(): int {
    $total = array_sum(LOOTBOX_MULTI_DROP);
    $roll = random_int(1, $total);
    $current = 0;

    foreach (LOOTBOX_MULTI_DROP as $count => $chance) {
        $current += $chance;
        if ($roll <= $current) return (int)$count;
    }
    return 1;
}

/**
 * Open een box — volledige flow met pity + multi-drop.
 */
function openLootbox(PDO $pdo, int $userId, string $boxKey, string $paidMethod = 'eur'): array {
    $box = getLootbox($pdo, $boxKey);
    if (!$box) return ['error' => 'Box niet gevonden.'];

    $stmt = $pdo->prepare("SELECT xp, money, btc, clicks, lootbox_pity FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) return ['error' => 'User niet gevonden.'];

    $rankData = getRankData((int)$user['xp'], getRanksArray());
    if ($rankData['level'] < (int)$box['min_rank']) {
        return ['error' => 'Je rank is te laag voor deze box.'];
    }

    // Prijs
    $price = 0;
    if ($paidMethod === 'eur') {
        $price = (int)$box['price_eur'];
        if ((int)$user['money'] < $price) {
            return ['error' => 'Je hebt €' . number_format($price - (int)$user['money'], 0, ',', '.') . ' tekort.'];
        }
    } elseif ($paidMethod === 'btc') {
        $price = (float)$box['price_btc'];
        if ((float)$user['btc'] < $price) {
            return ['error' => 'Je hebt niet genoeg BTC.'];
        }
    } elseif ($paidMethod === 'clicks') {
        $price = (int)$box['price_clicks'];
        if ((int)$user['clicks'] < $price) {
            return ['error' => 'Je hebt niet genoeg clicks.'];
        }
    } else {
        return ['error' => 'Ongeldige betaalmethode.'];
    }

    // Bepaal aantal drops
    $dropCount = rollLootboxDropCount();

    // Roll alle rewards
    $rewards = [];
    $bestRarity = 'common';
    $bestWeight = 999;

    for ($i = 0; $i < $dropCount; $i++) {
        $rarity = rollLootboxRarity($pdo, $userId);
        $reward = rollLootboxReward((int)$box['tier'], $rarity);
        if (!$reward) continue;

        $rewards[] = $reward;

        // Track beste rarity voor pity reset
        foreach (LOOTBOX_RARITIES as $rkey => $rinfo) {
            if ($rkey === $rarity) {
                if ($rinfo['weight'] < $bestWeight) {
                    $bestWeight = $rinfo['weight'];
                    $bestRarity = $rarity;
                }
                break;
            }
        }
    }

    if (empty($rewards)) return ['error' => 'Geen reward beschikbaar.'];

    $pdo->beginTransaction();
    try {
        // Trek prijs af
        if ($paidMethod === 'eur') {
            $pdo->prepare("UPDATE users SET money = money - ? WHERE id = ?")->execute([$price, $userId]);
        } elseif ($paidMethod === 'btc') {
            $pdo->prepare("UPDATE users SET btc = btc - ? WHERE id = ?")->execute([$price, $userId]);
        } else {
            $pdo->prepare("UPDATE users SET clicks = clicks - ? WHERE id = ?")->execute([$price, $userId]);
        }

        // Verwerk elke reward
        foreach ($rewards as $reward) {
            $rv = $reward['value'];
            $rt = $reward['type'];

            if ($rt === 'eur') {
                $pdo->prepare("UPDATE users SET money = money + ? WHERE id = ?")->execute([$rv, $userId]);
            } elseif ($rt === 'btc') {
                $pdo->prepare("UPDATE users SET btc = btc + ?, total_btc_earned = total_btc_earned + ? WHERE id = ?")
                    ->execute([$rv, $rv, $userId]);
            } elseif ($rt === 'clicks') {
                $pdo->prepare("UPDATE users SET clicks = clicks + ?, total_clicks_earned = total_clicks_earned + ? WHERE id = ?")
                    ->execute([$rv, $rv, $userId]);
            } elseif ($rt === 'weapon') {
                $wk = $reward['weapon'];
                $qty = (int)$rv;

                $stmt2 = $pdo->prepare("SELECT id FROM user_click_weapons WHERE user_id = ? AND weapon_key = ? LIMIT 1");
                $stmt2->execute([$userId, $wk]);
                $existing = $stmt2->fetch();

                if ($existing) {
                    $pdo->prepare("UPDATE user_click_weapons SET quantity = quantity + ? WHERE id = ?")
                        ->execute([$qty, $existing['id']]);
                } else {
                    $pdo->prepare("INSERT INTO user_click_weapons (user_id, weapon_key, quantity) VALUES (?, ?, ?)")
                        ->execute([$userId, $wk, $qty]);
                }
            }

            // Log
            $label = $rt === 'weapon'
                ? $reward['label']
                : ($rt === 'eur' ? '€' . number_format($rv, 0, ',', '.')
                  : ($rt === 'btc' ? '₿' . $rv
                  : number_format($rv, 0, ',', '.') . ' clicks'));

            $pdo->prepare("
                INSERT INTO lootbox_opens (user_id, box_key, rarity, reward_type, reward_value, reward_label, paid_method, paid_amount)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([$userId, $boxKey, $reward['rarity'], $rt, $rv, $label, $paidMethod, $price]);
        }

        // Update pity
        $isTopRarity = in_array($bestRarity, ['legendary', 'mythic', 'divine'], true);
        if ($isTopRarity) {
            $pdo->prepare("UPDATE users SET lootbox_pity = 0 WHERE id = ?")->execute([$userId]);
        } else {
            $pdo->prepare("UPDATE users SET lootbox_pity = lootbox_pity + 1 WHERE id = ?")->execute([$userId]);
        }

        // Update stats
        $pdo->prepare("
            UPDATE users
            SET total_lootboxes_opened = total_lootboxes_opened + 1,
                best_lootbox_rarity = CASE
                    WHEN best_lootbox_rarity IS NULL THEN ?
                    WHEN ? = 'divine' THEN 'divine'
                    WHEN ? = 'mythic' AND best_lootbox_rarity NOT IN ('divine') THEN 'mythic'
                    WHEN ? = 'legendary' AND best_lootbox_rarity NOT IN ('divine','mythic') THEN 'legendary'
                    WHEN ? = 'epic' AND best_lootbox_rarity NOT IN ('divine','mythic','legendary') THEN 'epic'
                    ELSE best_lootbox_rarity
                END
            WHERE id = ?
        ")->execute([$bestRarity, $bestRarity, $bestRarity, $bestRarity, $bestRarity, $userId]);

        if (function_exists('logActivity')) {
            logActivity($pdo, $userId, "🎁 {$box['name']} geopend — Beste: " . strtoupper($bestRarity));
        }

        $pdo->commit();

        // Labels voor weergave
        $displayRewards = [];
        foreach ($rewards as $r) {
            if ($r['type'] === 'eur') {
                $display = '€' . number_format($r['value'], 0, ',', '.');
            } elseif ($r['type'] === 'btc') {
                $display = '₿' . (function_exists('formatBtc') ? formatBtc($r['value']) : $r['value']);
            } elseif ($r['type'] === 'clicks') {
                $display = number_format($r['value'], 0, ',', '.') . ' clicks';
            } else {
                $display = number_format($r['value'], 0, ',', '.') . 'x ' . $r['label'];
            }
            $displayRewards[] = [
                'type'   => $r['type'],
                'rarity' => $r['rarity'],
                'label'  => $r['label'],
                'display'=> $display,
            ];
        }

        return [
            'success'      => true,
            'box'          => $box['name'],
            'box_icon'     => $box['icon'],
            'best_rarity'  => $bestRarity,
            'drop_count'   => count($rewards),
            'rewards'      => $displayRewards,
        ];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['error' => 'Systeemfout: ' . $e->getMessage()];
    }
}

function getRecentOpens(PDO $pdo, int $userId, int $limit = 10): array {
    $stmt = $pdo->prepare("
        SELECT lo.*, lt.name AS box_name, lt.icon AS box_icon
        FROM lootbox_opens lo
        JOIN lootbox_types lt ON lt.`key` = lo.box_key
        WHERE lo.user_id = ?
        ORDER BY lo.id DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function getRecentLegendaryDrops(PDO $pdo, int $limit = 10): array {
    $stmt = $pdo->prepare("
        SELECT lo.*, u.username, lt.name AS box_name, lt.icon AS box_icon
        FROM lootbox_opens lo
        JOIN users u ON u.id = lo.user_id
        JOIN lootbox_types lt ON lt.`key` = lo.box_key
        WHERE lo.rarity IN ('legendary', 'mythic', 'divine')
        ORDER BY lo.id DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}