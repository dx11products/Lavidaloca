<?php
/**
 * Vendetta — Familie upgrades (crime & heist succes-kans)
 * BELANGRIJK: Alleen functies en constanten.
 */

const FAMILY_UPGRADE_MAX_LEVEL = 10;

// Bonus per level
const CRIME_SUCCESS_BONUS_PER_LEVEL = 1;  // +1% per level
const HEIST_SUCCESS_BONUS_PER_LEVEL = 2;  // +2% per level

// Upgrade kosten per level (naar dat level)
const CRIME_UPGRADE_COSTS = [
    2  => 500000,
    3  => 2000000,
    4  => 5000000,
    5  => 15000000,
    6  => 40000000,
    7  => 100000000,
    8  => 250000000,
    9  => 600000000,
    10 => 1500000000,
];

const HEIST_UPGRADE_COSTS = [
    2  => 1000000,
    3  => 4000000,
    4  => 10000000,
    5  => 30000000,
    6  => 80000000,
    7  => 200000000,
    8  => 500000000,
    9  => 1200000000,
    10 => 3000000000,
];

// ============================================================
// BONUS BEREKENEN
// ============================================================
/**
 * Haal de crime-succes bonus op voor een user.
 */
function getCrimeSuccessBonus(PDO $pdo, int $userId): int {
    $fam = getUserFamily($pdo, $userId);
    if (!$fam) return 0;

    $lvl = max(1, (int)($fam['crime_success_level'] ?? 1));
    return ($lvl - 1) * CRIME_SUCCESS_BONUS_PER_LEVEL;
}

/**
 * Haal de heist-succes bonus op voor een user.
 */
function getHeistSuccessBonus(PDO $pdo, int $userId): int {
    $fam = getUserFamily($pdo, $userId);
    if (!$fam) return 0;

    $lvl = max(1, (int)($fam['heist_success_level'] ?? 1));
    return ($lvl - 1) * HEIST_SUCCESS_BONUS_PER_LEVEL;
}

// ============================================================
// UPGRADE INFO
// ============================================================
/**
 * Volgende crime upgrade info.
 */
function getNextCrimeUpgrade(PDO $pdo, int $userId): ?array {
    $fam = getUserFamily($pdo, $userId);
    if (!$fam) return null;

    $currentLevel = max(1, (int)($fam['crime_success_level'] ?? 1));
    if ($currentLevel >= FAMILY_UPGRADE_MAX_LEVEL) return null;

    $nextLevel = $currentLevel + 1;
    $cost = CRIME_UPGRADE_COSTS[$nextLevel] ?? 0;

    return [
        'type'         => 'crime',
        'level'        => $nextLevel,
        'cost'         => $cost,
        'current_bonus' => ($currentLevel - 1) * CRIME_SUCCESS_BONUS_PER_LEVEL,
        'new_bonus'    => ($nextLevel - 1) * CRIME_SUCCESS_BONUS_PER_LEVEL,
    ];
}

/**
 * Volgende heist upgrade info.
 */
function getNextHeistUpgrade(PDO $pdo, int $userId): ?array {
    $fam = getUserFamily($pdo, $userId);
    if (!$fam) return null;

    $currentLevel = max(1, (int)($fam['heist_success_level'] ?? 1));
    if ($currentLevel >= FAMILY_UPGRADE_MAX_LEVEL) return null;

    $nextLevel = $currentLevel + 1;
    $cost = HEIST_UPGRADE_COSTS[$nextLevel] ?? 0;

    return [
        'type'         => 'heist',
        'level'        => $nextLevel,
        'cost'         => $cost,
        'current_bonus' => ($currentLevel - 1) * HEIST_SUCCESS_BONUS_PER_LEVEL,
        'new_bonus'    => ($nextLevel - 1) * HEIST_SUCCESS_BONUS_PER_LEVEL,
    ];
}

// ============================================================
// UPGRADE UITVOEREN
// ============================================================
/**
 * Upgrade crime succes via familiekas.
 */
