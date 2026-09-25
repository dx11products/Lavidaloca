<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Niet ingelogd']);
    exit;
}

$user = currentUser($pdo);

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$csrf  = $input['csrf']  ?? '';
$key   = $input['crime'] ?? '';

if (!hash_equals(csrf_token(), $csrf)) {
    echo json_encode(['success' => false, 'error' => 'Ongeldige sessie']);
    exit;
}

if (!isset($CRIMES_V2[$key])) {
    echo json_encode(['success' => false, 'error' => 'Onbekende misdaad']);
    exit;
}

$rankData = getRankData((int)$user['xp'], $RANKS);
$crime    = $CRIMES_V2[$key];

if (isInHospital($user)) {
    echo json_encode(['success' => false, 'error' => 'Je ligt in het ziekenhuis']);
    exit;
}
if ($rankData['level'] < $crime['min_rank']) {
    echo json_encode(['success' => false, 'error' => 'Je rank is te laag']);
    exit;
}

$cooldown = canDoCrime($pdo, $user['id'], $key);
if (!$cooldown['ok']) {
    echo json_encode([
        'success'  => false,
        'error'    => 'Cooldown: wacht nog ' . $cooldown['wait'] . 's',
        'cooldown' => $cooldown['wait']
    ]);
    exit;
}

$chain = getUserChain($pdo, $user['id']);
$chainMultiplier = getChainMultiplier((int)$chain['current_chain']);
$levelMult = getLevelMultiplier($rankData['level']);
$eff = getEffectiveReward($crime, $rankData['level']);

// Familie bonus op succes-kans
$crimeBonus = function_exists('getCrimeSuccessBonus') ? getCrimeSuccessBonus($pdo, $user['id']) : 0;
$finalSuccessRate = min(95, $crime['success_rate'] + $crimeBonus);

$roll    = random_int(1, 100);
$success = $roll <= $finalSuccessRate;

if ($success) {
    $baseReward = random_int($eff['min'], $eff['max']);
    $reward = (int)floor($baseReward * $chainMultiplier);
    $xpGain = (int)floor($crime['xp_reward'] * $levelMult);
    $gotBusted = random_int(1, 100) <= $crime['busted_risk'];

    $pdo->beginTransaction();
    try {
        $pdo->prepare("
            UPDATE users
            SET money = money + ?, xp = xp + ?, crimes_done = crimes_done + 1, last_crime = NOW()
            WHERE id = ?
        ")->execute([$reward, $xpGain, $user['id']]);

        $pdo->prepare("
            INSERT INTO crime_logs (user_id, crime_key, success, reward, xp, energy_spent, fine)
            VALUES (?, ?, 1, ?, ?, 0, 0)
        ")->execute([$user['id'], $key, $reward, $xpGain]);

        updateChain($pdo, $user['id'], true);

        $cooldownSeconds = 0;
        $fine = 0;

        if ($gotBusted) {
            $fine = random_int($crime['fine_min'], $crime['fine_max']);
            $fine = min($fine, (int)$user['money'] + $reward);
            $pdo->prepare("UPDATE users SET money = money - ? WHERE id = ?")->execute([$fine, $user['id']]);
            recordBusted($pdo, $user['id']);

            $cooldownSeconds = (int)$crime['cooldown'];
            setCrimeCooldown($pdo, $user['id'], $key, $cooldownSeconds);

            logActivity($pdo, $user['id'],
                "🚔 GEPAKT na {$crime['name']} — boete €" . number_format($fine, 0, ',', '.') .
                " — cooldown {$cooldownSeconds}s");
        } else {
            logActivity($pdo, $user['id'],
                "✅ {$crime['name']} — €" . number_format($reward, 0, ',', '.'));
        }

        addClicks($pdo, $user['id'], CLICKS_PER_CRIME_WIN);

        $vaultDrop = null;
        if (random_int(1, 100) <= VAULT_DROP_CRIME_WIN) {
            $vaultDrop = giveRandomVaultCode($pdo, $user['id']);
        }

        $pdo->commit();

        $newRank = getRankData($user['xp'] + $xpGain, $RANKS);
        if ($newRank['level'] > $rankData['level']) {
            $pdo->prepare("UPDATE users SET rank_title = ? WHERE id = ?")
                ->execute([$newRank['name'], $user['id']]);
            logActivity($pdo, $user['id'], "🎖️ Gepromoveerd naar {$newRank['name']}!");
        }

        checkAchievements($pdo, $user['id']);
        $user = currentUser($pdo);

        echo json_encode([
            'success'       => true,
            'type'          => $gotBusted ? 'busted' : 'win',
            'crime'         => $crime['name'],
            'reward'        => $reward,
            'xp'            => $xpGain,
            'fine'          => $fine,
            'chain'         => (int)$chain['current_chain'] + 1,
            'multiplier'    => $chainMultiplier,
            'cooldown'      => $cooldownSeconds,
            'no_cooldown'   => !$gotBusted,
            'clicks_gained' => CLICKS_PER_CRIME_WIN,
            'vault_drop'    => $vaultDrop ? [
                'vault'    => $vaultDrop['vault_name'],
                'value'    => $vaultDrop['value'],
                'position' => $vaultDrop['position'],
            ] : null,
            'user' => [
                'money'      => (int)$user['money'],
                'xp'         => (int)$user['xp'],
                'clicks'     => (int)$user['clicks'],
                'rank_title' => $user['rank_title'],
            ],
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Systeemfout']);
    }
} else {
    $fine = random_int($crime['fine_min'], $crime['fine_max']);
    $fine = min($fine, (int)$user['money']);

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE users SET money = money - ?, last_crime = NOW() WHERE id = ?")
            ->execute([$fine, $user['id']]);

        $pdo->prepare("
            INSERT INTO crime_logs (user_id, crime_key, success, reward, xp, energy_spent, fine)
            VALUES (?, ?, 0, 0, 0, 0, ?)
        ")->execute([$user['id'], $key, $fine]);

        updateChain($pdo, $user['id'], false);

        $cooldownSeconds = (int)$crime['cooldown'];
        setCrimeCooldown($pdo, $user['id'], $key, $cooldownSeconds);

        logActivity($pdo, $user['id'],
            "❌ {$crime['name']} mislukt — €" . number_format($fine, 0, ',', '.') .
            " boete — cooldown {$cooldownSeconds}s");

        addClicks($pdo, $user['id'], CLICKS_PER_CRIME_FAIL);

        $vaultDrop = null;
        if (random_int(1, 100) <= VAULT_DROP_CRIME_FAIL) {
            $vaultDrop = giveRandomVaultCode($pdo, $user['id']);
        }

        $pdo->commit();
        $user = currentUser($pdo);

        echo json_encode([
            'success'       => true,
            'type'          => 'fail',
            'crime'         => $crime['name'],
            'fine'          => $fine,
            'cooldown'      => $cooldownSeconds,
            'no_cooldown'   => false,
            'clicks_gained' => CLICKS_PER_CRIME_FAIL,
            'vault_drop'    => $vaultDrop ? [
                'vault'    => $vaultDrop['vault_name'],
                'value'    => $vaultDrop['value'],
                'position' => $vaultDrop['position'],
            ] : null,
            'user' => [
                'money'      => (int)$user['money'],
                'xp'         => (int)$user['xp'],
                'clicks'     => (int)$user['clicks'],
                'rank_title' => $user['rank_title'],
            ],
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Systeemfout']);
    }
}