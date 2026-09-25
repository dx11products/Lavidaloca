<?php
// Bende-oorlog instellingen

const WAR_DURATION_HOURS   = 24;      // oorlog duurt 24 uur
const WAR_MIN_MEMBERS      = 3;       // min. leden om oorlog te starten
const WAR_MIN_BANK         = 5000;    // min. bendekas om te declareren
const WAR_PRIZE_PERCENT    = 25;      // % van verliezende kas als prijs
const WAR_BATTLE_ENERGY    = 20;      // energie per oorlogsaanval
const WAR_BATTLE_XP_WIN    = 30;      // XP voor winnaar
const WAR_BATTLE_XP_LOSS   = 5;       // XP voor verliezer
const WAR_SCORE_WIN        = 10;      // punten per gewonnen gevecht
const WAR_SCORE_LOSS       = -3;      // punten bij verlies (min)
const WAR_COOLDOWN_MIN     = 5;       // minuten tussen aanvallen op dezelfde user

/**
 * Haal actieve oorlog op voor een familie.
 */
function getActiveWar(PDO $pdo, int $familyId): ?array {
    $stmt = $pdo->prepare("
        SELECT w.*,
               fa.name AS family_a_name, fa.tag AS family_a_tag,
               fb.name AS family_b_name, fb.tag AS family_b_tag
        FROM family_wars w
        JOIN families fa ON fa.id = w.family_a_id
        JOIN families fb ON fb.id = w.family_b_id
        WHERE (w.family_a_id = ? OR w.family_b_id = ?)
          AND w.status = 'active'
        ORDER BY w.id DESC LIMIT 1
    ");
    $stmt->execute([$familyId, $familyId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Is er een actieve oorlog?
 */
function hasActiveWar(PDO $pdo, int $familyId): bool {
    return getActiveWar($pdo, $familyId) !== null;
}

/**
 * Alle actieve oorlogen (publiek overzicht).
 */
function getAllActiveWars(PDO $pdo): array {
    $stmt = $pdo->query("
        SELECT w.*,
               fa.name AS family_a_name, fa.tag AS family_a_tag,
               fb.name AS family_b_name, fb.tag AS family_b_tag,
               (SELECT COUNT(*) FROM war_battles WHERE war_id = w.id) AS total_battles
        FROM family_wars w
        JOIN families fa ON fa.id = w.family_a_id
        JOIN families fb ON fb.id = w.family_b_id
        WHERE w.status = 'active'
        ORDER BY w.started_at DESC
    ");
    return $stmt->fetchAll();
}

/**
 * Beschikbare tegenstanders (bendes zonder actieve oorlog).
 */
function findWarTargets(PDO $pdo, int $myFamilyId): array {
    $stmt = $pdo->prepare("
        SELECT f.*,
               (SELECT COUNT(*) FROM family_members WHERE family_id = f.id) AS member_count
        FROM families f
        WHERE f.id != ?
          AND f.id NOT IN (
              SELECT family_a_id FROM family_wars WHERE status = 'active'
              UNION
              SELECT family_b_id FROM family_wars WHERE status = 'active'
          )
        HAVING member_count >= ?
        ORDER BY f.money DESC
        LIMIT 20
    ");
    $stmt->execute([$myFamilyId, WAR_MIN_MEMBERS]);
    return $stmt->fetchAll();
}

/**
 * Recente gevechten in een oorlog.
 */
function getWarBattles(PDO $pdo, int $warId, int $limit = 20): array {
    $stmt = $pdo->prepare("
        SELECT wb.*,
               ua.username AS attacker_name,
               ud.username AS defender_name,
               fa.tag AS attacker_tag,
               fd.tag AS defender_tag
        FROM war_battles wb
        JOIN users ua ON ua.id = wb.attacker_id
        JOIN users ud ON ud.id = wb.defender_id
        JOIN families fa ON fa.id = wb.attacker_family_id
        JOIN families fd ON fd.id = wb.defender_family_id
        WHERE wb.war_id = ?
        ORDER BY wb.id DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, $warId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Leden van de vijandelijke familie (om aan te vallen).
 */
function getEnemyMembers(PDO $pdo, int $enemyFamilyId, int $myUserId): array {
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.money, u.health, u.max_health, u.rank_title, u.xp,
               fm.rank AS family_rank
        FROM family_members fm
        JOIN users u ON u.id = fm.user_id
        WHERE fm.family_id = ?
          AND u.id != ?
          AND (u.hospital_until IS NULL OR u.hospital_until < NOW())
        ORDER BY u.xp DESC
    ");
    $stmt->execute([$enemyFamilyId, $myUserId]);
    return $stmt->fetchAll();
}

/**
 * Aantal gevechten van user in deze oorlog.
 */
function getUserWarBattles(PDO $pdo, int $warId, int $userId): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM war_battles WHERE war_id = ? AND attacker_id = ?");
    $stmt->execute([$warId, $userId]);
    return (int)$stmt->fetchColumn();
}

/**
 * Cooldown check — wanneer mag user weer vechten?
 */
function canBattle(PDO $pdo, int $warId, int $userId, int $defenderId): bool {
    $stmt = $pdo->prepare("
        SELECT created_at FROM war_battles
        WHERE war_id = ? AND attacker_id = ? AND defender_id = ?
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$warId, $userId, $defenderId]);
    $last = $stmt->fetchColumn();

    if (!$last) return true;
    return (time() - strtotime($last)) >= (WAR_COOLDOWN_MIN * 60);
}

/**
 * Sluit verlopen oorlogen af.
 */
function closeExpiredWars(PDO $pdo): void {
    $stmt = $pdo->query("SELECT * FROM family_wars WHERE status = 'active' AND ends_at <= NOW()");
    $wars = $stmt->fetchAll();

    foreach ($wars as $war) {
        $winnerId = null;
        if ($war['score_a'] > $war['score_b'])      $winnerId = $war['family_a_id'];
        elseif ($war['score_b'] > $war['score_a'])  $winnerId = $war['family_b_id'];
        // gelijkspel = geen winnaar

        $prizeMoney = 0;
        if ($winnerId) {
            // Verliezer betaalt prijs uit kas
            $loserId = ($winnerId == $war['family_a_id']) ? $war['family_b_id'] : $war['family_a_id'];
            $stmt2 = $pdo->prepare("SELECT money FROM families WHERE id = ? LIMIT 1");
            $stmt2->execute([$loserId]);
            $loserMoney = (int)$stmt2->fetchColumn();

            $prizeMoney = (int)floor($loserMoney * WAR_PRIZE_PERCENT / 100);
            $prizeMoney = min($prizeMoney, $loserMoney);

            $pdo->prepare("UPDATE families SET money = money - ? WHERE id = ?")
                ->execute([$prizeMoney, $loserId]);
            $pdo->prepare("UPDATE families SET money = money + ? WHERE id = ?")
                ->execute([$prizeMoney, $winnerId]);

            // Notify alle leden van beide families
            $stmt2 = $pdo->prepare("SELECT user_id FROM family_members WHERE family_id = ?");
            $stmt2->execute([$winnerId]);
            foreach ($stmt2->fetchAll() as $m) {
                notify($pdo, $m['user_id'],
                    "🏆 Jullie bende heeft de oorlog gewonnen! Prijs: €" .
                    number_format($prizeMoney, 0, ',', '.'), '🏆');
            }
            $stmt2->execute([$loserId]);
            foreach ($stmt2->fetchAll() as $m) {
                notify($pdo, $m['user_id'],
                    "💀 Jullie bende heeft de oorlog verloren. Verlies: €" .
                    number_format($prizeMoney, 0, ',', '.'), '💀');
            }
        } else {
            // Gelijkspel — geen prijs
            foreach ([$war['family_a_id'], $war['family_b_id']] as $fid) {
                $stmt2 = $pdo->prepare("SELECT user_id FROM family_members WHERE family_id = ?");
                $stmt2->execute([$fid]);
                foreach ($stmt2->fetchAll() as $m) {
                    notify($pdo, $m['user_id'],
                        "🤝 De oorlog is geëindigd in gelijkspel.", '🤝');
                }
            }
        }

        $pdo->prepare("
            UPDATE family_wars
            SET status = 'finished', winner_id = ?, prize_money = ?
            WHERE id = ?
        ")->execute([$winnerId, $prizeMoney, $war['id']]);
    }
}