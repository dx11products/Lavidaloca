<?php
/**
 * Vendetta — Handmatige kogelproductie met dagelijks limiet
 * BELANGRIJK: Alleen functies en constanten.
 */

// Kost per kogel
const BULLET_COST_PER_UNIT = 5;

// Limiet per level
const BULLET_LIMITS = [
    1 => 5000,
    2 => 15000,
    3 => 50000,
    4 => 150000,
    5 => 500000,
    6 => 1500000,
    7 => 5000000,
];

// Upgrade kosten per level (naar dat level)
const BULLET_UPGRADE_COSTS = [
    2 => 1000000,
    3 => 5000000,
    4 => 25000000,
    5 => 100000000,
    6 => 500000000,
    7 => 2500000000,
];

const BULLET_MAX_LEVEL = 7;

/**
 * Haal dagelijkse limiet op voor user (rekening houdend met familie-level).
 */
function getUserBulletLimit(PDO $pdo, int $userId): int {
    // Familie level heeft voorrang
    $fam = getUserFamily($pdo, $userId);
    if ($fam) {
        $famLevel = max(1, (int)($fam['bullet_limit_level'] ?? 1));
        return BULLET_LIMITS[$famLevel] ?? BULLET_LIMITS[1];
    }

    // Anders persoonlijk level
    $stmt = $pdo->prepare("SELECT bullet_limit_level FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $lvl = max(1, (int)$stmt->fetchColumn());
    return BULLET_LIMITS[$lvl] ?? BULLET_LIMITS[1];
}

/**
 * Check of daily reset nodig is.
 */
function checkBulletDailyReset(PDO $pdo, int $userId): void {
    $stmt = $pdo->prepare("SELECT bullet_last_reset FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $lastReset = $stmt->fetchColumn();
    $today = date('Y-m-d');

    if ($lastReset !== $today) {
        $pdo->prepare("
            UPDATE users
            SET bullet_used_today = 0, bullet_last_reset = ?
            WHERE id = ?
        ")->execute([$today, $userId]);
    }
}

/**
 * Haal huidige status op.
 */
function getBulletStatus(PDO $pdo, int $userId): array {
    checkBulletDailyReset($pdo, $userId);

    $limit = getUserBulletLimit($pdo, $userId);

    $stmt = $pdo->prepare("SELECT bullet_used_today FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $used = (int)$stmt->fetchColumn();

    $remaining = max(0, $limit - $used);

    return [
        'limit'     => $limit,
        'used'      => $used,
        'remaining' => $remaining,
        'percent'   => $limit > 0 ? round(($used / $limit) * 100) : 0,
    ];
}

/**
 * Produceer kogels.
 */
function produceBullets(PDO $pdo, int $userId, int $amount): array {
    if ($amount < 1) {
        return ['error' => 'Voer minimaal 1 kogel in.'];
    }

    checkBulletDailyReset($pdo, $userId);

    $status = getBulletStatus($pdo, $userId);

    if ($amount > $status['remaining']) {
        return ['error' => 'Je dagelijkse limiet is ' . number_format($status['limit'], 0, ',', '.') .
                          '. Je kunt nog ' . number_format($status['remaining'], 0, ',', '.') . ' produceren vandaag.'];
    }

    $totalCost = $amount * BULLET_COST_PER_UNIT;

    // Check geld
    $stmt = $pdo->prepare("SELECT money FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $money = (int)$stmt->fetchColumn();

    if ($money < $totalCost) {
        return ['error' => 'Je hebt niet genoeg geld. Kosten: €' . number_format($totalCost, 0, ',', '.')];
    }

    $pdo->beginTransaction();
    try {
        // Geld af
        $pdo->prepare("UPDATE users SET money = money - ?, bullet_used_today = bullet_used_today + ? WHERE id = ?")
            ->execute([$totalCost, $amount, $userId]);

        // Voeg kogels toe (via bestaande addAmmo functie)
        if (function_exists('addAmmo')) {
            addAmmo($pdo, $userId, 'pistool', $amount);
        }

        // Log
        $pdo->prepare("
            INSERT INTO bullet_production_log (user_id, amount, cost)
            VALUES (?, ?, ?)
        ")->execute([$userId, $amount, $totalCost]);

        if (function_exists('logActivity')) {
            logActivity($pdo, $userId,
                "🔫 " . number_format($amount, 0, ',', '.') . " kogels geproduceerd voor €" .
                number_format($totalCost, 0, ',', '.'));
        }

        $pdo->commit();

        return [
            'success' => true,
            'amount'  => $amount,
            'cost'    => $totalCost,
        ];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['error' => 'Productie mislukt: ' . $e->getMessage()];
    }
}

/**
 * Upgrade familie-limiet.
 */
function upgradeFamilyBulletLimit(PDO $pdo, int $userId): array {
    $fam = getUserFamily($pdo, $userId);
    if (!$fam) {
        return ['error' => 'Je zit niet in een familie.'];
    }
    if ($fam['member_rank'] !== 'baas') {
        return ['error' => 'Alleen de baas kan de limiet upgraden.'];
    }

    $currentLevel = max(1, (int)($fam['bullet_limit_level'] ?? 1));

    if ($currentLevel >= BULLET_MAX_LEVEL) {
        return ['error' => 'Maximaal level bereikt.'];
    }

    $nextLevel = $currentLevel + 1;
    $cost = BULLET_UPGRADE_COSTS[$nextLevel] ?? null;

    if (!$cost) {
        return ['error' => 'Upgrade niet beschikbaar.'];
    }

    if ((int)$fam['money'] < $cost) {
        return ['error' => 'De familiekas heeft niet genoeg geld. Je hebt €' .
                          number_format($cost - (int)$fam['money'], 0, ',', '.') . ' tekort.'];
    }

    $pdo->beginTransaction();
    try {
        // Kas af
        $pdo->prepare("UPDATE families SET money = money - ?, bullet_limit_level = ? WHERE id = ?")
            ->execute([$cost, $nextLevel, $fam['id']]);

        // Notify alle leden
        if (function_exists('notify')) {
            $stmt = $pdo->prepare("SELECT user_id FROM family_members WHERE family_id = ?");
            $stmt->execute([$fam['id']]);
            foreach ($stmt->fetchAll() as $m) {
                notify($pdo, (int)$m['user_id'],
                    "🎯 Kogel-limiet geüpgraded naar level {$nextLevel} (" .
                    number_format(BULLET_LIMITS[$nextLevel], 0, ',', '.') . " kogels/dag)!",
                    '🎯');
            }
        }

        if (function_exists('logActivity')) {
            logActivity($pdo, $userId,
                "🎯 Familiekas gebruikt om kogel-limiet te upgraden naar level {$nextLevel} (€" .
                number_format($cost, 0, ',', '.') . ")");
        }

        $pdo->commit();

        return [
            'success'     => true,
            'level'       => $nextLevel,
            'cost'        => $cost,
            'new_limit'   => BULLET_LIMITS[$nextLevel],
        ];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['error' => 'Upgrade mislukt: ' . $e->getMessage()];
    }
}

/**
 * Recente productie van user.
 */
function getRecentBulletProduction(PDO $pdo, int $userId, int $limit = 10): array {
    $stmt = $pdo->prepare("
        SELECT * FROM bullet_production_log
        WHERE user_id = ?
        ORDER BY id DESC LIMIT ?
    ");
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Info over volgend upgrade level.
 */
function getNextBulletUpgrade(PDO $pdo, int $userId): ?array {
    $fam = getUserFamily($pdo, $userId);
    if (!$fam) return null;

    $currentLevel = max(1, (int)($fam['bullet_limit_level'] ?? 1));
    if ($currentLevel >= BULLET_MAX_LEVEL) return null;

    $nextLevel = $currentLevel + 1;
    return [
        'level'      => $nextLevel,
        'cost'       => BULLET_UPGRADE_COSTS[$nextLevel] ?? 0,
        'new_limit'  => BULLET_LIMITS[$nextLevel] ?? 0,
        'current_limit' => BULLET_LIMITS[$currentLevel] ?? 0,
    ];
}