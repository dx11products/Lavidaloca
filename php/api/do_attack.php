<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Niet ingelogd']);
    exit;
}

$user = currentUser($pdo);

$input    = json_decode(file_get_contents('php://input'), true) ?: [];
$csrf     = $input['csrf'] ?? '';
$targetId = (int)($input['target_id'] ?? 0);

if (!hash_equals(csrf_token(), $csrf)) {
    echo json_encode(['success' => false, 'error' => 'Ongeldige sessie']);
    exit;
}

if (isInHospital($user)) {
    echo json_encode(['success' => false, 'error' => 'Je ligt in het ziekenhuis']);
    exit;
}
if ($user['energy'] < ATTACK_ENERGY_COST) {
    echo json_encode(['success' => false, 'error' => 'Niet genoeg energie']);
    exit;
}
if ($targetId === (int)$user['id']) {
    echo json_encode(['success' => false, 'error' => 'Je kunt jezelf niet aanvallen']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$targetId]);
$target = $stmt->fetch();

if (!$target) {
    echo json_encode(['success' => false, 'error' => 'Slachtoffer niet gevonden']);
    exit;
}
if (isInHospital($target)) {
    echo json_encode(['success' => false, 'error' => 'Slachtoffer ligt in het ziekenhuis']);
    exit;
}

// Munitie
$myAmmo    = getUserAmmo($pdo, $user['id']);
$totalAmmo = getTotalAmmo($myAmmo);
$hasAmmo   = $totalAmmo >= AMMO_PER_ATTACK;

$myBonuses = getEquippedBonuses($pdo, $user['id']);
$myAttack  = 10 + (int)$myBonuses['attack'] + ($hasAmmo ? AMMO_DAMAGE_BONUS : 0);

$targetBonuses = getEquippedBonuses($pdo, $target['id']);
$targetDefense = 10 + (int)$targetBonuses['defense'];

$chance  = calcWinChance($myAttack, $targetDefense);
$roll    = random_int(1, 100);
$success = $roll <= $chance;

$newEnergy = $user['energy'] - ATTACK_ENERGY_COST;

if ($success) {
    $loot = min((int)$target['money'], (int)floor($target['money'] * ATTACK_STEAL_PERCENT / 100));
    $healthDamage = ATTACK_HEALTH_DAMAGE_WIN;
    $newHealth = max(0, $target['health'] - $healthDamage);

    $pdo->beginTransaction();
    try {
        if ($hasAmmo) removeAmmo($pdo, $user['id'], 'pistool', AMMO_PER_ATTACK);

        $pdo->prepare("
            UPDATE users
            SET money = money + ?, xp = xp + ?, energy = ?, energy_updated = NOW(),
                attacks_won = attacks_won + 1, last_attack = NOW()
            WHERE id = ?
        ")->execute([$loot, ATTACK_XP_WIN, $newEnergy, $user['id']]);

        $hospitalUntil = null;
        if ($newHealth <= 0) $hospitalUntil = date('Y-m-d H:i:s', time() + HOSPITAL_DURATION_MIN * 60);

        $pdo->prepare("
            UPDATE users
            SET money = money - ?, health = ?, hospital_until = COALESCE(?, hospital_until),
                times_hospitalized = times_hospitalized + ?
            WHERE id = ?
        ")->execute([$loot, $newHealth, $hospitalUntil, $newHealth <= 0 ? 1 : 0, $target['id']]);

        $ammoMsg = $hasAmmo ? " (met " . AMMO_PER_ATTACK . " kogels)" : " (zonder munitie)";
        logActivity($pdo, $user['id'], "⚔️ Aanval op {$target['username']} gewonnen{$ammoMsg} — €" . number_format($loot, 0, ',', '.'));
        logActivity($pdo, $target['id'], "💥 Aangevallen door {$user['username']} — €" . number_format($loot, 0, ',', '.') . " verloren");

        $pdo->commit();

        $newRank = getRankData($user['xp'] + ATTACK_XP_WIN, $RANKS);
        $rankData = getRankData((int)$user['xp'], $RANKS);
        if ($newRank['level'] > $rankData['level']) {
            $pdo->prepare("UPDATE users SET rank_title = ? WHERE id = ?")->execute([$newRank['name'], $user['id']]);
        }

        checkAchievements($pdo, $user['id']);
        $user = currentUser($pdo);

        echo json_encode([
            'success' => true,
            'type'    => 'win',
            'target'  => $target['username'],
            'loot'    => $loot,
            'xp'      => ATTACK_XP_WIN,
            'hospitalized' => $newHealth <= 0,
            'used_ammo'    => $hasAmmo,
            'user' => [
                'money'      => (int)$user['money'],
                'energy'     => (int)$user['energy'],
                'xp'         => (int)$user['xp'],
                'rank_title' => $user['rank_title'],
            ],
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Systeemfout']);
    }
} else {
    $healthDamage = ATTACK_HEALTH_DAMAGE_LOSS;
    $newHealth = max(0, $user['health'] - $healthDamage);
    $hospitalUntil = null;
    if ($newHealth <= 0) $hospitalUntil = date('Y-m-d H:i:s', time() + HOSPITAL_DURATION_MIN * 60);

    $pdo->beginTransaction();
    try {
        if ($hasAmmo) removeAmmo($pdo, $user['id'], 'pistool', AMMO_PER_ATTACK);

        $pdo->prepare("
            UPDATE users
            SET health = ?, hospital_until = COALESCE(?, hospital_until),
                energy = ?, energy_updated = NOW(),
                attacks_lost = attacks_lost + 1,
                times_hospitalized = times_hospitalized + ?,
                last_attack = NOW()
            WHERE id = ?
        ")->execute([$newHealth, $hospitalUntil, $newEnergy, $newHealth <= 0 ? 1 : 0, $user['id']]);

        $pdo->prepare("
            UPDATE users SET xp = xp + ?, attacks_won = attacks_won + 1 WHERE id = ?
        ")->execute([ATTACK_XP_LOSS, $target['id']]);

        logActivity($pdo, $user['id'], "❌ Aanval op {$target['username']} verloren — {$healthDamage} HP verloren");
        logActivity($pdo, $target['id'], "🛡️ Aanval van {$user['username']} afgeslagen");

        $pdo->commit();

        $user = currentUser($pdo);

        echo json_encode([
            'success' => true,
            'type'    => 'fail',
            'target'  => $target['username'],
            'hospitalized' => $newHealth <= 0,
            'used_ammo'    => $hasAmmo,
            'user' => [
                'money'      => (int)$user['money'],
                'energy'     => (int)$user['energy'],
                'xp'         => (int)$user['xp'],
                'rank_title' => $user['rank_title'],
            ],
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Systeemfout']);
    }
}