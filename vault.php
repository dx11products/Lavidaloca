<?php
/**
 * Vendetta — Kraak de Kluis (inbox systeem)
 * Je ontvangt VOLLEDIGE codes in je inbox. Bij openen: geld, BTC of clicks.
 *
 * BELANGRIJK: Dit bestand bevat ALLEEN functies en constanten.
 * GEEN require, GEEN redirect, GEEN header() aanroepen.
 */

// ============================================================
// CODE DROP-KANSEN per actie
// ============================================================
const VAULT_DROP_CRIME_WIN    = 25;
const VAULT_DROP_CRIME_FAIL   = 5;
const VAULT_DROP_ATTACK_WIN   = 35;
const VAULT_DROP_ATTACK_LOSS  = 8;
const VAULT_DROP_LIKE         = 20;
const VAULT_DROP_HEIST_WIN    = 60;

// ============================================================
// BELONINGEN PER TIER
// ============================================================
const VAULT_REWARDS = [
    1 => ['eur' => [10000, 50000],        'btc' => [0.005, 0.03],   'clicks' => [10, 50]],
    2 => ['eur' => [50000, 200000],       'btc' => [0.03, 0.15],    'clicks' => [50, 200]],
    3 => ['eur' => [200000, 1000000],     'btc' => [0.15, 0.8],     'clicks' => [200, 1000]],
    4 => ['eur' => [1000000, 5000000],    'btc' => [0.8, 4.0],      'clicks' => [1000, 5000]],
    5 => ['eur' => [5000000, 25000000],   'btc' => [4.0, 20.0],     'clicks' => [5000, 25000]],
];

// ============================================================
// KLUIZEN
// ============================================================
function getAllVaults(PDO $pdo): array {
    $stmt = $pdo->query("SELECT * FROM vaults ORDER BY tier ASC");
    return $stmt->fetchAll();
}

