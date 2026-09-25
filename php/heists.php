<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = currentUser($pdo);
$rankData = getRankData((int)$user['xp'], $RANKS);
$country = getCountry($pdo, $user['current_country']);

$activeLobby = getUserActiveLobby($pdo, $user['id']);
if ($activeLobby) {
    redirect("heist_lobby.php?id={$activeLobby['id']}");
}

$heistCooldown = getHeistCooldown($pdo, $user['id']);
$levelMult = getHeistLevelMultiplier($rankData['level']);

// Familie heist bonus
$heistBonus = function_exists('getHeistSuccessBonus') ? getHeistSuccessBonus($pdo, $user['id']) : 0;

$availableHeists = getAvailableHeists($pdo, $rankData['level']);
$openLobbies = getOpenHeistLobbies($pdo, $user['current_country']);
$myFamily = getUserFamily($pdo, $user['id']);
$familyLobbies = $myFamily ? getFamilyHeistLobbies($pdo, $myFamily['id'], $user['current_country']) : [];

$pageTitle = 'Georganiseerde misdaad — Vendetta';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Georganiseerde <span>misdaad</span></h1>
    <p>Werk samen met 1-4 spelers voor grote overvallen. Hoe groter het team, hoe hoger de buit.</p>
</div>

<?php if ($heistBonus > 0): ?>
    <div class="tip-card" style="border-left-color:#58e08c;margin-bottom:20px;">
        <p>🏴 <strong>Familie bonus actief:</strong>
        Je krijgt <strong style="color:#58e08c;">+<?= $heistBonus ?>%</strong>
        extra succes-kans op alle heists door je familie-upgrade!</p>
    </div>
<?php endif; ?>

<?php if (!$heistCooldown['ok']): ?>
    <div class="alert alert-error">
        ⏱️ Je bent nog herstellende van je laatste heist.
        Wacht nog <strong><?= floor($heistCooldown['wait'] / 60) ?>m <?= $heistCooldown['wait'] % 60 ?>s</strong>
        voor je weer een heist kunt starten.
    </div>
<?php endif; ?>

<div class="rank-multiplier-banner">
    <div class="rm-icon">📈</div>
    <div class="rm-info">
        <strong>Rank bonus actief: ×<?= number_format($levelMult, 2) ?></strong>
        <p class="muted">
            Op rank <?= $rankData['level'] ?> krijg je <?= round(($levelMult - 1) * 100) ?>% extra buit per heist.
            Cooldown: <?= HEIST_COMPLETION_COOLDOWN_MIN ?> minuten tussen heists.
        </p>
    </div>
</div>

