<?php
/**
 * Vendetta — Achievements
 */

function getAchievementsArray(): array {
    return [
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

        // ============ HEISTS ============
        'first_heist' => [
            'name' => 'Eerste overval',
            'desc' => 'Voltooi je eerste georganiseerde misdaad',
            'icon' => '🏴',
            'type' => 'heists_done', 'value' => 1,
        ],
        'heist_10' => [
            'name' => 'Teamspeler',
            'desc' => 'Voltooi 10 heists',
            'icon' => '🏴',
            'type' => 'heists_done', 'value' => 10,
        ],
        'heist_50' => [
            'name' => 'Meestercrimineel',
            'desc' => 'Voltooi 50 heists',
            'icon' => '🏴',
            'type' => 'heists_done', 'value' => 50,
        ],
        'heist_win_25' => [
            'name' => 'Succesvolle dief',
            'desc' => 'Win 25 heists',
            'icon' => '💰',
            'type' => 'heists_won', 'value' => 25,
        ],
        'heist_big_score' => [
            'name' => 'Grote klapper',
            'desc' => 'Verdien €500.000 met heists',
            'icon' => '💎',
            'type' => 'heist_earnings', 'value' => 500000,
        ],
        'role_master' => [
            'name' => 'Rol meester',
            'desc' => 'Bereik niveau 5 in een rol',
            'icon' => '🎭',
            'type' => 'role_level_5', 'value' => 1,
        ],
        'family_heist' => [
            'name' => 'Bende operatie',
            'desc' => 'Doe een heist met je bende',
            'icon' => '👥',
            'type' => 'family_heist_done', 'value' => 1,
        ],
    ];
}

$ACHIEVEMENTS = getAchievementsArray();

/**
 * Controleer achievements en ken nieuwe toe.
 */
function checkAchievements(PDO $pdo, int $userId): array {
    $ACHIEVEMENTS = getAchievementsArray();
    $RANKS = function_exists('getRanksArray') ? getRanksArray() : [];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) return [];

    // Bestaande achievements
    $stmt = $pdo->prepare("SELECT achievement_key FROM user_achievements WHERE user_id = ?");
    $stmt->execute([$userId]);
    $have = array_column($stmt->fetchAll(), 'achievement_key');

    // Rank
    $rankData = getRankData((int)$user['xp'], $RANKS);

    // Bende
    $isFamilyMember = false;
    $isFamilyLeader = false;
    try {
        $stmt = $pdo->prepare("
            SELECT f.leader_id, fm.user_id
            FROM family_members fm
            LEFT JOIN families f ON f.id = fm.family_id
            WHERE fm.user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $famRow = $stmt->fetch();
        $isFamilyMember = (bool)$famRow;
        $isFamilyLeader = $famRow && (int)$famRow['leader_id'] === $userId;
    } catch (Exception $e) {}

    // Heist statistieken
    $stats = [
        'heists_done'       => 0,
        'heists_won'        => 0,
        'heist_earnings'    => 0,
        'has_role_level_5'  => false,
        'family_heist_done' => false,
    ];

    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS total,
                   SUM(success = 1) AS won,
                   COALESCE(SUM(reward), 0) AS earnings
            FROM heist_history WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        $stats['heists_done']    = (int)($row['total'] ?? 0);
        $stats['heists_won']     = (int)($row['won'] ?? 0);
        $stats['heist_earnings'] = (int)($row['earnings'] ?? 0);

        // Rol niveau 5?
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_role_xp WHERE user_id = ? AND xp >= ?");
      $levelXp = defined('ROLE_LEVEL_XP') ? ROLE_LEVEL_XP[5] : 100;
$stmt->execute([$userId, $levelXp]);
        $stats['has_role_level_5'] = (int)$stmt->fetchColumn() > 0;

        // Bende heist?
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM heist_lobbies hl
            JOIN heist_members hm ON hm.lobby_id = hl.id
            WHERE hm.user_id = ? AND hl.is_family_heist = 1 AND hl.status IN ('finished', 'failed')
        ");
        $stmt->execute([$userId]);
        $stats['family_heist_done'] = (int)$stmt->fetchColumn() > 0;
    } catch (Exception $e) {}

    $unlocked = [];
    foreach ($ACHIEVEMENTS as $key => $ach) {
        if (in_array($key, $have, true)) continue;

        $pass = false;
        switch ($ach['type']) {
            case 'crimes_done':       $pass = ($user['crimes_done'] ?? 0) >= $ach['value']; break;
            case 'money':             $pass = (($user['money'] ?? 0) + ($user['bank_money'] ?? 0)) >= $ach['value']; break;
            case 'xp':                $pass = ($user['xp'] ?? 0) >= $ach['value']; break;
            case 'rank_level':        $pass = $rankData['level'] >= $ach['value']; break;
            case 'attacks_won':       $pass = ($user['attacks_won'] ?? 0) >= $ach['value']; break;
            case 'bank_money':        $pass = ($user['bank_money'] ?? 0) >= $ach['value']; break;
            case 'family_member':     $pass = $isFamilyMember; break;
            case 'family_leader':     $pass = $isFamilyLeader; break;
            case 'heists_done':       $pass = $stats['heists_done'] >= $ach['value']; break;
            case 'heists_won':        $pass = $stats['heists_won'] >= $ach['value']; break;
            case 'heist_earnings':    $pass = $stats['heist_earnings'] >= $ach['value']; break;
            case 'role_level_5':      $pass = $stats['has_role_level_5']; break;
            case 'family_heist_done': $pass = $stats['family_heist_done']; break;
        }

        if ($pass) {
            $pdo->prepare("INSERT IGNORE INTO user_achievements (user_id, achievement_key) VALUES (?, ?)")
                ->execute([$userId, $key]);

            if (function_exists('logActivity')) {
                logActivity($pdo, $userId, "🏆 Achievement unlocked: {$ach['name']}");
            }
            $unlocked[] = $ach;
        }
    }

    if (!empty($unlocked)) {
        $pdo->prepare("UPDATE users SET achievements_count = COALESCE(achievements_count, 0) + ? WHERE id = ?")
            ->execute([count($unlocked), $userId]);
    }

    return $unlocked;
}

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