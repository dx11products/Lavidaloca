<?php
/**
 * Vendetta — Progressieve Jackpots
 */

const JACKPOT_CONTRIBUTION = 0.05;
const JACKPOT_TIERS = ['mini', 'minor', 'major', 'grand'];

function getAllJackpots(PDO $pdo): array {
    $stmt = $pdo->query("SELECT * FROM jackpot_pools ORDER BY min_amount ASC");
    return $stmt->fetchAll();
}

function getJackpot(PDO $pdo, string $key): ?array {
    $stmt = $pdo->prepare("SELECT * FROM jackpot_pools WHERE `key` = ? LIMIT 1");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function contributeToJackpots(PDO $pdo, int $bet): void {
    $contribution = (int)floor($bet * JACKPOT_CONTRIBUTION);
    if ($contribution <= 0) return;

    $splits = ['mini' => 0.40, 'minor' => 0.30, 'major' => 0.20, 'grand' => 0.10];

    foreach ($splits as $key => $pct) {
        $add = (int)floor($contribution * $pct);
        if ($add <= 0) continue;

        $pdo->prepare("UPDATE jackpot_pools SET current_amount = current_amount + ? WHERE `key` = ?")
            ->execute([$add, $key]);
    }
}

function rollJackpot(PDO $pdo, int $userId, string $gameKey, int $bet): array {
    $tier = null;
    foreach (JACKPOT_TIERS as $key) {
        $jp = getJackpot($pdo, $key);
        if (!$jp) continue;
        if ($bet >= (int)$jp['min_bet'] && $bet <= (int)$jp['max_bet']) {
            $tier = $jp;
            break;
        }
    }

    if (!$tier) return ['won' => false];

    $chance = (int)$tier['hit_chance'];
    if ($chance <= 0) return ['won' => false];

    $roll = random_int(1, $chance);
    if ($roll !== 1) return ['won' => false];

    $amount = (int)$tier['current_amount'];
    $newAmount = (int)$tier['min_amount'];

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE users SET money = money + ? WHERE id = ?")
            ->execute([$amount, $userId]);

        $pdo->prepare("
            UPDATE jackpot_pools
            SET current_amount = ?,
                last_won_by = ?,
                last_won_at = NOW(),
                last_won_amount = ?,
                total_won = total_won + ?,
                win_count = win_count + 1
            WHERE `key` = ?
        ")->execute([$newAmount, $userId, $amount, $amount, $tier['key']]);

        $pdo->prepare("
            INSERT INTO jackpot_wins (jackpot_key, user_id, amount, game_key, bet)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([$tier['key'], $userId, $amount, $gameKey, $bet]);

        if (function_exists('logActivity')) {
            logActivity($pdo, $userId, "🎰 JACKPOT! Won {$tier['name']} — €" . number_format($amount, 0, ',', '.'));
        }

        try {
            $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $username = $stmt->fetchColumn();

            if (function_exists('notify') && $username) {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE id != ? AND last_login > NOW() - INTERVAL 7 DAY LIMIT 100");
                $stmt->execute([$userId]);
                foreach ($stmt->fetchAll() as $u) {
                    notify($pdo, (int)$u['id'],
                        "🎰 {$username} heeft de {$tier['name']} gewonnen: €" . number_format($amount, 0, ',', '.') . "!",
                        '🎰');
                }
            }
        } catch (Exception $e) {}

        $pdo->commit();

        return [
            'won'    => true,
            'tier'   => $tier['key'],
            'name'   => $tier['name'],
            'icon'   => $tier['icon'],
            'color'  => $tier['color'],
            'amount' => $amount,
        ];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['won' => false];
    }
}

function getJackpotWins(PDO $pdo, int $limit = 10): array {
    $stmt = $pdo->prepare("
        SELECT jw.*, u.username, jp.name AS jackpot_name, jp.icon, jp.color
        FROM jackpot_wins jw
        JOIN users u ON u.id = jw.user_id
        JOIN jackpot_pools jp ON jp.`key` = jw.jackpot_key
        ORDER BY jw.id DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function getUserJackpotWins(PDO $pdo, int $userId, int $limit = 10): array {
    $stmt = $pdo->prepare("
        SELECT jw.*, jp.name AS jackpot_name, jp.icon, jp.color
        FROM jackpot_wins jw
        JOIN jackpot_pools jp ON jp.`key` = jw.jackpot_key
        WHERE jw.user_id = ?
        ORDER BY jw.id DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function formatJackpot(int $amount): string {
    if ($amount >= 1000000000) return '€' . number_format($amount / 1000000000, 2, ',', '.') . ' mld';
    if ($amount >= 1000000)    return '€' . number_format($amount / 1000000, 2, ',', '.') . ' M';
    if ($amount >= 1000)       return '€' . number_format($amount / 1000, 0, ',', '.') . 'K';
    return '€' . number_format($amount, 0, ',', '.');
}