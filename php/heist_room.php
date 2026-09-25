<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = currentUser($pdo);
$rankData = getRankData((int)$user['xp'], $RANKS);
$lobbyId = (int)($_GET['id'] ?? 0);
$lobby = getHeistLobby($pdo, $lobbyId);

if (!$lobby) redirect('heists.php');

$members = getHeistMembers($pdo, $lobbyId);
$isMember = isHeistMember($pdo, $lobbyId, $user['id']);

if (!$isMember) redirect("heist_lobby.php?id=$lobbyId");

$error = null;
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'execute') {
    $csrf = $_POST['csrf'] ?? '';

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } elseif ((int)$lobby['host_id'] !== (int)$user['id']) {
        $error = 'Alleen de host kan uitvoeren.';
    } elseif ($lobby['status'] !== 'in_progress') {
        $error = 'Deze heist is al uitgevoerd.';
    } else {
        $chance = calculateHeistSuccessV2($pdo, $lobby, $members);
        $roll = random_int(1, 100);
        $success = $roll <= $chance;

        $pdo->beginTransaction();
        try {
            if ($success) {
                $totalReward = calculateHeistRewardV2($pdo, $lobby, $members);
                $distribution = distributeReward($totalReward, $members);

                foreach ($members as $m) {
                    $share = $distribution[$m['user_id']] ?? 0;
                    $roleXpGain = ROLE_XP_PER_HEIST_WIN;

                    // Rank-scaling op XP
                    $mRankData = getRankData((int)$m['xp'], $RANKS);
                    $mLevelMult = getHeistLevelMultiplier($mRankData['level']);
                    $xpGain = (int)floor($lobby['xp_reward'] * $mLevelMult);

                    $pdo->prepare("UPDATE users SET money = money + ?, xp = xp + ? WHERE id = ?")
                        ->execute([$share, $xpGain, $m['user_id']]);

                    addRoleXp($pdo, (int)$m['user_id'], $m['role'], $roleXpGain, true);

                    $pdo->prepare("
                        INSERT INTO heist_history
                        (lobby_id, user_id, heist_key, success, reward, xp, role, role_xp_gained)
                        VALUES (?, ?, ?, 1, ?, ?, ?, ?)
                    ")->execute([
                        $lobbyId, $m['user_id'], $lobby['heist_key'],
                        $share, $xpGain, $m['role'], $roleXpGain
                    ]);

                    notify($pdo, $m['user_id'],
                        "🏴 Heist geslaagd! Je deel: €" . number_format($share, 0, ',', '.') .
                        " + " . $xpGain . " XP + " . $roleXpGain . " rol XP",
                        '🏴');

                    logActivity($pdo, $m['user_id'],
                        "🏴 {$lobby['heist_name']} geslaagd — €" . number_format($share, 0, ',', '.'));

                    // Clicks toekennen
                    if (function_exists('addClicks')) {
                        addClicks($pdo, (int)$m['user_id'], CLICKS_PER_HEIST_WIN, 'Heist bonus');
                    }

                    // Achievements
                    if (function_exists('checkAchievements')) {
                        checkAchievements($pdo, (int)$m['user_id']);
                    }
                }

                // Cooldown zetten voor alle deelnemers
                foreach ($members as $m) {
                    setHeistCooldown($pdo, (int)$m['user_id']);
                }

                $pdo->prepare("
                    UPDATE heist_lobbies SET status = 'finished', success = 1, finished_at = NOW()
                    WHERE id = ?
                ")->execute([$lobbyId]);

                $result = [
                    'success' => true,
                    'total'   => $totalReward,
                    'distribution' => $distribution,
                ];
            } else {
                $fine = (int)floor($lobby['base_reward_min'] * HEIST_FAIL_FINE_PER);
                $fine = min($fine, 5000);

                foreach ($members as $m) {
                    $roleXpGain = ROLE_XP_PER_HEIST_LOSS;

                    // Haal actuele money op
                    $stmt = $pdo->prepare("SELECT money FROM users WHERE id = ?");
                    $stmt->execute([$m['user_id']]);
                    $mMoney = (int)$stmt->fetchColumn();
                    $actualFine = min($fine, $mMoney);

                    $pdo->prepare("UPDATE users SET money = money - ?, xp = xp + ? WHERE id = ?")
                        ->execute([$actualFine, HEIST_FAIL_XP, $m['user_id']]);

                    addRoleXp($pdo, (int)$m['user_id'], $m['role'], $roleXpGain, false);

                    $pdo->prepare("
                        INSERT INTO heist_history
                        (lobby_id, user_id, heist_key, success, reward, xp, role, role_xp_gained)
                        VALUES (?, ?, ?, 0, 0, ?, ?, ?)
                    ")->execute([
                        $lobbyId, $m['user_id'], $lobby['heist_key'],
                        HEIST_FAIL_XP, $m['role'], $roleXpGain
                    ]);

                    notify($pdo, $m['user_id'],
                        "💥 Heist mislukt! Je verloor €" . number_format($actualFine, 0, ',', '.') .
                        " maar kreeg " . $roleXpGain . " rol XP",
                        '💥');

                    logActivity($pdo, $m['user_id'],
                        "❌ {$lobby['heist_name']} mislukt — €" . number_format($actualFine, 0, ',', '.') . " boete");

                    if (function_exists('checkAchievements')) {
                        checkAchievements($pdo, (int)$m['user_id']);
                    }
                }

                // Cooldown zetten voor alle deelnemers (ook na fail)
                foreach ($members as $m) {
                    setHeistCooldown($pdo, (int)$m['user_id']);
                }

                $pdo->prepare("
                    UPDATE heist_lobbies SET status = 'failed', success = 0, finished_at = NOW()
                    WHERE id = ?
                ")->execute([$lobbyId]);

                $result = [
                    'success' => false,
                    'fine'    => $fine,
                ];
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Uitvoeren mislukt: ' . $e->getMessage();
        }
    }
}

