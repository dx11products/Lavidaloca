<?php
/**
 * Vendetta — Politie & Gevangenis systeem
 */

// ============================================================
// INSTELLINGEN
// ============================================================
const PRISON_RELEASE_COST_PER_MIN = 100;   // €100 per minuut om vrij te kopen
const CORRUPTION_MAX              = 100;   // max smeergeld niveau
const CORRUPTION_REDUCTION        = 0.005; // 0.5% pakkansverlaging per punt

// ============================================================
// POLITIE INFO PER LAND
// ============================================================
/**
 * Haal politie-info op voor een land.
 */
function getPoliceInfo(PDO $pdo, string $countryKey): array {
    try {
        $stmt = $pdo->prepare("SELECT * FROM police_activity WHERE country_key = ? LIMIT 1");
        $stmt->execute([$countryKey]);
        $row = $stmt->fetch();
        if ($row) return $row;
    } catch (Exception $e) {
        // Tabel bestaat niet — gebruik fallback
    }
    return ['risk_level' => 1, 'fine_per_drug' => 10, 'prison_minutes_per_drug' => 1];
}

// ============================================================
// PAKKANS BEREKENEN
// ============================================================
/**
 * Bereken pakkans bij reizen met drugs.
 * Basis = risk_level * 4% + (drugs / 25)
 * Corruption verlaagt dit met 0.5% per punt.
 */
function calculateCatchChance(int $drugCount, int $riskLevel, int $corruption): int {
    if ($drugCount <= 0) return 0;

    $base = ($riskLevel * 4) + (int)floor($drugCount / 25);

    // Corruption verlaagt (0.5% per punt)
    $reduction = (int)floor($corruption * CORRUPTION_REDUCTION);

    $chance = $base - $reduction;

    return max(0, min(95, $chance));
}

// ============================================================
// STRAF BEREKENEN
// ============================================================
/**
 * Bepaal boete en celstraf voor een user.
 */
function calculatePunishment(PDO $pdo, int $drugCount, string $countryKey): array {
    $police = getPoliceInfo($pdo, $countryKey);
    $fine    = $drugCount * (int)$police['fine_per_drug'];
    $minutes = max(5, $drugCount * (int)$police['prison_minutes_per_drug']);
    return ['fine' => $fine, 'minutes' => $minutes];
}

// ============================================================
// GEVANGENIS STATUS
// ============================================================
/**
 * Is user in de gevangenis?
 */
function isInPrison(array $user): bool {
    if (empty($user['in_prison'])) return false;
    if (empty($user['prison_until'])) return false;
    return strtotime($user['prison_until']) > time();
}

/**
 * Seconden tot vrijlating.
 */
function prisonSecondsLeft(array $user): int {
    if (empty($user['prison_until'])) return 0;
    return max(0, strtotime($user['prison_until']) - time());
}

/**
 * Kosten om vrijgekocht te worden.
 */
function prisonReleaseCost(array $user): int {
    $seconds = prisonSecondsLeft($user);
    $timeCost = (int)ceil($seconds / 60) * PRISON_RELEASE_COST_PER_MIN;
    $fineCost = (int)($user['prison_fine'] ?? 0);
    return $timeCost + $fineCost;
}

/**
 * Stuur user naar de gevangenis.
 */
function sendToPrison(PDO $pdo, int $userId, int $minutes, int $fine, string $reason): void {
    $until = date('Y-m-d H:i:s', time() + ($minutes * 60));
    $pdo->prepare("
        UPDATE users
        SET in_prison = 1, prison_until = ?, prison_fine = ?, prison_reason = ?,
            total_arrests = COALESCE(total_arrests, 0) + 1
        WHERE id = ?
    ")->execute([$until, $fine, $reason, $userId]);
}