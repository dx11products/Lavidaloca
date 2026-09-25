<?php
/**
 * Vendetta — Casino systeem
 * Spelers verliezen → eigenaar van casino-asset verdient.
 * Spelers winnen → Vendetta betaalt uit.
 */

// ============================================================
// INSTELLINGEN
// ============================================================
const CASINO_HOUSE_SHARE  = 0.80;  // 80% van verliezen naar eigenaar, 20% naar systeem
const CASINO_MIN_BET      = 1000;
const CASINO_MAX_BET      = 50000000;

// ============================================================
// KOPPELING GAME → ASSET KEY
// ============================================================
const CASINO_GAMES = [
    'blackjack' => [
        'name'   => 'Blackjack',
        'icon'   => '🃏',
        'asset'  => 'blackjack_table',
        'color'  => '#4a9dff',
        'desc'   => 'Speel 21 tegen de dealer. Hoe dichterbij, hoe beter.',
    ],
    'poker' => [
        'name'   => 'Poker',
        'icon'   => '♠️',
        'asset'  => 'poker_table',
        'color'  => '#b06aff',
        'desc'   => 'Vervang kaarten en versla de dealer met de beste hand.',
    ],
    'slots' => [
        'name'   => 'Slots',
        'icon'   => '🎰',
        'asset'  => 'slot_machine',
        'color'  => '#ff5c5c',
        'desc'   => '3 reels. Match de symbolen voor een mega payout.',
    ],
    'roulette' => [
        'name'   => 'Roulette',
        'icon'   => '🎰',
        'asset'  => 'roulette_table',
        'color'  => '#c9a44c',
        'desc'   => 'Kies een nummer of kleur. Waag je kans.',
    ],
];

// ============================================================
// CASINO EIGENAAR OPHALEN
// ============================================================
/**
 * Zoek de eigenaar van het casino-asset in een bepaald land.
 * Geeft array terug met user_id en asset_id, of null.
 */
