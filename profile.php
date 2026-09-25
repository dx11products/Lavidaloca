<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = currentUser($pdo);

// Welk profiel bekijken we?
$profileUserId = (int)($_GET['id'] ?? $user['id']);
$isOwnProfile  = $profileUserId === (int)$user['id'];

if ($isOwnProfile) {
    $viewUser = $user;
} else {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$profileUserId]);
    $viewUser = $stmt->fetch();
    if (!$viewUser) redirect('leaderboard.php');
}

$rankData = getRankData((int)$viewUser['xp'], $RANKS);

// Totaal activiteiten
$stmt = $pdo->prepare("SELECT COUNT(*) FROM activity_log WHERE user_id = ?");
$stmt->execute([$viewUser['id']]);
$totalActivities = (int)$stmt->fetchColumn();

// Laatste 10 activiteiten
$stmt = $pdo->prepare("
    SELECT message, created_at
    FROM activity_log
    WHERE user_id = ?
    ORDER BY id DESC
    LIMIT 10
");
$stmt->execute([$viewUser['id']]);
$activities = $stmt->fetchAll();

// Heist statistieken
$stmt = $pdo->prepare("
    SELECT COUNT(*) AS total,
           SUM(success = 1) AS won,
           COALESCE(SUM(reward), 0) AS earnings
    FROM heist_history WHERE user_id = ?
");
$stmt->execute([$viewUser['id']]);
$heistStats = $stmt->fetch();

// Rol-niveaus (alleen eigen profiel)
$myRoles = [];
$hasAnyRoleXp = false;
if ($isOwnProfile) {
    $myRoles = getUserRoleLevels($pdo, $viewUser['id']);
    foreach ($myRoles as $r) { if ($r['xp'] > 0) { $hasAnyRoleXp = true; break; } }
}

// Like-status
$alreadyLiked = false;
if (!$isOwnProfile) {
    $stmt = $pdo->prepare("SELECT id FROM user_likes WHERE user_id = ? AND target_id = ? LIMIT 1");
    $stmt->execute([$user['id'], $profileUserId]);
    $alreadyLiked = (bool)$stmt->fetch();
}

$likesReceived = (int)($viewUser['total_likes_received'] ?? 0);

// Flash bericht
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$pageTitle = 'Profiel — Vendetta';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1><?= htmlspecialchars($viewUser['username']) ?></h1>
    <p>
        <?= htmlspecialchars($rankData['name']) ?>
        • Lid sinds <?= date('d M Y', strtotime($viewUser['created_at'])) ?>
    </p>
</div>

<?php if ($flash): ?>
    <div class="alert alert-success"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<!-- Like banner (alleen andermans profiel) -->
<?php if (!$isOwnProfile): ?>
<section class="section">
    <div class="like-banner">
        <div class="like-info">
            <span class="like-count">❤️ <?= number_format($likesReceived) ?></span>
            <span class="muted">likes ontvangen</span>
        </div>
        <?php if ($alreadyLiked): ?>
            <button class="btn btn-outline" disabled>Al geliked</button>
        <?php else: ?>
            <a href="like.php?id=<?= $profileUserId ?>&csrf=<?= htmlspecialchars(csrf_token()) ?>"
               class="btn btn-gold">❤️ Like dit profiel</a>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<!-- Stats overzicht -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon">💰</div>
        <div class="stat-value gold">€<?= number_format($viewUser['money'], 0, ',', '.') ?></div>
        <div class="stat-label">Cash</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">⭐</div>
        <div class="stat-value"><?= number_format($viewUser['xp']) ?></div>
        <div class="stat-label">Totaal XP</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">🎯</div>
        <div class="stat-value"><?= (int)$viewUser['crimes_done'] ?></div>
        <div class="stat-label">Crimes gepleegd</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">📜</div>
        <div class="stat-value"><?= $totalActivities ?></div>
        <div class="stat-label">Activiteiten</div>
    </div>
</div>

<!-- Gevechtsstats -->
<section class="section">
    <h2>⚔️ Gevechten</h2>
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon">⚔️</div>
            <div class="stat-value"><?= (int)($viewUser['attacks_won'] ?? 0) ?></div>
            <div class="stat-label">Aanvallen gewonnen</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">❌</div>
            <div class="stat-value"><?= (int)($viewUser['attacks_lost'] ?? 0) ?></div>
            <div class="stat-label">Aanvallen verloren</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🏥</div>
            <div class="stat-value"><?= (int)($viewUser['times_hospitalized'] ?? 0) ?></div>
            <div class="stat-label">Keer in ziekenhuis</div>
        </div>
    </div>
</section>

<!-- Heist statistieken -->
<section class="section">
    <h2>🏴 Georganiseerde misdaad</h2>
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon">🏴</div>
            <div class="stat-value"><?= (int)($heistStats['total'] ?? 0) ?></div>
            <div class="stat-label">Heists totaal</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">✅</div>
            <div class="stat-value" style="color:#58e08c;"><?= (int)($heistStats['won'] ?? 0) ?></div>
            <div class="stat-label">Heists gewonnen</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">💰</div>
            <div class="stat-value gold">€<?= number_format((int)($heistStats['earnings'] ?? 0), 0, ',', '.') ?></div>
            <div class="stat-label">Heist inkomsten</div>
        </div>
    </div>
</section>

<!-- Rol-niveaus -->
<?php if ($isOwnProfile): ?>
<section class="section">
    <h2>🎭 Rol-niveaus</h2>

    <?php if (!$hasAnyRoleXp): ?>
        <p class="muted">Nog geen rol-XP. Doe heists via <a href="heists.php">Georganiseerde misdaad</a>.</p>
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
<?php endif; ?>

<!-- Rank progressie -->
<section class="section">
    <h2>🏆 Rank voortgang</h2>
    <div class="rank-progress">
        <div class="rank-row">
            <span>Huidige rank</span>
            <strong><?= htmlspecialchars($rankData['name']) ?> (Rank <?= $rankData['level'] ?>)</strong>
        </div>

        <?php if ($rankData['next']): ?>
            <?php
                $xpNow  = $viewUser['xp'] - $RANKS[$rankData['level']]['xp'];
                $xpNeed = $rankData['next']['xp'] - $RANKS[$rankData['level']]['xp'];
                $pct    = $xpNeed > 0 ? min(100, round(($xpNow / $xpNeed) * 100)) : 100;
            ?>
            <div class="rank-row">
                <span>Volgende rank</span>
                <strong><?= htmlspecialchars($rankData['next']['name']) ?> (<?= number_format($rankData['next']['xp']) ?> XP)</strong>
            </div>
            <div class="bar">
                <div class="bar-fill xp" style="width:<?= $pct ?>%"></div>
            </div>
            <p class="muted">
                <?= number_format($xpNow) ?> / <?= number_format($xpNeed) ?> XP richting promotie
                (<?= $pct ?>%)
            </p>
        <?php else: ?>
            <p class="muted">🎩 Hoogste rank bereikt. Je bent een echte Godfather.</p>
        <?php endif; ?>
    </div>
</section>

<!-- Status -->
<section class="section">
    <h2>📊 Status</h2>
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon">⚡</div>
            <div class="stat-value"><?= (int)$viewUser['energy'] ?> / <?= (int)$viewUser['max_energy'] ?></div>
            <div class="stat-label">Energie</div>
            <div class="bar">
                <div class="bar-fill energy"
                     style="width:<?= round(($viewUser['energy'] / max(1, $viewUser['max_energy'])) * 100) ?>%"></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">❤️</div>
            <div class="stat-value"><?= (int)$viewUser['health'] ?> / <?= (int)$viewUser['max_health'] ?></div>
            <div class="stat-label">Gezondheid</div>
            <div class="bar">
                <div class="bar-fill health"
                     style="width:<?= round(($viewUser['health'] / max(1, $viewUser['max_health'])) * 100) ?>%"></div>
            </div>
        </div>
    </div>
</section>

<!-- Account gegevens -->
<?php if ($isOwnProfile): ?>
<section class="section">
    <h2>👤 Account</h2>
    <ul class="info-list">
        <li>
            <span>Gebruikersnaam</span>
            <strong><?= htmlspecialchars($viewUser['username']) ?></strong>
        </li>
        <li>
            <span>E-mail</span>
            <strong><?= htmlspecialchars($viewUser['email']) ?></strong>
        </li>
        <li>
            <span>Rang</span>
            <strong><?= htmlspecialchars($viewUser['rank_title']) ?></strong>
        </li>
        <li>
            <span>Clicks</span>
            <strong><?= number_format((int)($viewUser['clicks'] ?? 0), 0, ',', '.') ?></strong>
        </li>
        <li>
            <span>Geregistreerd</span>
            <strong><?= date('d M Y H:i', strtotime($viewUser['created_at'])) ?></strong>
        </li>
        <li>
            <span>Laatste login</span>
            <strong><?= $viewUser['last_login'] ? date('d M Y H:i', strtotime($viewUser['last_login'])) : '—' ?></strong>
        </li>
    </ul>
</section>
<?php endif; ?>

<!-- Activiteiten logboek -->
<section class="section">
    <h2>📜 Laatste activiteiten</h2>
    <?php if (empty($activities)): ?>
        <p class="muted">Nog geen activiteiten.</p>
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

<!-- Acties -->
<section class="section">
    <a href="logout.php" class="btn btn-outline btn-full">Uitloggen</a>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>