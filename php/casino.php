<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = currentUser($pdo);
$country = getCountry($pdo, $user['current_country']);

$casinos = getCasinosInCountry($pdo, $user['current_country']);
$myCasinos = getUserAssets($pdo, $user['id']);
$myCasinos = array_filter($myCasinos, fn($a) => $a['category'] === 'casino');
$totalEarnings = getCasinoTotalEarnings($pdo, $user['id']);

$pageTitle = 'Casino — Vendetta';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>🎰 Het <span>Casino</span></h1>
    <p><?= $country['flag'] ?> <?= htmlspecialchars($country['name']) ?> — speel en win, of bezit het casino en verdien.</p>
</div>

<?php if ((int)$totalEarnings > 0): ?>
    <div class="alert alert-success">
        🏆 Je casino's hebben je al <strong>€<?= number_format($totalEarnings, 0, ',', '.') ?></strong> opgeleverd!
        <a href="my_assets.php">Beheer je casino's →</a>
    </div>
<?php endif; ?>

<!-- Beschikbare spellen -->
<section class="section">
    <h2>🎲 Beschikbare spellen in <?= htmlspecialchars($country['name']) ?></h2>

    <div class="casino-game-grid">
        <?php foreach ($casinos as $gameKey => $info):
            $game = $info['game'];
            $owner = $info['owner'];
            $available = $owner !== null;
            $isMine = $owner && (int)$owner['user_id'] === (int)$user['id'];
        ?>
            <div class="casino-game-card <?= !$available ? 'unavailable' : '' ?> <?= $isMine ? 'mine' : '' ?>"
                 style="--game-color: <?= htmlspecialchars($game['color']) ?>;">

                <div class="cgc-icon"><?= $game['icon'] ?></div>
                <h3 style="color:<?= htmlspecialchars($game['color']) ?>;"><?= htmlspecialchars($game['name']) ?></h3>
                <p class="muted"><?= htmlspecialchars($game['desc']) ?></p>

                <div class="cgc-owner">
                    <?php if ($available): ?>
                        <?php if ($isMine): ?>
                            <span class="owner-badge mine">👑 Jij bent eigenaar</span>
                        <?php else: ?>
                            <span class="owner-badge">
                                👤 Eigenaar: <strong><?= htmlspecialchars($owner['username']) ?></strong>
                            </span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="owner-badge unavailable">
                            ❌ Geen casino in dit land
                        </span>
                    <?php endif; ?>
                </div>

                <?php if ($available && !$isMine): ?>
                    <?php if ($gameKey === 'blackjack'): ?>
                        <a href="casino_blackjack.php" class="btn btn-gold btn-full">🃏 Speel Blackjack</a>
                    <?php elseif ($gameKey === 'poker'): ?>
                        <a href="casino_poker.php" class="btn btn-gold btn-full">♠️ Speel Poker</a>
                    <?php elseif ($gameKey === 'slots'): ?>
                        <a href="casino_slots.php" class="btn btn-gold btn-full">🎰 Speel Slots</a>
                    <?php elseif ($gameKey === 'roulette'): ?>
                        <a href="casino.php" class="btn btn-gold btn-full">🎰 Speel Roulette</a>
                    <?php endif; ?>
                <?php elseif ($isMine): ?>
                    <div class="cgc-mine-info">
                        Verdien aan spelers die verliezen!
                    </div>
                <?php else: ?>
                    <div class="cgc-mine-info">
                        <a href="diamond_shop.php">Koop een <?= htmlspecialchars($game['asset']) ?> →</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Jouw casino's -->
<?php if (!empty($myCasinos)): ?>
<section class="section">
    <h2>👑 Jouw casino's</h2>
    <p class="muted">Je krijgt 80% van alle verliezen van spelers in jouw casino. 20% gaat naar Vendetta.</p>

    <div class="asset-grid">
        <?php foreach ($myCasinos as $a):
            $earnings = getCasinoEarnings($pdo, $user['id']);
            $thisEarning = 0;
            foreach ($earnings as $e) {
                if ($e['asset_key'] === $a['asset_key'] && $e['country_key'] === $a['country_key']) {
                    $thisEarning = (int)$e['net_profit'];
                    break;
                }
            }
        ?>
            <div class="asset-card" style="--asset-color: <?= htmlspecialchars($a['color']) ?>;">
                <div class="asset-icon"><?= $a['icon'] ?></div>
                <h3 style="color:<?= htmlspecialchars($a['color']) ?>;"><?= htmlspecialchars($a['name']) ?></h3>
                <div class="asset-location"><?= $a['flag'] ?> <?= htmlspecialchars($a['country_name']) ?></div>

                <div class="asset-stats">
                    <div class="asset-stat">
                        <span>💰 Opbrengst</span>
                        <strong style="color:#58e08c;">€<?= number_format($thisEarning, 0, ',', '.') ?></strong>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php $jackpots = getAllJackpots($pdo); ?>
<div class="jackpot-mini-bar">
    <?php foreach ($jackpots as $j): ?>
        <div class="jmb-item" data-jackpot="<?= htmlspecialchars($j['key']) ?>">
            <span class="jmb-icon"><?= $j['icon'] ?></span>
            <span class="jmb-name"><?= htmlspecialchars($j['name']) ?></span>
            <strong class="jp-amount" style="color:<?= htmlspecialchars($j['color']) ?>;"
                    data-amount="<?= (int)$j['current_amount'] ?>">
                <?= formatJackpot((int)$j['current_amount']) ?>
            </strong>
        </div>
    <?php endforeach; ?>
</div>
<script src="assets/js/jackpot.js"></script>

<?php require __DIR__ . '/includes/footer.php'; ?>