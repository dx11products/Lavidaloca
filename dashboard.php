<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user     = currentUser($pdo);
$rankData = getRankData((int)$user['xp'], $RANKS);

// Achievement check
$newAchievements = checkAchievements($pdo, $user['id']);
if (!empty($newAchievements)) {
    $user     = currentUser($pdo);
    $rankData = getRankData((int)$user['xp'], $RANKS);
}

// XP progress
$xpNow  = $rankData['next'] ? ($user['xp'] - $RANKS[$rankData['level']]['xp']) : 0;
$xpNeed = $rankData['next'] ? ($rankData['next']['xp'] - $RANKS[$rankData['level']]['xp']) : 1;
$xpPct  = $rankData['next'] ? min(100, round(($xpNow / $xpNeed) * 100)) : 100;

// Status
$inHospital  = isInHospital($user);
$secondsLeft = hospitalSecondsLeft($user);
$userFamily  = getUserFamily($pdo, $user['id']);
$unreadCount = unreadNotifications($pdo, $user['id']);

// Berekeningen
$totalWealth = (int)$user['money'] + (int)($user['bank_money'] ?? 0);
$energyPct   = round(($user['energy'] / max(1, $user['max_energy'])) * 100);
$healthPct   = round(($user['health'] / max(1, $user['max_health'])) * 100);
$btc         = (float)($user['btc'] ?? 0);
$btcEur      = function_exists('btcToEur') ? btcToEur($btc) : 0;

// Win rate
$totalAttacks = (int)$user['attacks_won'] + (int)$user['attacks_lost'];
$winRate      = $totalAttacks > 0 ? round(($user['attacks_won'] / $totalAttacks) * 100) : 0;

// Begroeting op basis van tijd
$hour = (int)date('H');
if ($hour < 6)      $greeting = 'Goedenacht';
elseif ($hour < 12) $greeting = 'Goedemorgen';
elseif ($hour < 18) $greeting = 'Goedemiddag';
else                  $greeting = 'Goedenavond';

// Laatste 5 activiteiten
$stmt = $pdo->prepare("SELECT message, created_at FROM activity_log WHERE user_id = ? ORDER BY id DESC LIMIT 5");
$stmt->execute([$user['id']]);
$activities = $stmt->fetchAll();

// Laatste 3 notificaties
$stmt = $pdo->prepare("SELECT message, icon, created_at FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 3");
$stmt->execute([$user['id']]);
$notifications = $stmt->fetchAll();

// BTC miners count
$minerCount = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_btc_miners WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $minerCount = (int)$stmt->fetchColumn();
} catch (Exception $e) {}

$pageTitle = 'Dashboard — Vendetta';
require __DIR__ . '/includes/header.php';
?>

<!-- Welkomst banner -->
<section class="dashboard-hero">
    <div class="dh-content">
        <div class="dh-greeting">
            <?= $greeting ?>, <strong><?= htmlspecialchars($user['username']) ?></strong>
        </div>
        <div class="dh-rank">
            <?= htmlspecialchars($rankData['name']) ?>
            <span class="dh-rank-level">· Rank <?= $rankData['level'] ?></span>
        </div>
        <div class="dh-subtitle">
            "De wereld is van wie durft te nemen."
        </div>
    </div>
    <div class="dh-quickstats">
        <div class="dh-qs">
            <span class="dh-qs-label">Totaal vermogen</span>
            <strong class="dh-qs-value gold">€<?= number_format($totalWealth, 0, ',', '.') ?></strong>
        </div>
        <div class="dh-qs">
            <span class="dh-qs-label">Achievements</span>
            <strong class="dh-qs-value"><?= (int)($user['achievements_count'] ?? 0) ?></strong>
        </div>
    </div>
</section>

<!-- Meldingen -->
<?php if ($inHospital): ?>
    <div class="alert alert-error">
        🏥 Je ligt in het ziekenhuis voor nog
        <strong><?= floor($secondsLeft / 60) ?> min <?= $secondsLeft % 60 ?> sec</strong>.
        <a href="hospital.php">Koop vervroegd ontslag</a> of wacht tot je hersteld bent.
    </div>
<?php endif; ?>

<?php if (!empty($newAchievements)): ?>
    <div class="alert alert-success">
        🏆 <strong>Nieuwe achievement<?= count($newAchievements) > 1 ? 's' : '' ?> unlocked!</strong>
        <?php foreach ($newAchievements as $a): ?>
            <br><?= $a['icon'] ?> <?= htmlspecialchars($a['name']) ?>
        <?php endforeach; ?>
        <br><a href="achievements.php">Bekijk alle achievements →</a>
    </div>
