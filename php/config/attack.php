<?php
/**
 * Vendetta — Aanval & Gevecht systeem
 *
 * BELANGRIJK: Alleen functies en constanten.
 * GEEN require, GEEN redirect.
 */

// ============================================================
// INSTELLINGEN
// ============================================================
const ATTACK_ENERGY_COST      = 15;   // Energie per aanval
const ATTACK_STEAL_PERCENT    = 12;   // % van slachtoffer's geld
const ATTACK_XP_WIN           = 20;   // XP bij winst
const ATTACK_XP_LOSS          = 3;    // XP bij verlies
const ATTACK_HEALTH_DAMAGE_WIN  = 30; // Schade aan slachtoffer bij winst
const ATTACK_HEALTH_DAMAGE_LOSS = 20; // Schade aan jou bij verlies
const HOSPITAL_DURATION_MIN   = 10;   // Minuten in ziekenhuis na 0 health

// ============================================================
// ZIEKENHUIS
// ============================================================
function isInHospital(array $user): bool {
    if (empty($user['hospital_until'])) return false;
    return strtotime($user['hospital_until']) > time();
}

function hospitalSecondsLeft(array $user): int {
    if (empty($user['hospital_until'])) return 0;
    return max(0, strtotime($user['hospital_until']) - time());
}

// ============================================================
// WIN CHANCE
// ============================================================
/**
 * Kans op winst berekenen (0-100).
 * Basis 50% + verschil tussen aanval en verdediging.
 */
function calcWinChance(int $myAttack, int $enemyDefense): int {
    $chance = 50 + (($myAttack - $enemyDefense) / 2);
    return max(5, min(95, (int)$chance));
}

// ============================================================
// AANVALSKRACHT BEREKENEN
// ============================================================
/**
 * Berekent de effectieve aanvalskracht van een user.
 * Basis 10 + shop wapens + click-wapens (bulk) + munitie bonus.
 */
function getAttackPower(PDO $pdo, int $userId): int {
    $power = 10;

    // Shop items bonus
    if (function_exists('getEquippedBonuses')) {
        $bonuses = getEquippedBonuses($pdo, $userId);
        $power += (int)($bonuses['attack'] ?? 0);
    }

    // Click-wapens (bulk) bonus
    if (function_exists('getClickWeaponBonus')) {
        $power += getClickWeaponBonus($pdo, $userId);
    }

    return $power;
}

/**
 * Berekent de effectieve verdediging van een user.
 */
function getDefensePower(PDO $pdo, int $userId): int {
    $defense = 10;

    if (function_exists('getEquippedBonuses')) {
        $bonuses = getEquippedBonuses($pdo, $userId);
        $defense += (int)($bonuses['defense'] ?? 0);
    }

    return $defense;
}