function getVault(PDO $pdo, int $vaultId): ?array {
    $stmt = $pdo->prepare("SELECT * FROM vaults WHERE id = ? LIMIT 1");
    $stmt->execute([$vaultId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function getVaultByKey(PDO $pdo, string $key): ?array {
    $stmt = $pdo->prepare("SELECT * FROM vaults WHERE `key` = ? LIMIT 1");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ?: null;
}

// ============================================================
// CODE GENEREREN
// ============================================================
function generateVaultCode(int $length): string {
    $parts = [];
    for ($i = 0; $i < $length; $i++) {
        $parts[] = random_int(0, 9);
    }
    return implode(' ', $parts);
}

// ============================================================
// INBOX — CODE ONTVANGEN
// ============================================================
function giveRandomVaultCode(PDO $pdo, int $userId, string $source = 'crime'): ?array {
    // Bepaal rank van user
    $stmt = $pdo->prepare("SELECT xp FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $xp = (int)$stmt->fetchColumn();

    // Gebruik getRanksArray() uit game.php
    $ranks = function_exists('getRanksArray') ? getRanksArray() : [];
    $rankData = getRankData($xp, $ranks);
    $level = $rankData['level'];

    // Kies een random kluis waar user toegang tot heeft
    $stmt = $pdo->prepare("SELECT * FROM vaults WHERE min_rank <= ? ORDER BY RAND() LIMIT 1");
    $stmt->execute([$level]);
    $vault = $stmt->fetch();

    if (!$vault) return null;

    $code = generateVaultCode((int)$vault['code_length']);

    try {
        $pdo->prepare("
            INSERT INTO vault_inbox (user_id, vault_id, code, source)
            VALUES (?, ?, ?, ?)
        ")->execute([$userId, $vault['id'], $code, $source]);
    } catch (Exception $e) {
        return null;
    }

    return [
        'vault_id'   => (int)$vault['id'],
        'vault_name' => $vault['name'],
        'vault_icon' => $vault['icon'],
        'code'       => $code,
        'tier'       => (int)$vault['tier'],
    ];
}

// ============================================================
// INBOX — LIJST
// ============================================================
function getInboxItems(PDO $pdo, int $userId, bool $onlyUnopened = false, int $limit = 50): array {
    $sql = "
        SELECT vi.*, v.name AS vault_name, v.icon, v.tier, v.rarity, v.description
        FROM vault_inbox vi
        JOIN vaults v ON v.id = vi.vault_id
        WHERE vi.user_id = ?
    ";
    if ($onlyUnopened) $sql .= " AND vi.opened = 0";
    $sql .= " ORDER BY vi.opened ASC, vi.id DESC LIMIT ?";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function countUnopenedInbox(PDO $pdo, int $userId): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM vault_inbox WHERE user_id = ? AND opened = 0");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

// ============================================================
// KLUIS OPENEN
// ============================================================
function openVaultFromInbox(PDO $pdo, int $userId, int $inboxId): array {
    $stmt = $pdo->prepare("
        SELECT vi.*, v.tier, v.name AS vault_name, v.icon
        FROM vault_inbox vi
        JOIN vaults v ON v.id = vi.vault_id
        WHERE vi.id = ? AND vi.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$inboxId, $userId]);
    $item = $stmt->fetch();

    if (!$item) {
        return ['success' => false, 'error' => 'Code niet gevonden.'];
    }
    if ((int)$item['opened'] === 1) {
        return ['success' => false, 'error' => 'Deze kluis is al geopend.'];
    }

    $tier = (int)$item['tier'];
    if (!isset(VAULT_REWARDS[$tier])) {
        return ['success' => false, 'error' => 'Ongeldig tier.'];
    }

    $rewards = VAULT_REWARDS[$tier];
    $types = ['eur', 'btc', 'clicks'];
    $rewardType = $types[array_rand($types)];

    if ($rewardType === 'eur') {
        $rewardValue = random_int($rewards['eur'][0], $rewards['eur'][1]);
    } elseif ($rewardType === 'btc') {
        $min = (float)$rewards['btc'][0];
        $max = (float)$rewards['btc'][1];
        $rewardValue = $min + (mt_rand() / mt_getrandmax()) * ($max - $min);
        $rewardValue = round($rewardValue, 8);
    } else {
        $rewardValue = random_int($rewards['clicks'][0], $rewards['clicks'][1]);
    }

    $pdo->beginTransaction();
    try {
        if ($rewardType === 'eur') {
            $pdo->prepare("UPDATE users SET money = money + ? WHERE id = ?")
                ->execute([$rewardValue, $userId]);
        } elseif ($rewardType === 'btc') {
            $pdo->prepare("UPDATE users SET btc = btc + ?, total_btc_earned = total_btc_earned + ? WHERE id = ?")
                ->execute([$rewardValue, $rewardValue, $userId]);
        } else {
            $pdo->prepare("UPDATE users SET clicks = clicks + ?, total_clicks_earned = total_clicks_earned + ? WHERE id = ?")
                ->execute([$rewardValue, $rewardValue, $userId]);
        }

        $pdo->prepare("
            UPDATE vault_inbox
            SET opened = 1, opened_at = NOW(), reward_type = ?, reward_value = ?
            WHERE id = ?
        ")->execute([$rewardType, $rewardValue, $inboxId]);

        // Log message
        if ($rewardType === 'eur') {
            $msg = "🔓 {$item['vault_name']} gekraakt — €" . number_format($rewardValue, 0, ',', '.');
        } elseif ($rewardType === 'btc') {
            $btcFormatted = function_exists('formatBtc') ? formatBtc($rewardValue) : (string)$rewardValue;
            $msg = "🔓 {$item['vault_name']} gekraakt — ₿" . $btcFormatted;
        } else {
            $msg = "🔓 {$item['vault_name']} gekraakt — {$rewardValue} clicks";
        }

        if (function_exists('logActivity')) {
            logActivity($pdo, $userId, $msg);
        }

        if (function_exists('checkAchievements')) {
            checkAchievements($pdo, $userId);
        }

        $pdo->commit();

        return [
            'success'      => true,
            'reward_type'  => $rewardType,
            'reward_value' => $rewardValue,
            'vault_name'   => $item['vault_name'],
            'vault_icon'   => $item['icon'],
        ];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'error' => 'Systeemfout: ' . $e->getMessage()];
    }
}

// ============================================================
// GESCHIEDENIS
// ============================================================
function getVaultOpenHistory(PDO $pdo, int $userId, int $limit = 15): array {
    $stmt = $pdo->prepare("
        SELECT vi.*, v.name AS vault_name, v.icon
        FROM vault_inbox vi
        JOIN vaults v ON v.id = vi.vault_id
        WHERE vi.user_id = ? AND vi.opened = 1
        ORDER BY vi.opened_at DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}