<?php endif; ?>

<!-- Status -->
<section class="section">
    <h2>Jouw status</h2>

    <div class="dashboard-stats">
        <div class="ds-card">
            <div class="ds-head">
                <span class="ds-icon">💵</span>
                <span class="ds-label">Contant</span>
            </div>
            <div class="ds-value gold">€<?= number_format($user['money'], 0, ',', '.') ?></div>
            <div class="ds-foot"><a href="bank.php">Naar bank →</a></div>
        </div>

        <div class="ds-card">
            <div class="ds-head">
                <span class="ds-icon">🏦</span>
                <span class="ds-label">Op de bank</span>
            </div>
            <div class="ds-value gold">€<?= number_format($user['bank_money'] ?? 0, 0, ',', '.') ?></div>
            <div class="ds-foot"><span class="muted">+<?= BANK_INTEREST_RATE * 100 ?>% per dag</span></div>
        </div>

        <div class="ds-card">
            <div class="ds-head">
                <span class="ds-icon">₿</span>
                <span class="ds-label">Bitcoin</span>
            </div>
            <div class="ds-value" style="color:#f7931a;">
                <?= function_exists('formatBtc') ? formatBtc($btc) : '0' ?>
            </div>
            <div class="ds-foot">
                <span class="muted">≈ €<?= number_format($btcEur, 0, ',', '.') ?>
                · <?= $minerCount ?> miner<?= $minerCount === 1 ? '' : 's' ?></span>
            </div>
        </div>

        <div class="ds-card">
            <div class="ds-head">
                <span class="ds-icon">🖱️</span>
                <span class="ds-label">Clicks</span>
            </div>
            <div class="ds-value" style="color:#4a9dff;"><?= number_format((int)($user['clicks'] ?? 0), 0, ',', '.') ?></div>
            <div class="ds-foot"><a href="weapons.php">Naar wapens →</a></div>
        </div>

        <div class="ds-card">
            <div class="ds-head">
                <span class="ds-icon">⚡</span>
                <span class="ds-label">Energie</span>
            </div>
            <div class="ds-value"><?= (int)$user['energy'] ?> <span class="ds-value-small">/ <?= (int)$user['max_energy'] ?></span></div>
            <div class="bar"><div class="bar-fill energy" style="width:<?= $energyPct ?>%"></div></div>
            <div class="ds-foot"><span class="muted">+1 per minuut</span></div>
        </div>

        <div class="ds-card">
            <div class="ds-head">
                <span class="ds-icon">❤️</span>
                <span class="ds-label">Gezondheid</span>
            </div>
            <div class="ds-value"><?= (int)$user['health'] ?> <span class="ds-value-small">/ <?= (int)$user['max_health'] ?></span></div>
            <div class="bar"><div class="bar-fill health" style="width:<?= $healthPct ?>%"></div></div>
            <div class="ds-foot"><a href="hospital.php">Naar ziekenhuis →</a></div>
        </div>
    </div>
</section>

<!-- Rank -->
<section class="section">
    <h2>Rank vooruitgang</h2>

    <div class="rank-banner">
        <div class="rank-banner-left">
            <div class="rank-banner-title"><?= htmlspecialchars($rankData['name']) ?></div>
            <div class="rank-banner-sub"><?= number_format($user['xp']) ?> XP totaal</div>
        </div>
        <?php if ($rankData['next']): ?>
            <div class="rank-banner-right">
                Volgende: <strong><?= htmlspecialchars($rankData['next']['name']) ?></strong>
            </div>
        <?php else: ?>
            <div class="rank-banner-right gold">👑 Hoogste rank bereikt</div>
        <?php endif; ?>
    </div>

    <?php if ($rankData['next']): ?>
        <div class="rank-bar">
            <div class="rank-bar-fill" style="width:<?= $xpPct ?>%"></div>
            <div class="rank-bar-label"><?= $xpPct ?>%</div>
        </div>
        <div class="rank-bar-meta">
            <span><?= number_format($xpNow) ?> / <?= number_format($xpNeed) ?> XP</span>
            <span>Nog <?= number_format($xpNeed - $xpNow) ?> XP tot promotie</span>
        </div>
    <?php endif; ?>
</section>

