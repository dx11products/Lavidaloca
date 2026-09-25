<?php
// Verwacht: $user (van currentUser) en $pageTitle
$rankData    = getRankData((int)$user['xp'], $RANKS);
$currentPage = basename($_SERVER['PHP_SELF']);
$unreadCount = unreadNotifications($pdo, $user['id']);
$userFamily  = getUserFamily($pdo, $user['id']);
$showDaily   = canClaimDaily(getDailyReward($pdo, $user['id']));

// Wereld-event banner
$worldEvent = null;
try {
    $stmt = $pdo->query("
        SELECT * FROM world_events
        WHERE is_active = 1 AND expires_at > NOW()
        ORDER BY id DESC LIMIT 1
    ");
    $worldEvent = $stmt->fetch();
} catch (Exception $e) {}

// Familie-heist notificatie
$hasFamilyLobby = false;
if ($userFamily) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM heist_lobbies WHERE family_id = ? AND status = 'waiting'");
        $stmt->execute([$userFamily['id']]);
        $hasFamilyLobby = (int)$stmt->fetchColumn() > 0;
    } catch (Exception $e) {}
}

// Inbox count
$inboxCount = 0;
if (function_exists('countUnopenedInbox')) {
    try {
        $inboxCount = countUnopenedInbox($pdo, $user['id']);
    } catch (Exception $e) {}
}

