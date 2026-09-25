<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = currentUser($pdo);
$myFamily = getUserFamily($pdo, $user['id']);

if (!$myFamily) redirect('families.php');

$warId = (int)($_GET['id'] ?? 0);

// Haal oorlog op
$stmt = $pdo->prepare("
    SELECT w.*,
           fa.name AS family_a_name, fa.tag AS family_a_tag,
           fb.name AS family_b_name, fb.tag AS family_b_tag
    FROM family_wars w
    JOIN families fa ON fa.id = w.family_a_id
    JOIN families fb ON fb.id = w.family_b_id
    WHERE w.id = ? AND w.status = 'active' LIMIT 1
");
$stmt->execute([$warId]);
$war = $stmt->fetch();

if (!$war) {
    redirect('war.php');
}

$isA = (int)$war['family_a_id'] === (int)$myFamily['id'];
$isB = (int)$war['family_b_id'] === (int)$myFamily['id'];

if (!$isA && !$isB) {
    redirect('war.php');
}

$enemyFamilyId = $isA ? (int)$war['family_b_id'] : (int)$war['family_a_id'];
$enemyTag = $isA ? $war['family_b_tag'] : $war['family_a_tag'];
$enemyName = $isA ? $war['family_b_name'] : $war['family_a_name'];

$myBonuses = getEquippedBonuses($pdo, $user['id']);
$myAttack = 10 + (int)$myBonuses['attack'];
$myAmmo = getUserAmmo($pdo, $user['id']);
$totalAmmo = getTotalAmmo($myAmmo);
$hasAmmo = $totalAmmo >= AMMO_PER_ATTACK;
if ($hasAmmo) $myAttack += AMMO_DAMAGE_BONUS;

$inHospital = isInHospital($user);
$result = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetId = (int)($_POST['target_id'] ?? 0);
    $csrf = $_POST['csrf'] ?? '';

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } elseif ($inHospital) {
        $error = 'Je ligt in het ziekenhuis.';
    } elseif ($user['energy'] < WAR_BATTLE_ENERGY) {
        $error = 'Niet genoeg energie (' . WAR_BATTLE_ENERGY . ' nodig).';
    } else {
        // Haal target op
        $stmt = $pdo->prepare("
            SELECT u.*, fm.family_id
            FROM users u
            JOIN family_members fm ON fm.user_id = u.id
            WHERE u.id = ? AND fm.family_id = ?
            LIMIT 1
        ");
        $stmt->execute([$targetId, $enemyFamilyId]);
        $target = $stmt->fetch();

        if (!$target) {
            $error = 'Deze speler is geen lid van de vijand.';
        } elseif (isInHospital($target)) {
            $error = 'Dit doelwit ligt in het ziekenhuis.';
        } elseif (!canBattle($pdo, $warId, $user['id'], $targetId)) {
            $error = 'Je moet ' . WAR_COOLDOWN_MIN . ' minuten wachten voor je dezelfde speler weer aanvalt.';
        } else {
            $targetBonuses = getEquippedBonuses($pdo, $target['id']);
            $targetDefense = 10 + (int)$targetBonuses['defense'];

            $chance = calcWinChance($myAttack, $targetDefense);
            $roll = random_int(1, 100);
            $success = $roll <= $chance;
            $newEnergy = $user['energy'] - WAR_BATTLE_ENERGY;

            if ($success) {
                $loot = min((int)$target['money'], (int)floor($target['money'] * 10 / 100));
                $damage = 25;
                $newHealth = max(0, $target['health'] - $damage);

                $pdo->beginTransaction();
                try {
                    if ($hasAmmo) {
                        removeAmmo($pdo, $user['id'], 'pistool', AMMO_PER_ATTACK);
                    }

                    $pdo->prepare("
                        UPDATE users
                        SET money = money + ?, xp = xp + ?, energy = ?, energy_updated = NOW(),
                            attacks_won = attacks_won + 1, last_attack = NOW()
                        WHERE id = ?
                    ")->execute([$loot, WAR_BATTLE_XP_WIN, $newEnergy, $user['id']]);

                    $hospitalUntil = null;
                    if ($newHealth <= 0) {
                        $hospitalUntil = date('Y-m-d H:i:s', time() + HOSPITAL_DURATION_MIN * 60);
                    }

                    $pdo->prepare("
                        UPDATE users
                        SET money = money - ?, health = ?, hospital_until = COALESCE(?, hospital_until),
                            attacks_lost = attacks_lost + 1,
                            times_hospitalized = times_hospitalized + ?
                        WHERE id = ?
                    ")->execute([
                        $loot, $newHealth, $hospitalUntil,
                        $newHealth <= 0 ? 1 : 0, $target['id']
                    ]);

                    // Update war score
                    if ($isA) {
                        $pdo->prepare("UPDATE family_wars SET score_a = score_a + ? WHERE id = ?")
                            ->execute([WAR_SCORE_WIN, $warId]);
                    } else {
                        $pdo->prepare("UPDATE family_wars SET score_b = score_b + ? WHERE id = ?")
                            ->execute([WAR_SCORE_WIN, $warId]);
                    }

                    // Log battle
                    $pdo->prepare("
                        INSERT INTO war_battles
                        (war_id, attacker_id, attacker_family_id, defender_id, defender_family_id, success, damage, loot)
                        VALUES (?, ?, ?, ?, ?, 1, ?, ?)
                    ")->execute([
                        $warId, $user['id'], $myFamily['id'],
                        $target['id'], $enemyFamilyId, $damage, $loot
                    ]);

                    logActivity($pdo, $user['id'],
                        "⚔️ Oorlog: {$target['username']} verslagen — €" . number_format($loot, 0, ',', '.'));
                    logActivity($pdo, $target['id'],
                        "💀 Oorlog: verslagen door {$user['username']} — €" . number_format($loot, 0, ',', '.') . " verloren");

                    notify($pdo, $target['id'],
                        "⚔️ Je bent verslagen in de oorlog door {$user['username']}!", '⚔️');

                    $pdo->commit();

                    $result = [
                        'success'      => true,
                        'target'       => $target['username'],
                        'loot'         => $loot,
                        'xp'           => WAR_BATTLE_XP_WIN,
                        'hospitalized' => $newHealth <= 0,
                    ];
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Gevecht mislukt.';
                }
            } else {
                $damage = 20;
                $newHealth = max(0, $user['health'] - $damage);
                $hospitalUntil = null;
                if ($newHealth <= 0) {
                    $hospitalUntil = date('Y-m-d H:i:s', time() + HOSPITAL_DURATION_MIN * 60);
                }

                $pdo->beginTransaction();
                try {
                    if ($hasAmmo) {
                        removeAmmo($pdo, $user['id'], 'pistool', AMMO_PER_ATTACK);
                    }

                    $pdo->prepare("
                        UPDATE users
                        SET health = ?, hospital_until = COALESCE(?, hospital_until),
                            energy = ?, energy_updated = NOW(),
                            attacks_lost = attacks_lost + 1,
                            times_hospitalized = times_hospitalized + ?
                        WHERE id = ?
                    ")->execute([
                        $newHealth, $hospitalUntil, $newEnergy,
                        $newHealth <= 0 ? 1 : 0, $user['id']
                    ]);

                    $pdo->prepare("UPDATE users SET xp = xp + ? WHERE id = ?")
                        ->execute([WAR_BATTLE_XP_LOSS, $target['id']]);

                    // Score voor enemy
                    if ($isA) {
                        $pdo->prepare("UPDATE family_wars SET score_b = score_b + ? WHERE id = ?")
                            ->execute([WAR_SCORE_WIN, $warId]);
                    } else {
                        $pdo->prepare("UPDATE family_wars SET score_a = score_a + ? WHERE id = ?")
                            ->execute([WAR_SCORE_WIN, $warId]);
                    }

                    $pdo->prepare("
                        INSERT INTO war_battles
                        (war_id, attacker_id, attacker_family_id, defender_id, defender_family_id, success, damage, loot)
                        VALUES (?, ?, ?, ?, ?, 0, ?, 0)
                    ")->execute([
                        $warId, $user['id'], $myFamily['id'],
                        $target['id'], $enemyFamilyId, $damage
                    ]);

                    logActivity($pdo, $user['id'],
                        "❌ Oorlog: aanval op {$target['username']} mislukt");
                    logActivity($pdo, $target['id'],
                        "🛡️ Oorlog: aanval van {$user['username']} afgeslagen");

                    $pdo->commit();

                    $result = [
                        'success'      => false,
                        'target'       => $target['username'],
                        'hospitalized' => $newHealth <= 0,
                    ];
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Gevecht mislukt.';
                }
            }

            $user = currentUser($pdo);
            $inHospital = isInHospital($user);
            $myAmmo = getUserAmmo($pdo, $user['id']);
            $totalAmmo = getTotalAmmo($myAmmo);
            $hasAmmo = $totalAmmo >= AMMO_PER_ATTACK;
        }
    }
}

// Refresh war data
$stmt = $pdo->prepare("SELECT * FROM family_wars WHERE id = ? LIMIT 1");
$stmt->execute([$warId]);
$war = $stmt->fetch();

$myScore = $isA ? (int)$war['score_a'] : (int)$war['score_b'];
$enemyScore = $isA ? (int)$war['score_b'] : (int)$war['score_a'];
$secondsLeft = max(0, strtotime($war['ends_at']) - time());
$hoursLeft = floor($secondsLeft / 3600);
$minLeft = floor(($secondsLeft % 3600) / 60);

$enemies = getEnemyMembers($pdo, $enemyFamilyId, $user['id']);
$battles = getWarBattles($pdo, $warId, 10);

$pageTitle = 'Oorlog — Vendetta';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Oorlog: [<?= htmlspecialchars($myFamily['tag']) ?>] vs [<?= htmlspecialchars($enemyTag) ?>]</h1>
    <p>Nog <?= $hoursLeft ?>u <?= $minLeft ?>m · Versla v