$lobby = getHeistLobby($pdo, $lobbyId);
$members = getHeistMembers($pdo, $lobbyId);
$isHost = (int)$lobby['host_id'] === (int)$user['id'];

$pageTitle = 'Heist — Vendetta';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1><?= $lobby['icon'] ?> <span><?= htmlspecialchars($lobby['heist_name']) ?></span></h1>
    <p>
        <?php if ($lobby['status'] === 'in_progress'): ?>
            Klaar om uit te voeren — wacht op de host.
        <?php elseif ($lobby['status'] === 'finished'): ?>
            ✅ Heist geslaagd!
        <?php elseif ($lobby['status'] === 'failed'): ?>
            💥 Heist mislukt!
        <?php endif; ?>
    </p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($result): ?>
    <div class="alert <?= $result['success'] ? 'alert-success' : 'alert-error' ?>">
        <?php if ($result['success']): ?>
            🏴 <strong>HEIST GESLAAGD!</strong> Totale buit: <strong>€<?= number_format($result['total'], 0, ',', '.') ?></strong>
            <br>
            <?php foreach ($members as $m): ?>
                <?= htmlspecialchars($m['username']) ?>: <strong>€<?= number_format($result['distribution'][$m['user_id']] ?? 0, 0, ',', '.') ?></strong><br>
            <?php endforeach; ?>
        <?php else: ?>
            💥 <strong>HEIST MISLUKT!</strong> Iedereen verloor <strong>€<?= number_format($result['fine'], 0, ',', '.') ?></strong>.
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (in_array($lobby['status'], ['finished', 'failed'], true)): ?>
    <div class="alert alert-error">
        ⏱️ Je volgende heist is mogelijk over <?= HEIST_COMPLETION_COOLDOWN_MIN ?> minuten.
    </div>
<?php endif; ?>

<section class="section">
    <h2>Team</h2>
    <div class="family-members">
        <?php foreach ($members as $m):
            $roleInfo = HEIST_ROLES[$m['role']] ?? HEIST_ROLES['generiek'];
        ?>
            <div class="member-row">
                <div class="member-rank-badge rank-<?= $m['role'] === 'meesterbrein' ? 'baas' : ($m['role'] === 'hacker' ? 'onderbaas' : 'lid') ?>">
                    <?= $roleInfo['icon'] ?> <?= htmlspecialchars($roleInfo['name']) ?>
                </div>
                <div class="member-info">
                    <strong><?= htmlspecialchars($m['username']) ?></strong>
                </div>
                <?php if (!empty($result['distribution'][$m['user_id']])): ?>
                    <div class="member-stats">
                        <span style="color:#58e08c;font-weight:900;">+€<?= number_format($result['distribution'][$m['user_id']], 0, ',', '.') ?></span>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php if ($lobby['status'] === 'in_progress' && $isHost): ?>
<section class="section">
    <form method="POST">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="execute">
        <button type="submit" class="btn btn-gold btn-large btn-full">
            🎬 VOER DE HEIST UIT
        </button>
    </form>
</section>
<?php elseif ($lobby['status'] === 'in_progress'): ?>
<section class="section">
    <div class="alert alert-success">
        Wachten tot de host de heist uitvoert...
    </div>
</section>
<?php endif; ?>

<?php if (in_array($lobby['status'], ['finished', 'failed'], true)): ?>
<section class="section">
    <a href="heists.php" class="btn btn-gold btn-full">← Terug naar heists</a>
</section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>