<?php if ($myFamily): ?>
<section class="section">
    <h2>👥 Bende-heists — [<?= htmlspecialchars($myFamily['tag']) ?>]</h2>
    <p class="muted">Deze heists zijn alleen voor leden van jouw familie.</p>

    <?php if (empty($familyLobbies)): ?>
        <p class="muted">Geen open bende-heists. Start er een hieronder!</p>
    <?php else: ?>
        <div class="lobby-list">
            <?php foreach ($familyLobbies as $l): ?>
                <div class="lobby-row">
                    <span class="lobby-icon"><?= $l['icon'] ?></span>
                    <div class="lobby-info">
                        <h3><?= htmlspecialchars($l['heist_name']) ?></h3>
                        <p class="muted">Host: <?= htmlspecialchars($l['host_name']) ?></p>
                        <div class="lobby-meta">
                            <span>👥 <?= $l['member_count'] ?> / <?= $l['max_players'] ?></span>
                            <span>⏱️ <?= $l['duration_minutes'] ?> min</span>
                            <span>💰 €<?= number_format((int)floor($l['base_reward_min'] * $levelMult), 0, ',', '.') ?>–€<?= number_format((int)floor($l['base_reward_max'] * $levelMult), 0, ',', '.') ?></span>
                        </div>
                    </div>
                    <div class="lobby-action">
                        <a href="heist_lobby.php?id=<?= (int)$l['id'] ?>" class="btn btn-gold">Bekijk</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="section">
    <h2>🏴 Actieve teams (<?= count($openLobbies) ?>)</h2>

    <?php if (empty($openLobbies)): ?>
        <p class="muted">Geen open teams. Start zelf een heist hieronder!</p>
    <?php else: ?>
        <div class="lobby-list">
            <?php foreach ($openLobbies as $l):
                $full = (int)$l['member_count'] >= (int)$l['max_players'];
                $lock = $rankData['level'] < (int)$l['min_rank'];
                $disabled = $full || $lock || !$heistCooldown['ok'];
            ?>
                <div class="lobby-row">
                    <span class="lobby-icon"><?= $l['icon'] ?></span>
                    <div class="lobby-info">
                        <h3><?= htmlspecialchars($l['heist_name']) ?></h3>
                        <p class="muted">Host: <?= htmlspecialchars($l['host_name']) ?></p>
                        <div class="lobby-meta">
                            <span>👥 <?= $l['member_count'] ?> / <?= $l['max_players'] ?></span>
                            <span>⏱️ <?= $l['duration_minutes'] ?> min</span>
                            <span>💰 €<?= number_format((int)floor($l['base_reward_min'] * $levelMult), 0, ',', '.') ?>–€<?= number_format((int)floor($l['base_reward_max'] * $levelMult), 0, ',', '.') ?></span>
                        </div>
                    </div>
                    <div class="lobby-action">
                        <?php if ($full): ?>
                            <button class="btn btn-outline" disabled>Vol</button>
                        <?php elseif ($lock): ?>
                            <button class="btn btn-outline" disabled>Rank <?= $l['min_rank'] ?>+</button>
                        <?php elseif (!$heistCooldown['ok']): ?>
                            <button class="btn btn-outline" disabled>⏱️ Cooldown</button>
                        <?php else: ?>
                            <a href="heist_lobby.php?id=<?= (int)$l['id'] ?>" class="btn btn-gold">Bekijk</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="section">
    <h2>🎭 Jouw rol-niveaus</h2>
    <p class="muted">Doe heists in een rol om XP te verdienen. Hogere niveaus = meer bonus.</p>

    <?php
    $myRoles = getUserRoleLevels($pdo, $user['id']);
    $hasAnyXp = false;
    foreach ($myRoles as $r) { if ($r['xp'] > 0) { $hasAnyXp = true; break; } }
    ?>

    <?php if (!$hasAnyXp): ?>
        <p class="muted">Je hebt nog geen rol-XP. Doe je eerste heist om te beginnen!</p>
    <?php else: ?>
        <div class="role-level-grid">
            <?php foreach ($myRoles as $r):
                if ($r['xp'] === 0) continue;
                $nextLevel = $r['level'] < 5 ? $r['level'] + 1 : null;
                $xpToNext = $nextLevel ? (ROLE_LEVEL_XP[$nextLevel] - $r['xp']) : 0;
                $progressPct = $nextLevel
                    ? min(100, round((($r['xp'] - ROLE_LEVEL_XP[$r['level']]) /
                                     (ROLE_LEVEL_XP[$nextLevel] - ROLE_LEVEL_XP[$r['level']])) * 100))
                    : 100;
            ?>
                <div class="role-level-card" style="border-color:<?= $r['level_color'] ?>33;">
                    <div class="role-level-head">
                        <span class="role-level-icon"><?= $r['role_icon'] ?></span>
                        <div>
                            <h3><?= htmlspecialchars($r['role_name']) ?></h3>
                            <span class="role-level-badge" style="color:<?= $r['level_color'] ?>;">
                                <?= $r['level_icon'] ?> <?= $r['level_name'] ?> — Level <?= $r['level'] ?>
                            </span>
                        </div>
                    </div>

                    <div class="role-level-stats">
                        <span><?= $r['heists_done'] ?> heists</span>
                        <span><?= $r['heists_won'] ?> gewonnen</span>
                    </div>

                    <?php if ($nextLevel): ?>
                        <div class="bar" style="margin-top:10px;">
                            <div class="bar-fill xp" style="width:<?= $progressPct ?>%"></div>
                        </div>
                        <div class="role-level-progress">
                            <span><?= $r['xp'] ?> / <?= ROLE_LEVEL_XP[$nextLevel] ?> XP</span>
                            <span>Nog <?= $xpToNext ?> XP</span>
                        </div>
                    <?php else: ?>
                        <div class="role-level-maxed">🏆 Meester-niveau bereikt</div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="section">
    <h2>🎯 Beschikbare heists</h2>
    <p class="muted">Kies een heist om een nieuw team te starten.</p>

    <?php
    $tiers = [
        1 => ['name' => 'Kleine overvallen', 'icon' => '🥉'],
        2 => ['name' => 'Samenwerking',      'icon' => '🥈'],
        3 => ['name' => 'Grote overvallen',  'icon' => '🥇'],
        4 => ['name' => 'Elite operaties',   'icon' => '👑'],
    ];
    foreach ($tiers as $tierNum => $tierInfo):
        $tierHeists = array_filter($availableHeists, fn($h) => $h['tier'] === $tierNum);
        if (empty($tierHeists)) continue;
    ?>
        <h3 style="margin-top:24px;font-size:1rem;color:var(--gold);letter-spacing:1px;">
            <?= $tierInfo['icon'] ?> <?= $tierInfo['name'] ?>
        </h3>

        <div class="heist-grid">
            <?php foreach ($tierHeists as $h):
                $canAfford = $user['money'] >= (int)$h['entry_fee'];
                $cooldownOK = $heistCooldown['ok'];
                $disabled = !$canAfford || !$cooldownOK;
                $riskColor = match($h['risk_level']) {
                    'low'     => '#58e08c',
                    'medium'  => '#c9a44c',
                    'high'    => '#ff9a5c',
                    'extreme' => '#ff5c5c',
                };
                $effMin = (int)floor($h['base_reward_min'] * $levelMult);
                $effMax = (int)floor($h['base_reward_max'] * $levelMult);
                $effXp  = (int)floor($h['xp_reward'] * $levelMult);
                $finalRate = min(95, $h['success_rate'] + $heistBonus);
            ?>
                <div class="heist-card risk-<?= $h['risk_level'] ?>">
                    <div class="heist-head">
                        <span class="heist-icon"><?= $h['icon'] ?></span>
                        <div>
                            <h3><?= htmlspecialchars($h['name']) ?></h3>
                            <span class="heist-risk" style="color:<?= $riskColor ?>;">
                                <?= strtoupper($h['risk_level']) ?> RISICO
                            </span>
                        </div>
                    </div>
                    <p><?= htmlspecialchars($h['description']) ?></p>

                    <div class="heist-stats">
                        <div><span>👥</span> <strong><?= $h['min_players'] ?>–<?= $h['max_players'] ?> spelers</strong></div>
                        <div><span>⏱️</span> <strong><?= $h['duration_minutes'] ?> min</strong></div>
                        <div><span>💰</span> <strong>€<?= number_format($effMin, 0, ',', '.') ?>–€<?= number_format($effMax, 0, ',', '.') ?></strong></div>
                        <div><span>⭐</span> <strong><?= number_format($effXp) ?> XP</strong></div>
                        <div>
                            <span>🎯</span>
                            <strong><?= $finalRate ?>% kans
                                <?php if ($heistBonus > 0): ?>
                                    <small style="color:#58e08c;">(+<?= $heistBonus ?>%)</small>
                                <?php endif; ?>
                            </strong>
                        </div>
                        <?php if ((int)$h['entry_fee'] > 0): ?>
                            <div><span>💵</span> <strong>€<?= number_format((int)$h['entry_fee'], 0, ',', '.') ?> inleg</strong></div>
                        <?php endif; ?>
                    </div>

                    <a href="heist_create.php?heist=<?= urlencode($h['key']) ?>"
                       class="btn btn-gold btn-full <?= $disabled ? 'disabled' : '' ?>"
                       <?= $disabled ? 'style="pointer-events:none;opacity:.5;"' : '' ?>>
                        <?php if (!$cooldownOK): ?>
                            ⏱️ Cooldown
                        <?php elseif (!$canAfford): ?>
                            Te weinig geld
                        <?php else: ?>
                            Team starten
                        <?php endif; ?>
                    </a>

                    <?php if ($myFamily): ?>
                        <a href="heist_create.php?heist=<?= urlencode($h['key']) ?>&family=1"
                           class="btn btn-outline btn-full"
                           style="margin-top:6px;<?= $disabled ? 'pointer-events:none;opacity:.5;' : '' ?>">
                            👥 Bende-versie
                        </a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>