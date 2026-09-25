<?php
// Bank instellingen

const BANK_INTEREST_RATE = 0.02;      // 2% rente per dag
const BANK_MIN_DEPOSIT   = 100;        // Minimum storting
const BANK_MAX_WITHDRAW  = 10000000;   // Maximum opname per keer
const BANK_ATTACK_COST   = 25;         // Energie om bank te overvallen

/**
 * Bereken en pas rente toe op bankgeld.
 * Wordt automatisch aangeroepen door currentUser().
 */
function applyInterest(PDO $pdo, array $user): array {
    $today = date('Y-m-d');
    if ($user['last_interest'] === $today) {
        return $user;
    }

    if ((int)$user['bank_money'] > 0) {
        $interest = (int)floor($user['bank_money'] * BANK_INTEREST_RATE);
        if ($interest > 0) {
            $pdo->prepare("
                UPDATE users
                SET bank_money = bank_money + ?, last_interest = ?
                WHERE id = ?
            ")->execute([$interest, $today, $user['id']]);

            logActivity($pdo, $user['id'],
                "🏦 Rente ontvangen — €" . number_format($interest, 0, ',', '.'));

            $user['bank_money'] += $interest;
        }
    }

    $pdo->prepare("UPDATE users SET last_interest = ? WHERE id = ?")
        ->execute([$today, $user['id']]);
    $user['last_interest'] = $today;

    return $user;
}

/**
 * Stort geld naar bank.
 */
function depositToBank(PDO $pdo, int $userId, int $amount): array {
    if ($amount < BANK_MIN_DEPOSIT) {
        return ['error' => 'Minimum storting is €' . number_format(BANK_MIN_DEPOSIT, 0, ',', '.')];
    }
    $stmt = $pdo->prepare("SELECT money FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $money = (int)$stmt->fetchColumn();
    if ($money < $amount) {
        return ['error' => 'Je hebt niet genoeg cash.'];
    }
    $pdo->prepare("UPDATE users SET money = money - ?, bank_money = bank_money + ? WHERE id = ?")
        ->execute([$amount, $amount, $userId]);
    return ['success' => "€" . number_format($amount, 0, ',', '.') . " gestort"];
}

/**
 * Geld opnemen van bank.
 */
function withdrawFromBank(PDO $pdo, int $userId, int $amount): array {
    if ($amount <= 0) {
        return ['error' => 'Ongeldig bedrag.'];
    }
    if ($amount > BANK_MAX_WITHDRAW) {
        return ['error' => 'Maximum opname is €' . number_format(BANK_MAX_WITHDRAW, 0, ',', '.')];
    }
    $stmt = $pdo->prepare("SELECT bank_money FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $bank = (int)$stmt->fetchColumn();
    if ($bank < $amount) {
        return ['error' => 'Je hebt niet genoeg op de bank.'];
    }
    $pdo->prepare("UPDATE users SET money = money + ?, bank_money = bank_money - ? WHERE id = ?")
        ->execute([$amount, $amount, $userId]);
    return ['success' => "€" . number_format($amount, 0, ',', '.') . " opgenomen"];
}