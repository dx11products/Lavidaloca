<?php
/**
 * Vendetta — Achievements
 */

$ACHIEVEMENTS = [
    // ============ MISDADEN ============
    'first_crime' => [
        'name' => 'Eerste misdaad',
        'desc' => 'Pleeg je eerste misdaad',
        'icon' => '🎯',
        'type' => 'crimes_done', 'value' => 1,
    ],
    'crime_10' => [
        'name' => 'Straatvechter',
        'desc' => 'Pleeg 10 misdaden',
        'icon' => '🎯',
        'type' => 'crimes_done', 'value' => 10,
    ],
    'crime_50' => [
        'name' => 'Misdadiger',
        'desc' => 'Pleeg 50 misdaden',
        'icon' => '🎯',
        'type' => 'crimes_done', 'value' => 50,
    ],
    'crime_200' => [
        'name' => 'Beroepscrimineel',
        'desc' => 'Pleeg 200 misdaden',
        'icon' => '🎯',
        'type' => 'crimes_done', 'value' => 200,
    ],

    // ============ GELD ============
    'money_1k' => [
        'name' => 'Eerste duizend',
        'desc' => 'Verdien €1.000 totaal',
        'icon' => '💰',
        'type' => 'money', 'value' => 1000,
    ],
    'money_10k' => [
        'name' => 'Kleine baas',
        'desc' => 'Verdien €10.000 totaal',
        'icon' => '💰',
        'type' => 'money', 'value' => 10000,
    ],
    'money_100k' => [
        'name' => 'Grote vangst',
        'desc' => 'Verdien €100.000 totaal',
        'icon' => '💰',
        'type' => 'money', 'value' => 100000,
    ],
    'money_1m' => [
        'name' => 'Miljonair',
        'desc' => 'Verdien €1.000.000 totaal',
        'icon' => '💎',
        'type' => 'money', 'value' => 1000000,
    ],

    // ============ RANKS / XP ============
    'xp_100' => [
        'name' => 'Opkomend talent',
        'desc' => 'Verdien 100 XP',
        'icon' => '⭐',
        'type' => 'xp', 'value' => 100,
    ],
    'xp_1000' => [
        'name' => 'Ervaren gangster',
        'desc' => 'Verdien 1.000 XP',
        'icon' => '⭐',
        'type' => 'xp', 'value' => 1000,
    ],
    'rank_capo' => [
        'name' => 'Capo',
        'desc' => 'Bereik de rank Capo',
        'icon' => '🎖️',
        'type' => 'rank_level', 'value' => 7,
    ],
    'rank_godfather' => [
        'name' => 'Godfather',
        'desc' => 'Bereik de hoogste rank',
        'icon' => '👑',
        'type' => 'rank_level', 'value' => 10,
    ],

    // ============ GEVECHTEN ============
    'first_attack' => [
        'name' => 'Eerste bloed',
        'desc' => 'Win je eerste gevecht',
        'icon' => '⚔️',
        'type' => 'attacks_won', 'value' => 1,
    ],
    'attack_10' => [
        'name' => 'Vechter',
        'desc' => 'Win 10 gevechten',
        'icon' => '⚔️',
        'type' => 'attacks_won', 'value' => 10,
    ],
    'attack_50' => [
        'name' => 'Krijgsheer',
        'desc' => 'Win 50 gevechten',
        'icon' => '⚔️',
        'type' => 'attacks_won', 'value' => 50,
    ],
    'attack_200' => [
        'name' => 'Onoverwinnelijk',
        'desc' => 'Win 200 gevechten',
        'icon' => '⚔️',
        'type' => 'attacks_won', 'value' => 200,
    ],

    // ============ BANK ============
    'bank_10k' => [
        'name' => 'Spaarder',
        'desc' => 'Heb €10.000 op de bank',
        'icon' => '🏦',
        'type' => 'bank_money', 'value' => 10000,
    ],
    'bank_100k' => [
        'name' => 'Vermogend',
        'desc' => 'Heb €100.000 op de bank',
        'icon' => '🏦',
        'type' => 'bank_money', 'value' => 100000,
    ],
    'bank_1m' => [
        'name' => 'Bankier',
        'desc' => 'Heb €1.000.000 op de bank',
        'icon' => '🏦',
        'type' => 'bank_money', 'value' => 1000000,
    ],

    // ============ BENDE ============
    'family_joined' => [
        'name' => 'Familielid',
        'desc' => 'Word lid van een bende',
        'icon' => '👥',
        'type' => 'family_member', 'value' => 1,
    ],
    'family_leader' => [
        'name' => 'Bendeleider',
        'desc' => 'Start je eigen bende',
        'icon' => '👑',
        'type' => 'family_leader', 'value' => 1,
    ],
];

/**
 * Controleer alle achievements en ken nieuwe toe.
 * Wordt aangeroepen na belangrijke acties.
 */
function checkAchievements(PDO $pdo, int $userId): array {
    global $ACHIEVEMENTS, $RANKS;

    // Fallback: mocht $RANKS toch niet geladen zijn
    if (!is_array($RANKS) || empty($RANKS)) {
        require_once __DIR__ . '/game.php';
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) return [];

    // Bestaande achievements
    $stmt = $pdo->prepare("SELECT achievement_key FROM user_achievements WHERE user_id = ?");
    $stmt->execute([$userId]);
    $have = array_column($stmt->fetchAll(), 'achievement_key');

    // Rank data
    $rankData = getRankData((int)$user['xp'], $RANKS);

    // Bende check
    $stmt = $pdo->prepare("
        SELECT f.leader_id, fm.user_id
        FROM family_members fm
        LEFT JOIN families f ON f.id = fm.family_id
        WHERE fm.user_id = ?
    ");
    $stmt->execute([$userId]);
    $famRow = $stmt->fetch();
    $isFamilyMember = (bool)$famRow;
    $isFamilyLeader = $famRow && (int)$famRow['leader_id'] === $userId;

    $unlocked = [];
    foreach ($ACHIEVEMENTS as $key => $ach) {
        if (in_array($key, $have, true)) continue;

        $pass = false;
        switch ($ach['type']) {
            case 'crimes_done':   $pass = $user['crimes_done'] >= $ach['value']; break;
            case 'money':         $pass = (($user['money'] ?? 0) + ($user['bank_money'] ?? 0)) >= $ach['value']; break;
            case 'xp':            $pass = $user['xp'] >= $ach['value']; break;
            case 'rank_level':    $pass = $rankData['level'] >= $ach['value']; break;
            case 'attacks_won':   $pass = ($user['attacks_won'] ?? 0) >= $ach['value']; break;
            case 'bank_money':    $pass = ($user['bank_money'] ?? 0) >= $ach['value']; break;
            case 'family_member': $pass = $isFamilyMember; break;
            case 'family_leader': $pass = $isFamilyLeader; break;
        }

        if ($pass) {
            $pdo->prepare("INSERT IGNORE INTO user_achievements (user_id, achievement_key) VALUES (?, ?)")
                ->execute([$userId, $key]);
            logActivity($pdo, $userId, "🏆 Achievement unlocked: {$ach['name']}");
            $unlocked[] = $ach;
        }
    }

    if (!empty($unlocked)) {
        $pdo->prepare("UPDATE users SET achievements_count = achievements_count + ? WHERE id = ?")
            ->execute([count($unlocked), $userId]);
    }

    return $unlocked;
}

/**
 * Haal opgeslagen achievements van een user op.
 */
function getUserAchievements(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare("
        SELECT achievement_key, unlocked_at
        FROM user_achievements
        WHERE user_id = ?
        ORDER BY unlocked_at DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}