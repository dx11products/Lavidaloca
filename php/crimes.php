<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user     = currentUser($pdo);
$rankData = getRankData((int)$user['xp'], $RANKS);

if (isInHospital($user)) redirect('hospital.php');

$chain = getUserChain($pdo, $user['id']);
$chainMultiplier = getChainMultiplier((int)$chain['current_chain']);
$chainLabel = getChainLabel((int)$chain['current_chain']);
$nextMilestone = getNextChainMilestone((int)$chain['current_chain']);
$busted = getBustedInfo($pdo, $user['id']);

$levelMult = getLevelMultiplier($rankData['level']);

// Familie bonus
$crimeBonus = function_exists('getCrimeSuccessBonus') ? getCrimeSuccessBonus($pdo, $user['id']) : 0;

$result = null;
$error  = null;

// POST fallback
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crime'])) {
    $key  = $_POST['crime'] ?? '';
    $csrf = $_POST['csrf'] ?? '';

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } elseif (!isset($CRIMES_V2[$key])) {
        $error = 'Onbekende misdaad.';
    } else {
        $crime = $CRIMES_V2[$key];

        if ($rankData['level'] < $crime['min_rank']) {
            $error = 'Je rank is te laag.';
        } else {
            $cooldown = canDoCrime($pdo, $user['id'], $key);
            if (!$cooldown['ok']) {
                $error = 'Wacht nog ' . $cooldown['wait'] . ' seconden.';
            } else {
                $finalSuccessRate = min(95, $crime['success_rate'] + $crimeBonus);

                $roll = random_int(1, 100);
                $success = $roll <= $finalSuccessRate;
                $eff = getEffectiveReward($crime, $rankData['level']);

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

                        $cooldownSec = 0;
                        $fine = 0;
                        if ($gotBusted) {
                            $fine = random_int($crime['fine_min'], $crime['fine_max']);
                            $fine = min($fine, (int)$user['money'] + $reward);
                            $pdo->prepare("UPDATE users SET money = money - ? WHERE id = ?")->execute([$fine, $user['id']]);
                            recordBusted($pdo, $user['id']);
                            $cooldownSec = (int)$crime['cooldown'];
                            setCrimeCooldown($pdo, $user['id'], $key, $cooldownSec);
                            logActivity($pdo, $user['id'], "🚔 GEPAKT na {$crime['name']} — boete €" . number_format($fine, 0, ',', '.'));
                        } else {
                            logActivity($pdo, $user['id'], "✅ {$crime['name']} — €" . number_format($reward, 0, ',', '.'));
                        }

                        addClicks($pdo, $user['id'], CLICKS_PER_CRIME_WIN);

                        $vaultDrop = null;
                        if (random_int(1, 100) <= VAULT_DROP_CRIME_WIN) {
                            $vaultDrop = giveRandomVaultCode($pdo, $user['id']);
                        }

                        $pdo->commit();

                        $newRank = getRankData($user['xp'] + $xpGain, $RANKS);
                        if ($newRank['level'] > $rankData['level']) {
                            $pdo->prepare("UPDATE users SET rank_title = ? WHERE id = ?")->execute([$newRank['name'], $user['id']]);
                            logActivity($pdo, $user['id'], "🎖️ Gepromoveerd naar {$newRank['name']}!");
                        }

                        $result = [
                            'success' => true, 'name' => $crime['name'], 'reward' => $reward,
                            'xp' => $xpGain, 'busted' => $gotBusted, 'fine' => $gotBusted ? $fine : 0,
                            'vault_drop' => $vaultDrop,
                        ];
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $error = 'Systeemfout.';
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
                        setCrimeCooldown($pdo, $user['id'], $key, (int)$crime['cooldown']);

                        logActivity($pdo, $user['id'], "❌ {$crime['name']} mislukt — €" . number_format($fine, 0, ',', '.'));
                        addClicks($pdo, $user['id'], CLICKS_PER_CRIME_FAIL);
                        $pdo->commit();

                        $result = ['success' => false, 'name' => $crime['name'], 'fine' => $fine];
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $error = 'Systeemfout.';
                    }
                }

                $user = currentUser($pdo);
                $chain = getUserChain($pdo, $user['id']);
                $chainMultiplier = getChainMultiplier((int)$chain['current_chain']);
                $chainLabel = getChainLabel((int)$chain['current_chain']);
                $nextMilestone = getNextChainMilestone((int)$chain['current_chain']);
            }
        }
    }
}