<!-- Bende -->
<?php if ($userFamily): ?>
<section class="section">
    <h2>Jouw familie</h2>
    <a href="family.php?id=<?= (int)$userFamily['id'] ?>" class="family-widget">
        <div class="fw-tag">[<?= htmlspecialchars($userFamily['tag']) ?>]</div>
        <div class="fw-info">
            <h3><?= htmlspecialchars($userFamily['name']) ?></h3>
            <p>Jouw rol: <strong><?= htmlspecialchars($userFamily['member_rank']) ?></strong></p>
        </div>
        <div class="fw-money">
            <span class="muted">Bendekas</span>
            <strong class="gold">€<?= number_format($userFamily['money'], 0, ',', '.') ?></strong>
        </div>
    </a>
</section>
<?php endif; ?>

<!-- Statistieken -->
<section class="section">
    <h2>Statistieken</h2>

    <div class="mini-stats-grid">
        <div class="mini-stat">
            <div class="mini-stat-icon">🎯</div>
            <div class="mini-stat-value"><?= number_format((int)$user['crimes_done']) ?></div>
            <div class="mini-stat-label">Crimes</div>
        </div>
        <div class="mini-stat">
            <div class="mini-stat-icon">⚔️</div>
            <div class="mini-stat-value"><?= number_format((int)$user['attacks_won']) ?></div>
            <div class="mini-stat-label">Gewonnen</div>
        </div>
        <div class="mini-stat">
            <div class="mini-stat-icon">❌</div>
            <div class="mini-stat-value"><?= number_format((int)$user['attacks_lost']) ?></div>
            <div class="mini-stat-label">Verloren</div>
        </div>
        <div class="mini-stat">
            <div class="mini-stat-icon">📊</div>
            <div class="mini-stat-value"><?= $winRate ?>%</div>
            <div class="mini-stat-label">Win rate</div>
        </div>
        <div class="mini-stat">
            <div class="mini-stat-icon">🏥</div>
            <div class="mini-stat-value"><?= number_format((int)($user['times_hospitalized'] ?? 0)) ?></div>
            <div class="mini-stat-label">Ziekenhuis</div>
        </div>
        <div class="mini-stat">
            <div class="mini-stat-icon">🎖️</div>
            <div class="mini-stat-value"><?= number_format((int)($user['achievements_count'] ?? 0)) ?></div>
            <div class="mini-stat-label">Achievements</div>
        </div>
    </div>
</section>

<!-- Activiteit & Notificaties -->
<div class="dashboard-split">
    <section class="section">
        <h2>Recente activiteit</h2>
        <?php if (empty($activities)): ?>
            <p class="muted">Nog geen activiteit. Ga op pad en verdien je eerste geld!</p>
        <?php else: ?>
            <ul class="activity-list">
                <?php foreach ($activities as $a): ?>
                    <li>
                        <span><?= htmlspecialchars($a['message']) ?></span>
                        <time><?= date('d M H:i', strtotime($a['created_at'])) ?></time>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="section">
        <h2>
            Notificaties
            <?php if ($unreadCount > 0): ?>
                <a href="notifications.php" class="section-link"><?= $unreadCount ?> nieuw</a>
            <?php endif; ?>
        </h2>
        <?php if (empty($notifications)): ?>
            <p class="muted">Nog geen meldingen.</p>
        <?php else: ?>
            <ul class="notif-mini-list">
                <?php foreach ($notifications as $n): ?>
                    <li>
                        <span class="notif-mini-icon"><?= htmlspecialchars($n['icon']) ?></span>
                        <div>
                            <p><?= htmlspecialchars($n['message']) ?></p>
                            <time><?= date('d M H:i', strtotime($n['created_at'])) ?></time>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<!-- Tip -->
<?php
$tips = [
    "💡 Stort je geld op de bank — overvallers kunnen het niet stelen.",
    "💡 Equipe een wapen voordat je gaat vechten. Je aanval stijgt meteen!",
    "💡 Onderhoud je energie: 1 punt per minuut, dus log regelmatig in.",
    "💡 Doe dagelijks je streak — na 7 dagen verdien je 7x zoveel.",
    "💡 Sluit je aan bij een bende voor extra bescherming en sociaal voordeel.",
    "💡 Bankovervallen zijn riskant maar leveren €5.000 tot €25.000 op.",
    "💡 Win rate is belangrijker dan aantal aanvallen. Kies je doelwitten slim.",
    "💡 Plaats een Bitcoin miner in je huis voor passief inkomen!",
    "💡 Kraak kluizen voor zeldzame BTC beloningen.",
    "💡 Verdien clicks met crimes en gevechten — koop er krachtige wapens mee.",
];
$tip = $tips[array_rand($tips)];
?>
<section class="section">
    <h2>Tip van de dag</h2>
    <div class="tip-card">
        <p><?= $tip ?></p>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>