function getCasinoOwner(PDO $pdo, string $countryKey, string $gameKey): ?array {
    $game = CASINO_GAMES[$gameKey] ?? null;
    if (!$game) return null;

    $stmt = $pdo->prepare("
        SELECT ua.id AS asset_id, ua.user_id, u.username
        FROM user_assets ua
        JOIN users u ON u.id = ua.user_id
        WHERE ua.asset_key = ?
          AND ua.country_key = ?
        ORDER BY ua.id ASC
        LIMIT 1
    ");
    $stmt->execute([$game['asset'], $countryKey]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Lijst alle casino's in een land.
 */
function getCasinosInCountry(PDO $pdo, string $countryKey): array {
    $casinos = [];
    foreach (CASINO_GAMES as $gameKey => $game) {
        $owner = getCasinoOwner($pdo, $countryKey, $gameKey);
        $casinos[$gameKey] = [
            'game'  => $game,
            'owner' => $owner,
        ];
    }
    return $casinos;
}

// ============================================================
// UITBETALING / VERLIES VERWERKEN
// ============================================================
/**
 * Verwerk een casino resultaat.
 * $net > 0 = speler won, $net < 0 = speler verloor.
 */
function settleCasinoGame(PDO $pdo, int $userId, string $gameKey, string $countryKey, int $bet, int $payout, string $details = ''): array {
    $game = CASINO_GAMES[$gameKey] ?? null;
    if (!$game) return ['error' => 'Onbekend spel.'];

    $net = $payout - $bet;

    $result = 'loss';
    if ($net > 0) $result = 'win';
    elseif ($net === 0) $result = 'push';

    $owner = getCasinoOwner($pdo, $countryKey, $gameKey);
    $ownerId = $owner ? (int)$owner['user_id'] : null;
    $ownerShare = 0;

    $pdo->beginTransaction();
    try {
        // 1. Trek de inzet af
        $pdo->prepare("UPDATE users SET money = money - ? WHERE id = ?")
            ->execute([$bet, $userId]);

        // 2. Speler won → betaal uit vanuit systeem
        if ($net > 0) {
            $pdo->prepare("UPDATE users SET money = money + ? WHERE id = ?")
                ->execute([$payout, $userId]);
        }
        // 3. Speler verloor → geld naar eigenaar (deel) + systeem
        elseif ($net < 0) {
            if ($ownerId) {
                $ownerShare = (int)floor($bet * CASINO_HOUSE_SHARE);
                $pdo->prepare("UPDATE users SET money = money + ? WHERE id = ?")
                    ->execute([$ownerShare, $ownerId]);

                // Notify owner
                if (function_exists('notify')) {
                    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
                    $stmt->execute([$userId]);
                    $gambler = $stmt->fetchColumn();
                    // Alleen notificeren als het bedrag hoog genoeg is
                    if ($ownerShare >= 10000) {
                        notify($pdo, $ownerId,
                            "🎰 {$gambler} verloor €" . number_format($bet, 0, ',', '.') .
                            " bij {$game['name']}. Jij krijgt €" . number_format($ownerShare, 0, ',', '.') . "!",
                            $game['icon']);
                    }
                }
            }
        }

        // Log
        $pdo->prepare("
            INSERT INTO casino_game_logs
            (user_id, game_key, country_key, bet, result, payout, net, owner_id, owner_share, details)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $userId, $gameKey, $countryKey, $bet, $result, $payout, $net,
            $ownerId, $ownerShare, $details
        ]);

        // Casino earnings updaten
        if ($ownerId) {
            $pdo->prepare("
                INSERT INTO casino_earnings (owner_id, game_key, country_key, total_bets, total_earnings, net_profit)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    total_bets = total_bets + VALUES(total_bets),
                    total_earnings = total_earnings + VALUES(total_earnings),
                    net_profit = net_profit + VALUES(net_profit)
            ")->execute([
                $ownerId, $gameKey, $countryKey,
                $bet, $ownerShare, $ownerShare
            ]);
        }

        $pdo->commit();

        return [
            'success'      => true,
            'bet'          => $bet,
            'payout'       => $payout,
            'net'          => $net,
            'result'       => $result,
            'owner_id'     => $ownerId,
            'owner_share'  => $ownerShare,
        ];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['error' => 'Systeemfout: ' . $e->getMessage()];
    }
}

// ============================================================
// BLACKJACK ENGINE
// ============================================================
function bjNewDeck(): array {
    $suits = ['♠', '♥', '♦', '♣'];
    $values = ['2','3','4','5','6','7','8','9','10','J','Q','K','A'];
    $deck = [];
    foreach ($suits as $s) {
        foreach ($values as $v) {
            $deck[] = ['suit' => $s, 'value' => $v];
        }
    }
    shuffle($deck);
    return $deck;
}

function bjCardValue(string $value): int {
    if ($value === 'A') return 11;
    if (in_array($value, ['J', 'Q', 'K'])) return 10;
    return (int)$value;
}

function bjHandValue(array $cards): int {
    $total = 0;
    $aces = 0;
    foreach ($cards as $c) {
        if ($c['value'] === 'A') $aces++;
        $total += bjCardValue($c['value']);
    }
    while ($total > 21 && $aces > 0) {
        $total -= 10;
        $aces--;
    }
    return $total;
}

function bjFormatCard(array $c): string {
    return $c['value'] . $c['suit'];
}

function bjFormatHand(array $cards): string {
    return implode(' ', array_map('bjFormatCard', $cards));
}

// ============================================================
// SLOTS ENGINE
// ============================================================
const SLOT_SYMBOLS = ['🍒', '🍋', '🔔', '💎', '7️⃣', '⭐'];
const SLOT_PAYOUTS = [
    '7️⃣' => 20,   // 3x 7 = 20x
    '💎' => 12,
    '⭐' => 8,
    '🔔' => 5,
    '🍋' => 3,
    '🍒' => 2,
];

// ============================================================
// POKER ENGINE (5-card draw vs dealer)
// ============================================================
function pokerNewDeck(): array {
    $suits = ['♠', '♥', '♦', '♣'];
    $values = ['2','3','4','5','6','7','8','9','10','J','Q','K','A'];
    $deck = [];
    foreach ($suits as $s) {
        foreach ($values as $v) {
            $deck[] = ['suit' => $s, 'value' => $v];
        }
    }
    shuffle($deck);
    return $deck;
}

/**
 * Bepaal poker hand ranking (0-8, hoger is beter).
 * 8=Royal Flush, 7=Straight Flush, 6=Four of a Kind, 5=Full House,
 * 4=Flush, 3=Straight, 2=Three of a Kind, 1=Two Pair, 0=Pair/High Card
 */
function pokerHandRank(array $cards): int {
    $values = array_map(fn($c) => $c['value'], $cards);
    $suits  = array_map(fn($c) => $c['suit'], $cards);

    $order = ['2','3','4','5','6','7','8','9','10','J','Q','K','A'];
    $idx = array_map(fn($v) => array_search($v, $order), $values);
    sort($idx);

    $isFlush = count(array_unique($suits)) === 1;
    $isStraight = ($idx[4] - $idx[0] === 4 && count(array_unique($idx)) === 5);

    $counts = array_count_values($values);
    arsort($counts);
    $countValues = array_values($counts);

    if ($isFlush && $isStraight && $idx[0] === 8) return 8; // Royal flush
    if ($isFlush && $isStraight) return 7;
    if ($countValues[0] === 4) return 6;
    if ($countValues[0] === 3 && ($countValues[1] ?? 0) === 2) return 5;
    if ($isFlush) return 4;
    if ($isStraight) return 3;
    if ($countValues[0] === 3) return 2;
    if ($countValues[0] === 2 && ($countValues[1] ?? 0) === 2) return 1;
    if ($countValues[0] === 2) return 0;
    return -1; // High card only
}

const POKER_HAND_NAMES = [
    8 => 'Royal Flush',
    7 => 'Straight Flush',
    6 => 'Four of a Kind',
    5 => 'Full House',
    4 => 'Flush',
    3 => 'Straight',
    2 => 'Three of a Kind',
    1 => 'Two Pair',
    0 => 'One Pair',
    -1 => 'High Card',
];

// ============================================================
// SPEEL LIMIET CHECK
// ============================================================
function validateCasinoBet(PDO $pdo, array $user, string $countryKey, string $gameKey, int $bet): array {
    if (!isset(CASINO_GAMES[$gameKey])) {
        return ['error' => 'Onbekend spel.'];
    }

    $owner = getCasinoOwner($pdo, $countryKey, $gameKey);
    if (!$owner) {
        return ['error' => 'Er is geen casino voor ' . CASINO_GAMES[$gameKey]['name'] . ' in dit land.'];
    }
    if ((int)$owner['user_id'] === (int)$user['id']) {
        return ['error' => 'Je kunt niet in je eigen casino spelen.'];
    }
    if ($bet < CASINO_MIN_BET) {
        return ['error' => 'Minimum inzet is €' . number_format(CASINO_MIN_BET, 0, ',', '.') . '.'];
    }
    if ($bet > CASINO_MAX_BET) {
        return ['error' => 'Maximum inzet is €' . number_format(CASINO_MAX_BET, 0, ',', '.') . '.'];
    }
    if ((int)$user['money'] < $bet) {
        return ['error' => 'Je hebt niet genoeg geld.'];
    }

    return ['success' => true, 'owner' => $owner];
}

// ============================================================
// STATS VOOR EIGENAAR
// ============================================================
function getCasinoEarnings(PDO $pdo, int $ownerId): array {
    $stmt = $pdo->prepare("
        SELECT ce.*, c.flag, c.name AS country_name
        FROM casino_earnings ce
        JOIN countries c ON c.`key` = ce.country_key
        WHERE ce.owner_id = ?
        ORDER BY ce.net_profit DESC
    ");
    $stmt->execute([$ownerId]);
    return $stmt->fetchAll();
}

function getCasinoTotalEarnings(PDO $pdo, int $ownerId): int {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_profit), 0) FROM casino_earnings WHERE owner_id = ?");
    $stmt->execute([$ownerId]);
    return (int)$stmt->fetchColumn();
}