$recentCrimes = getRecentCrimes($pdo, $user['id'], 5);
$crimeStats = getUserCrimeStats($pdo, $user['id']);

$pageTitle = 'Crimes — Vendetta';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Pleeg een <span>misdaad</span></h1>
    <p>Alleen als je gepakt wordt krijg je een korte cooldown. Anders kun je direct door!</p>
</div>

<?php if ($crimeBonus > 0): ?>
    <div class="tip-card" style="border-left-color:#58e08c;margin-bottom:20px;">
        <p>🎯 <strong>Familie bonus actief:</strong>
        Je krijgt <strong style="color:#58e08c;">+<?= $crimeBonus ?>%</strong>
        extra succes-kans op alle crimes door je familie-upgrade!</p>
    </div>
<?php endif; ?>

<div class="rank-multiplier-banner">
    <div class="rm-icon">📈</div>
    <div class="rm-info">
        <strong>Rank bonus actief: ×<?= number_format($levelMult, 2) ?></strong>
        <p class="muted">Op rank <?= $rankData['level'] ?> krijg je <?= round(($levelMult - 1) * 100) ?>% extra op elke misdaad.</p>
    </div>
</div>

<?php if ((int)$chain['current_chain'] > 0): ?>
<section class="section">
    <div class="chain-banner <?= $chainMultiplier >= 1.5 ? 'hot' : '' ?>">
        <div class="chain-icon">🔥</div>
        <div class="chain-info">
            <h3>
                Chain: <?= (int)$chain['current_chain'] ?>
                <?php if ($chainLabel): ?><span class="chain-label">— <?= $chainLabel ?></span><?php endif; ?>
            </h3>
            <p class="muted">
                Bonus: <strong style="color:var(--gold);">×<?= number_format($chainMultiplier, 2) ?></strong>
                <?php if ($nextMilestone): ?> · Volgende: <?= $nextMilestone ?> crimes<?php endif; ?>
            </p>
        </div>
        <div class="chain-best">
            <span>Best</span>
            <strong><?= (int)$chain['best_chain'] ?></strong>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($result): ?>
    <div class="alert <?= $result['success'] ? (!empty($result['busted']) ? 'alert-error' : 'alert-success') : 'alert-error' ?>">
        <?php if ($result['success']): ?>
            <?php if (!empty($result['busted'])): ?>
                🚔 <strong><?= htmlspecialchars($result['name']) ?></strong> geslaagd maar je werd GEPAKT!
                <br>💰 Buit: €<?= number_format($result['reward'], 0, ',', '.') ?>
                · 🚔 Boete: €<?= number_format($result['fine'], 0, ',', '.') ?>
                · ⭐ +<?= $result['xp'] ?> XP
            <?php else: ?>
                ✅ <strong><?= htmlspecialchars($result['name']) ?></strong> geslaagd!
                <br>💰 €<?= number_format($result['reward'], 0, ',', '.') ?> · ⭐ +<?= $result['xp'] ?> XP
            <?php endif; ?>
            <?php if (!empty($result['vault_drop'])): ?>
                <br>🔐 <strong>Kluiscijfer!</strong> <?= htmlspecialchars($result['vault_drop']['vault_name']) ?> — cijfer <?= (int)$result['vault_drop']['value'] ?>
            <?php endif; ?>
        <?php else: ?>
            ❌ <strong><?= htmlspecialchars($result['name']) ?></strong> mislukt. Boete: €<?= number_format($result['fine'], 0, ',', '.') ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon">💰</div>
        <div class="stat-value gold">€<?= number_format($user['money'], 0, ',', '.') ?></div>
        <div class="stat-label">Cash</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">⭐</div>
        <div class="stat-value"><?= number_format($rankData['level']) ?></div>
        <div class="stat-label">Rank level</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">🎯</div>
        <div class="stat-value"><?= number_format((int)($crimeStats['successes'] ?? 0)) ?></div>
        <div class="stat-label">Successen</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">🚔</div>
        <div class="stat-value"><?= (int)$busted['busted_count'] ?></div>
        <div class="stat-label">Keer gepakt</div>
    </div>
</div>

