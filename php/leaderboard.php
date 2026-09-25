<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = currentUser($pdo);

// Bepaal welk tabblad actief is
$tab = $_GET['tab'] ?? 'money';
$validTabs = ['money', 'xp', 'attacks', 'crimes'];
if (!in_array($tab, $validTabs, true)) $tab = 'money';

// Query per tabblad
$orderBy = [
    'money'   => 'money DESC',
    'xp'      => 'xp DESC',
    'attacks' => 'attacks_won DESC',
    'crimes'  => 'crimes_done DESC',
][$tab];

$stmt = $pdo->query("
    SELECT id, username, money, xp, rank_title,
           attacks_won, attacks_lost, crimes_done
    FROM users
    ORDER BY $orderBy
    LIMIT 25
");
$players = $stmt->fetchAll();

// Positie van huidige user in elke ranking
$myPositions = [];
foreach ($validTabs as $t) {
    $col = ['money' => 'money', 'xp' => 'xp', 'attacks' => 'attacks_won', 'crimes' => 'crimes_done'][$t];
    $stmt = $pdo->prepare("SELECT COUNT(*) + 1 FROM users WHERE $col > (SELECT $col FROM users WHERE id = ?)");
    $stmt->execute([$user['id']]);
    $myPositions[$t] = (int)$stmt->fetchColumn();
}

// Kolomtitels per tabblad
$columns = [
    'money'   => ['label' => 'Cash',         'icon' => '💰', 'field' => 'money'],
    'xp'      => ['label' => 'XP',           'icon' => '⭐', 'field' => 'xp'],
    'attacks' => ['label' => 'Aanvallen gewonnen', 'icon' => '⚔️', 'field' => 'attacks_won'],
    'crimes'  => ['label' => 'Crimes',       'icon' => '🎯', 'field' => 'crimes_done'],
];
$col = $columns[$tab];

$pageTitle = 'Leaderboard — Vendetta';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Het <span>Leaderboard</span></h1>
    <p>Wie heerst er over de onderwereld? Bekijk de top 25.</p>
</div>

<!-- Tabbladen -->
<div class="tabs">
    <a href="?tab=money"   class="tab <?= $tab === 'money'   ? 'active' : '' ?>">💰 Cash</a>
    <a href="?tab=xp"      class="tab <?= $tab === 'xp'      ? 'active' : '' ?>">⭐ XP</a>
    <a href="?tab=attacks" class="tab <?= $tab === 'attacks' ? 'active' : '' ?>">⚔️ Aanvallen</a>
    <a href="?tab=crimes"  class="tab <?= $tab === 'crimes'  ? 'active' : '' ?>">🎯 Crimes</a>
</div>

<!-- Jouw positie -->
<div class="my-rank-banner">
    <span>Jouw positie</span>
    <strong>#<?= $myPositions[$tab] ?></strong>
</div>

<!-- Lijst -->
<div class="leaderboard">
    <div class="lb-header">
        <span class="lb-pos">#</span>
        <span class="lb-name">Speler</span>
        <span class="lb-value"><?= $col['icon'] ?> <?= $col['label'] ?></span>
    </div>

    <?php foreach ($players as $i => $p):
        $pos = $i + 1;
        $isMe = (int)$p['id'] === (int)$user['id'];
        $posClass = $pos === 1 ? 'gold' : ($pos === 2 ? 'silver' : ($pos === 3 ? 'bronze' : ''));
    ?>
    <div class="lb-row <?= $isMe ? 'is-me' : '' ?>">
        <span class="lb-pos <?= $posClass ?>">
            <?php if ($pos === 1): ?>🥇
            <?php elseif ($pos === 2): ?>🥈
            <?php elseif ($pos === 3): ?>🥉
            <?php else: ?><?= $pos ?>
            <?php endif; ?>
        </span>
        <span class="lb-name">
            <strong><?= htmlspecialchars($p['username']) ?></strong>
            <small><?= htmlspecialchars($p['rank_title']) ?></small>
        </span>
        <span class="lb-value">
            <?php if ($tab === 'money'): ?>
                €<?= number_format($p['money'], 0, ',', '.') ?>
            <?php else: ?>
                <?= number_format($p[$col['field']], 0, ',', '.') ?>
            <?php endif; ?>
        </span>
    </div>
    <?php endforeach; ?>

    <?php if (empty($players)): ?>
        <p class="muted" style="padding:20px;text-align:center;">Nog geen spelers geregistreerd.</p>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>