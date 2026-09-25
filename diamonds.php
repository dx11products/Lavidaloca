<?php
/**
 * Vendetta — Diamanten (premium currency)
 * BELANGRIJK: Alleen functies en constanten.
 */

const DIAMOND_EUR_RATE = 100000;  // 1 💎 = €100.000

function getUserDiamonds(PDO $pdo, int $userId): int {
    $stmt = $pdo->prepare("SELECT diamonds FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

function addDiamonds(PDO $pdo, int $userId, int $amount, string $type = 'reward', string $desc = ''): void {
    if ($amount <= 0) return;

    $pdo->prepare("
        UPDATE users
        SET diamonds = diamonds + ?, total_diamonds_earned = total_diamonds_earned + ?
        WHERE id = ?
    ")->execute([$amount, $amount, $userId]);

    try {
        $pdo->prepare("
            INSERT INTO diamond_transactions (user_id, amount, type, description)
            VALUES (?, ?, ?, ?)
        ")->execute([$userId, $amount, $type, $desc]);
    } catch (Exception $e) {}
}

function removeDiamonds(PDO $pdo, int $userId, int $amount, string $type = 'spend', string $desc = ''): bool {
    if ($amount <= 0) return false;

    $stmt = $pdo->prepare("SELECT diamonds FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $have = (int)$stmt->fetchColumn();

    if ($have < $amount) return false;

    $pdo->prepare("
        UPDATE users
        SET diamonds = diamonds - ?, total_diamonds_spent = total_diamonds_spent + ?
        WHERE id = ?
    ")->execute([$amount, $amount, $userId]);

    try {
        $pdo->prepare("
            INSERT INTO diamond_transactions (user_id, amount, type, description)
            VALUES (?, ?, ?, ?)
        ")->execute([$userId, -$amount, $type, $desc]);
    } catch (Exception $e) {}

    return true;
}

function getDiamondTransactions(PDO $pdo, int $userId, int $limit = 20): array {
    $stmt = $pdo->prepare("
        SELECT * FROM diamond_transactions
        WHERE user_id = ?
        ORDER BY id DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function buyMoneyWithDiamonds(PDO $pdo, int $userId, int $diamonds): array {
    if ($diamonds < 1) return ['error' => 'Minimum 1 diamant'];

    $have = getUserDiamonds($pdo, $userId);
    if ($have < $diamonds) return ['error' => 'Niet genoeg diamanten'];

    $eur = $diamonds * DIAMOND_EUR_RATE;

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE users SET money = money + ? WHERE id = ?")->execute([$eur, $userId]);
        removeDiamonds($pdo, $userId, $diamonds, 'buy_money', "€" . number_format($eur, 0, ',', '.'));
        if (function_exists('logActivity')) {
            logActivity($pdo, $userId, "💎 {$diamonds} diamanten → €" . number_format($eur, 0, ',', '.'));
        }
        $pdo->commit();

        return ['success' => true, 'eur' => $eur, 'diamonds' => $diamonds];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['error' => 'Aankoop mislukt'];
    }
}

function getDiamondStats(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare("SELECT diamonds, total_diamonds_earned, total_diamonds_spent FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $u = $stmt->fetch();
    return [
        'diamonds'     => (int)($u['diamonds'] ?? 0),
        'total_earned' => (int)($u['total_diamonds_earned'] ?? 0),
        'total_spent'  => (int)($u['total_diamonds_spent'] ?? 0),
    ];
}