<?php
$tiers = [
    1 => ['name' => 'Kleine misdaden',     'icon' => '🥉'],
    2 => ['name' => 'Straatcriminaliteit', 'icon' => '🥈'],
    3 => ['name' => 'Gewapende misdaden',  'icon' => '🥇'],
    4 => ['name' => 'Grote overvallen',    'icon' => '💎'],
    5 => ['name' => 'Meesters',            'icon' => '👑'],
];
foreach ($tiers as $tierNum => $tier):
    $tierCrimes = array_filter($CRIMES_V2, fn($c) => $c['tier'] === $tierNum);
    if (empty($tierCrimes)) continue;
?>
<section class="section">
    <h2><?= $tier['icon'] ?> <?= $tier['name'] ?></h2>

    <div class="crime-list">
        <?php foreach ($tierCrimes as $key => $crime):
            $locked = $rankData['level'] < $crime['min_rank'];
            $cooldown = canDoCrime($pdo, $user['id'], $key);
            $disabled = $locked || !$cooldown['ok'];
            $eff = getEffectiveReward($crime, $rankData['level']);
            $finalRate = min(95, $crime['success_rate'] + $crimeBonus);
        ?>
        <div class="crime-card <?= $disabled ? 'disabled' : '' ?>"
             data-cooldown="<?= (int)$crime['cooldown'] ?>"
             data-cooldown-remaining="<?= $cooldown['wait'] ?>">
            <div class="crime-info">
                <h3>
                    <?= htmlspecialchars($crime['name']) ?>
                    <?php if ($locked): ?>
                        <span class="lock">🔒 Rank <?= $crime['min_rank'] ?>+</span>
                    <?php endif; ?>
                    <span class="cooldown-badge">
                        🚔 <?= (int)$crime['cooldown'] ?>s bij boete
                    </span>
                </h3>
                <p><?= htmlspecialchars($crime['desc']) ?></p>
                <div class="crime-meta">
                    <span>💰 €<?= number_format($eff['min'], 0, ',', '.') ?>–€<?= number_format($eff['max'], 0, ',', '.') ?></span>
                    <span>⭐ <?= (int)floor($crime['xp_reward'] * $levelMult) ?> XP</span>
                    <span>
                        🎯 <?= $finalRate ?>%
                        <?php if ($crimeBonus > 0): ?>
                            <small style="color:#58e08c;">(+<?= $crimeBonus ?>% familie)</small>
                        <?php endif; ?>
                    </span>
                    <span class="risk-badge <?= $crime['busted_risk'] >= 30 ? 'high' : ($crime['busted_risk'] >= 15 ? 'medium' : 'low') ?>">
                        🚔 <?= $crime['busted_risk'] ?>%
                    </span>
                </div>
            </div>

            <div class="crime-action">
                <button type="button"
                        class="btn btn-gold crime-btn"
                        data-action="crime"
                        data-crime="<?= htmlspecialchars($key) ?>"
                        <?= $disabled ? 'disabled' : '' ?>>
                    <?php if ($locked): ?>
                        Rank <?= $crime['min_rank'] ?>+
                    <?php elseif (!$cooldown['ok']): ?>
                        🚔 <?= $cooldown['wait'] ?>s
                    <?php else: ?>
                        Uitvoeren
                    <?php endif; ?>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endforeach; ?>

<?php if (!empty($recentCrimes)): ?>
<section class="section">
    <h2>📜 Laatste misdaden</h2>
    <ul class="activity-list">
        <?php foreach ($recentCrimes as $c):
            $crimeInfo = $CRIMES_V2[$c['crime_key']] ?? null;
            $name = $crimeInfo['name'] ?? $c['crime_key'];
        ?>
            <li>
                <span>
                    <?= $c['success'] ? '✅' : '❌' ?>
                    <strong><?= htmlspecialchars($name) ?></strong>
                    <?php if ($c['success'] && $c['reward'] > 0): ?>
                        <span style="color:#58e08c;">+€<?= number_format($c['reward'], 0, ',', '.') ?></span>
                    <?php endif; ?>
                    <?php if (!$c['success'] && $c['fine'] > 0): ?>
                        <span style="color:#ff5c5c;">-€<?= number_format($c['fine'], 0, ',', '.') ?></span>
                    <?php endif; ?>
                </span>
                <time><?= date('d M H:i', strtotime($c['created_at'])) ?></time>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>