function upgradeFamilyCrimeSuccess(PDO $pdo, int $userId): array {
    $fam = getUserFamily($pdo, $userId);
    if (!$fam) return ['error' => 'Je zit niet in een familie.'];
    if ($fam['member_rank'] !== 'baas') return ['error' => 'Alleen de baas kan upgraden.'];

    $currentLevel = max(1, (int)($fam['crime_success_level'] ?? 1));
    if ($currentLevel >= FAMILY_UPGRADE_MAX_LEVEL) {
        return ['error' => 'Maximaal level bereikt.'];
    }

    $nextLevel = $currentLevel + 1;
    $cost = CRIME_UPGRADE_COSTS[$nextLevel] ?? null;
    if (!$cost) return ['error' => 'Upgrade niet beschikbaar.'];

    if ((int)$fam['money'] < $cost) {
        return ['error' => 'De familiekas heeft €' .
                          number_format($cost - (int)$fam['money'], 0, ',', '.') . ' tekort.'];
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE families SET money = money - ?, crime_success_level = ? WHERE id = ?")
            ->execute([$cost, $nextLevel, $fam['id']]);

        $newBonus = ($nextLevel - 1) * CRIME_SUCCESS_BONUS_PER_LEVEL;

        if (function_exists('notify')) {
            $stmt = $pdo->prepare("SELECT user_id FROM family_members WHERE family_id = ?");
            $stmt->execute([$fam['id']]);
            foreach ($stmt->fetchAll() as $m) {
                notify($pdo, (int)$m['user_id'],
                    "🎯 Crime succes geüpgraded naar level {$nextLevel} (+{$newBonus}% kans voor alle leden)!",
                    '🎯');
            }
        }

        if (function_exists('logActivity')) {
            logActivity($pdo, $userId,
                "🎯 Familiekas: crime succes upgrade naar level {$nextLevel} (€" .
                number_format($cost, 0, ',', '.') . ")");
        }

        $pdo->commit();

        return [
            'success'    => true,
            'level'      => $nextLevel,
            'cost'       => $cost,
            'new_bonus'  => $newBonus,
        ];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['error' => 'Upgrade mislukt.'];
    }
}

/**
 * Upgrade heist succes via familiekas.
 */
function upgradeFamilyHeistSuccess(PDO $pdo, int $userId): array {
    $fam = getUserFamily($pdo, $userId);
    if (!$fam) return ['error' => 'Je zit niet in een familie.'];
    if ($fam['member_rank'] !== 'baas') return ['error' => 'Alleen de baas kan upgraden.'];

    $currentLevel = max(1, (int)($fam['heist_success_level'] ?? 1));
    if ($currentLevel >= FAMILY_UPGRADE_MAX_LEVEL) {
        return ['error' => 'Maximaal level bereikt.'];
    }

    $nextLevel = $currentLevel + 1;
    $cost = HEIST_UPGRADE_COSTS[$nextLevel] ?? null;
    if (!$cost) return ['error' => 'Upgrade niet beschikbaar.'];

    if ((int)$fam['money'] < $cost) {
        return ['error' => 'De familiekas heeft €' .
                          number_format($cost - (int)$fam['money'], 0, ',', '.') . ' tekort.'];
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE families SET money = money - ?, heist_success_level = ? WHERE id = ?")
            ->execute([$cost, $nextLevel, $fam['id']]);

        $newBonus = ($nextLevel - 1) * HEIST_SUCCESS_BONUS_PER_LEVEL;

        if (function_exists('notify')) {
            $stmt = $pdo->prepare("SELECT user_id FROM family_members WHERE family_id = ?");
            $stmt->execute([$fam['id']]);
            foreach ($stmt->fetchAll() as $m) {
                notify($pdo, (int)$m['user_id'],
                    "🏴 Heist succes geüpgraded naar level {$nextLevel} (+{$newBonus}% kans voor alle leden)!",
                    '🏴');
            }
        }

        if (function_exists('logActivity')) {
            logActivity($pdo, $userId,
                "🏴 Familiekas: heist succes upgrade naar level {$nextLevel} (€" .
                number_format($cost, 0, ',', '.') . ")");
        }

        $pdo->commit();

        return [
            'success'    => true,
            'level'      => $nextLevel,
            'cost'       => $cost,
            'new_bonus'  => $newBonus,
        ];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['error' => 'Upgrade mislukt.'];
    }
}