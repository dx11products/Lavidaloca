<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user  = currentUser($pdo);
$daily = getDailyReward($pdo, $user['id']);
$canClaim = canClaimDaily($daily);
$result = null;
$error = null;

// ============================================================
// CLAIM
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canClaim) {
    $csrf = $_POST['csrf'] ?? '';

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } else {
        $today     = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        // Bepaal nieuwe streak
        $newStreak = 1;
        if ($daily && $daily['last_claim'] === $yesterday) {
            $newStreak = min(7, (int)$daily['streak'] + 1);
        }

        $rewardEur = dailyRewardAmount($newStreak);
        $rewardDia = dailyDiamondReward($newStreak);
        $rewardXp  = dailyXpReward($newStreak);

        $pdo->beginTransaction();
        try {
            // Geld + XP
            $pdo->prepare("UPDATE users SET money = money + ?, xp = xp + ? WHERE id = ?")
                ->execute([$rewardEur, $rewardXp, $user['id']]);

            // Diamanten
            if ($rewardDia > 0 && function_exists('addDiamonds')) {
                addDiamonds($pdo, $user['id'], $rewardDia, 'daily', "Streak dag {$newStreak}");
            }

            // Update / insert daily record
            if ($daily) {
                $pdo->prepare("
                    UPDATE daily_rewards
                    SET last_claim = ?, streak = ?, total_claims = total_claims + 1
                    WHERE user_id = ?
                ")->execute([$today, $newStreak, $user['id']]);
            } else {
                $pdo->prepare("
                    INSERT INTO daily_rewards (user_id, last_claim, streak, total_claims)
                    VALUES (?, ?, 1, 1)
                ")->execute([$user['id'], $today]);
            }

            logActivity($pdo, $user['id'],
                "🎁 Dagelijkse beloning — €" . number_format($rewardEur, 0, ',', '.') .
                " + " . number_format($rewardDia, 0, ',', '.') . " 💎 (streak: {$newStreak}x)");

            $pdo->commit();

            $result = [
                'eur'    => $rewardEur,
                'dia'    => $rewardDia,
                'xp'     => $rewardXp,
                'streak' => $newStreak,
                'day7'   => $newStreak === 7,
            ];

            // Refresh
            $user  = currentUser($pdo);
            $daily = getDailyReward($pdo, $user['id']);
            $canClaim = canClaimDaily($daily);

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Kon beloning niet ophalen: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Dagelijkse beloning — Vendetta';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Dagelijkse <span>beloning</span></h1>
    <p>Log elke dag in om je streak op te bouwen. Hoe hoger je streak, hoe meer geld én diamanten.</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Resultaat -->
<?php if ($result): ?>
    <div class="vault-open-reveal">
        <div class="vor-icon"><?= $result['day7'] ? '🌟' : '🎁' ?></div>
        <h2>Dag <?= $result['streak'] ?> beloning!</h2>

        <div class="vor-reward gold">
            💰 €<?= number_format($result['eur'], 0, ',', '.') ?>
        </div>

        <div class="vor-reward" style="color:#b9f2ff;margin-top:12px;font-size:1.8rem;">
            💎 <?= number_format($result['dia'], 0, ',', '.') ?>
        </div>

        <div style="margin-top:12px;font-family:var(--font-ui);font-size:.85rem;color:#58e08c;">
            ⭐ +<?= $result['xp'] ?> XP
        </div>

        <?php if ($result['day7']): ?>
            <div style="margin-top:18px;font-family:var(--font-ui);font-size:.95rem;color:#ffb040;text-transform:uppercase;letter-spacing:3px;font-weight:900;text-shadow:0 0 25px rgba(255,176,64,.6);">
                🌟 WEEK BONUS 🌟
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (!$daily && !$result): ?>
    <div class="alert alert-success">
        🎁 Dit is je eerste keer! Claim je welkomstbeloning.
    </div>
<?php endif; ?>

<!-- Streak kalender -->
<section class="section">
    <h2>Jouw streak</h2>

    <?php
    $currentStreak = $daily ? (int)$daily['streak'] : 0;
    if ($result) $currentStreak = $result['streak'];
    ?>

    <div class="streak-grid">
        <?php for ($i = 1; $i <= 7; $i++):
            $claimed = $i <= $currentStreak;
            $isNext  = $i === $currentStreak + 1;
            $eur = dailyRewardAmount($i);
            $dia = dailyDiamondReward($i);
        ?>
            <div class="streak-day <?= $claimed ? 'claimed' : ($isNext ? 'next' : '') ?>">
                <span class="streak-day-num">
                    Dag <?= $i ?><?= $i === 7 ? ' ⭐' : '' ?>
                </span>
                <span class="streak-day-reward">€<?= number_format($eur / 1000000, 1, ',', '.') ?>M</span>
                <span class="streak-day-reward diamond">+<?= number_format($dia, 0, ',', '.') ?> 💎</span>
                <?php if ($claimed): ?>
                    <span class="streak-check">✓</span>
                <?php endif; ?>
            </div>
        <?php endfor; ?>
    </div>

    <p class="muted" style="text-align:center;margin-top:14px;">
        Mis je een dag? Dan begint je streak weer bij 1.
    </p>
</section>

<!-- Beloning ophalen -->
<section class="section">
    <h2>Ophalen</h2>
    <div class="daily-card">

        <?php if ($canClaim && !$result): ?>
            <?php
            $nextStreak = 1;
            if ($daily && $daily['last_claim'] === date('Y-m-d', strtotime('-1 day'))) {
                $nextStreak = min(7, (int)$daily['streak'] + 1);
            }
            $nextEur = dailyRewardAmount($nextStreak);
            $nextDia = dailyDiamondReward($nextStreak);
            $nextXp  = dailyXpReward($nextStreak);
            ?>
            <div class="daily-reward-preview">
                <div class="daily-reward-icon"><?= $nextStreak === 7 ? '🌟' : '🎁' ?></div>
                <div class="daily-reward-info">
                    <h3>€<?= number_format($nextEur, 0, ',', '.') ?></h3>
                    <p style="color:#b9f2ff;font-size:1.05rem;margin:4px 0;">
                        💎 <?= number_format($nextDia, 0, ',', '.') ?> diamanten
                    </p>
                    <p style="color:var(--text-dim);font-size:.82rem;">
                        Streak wordt <strong><?= $nextStreak ?>x</strong> · +<?= $nextXp ?> XP
                    </p>
                </div>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <button type="submit" class="btn btn-gold btn-large btn-full">
                    🎁 Beloning ophalen
                </button>
            </form>

        <?php elseif (!$result): ?>
            <div class="daily-reward-preview">
                <div class="daily-reward-icon">⏰</div>
                <div class="daily-reward-info">
                    <h3>Kom morgen terug</h3>
                    <p>Je hebt vandaag al opgehaald. Nieuwe beloning beschikbaar om middernacht.</p>
                </div>
            </div>
            <div class="muted" style="text-align:center;margin-top:14px;font-family:var(--font-ui);font-size:.85rem;">
                Huidige streak: <strong style="color:var(--gold);"><?= $currentStreak ?>x</strong>
            </div>

        <?php else: ?>
            <a href="daily.php" class="btn btn-outline btn-full">← Terug naar overzicht</a>
        <?php endif; ?>

    </div>
</section>

<!-- Week overzicht -->
<section class="section">
    <h2>Week overzicht</h2>
    <ul class="info-list">
        <li>
            <span>💰 Totaal deze week</span>
            <strong style="color:var(--gold);">
                €<?= number_format(
                    dailyRewardAmount(1) + dailyRewardAmount(2) + dailyRewardAmount(3) +
                    dailyRewardAmount(4) + dailyRewardAmount(5) + dailyRewardAmount(6) +
                    dailyRewardAmount(7), 0, ',', '.') ?>
            </strong>
        </li>
        <li>
            <span>💎 Totaal diamanten</span>
            <strong style="color:#b9f2ff;">
                <?= number_format(
                    dailyDiamondReward(1) + dailyDiamondReward(2) + dailyDiamondReward(3) +
                    dailyDiamondReward(4) + dailyDiamondReward(5) + dailyDiamondReward(6) +
                    dailyDiamondReward(7), 0, ',', '.') ?> 💎
            </strong>
        </li>
        <li>
            <span>⭐ Totaal XP</span>
            <strong style="color:#58e08c;">
                <?= number_format(
                    dailyXpReward(1) + dailyXpReward(2) + dailyXpReward(3) +
                    dailyXpReward(4) + dailyXpReward(5) + dailyXpReward(6) +
                    dailyXpReward(7), 0, ',', '.') ?>
            </strong>
        </li>
    </ul>
</section>

<!-- Info -->
<section class="section">
    <div class="tip-card">
        <p>💡 <strong>Tip:</strong> Op <strong>dag 7</strong> krijg je een mega bonus:
        <strong style="color:#ffb040;">€5.000.000</strong> +
        <strong style="color:#b9f2ff;">1.750 💎</strong>!</p>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>