// Privéberichten count
$pmUnread = 0;
if (function_exists('countUnreadMessages')) {
    try {
        $pmUnread = countUnreadMessages($pdo, $user['id']);
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Vendetta') ?></title>
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token()) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700;900&family=Cormorant+Garamond:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="game-body">

<?php if ($worldEvent): ?>
<a href="cron_log.php" class="world-event-banner">
    <span class="we-icon"><?= htmlspecialchars($worldEvent['icon']) ?></span>
    <span class="we-title"><?= htmlspecialchars($worldEvent['title']) ?></span>
    <span class="we-desc"><?= htmlspecialchars($worldEvent['description']) ?></span>
</a>
<?php endif; ?>

<header class="game-topbar">
    <a href="dashboard.php" class="logo">VEN<span>DETTA</span></a>

    <div class="hud">
        <?php if (function_exists('isInPrison') && isInPrison($user)): ?>
            <a href="prison.php" class="hud-item hud-prison" title="Je zit in de gevangenis">
                <span class="hud-label">Gevangenis</span>
                <strong class="hud-value">
                    <?= floor(prisonSecondsLeft($user) / 60) ?>m
                </strong>
            </a>
        <?php endif; ?>

        <?php if ($userFamily): ?>
            <a href="family.php?id=<?= $userFamily['id'] ?>" class="hud-family" title="Jouw familie">
                <span class="hud-label">Familie</span>
                <strong class="hud-value">[<?= htmlspecialchars($userFamily['tag']) ?>]</strong>
            </a>
        <?php endif; ?>

        <div class="hud-item">
            <span class="hud-label">Cash</span>
            <strong class="hud-value gold">€<?= number_format($user['money'], 0, ',', '.') ?></strong>
        </div>

        <div class="hud-item">
            <span class="hud-label">Clicks</span>
            <strong class="hud-value clicks">🖱️ <?= number_format((int)($user['clicks'] ?? 0), 0, ',', '.') ?></strong>
        </div>

       <div class="hud-item">
          <span class="hud-label">Diamanten</span>
          <strong class="hud-value diamonds">💎 <?= number_format((int)($user['diamonds'] ?? 0), 0, ',', '.') ?></strong>
      </div>

        <div class="hud-item">
            <span class="hud-label">Bitcoin</span>
            <strong class="hud-value btc">₿ <?= function_exists('formatBtc') ? formatBtc((float)($user['btc'] ?? 0)) : '0' ?></strong>
        </div>

        <div class="hud-item">
            <span class="hud-label">Rank</span>
            <strong class="hud-value"><?= htmlspecialchars($rankData['name']) ?></strong>
        </div>

        <a href="messages.php" class="hud-icon" title="Privéberichten">
            💌
            <?php if ($pmUnread > 0): ?>
                <span class="badge"><?= $pmUnread > 9 ? '9+' : $pmUnread ?></span>
            <?php endif; ?>
        </a>

        <a href="inbox.php" class="hud-icon" title="Inbox">
            📬
            <?php if ($inboxCount > 0): ?>
                <span class="badge"><?= $inboxCount > 9 ? '9+' : $inboxCount ?></span>
            <?php endif; ?>
        </a>

        <a href="notifications.php" class="hud-icon" title="Notificaties">
            🔔
            <?php if ($unreadCount > 0): ?>
                <span class="badge"><?= $unreadCount > 9 ? '9+' : $unreadCount ?></span>
            <?php endif; ?>
        </a>
    </div>
</header>

<nav class="hotbar">
    <a href="dashboard.php" class="hb-item <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
        <span class="hb-icon">🏠</span>
        <span class="hb-label">Home</span>
    </a>

    <a href="crimes.php" class="hb-item <?= $currentPage === 'crimes.php' ? 'active' : '' ?>">
        <span class="hb-icon">💰</span>
        <span class="hb-label">Crimes</span>
    </a>

    <a href="attack.php" class="hb-item <?= $currentPage === 'attack.php' ? 'active' : '' ?>">
        <span class="hb-icon">⚔️</span>
        <span class="hb-label">Vecht</span>
    </a>

    <a href="casino.php" class="hb-item <?= $currentPage === 'casino.php' ? 'active' : '' ?>">
        <span class="hb-icon">🎰</span>
        <span class="hb-label">Casino</span>
    </a>

    <a href="bank.php" class="hb-item <?= $currentPage === 'bank.php' ? 'active' : '' ?>">
        <span class="hb-icon">🏦</span>
        <span class="hb-label">Bank</span>
    </a>

    <a href="shop.php" class="hb-item <?= $currentPage === 'shop.php' ? 'active' : '' ?>">
        <span class="hb-icon">🛒</span>
        <span class="hb-label">Shop</span>
    </a>

    <a href="inventory.php" class="hb-item <?= $currentPage === 'inventory.php' ? 'active' : '' ?>">
        <span class="hb-icon">🎒</span>
        <span class="hb-label">Items</span>
    </a>

    <a href="weapons.php" class="hb-item <?= $currentPage === 'weapons.php' ? 'active' : '' ?>">
        <span class="hb-icon">🔫</span>
        <span class="hb-label">Wapens</span>
    </a>

    <a href="ammo.php" class="hb-item <?= $currentPage === 'ammo.php' ? 'active' : '' ?>">
        <span class="hb-icon">🎯</span>
        <span class="hb-label">Munitie</span>
    </a>

    <a href="chat.php" class="hb-item <?= $currentPage === 'chat.php' ? 'active' : '' ?>">
        <span class="hb-icon">💬</span>
        <span class="hb-label">Chat</span>
    </a>


    <a href="inbox.php" class="hb-item <?= ($currentPage === 'inbox.php' || $currentPage === 'vault.php') ? 'active' : '' ?>">
        <span class="hb-icon">📬</span>
        <span class="hb-label">Inbox</span>
        <?php if ($inboxCount > 0): ?><span class="hb-dot"></span><?php endif; ?>
    </a>

    <a href="messages.php" class="hb-item <?= in_array($currentPage, ['messages.php', 'message.php', 'message_new.php']) ? 'active' : '' ?>">
        <span class="hb-icon">💌</span>
        <span class="hb-label">Berichten</span>
        <?php if ($pmUnread > 0): ?><span class="hb-dot"></span><?php endif; ?>
    </a>

    <a href="lootboxes.php" class="hb-item <?= $currentPage === 'lootboxes.php' ? 'active' : '' ?>">
        <span class="hb-icon">🎁</span>
        <span class="hb-label">Loot</span>
    </a>

    <a href="btc.php" class="hb-item <?= ($currentPage === 'btc.php' || $currentPage === 'miner.php') ? 'active' : '' ?>">
        <span class="hb-icon">₿</span>
        <span class="hb-label">Bitcoin</span>
    </a>

    <a href="diamonds.php" class="hb-item <?= $currentPage === 'diamonds.php' ? 'active' : '' ?>">
       <span class="hb-icon">💎</span>
       <span class="hb-label">Diamanten</span>
    </a>

    <a href="diamond_shop.php" class="hb-item <?= $currentPage === 'diamond_shop.php' ? 'active' : '' ?>">
       <span class="hb-icon">🛍️</span>
       <span class="hb-label">Shop</span>
    </a>

    <a href="market.php" class="hb-item <?= in_array($currentPage, ['market.php','my_assets.php']) ? 'active' : '' ?>">
       <span class="hb-icon">🌍</span>
       <span class="hb-label">Markt</span>
    </a>

    <a href="hospital.php" class="hb-item <?= $currentPage === 'hospital.php' ? 'active' : '' ?>">
        <span class="hb-icon">🏥</span>
        <span class="hb-label">Ziekenhuis</span>
    </a>

    <a href="travel.php" class="hb-item <?= $currentPage === 'travel.php' ? 'active' : '' ?>">
        <span class="hb-icon">✈️</span>
        <span class="hb-label">Reizen</span>
    </a>

    <a href="drugs.php" class="hb-item <?= $currentPage === 'drugs.php' ? 'active' : '' ?>">
        <span class="hb-icon">💊</span>
        <span class="hb-label">Drugs</span>
    </a>

    <a href="houses.php" class="hb-item <?= ($currentPage === 'houses.php' || $currentPage === 'grow.php') ? 'active' : '' ?>">
        <span class="hb-icon">🏘️</span>
        <span class="hb-label">Huizen</span>
    </a>

    <a href="labs.php" class="hb-item <?= $currentPage === 'labs.php' ? 'active' : '' ?>">
        <span class="hb-icon">🧪</span>
        <span class="hb-label">Labs</span>
    </a>

    <a href="bullets.php" class="hb-item <?= $currentPage === 'bullets.php' ? 'active' : '' ?>">
        <span class="hb-icon">🏭</span>
        <span class="hb-label">Fabriek</span>
    </a>

    <a href="cars.php" class="hb-item <?= $currentPage === 'cars.php' ? 'active' : '' ?>">
        <span class="hb-icon">🚗</span>
        <span class="hb-label">Autos</span>
    </a>

    <a href="garage.php" class="hb-item <?= $currentPage === 'garage.php' ? 'active' : '' ?>">
        <span class="hb-icon">🅿️</span>
        <span class="hb-label">Garage</span>
    </a>

    <a href="race.php" class="hb-item <?= ($currentPage === 'race.php' || $currentPage === 'race_room.php') ? 'active' : '' ?>">
        <span class="hb-icon">🏁</span>
        <span class="hb-label">Race</span>
    </a>

    <a href="tune.php" class="hb-item <?= $currentPage === 'tune.php' ? 'active' : '' ?>">
        <span class="hb-icon">🔧</span>
        <span class="hb-label">Tune</span>
    </a>

    <a href="war.php" class="hb-item <?= ($currentPage === 'war.php' || $currentPage === 'war_battle.php') ? 'active' : '' ?>">
        <span class="hb-icon">🛡️</span>
        <span class="hb-label">Oorlog</span>
    </a>

    <a href="heists.php" class="hb-item <?= in_array($currentPage, ['heists.php', 'heist_create.php', 'heist_lobby.php', 'heist_room.php']) ? 'active' : '' ?>">
        <span class="hb-icon">🏴</span>
        <span class="hb-label">Heist</span>
        <?php if ($hasFamilyLobby): ?><span class="hb-dot"></span><?php endif; ?>
    </a>

    <a href="families.php" class="hb-item <?= ($currentPage === 'families.php' || $currentPage === 'family.php') ? 'active' : '' ?>">
        <span class="hb-icon">👥</span>
        <span class="hb-label">Families</span>
    </a>

    <a href="forum.php" class="hb-item <?= in_array($currentPage, ['forum.php', 'forum_board.php', 'forum_topic.php', 'forum_new.php', 'forum_edit.php']) ? 'active' : '' ?>">
        <span class="hb-icon">💬</span>
        <span class="hb-label">Forum</span>
    </a>

    <a href="achievements.php" class="hb-item <?= $currentPage === 'achievements.php' ? 'active' : '' ?>">
        <span class="hb-icon">🏆</span>
        <span class="hb-label">Trofeeën</span>
    </a>

    <a href="leaderboard.php" class="hb-item <?= $currentPage === 'leaderboard.php' ? 'active' : '' ?>">
        <span class="hb-icon">📊</span>
        <span class="hb-label">Ranking</span>
    </a>

    <a href="daily.php" class="hb-item <?= $currentPage === 'daily.php' ? 'active' : '' ?>">
        <span class="hb-icon">🎁</span>
        <span class="hb-label">Daily</span>
        <?php if ($showDaily): ?><span class="hb-dot"></span><?php endif; ?>
    </a>

    <a href="profile.php" class="hb-item <?= $currentPage === 'profile.php' ? 'active' : '' ?>">
        <span class="hb-icon">👤</span>
        <span class="hb-label">Profiel</span>
    </a>

    <a href="logout.php" class="hb-item hb-logout">
        <span class="hb-icon">🚪</span>
        <span class="hb-label">Uit</span>
    </a>
</nav>

<main class="game-main">