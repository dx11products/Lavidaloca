<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = currentUser($pdo);
$rankData = getRankData((int)$user['xp'], $RANKS);
$myFamily = getUserFamily($pdo, $user['id']);
$isFamilyHeist = isset($_GET['family']) && $_GET['family'] == 1 && $myFamily;

$heistKey = $_GET['heist'] ?? '';
$heist = getHeist($pdo, $heistKey);

if (!$heist) redirect('heists.php');

// Cooldown check
$heistCooldown = getHeistCooldown($pdo, $user['id']);
if (!$heistCooldown['ok']) {
    redirect('heists.php');
}

$levelMult = getHeistLevelMultiplier($rankData['level']);
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? 'generiek';
    $csrf = $_POST['csrf'] ?? '';

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } elseif ($rankData['level'] < (int)$heist['min_rank']) {
        $error = 'Je rank is te laag.';
    } elseif ($user['money'] < (int)$heist['entry_fee']) {
        $error = 'Je hebt niet genoeg geld voor de inleg.';
    } elseif (getUserActiveLobby($pdo, $user['id'])) {
        $error = 'Je zit al in een team.';
    } elseif (!$heistCooldown['ok']) {
        $error = 'Cooldown actief — wacht nog ' . $heistCooldown['wait'] . 's.';
    } else {
        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE users SET money = money - ? WHERE id = ?")
                ->execute([$heist['entry_fee'], $user['id']]);

            $pdo->prepare("
                INSERT INTO heist_lobbies (heist_key, host_id, country_key, reward_pool, family_id, is_family_heist)
                VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([
                $heistKey,
                $user['id'],
                $user['current_country'],
                $heist['entry_fee'],
                $isFamilyHeist ? $myFamily['id'] : null,
                $isFamilyHeist ? 1 : 0,
            ]);

            $lobbyId = $pdo->lastInsertId();

            $pdo->prepare("
                INSERT INTO heist_members (lobby_id, user_id, role, is_ready)
                VALUES (?, ?, ?, 1)
            ")->execute([$lobbyId, $user['id'], $role]);

            logActivity($pdo, $user['id'], "🏴 {$heist['name']} team gestart");
            $pdo->commit();

            redirect("heist_lobby.php?id=$lobbyId");
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Kon team niet starten: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Heist starten — Vendetta';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1><?= $heist['icon'] ?> <span><?= htmlspecialchars($heist['name']) ?></span></h1>
    <p><?= htmlspecialchars($heist['description']) ?></p>
</div>

<?php if ($isFamilyHeist): ?>
    <div class="alert alert-success">
        👥 Dit is een <strong>familie-heist</strong> voor [<?= htmlspecialchars($myFamily['tag']) ?>].
        Alleen familie-leden kunnen meedoen.
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Rank multiplier -->
<div class="rank-multiplier-banner">
    <div class="rm-icon">📈</div>
    <div class="rm-info">
        <strong>Rank bonus: ×<?= number_format($levelMult, 2) ?></strong>
        <p class="muted">Op rank <?= $rankData['level'] ?> krijg je <?= round(($levelMult - 1) * 100) ?>% extra buit.</p>
    </div>
</div>

<?php
$effMin = (int)floor($heist['base_reward_min'] * $levelMult);
$effMax = (int)floor($heist['base_reward_max'] * $levelMult);
$effXp  = (int)floor($heist['xp_reward'] * $levelMult);
?>

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon">👥</div>
        <div class="stat-value"><?= $heist['min_players'] ?>–<?= $heist['max_players'] ?></div>
        <div class="stat-label">Team grootte</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">💰</div>
        <div class="stat-value gold">€<?= number_format($effMin, 0, ',', '.') ?>–€<?= number_format($effMax, 0, ',', '.') ?></div>
        <div class="stat-label">Mogelijke buit</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">🎯</div>
        <div class="stat-value"><?= $heist['success_rate'] ?>%</div>
        <div class="stat-label">Basis kans</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">⏱️</div>
        <div class="stat-value"><?= $heist['duration_minutes'] ?> min</div>
        <div class="stat-label">Duur</div>
    </div>
</div>

<section class="section">
    <h2>Kies je rol</h2>
    <p class="muted">Elke rol geeft een andere bonus aan het team. Kies slim!</p>

    <form method="POST">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

        <div class="role-grid">
            <?php
            $myRoles = getUserRoleLevels($pdo, $user['id']);
            foreach (HEIST_ROLES as $key => $role):
                $myRole = $myRoles[$key] ?? null;
                $totalBonus = $myRole ? ($myRole['role_bonus'] + $myRole['level_bonus']) : $role['bonus'];
                $bonusPct = (int)round(($totalBonus - 1) * 100);
            ?>
                <label class="role-card">
                    <input type="radio" name="role" value="<?= $key ?>" <?= $key === 'generiek' ? 'checked' : '' ?>>
                    <div class="role-content">
                        <span class="role-icon"><?= $role['icon'] ?></span>
                        <h3><?= htmlspecialchars($role['name']) ?></h3>
                        <p><?= htmlspecialchars($role['desc']) ?></p>
                        <div class="role-bonus">
                            Bonus: <strong>+<?= $bonusPct ?>%</strong>
                            <?php if ($myRole && $myRole['level'] > 1): ?>
                                <br><small style="color:<?= $myRole['level_color'] ?>;">
                                    <?= $myRole['level_icon'] ?> <?= $myRole['level_name'] ?>
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </label>
            <?php endforeach; ?>
        </div>

        <button type="submit" class="btn btn-gold btn-large btn-full" style="margin-top:20px;">
            🏴 Team starten<?= (int)$heist['entry_fee'] > 0 ? ' — €' . number_format((int)$heist['entry_fee'], 0, ',', '.') : '' ?>
        </button>
    </form>
</section>

<section class="section">
    <a href="heists.php" class="btn btn-outline btn-full">← Terug</a>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>