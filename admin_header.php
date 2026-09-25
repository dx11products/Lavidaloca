<?php
require_once __DIR__ . '/../../config/db.php';
$admin = requireAdmin($pdo);

if (isBanned($admin)) redirect('../logout.php');

$currentAdminPage = basename($_SERVER['PHP_SELF']);
$adminPageTitle = $adminPageTitle ?? 'Admin';

// Menu gegroepeerd per categorie
$adminMenuGroups = [
    'main' => [
        ['url' => 'index.php', 'icon' => '📊', 'label' => 'Dashboard', 'pages' => ['index.php']],
    ],
    'players' => [
        ['url' => 'users.php',    'icon' => '👥', 'label' => 'Spelers',    'pages' => ['users.php', 'user_edit.php']],
        ['url' => 'diamonds.php', 'icon' => '💎', 'label' => 'Diamanten',  'pages' => ['diamonds.php']],
        ['url' => 'gifts.php',    'icon' => '🎀', 'label' => 'Gifts',      'pages' => ['gifts.php']],
    ],
    'economy' => [
        ['url' => 'economy.php',   'icon' => '💰', 'label' => 'Economie',  'pages' => ['economy.php']],
        ['url' => 'market.php',    'icon' => '🌍', 'label' => 'Markt',     'pages' => ['market.php']],
        ['url' => 'jackpots.php',  'icon' => '🎰', 'label' => 'Jackpots',  'pages' => ['jackpots.php']],
        ['url' => 'casino.php',    'icon' => '🃏', 'label' => 'Casino',    'pages' => ['casino.php']],
        ['url' => 'lootboxes.php', 'icon' => '🎁', 'label' => 'Lootboxes', 'pages' => ['lootboxes.php']],
    ],
    'community' => [
        ['url' => 'forum.php',     'icon' => '💬', 'label' => 'Forum',     'pages' => ['forum.php']],
        ['url' => 'broadcast.php', 'icon' => '📢', 'label' => 'Broadcast', 'pages' => ['broadcast.php']],
    ],
    'game' => [
        ['url' => 'events.php',       'icon' => '🌟', 'label' => 'Events',   'pages' => ['events.php']],
        ['url' => 'mass_actions.php', 'icon' => '⚡', 'label' => 'Mass',     'pages' => ['mass_actions.php']],
        ['url' => 'live.php',         'icon' => '🔴', 'label' => 'Live',     'pages' => ['live.php']],
    ],
    'system' => [
        ['url' => 'logs.php',     'icon' => '📜', 'label' => 'Logs',       'pages' => ['logs.php']],
        ['url' => 'server.php',   'icon' => '🔧', 'label' => 'Server',     'pages' => ['server.php']],
        ['url' => 'database.php', 'icon' => '🗄️', 'label' => 'Database',   'pages' => ['database.php']],
        ['url' => 'settings.php', 'icon' => '⚙️', 'label' => 'Settings',   'pages' => ['settings.php']],
    ],
];

function isMenuActive(array $item, string $currentPage): bool {
    return in_array($currentPage, $item['pages'], true);
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($adminPageTitle) ?> — Admin Vendetta</title>
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token()) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700;900&family=Cormorant+Garamond:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-body">

<!-- ============================================================
     TOPBAR
     ============================================================ -->
<header class="admin-topbar">
    <div class="at-left">
        <a href="index.php" class="admin-logo">
            <span class="al-shield">⚙️</span>
            <span class="al-text"> Vendetta <span class="al-sub">  Admin  </span></span>
        </a>
    </div>

    <div class="at-right">
        <div class="admin-user-info">
            <div class="aui-avatar"><?= strtoupper(mb_substr($admin['username'], 0, 1)) ?></div>
            <div class="aui-text">
                <strong><?= htmlspecialchars($admin['username']) ?></strong>
                <small>Administrator</small>
            </div>
        </div>
        <a href="../dashboard.php" class="at-btn">
            <span>←</span> Terug naar game
        </a>
        <a href="../logout.php" class="at-btn at-btn-danger">
            🚪 Uitloggen
        </a>
    </div>
</header>

<!-- ============================================================
     NAVIGATIE met categorie-scheidingen
     ============================================================ -->
<nav class="admin-nav">
    <div class="an-scroll">
        <?php $first = true; foreach ($adminMenuGroups as $groupKey => $items): ?>
            <?php if (!$first): ?>
                <span class="an-sep"></span>
            <?php endif; $first = false; ?>

            <?php foreach ($items as $item): ?>
                <a href="<?= htmlspecialchars($item['url']) ?>"
                   class="an-link <?= isMenuActive($item, $currentAdminPage) ? 'active' : '' ?>">
                    <span class="an-icon"><?= $item['icon'] ?></span>
                    <span class="an-label"><?= htmlspecialchars($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
</nav>

<!-- ============================================================
     MAIN CONTENT
     ============================================================ -->
<main class="admin-main">