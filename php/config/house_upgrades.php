<?php
/**
 * Vendetta — Familie huis-upgrades (plantplekken)
 * BELANGRIJK: Alleen functies en constanten.
 */

const HOUSE_GROW_MAX_LEVEL = 15;

// Plantplekken per level (vermenigvuldigd met huis grow_slots)
const FAMILY_GROW_SLOTS = [
    1  => 5,
    2  => 10,
    3  => 25,
    4  => 60,
    5  => 150,
    6  => 400,
    7  => 1000,
    8  => 2500,
    9  => 6500,
    10 => 20000,
    11 => 100000,
    12 => 500000,
    13 => 2500000,
    14 => 15000000,
    15 => 100000000,
];

// Upgrade kosten per level (naar dat level)
const FAMILY_GROW_COSTS = [
    2  => 500000,
    3  => 2000000,
    4  => 8000000,
    5  => 30000000,
    6  => 120000000,
    7  => 500000000,
    8  => 2000000000,
    9  => 8000000000,
    10 => 30000000000,
    11 => 150000000000,
    12 => 750000000000,
    13 => 4000000000000,
    14 => 25000000000000,
    15 => 150000000000000,
];

/**
 * Haal familie grow level op (of 1 als geen familie).
 */
function getFamilyGrowLevel(PDO $pdo, int $userId): int {
    $fam = getUserFamily($pdo, $userId);
    if (!$fam) return 1;
    return max(1, (int)($fam['house_grow_level'] ?? 1));
}

/**
 * Aantal plantplekken dat een user krijgt in een huis.
 * Berekening: familie slots (per level) OF huis basis als geen familie.
 */
function getEffectiveGrowSlots(PDO $pdo, int $userId, array $house): int {
    $famLevel = getFamilyGrowLevel($pdo, $userId);

    // Zonder familie → gebruik huis standaard
    if ($famLevel <= 1) {
        return (int)$house['grow_slots'];
    }

    // Met familie → familie slots zijn leidend
    return FAMILY_GROW_SLOTS[$famLevel] ?? (int)$house['grow_slots'];
}

/**
 * Volgende upgrade info.
 */
function getNextGrowUpgrade(PDO $pdo, int $userId): ?array {
    $fam = getUserFamily($pdo, $userId);
    if (!$fam) return null;

    $currentLevel = max(1, (int)($fam['house_grow_level'] ?? 1));
    if ($currentLevel >= HOUSE_GROW_MAX_LEVEL) return null;

    $nextLevel = $currentLevel + 1;
    $cost = FAMILY_GROW_COSTS[$nextLevel] ?? 0;

    return [
        'level'         => $nextLevel,
        'cost'          => $cost,
        'current_slots' => FAMILY_GROW_SLOTS[$currentLevel] ?? 0,
        'new_slots'     => FAMILY_GROW_SLOTS[$nextLevel] ?? 0,
    ];
}

/**
 * Upgrade familie grow level.
 */
function upgradeFamilyGrowLevel(PDO $pdo, int $userId): array {
    $fam = getUserFamily($pdo, $userId);
    if (!$fam) return ['error' => 'Je zit niet in een familie.'];
    if ($fam['member_rank'] !== 'baas') return ['error' => 'Alleen de baas kan upgraden.'];

    $currentLevel = max(1, (int)($fam['house_grow_level'] ?? 1));
    if ($currentLevel >= HOUSE_GROW_MAX_LEVEL) {
        return ['error' => 'Maximaal level bereikt.'];
    }

    $nextLevel = $currentLevel + 1;
    $cost = FAMILY_GROW_COSTS[$nextLevel] ?? null;
    if (!$cost) return ['error' => 'Upgrade niet beschikbaar.'];

    if ((int)$fam['money'] < $cost) {
        return ['error' => 'De familiekas heeft €' .
                          number_format($cost - (int)$fam['money'], 0, ',', '.') . ' tekort.'];
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE families SET money = money - ?, house_grow_level = ? WHERE id = ?")
            ->execute([$cost, $nextLevel, $fam['id']]);

        $newSlots = FAMILY_GROW_SLOTS[$nextLevel] ?? 0;

        if (function_exists('notify')) {
            $stmt = $pdo->prepare("SELECT user_id FROM family_members WHERE family_id = ?");
            $stmt->execute([$fam['id']]);
            foreach ($stmt->fetchAll() as $m) {
                notify($pdo, (int)$m['user_id'],
                    "🌿 Plantplekken geüpgraded naar level {$nextLevel} (" .
                    number_format($newSlots, 0, ',', '.') . " planten per huis)!",
                    '🌿');
            }
        }

        if (function_exists('logActivity')) {
            logActivity($pdo, $userId,
                "🌿 Familiekas: grow upgrade naar level {$nextLevel} (€" .
                number_format($cost, 0, ',', '.') . ")");
        }

        $pdo->commit();

        return [
            'success'    => true,
            'level'      => $nextLevel,
            'cost'       => $cost,
            'new_slots'  => $newSlots,
        ];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['error' => 'Upgrade mislukt.'];
    }
}