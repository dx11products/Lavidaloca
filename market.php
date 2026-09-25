<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = currentUser($pdo);

$filterAsset = $_GET['asset'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$listings = getMarketListings($pdo, $filterAsset ?: null, $sort, 50);
$stats = getMarketStats($pdo);
$recentSales = getRecentMarketSales($pdo, 10);
$assets = getAllMarketAssets($pdo);

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $listingId = (int)($_POST['listing_id'] ?? 0);
    $csrf = $_POST['csrf'] ?? '';

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } elseif ($action === 'buy') {
        $res = buyMarketListing($pdo, $user['id'], $listingId);
        if (isset($res['error'])) {
            $error = $res['error'];
        } else {
            $success = "💎 {$res['asset']['name']} gekocht van {$res['asset']['seller_name']} voor {$res['price']} 💎!";
            $user = currentUser($pdo);
            $listings = getMarketListings($pdo, $filterAsset ?: null, $sort, 50);
            $stats = getMarketStats($pdo);
        }
    }
}

$pageTitle = 'Globale Markt — Vendetta';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>🌍 <span>Globale Markt</span></h1>
    <p>Koop assets van andere spelers. Of verkoop je eigen bezittingen voor diamanten.</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon">🏪</div>
        <div class="stat-value" style="color:#58e08c;"><?= number_format($stats['total_listings']) ?></div>
        <div class="stat-label">Actieve listings</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">💎</div>
        <div class="stat-value" style="color:#b9f2ff;"><?= number_format($user['diamonds'], 0, ',', '.') ?></div>
        <div class="stat-label">Jouw diamanten</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">📊</div>
        <div class="stat-value"><?= number_format($stats['total_sales']) ?></div>
        <div class="stat-label">Verkopen</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">💰</div>
        <div class="stat-value" style="color:#c9a44c;"><?= number_format($stats['total_volume'], 0, ',', '.') ?> 💎</div>
        <div class="stat-label">Totaal volume</div>
    </div>
</div>

<section class="section">
    <div class="action-grid">
        <a href="my_assets.php" class="action-card">
            <div class="stat-icon">📦</div>
            <h3>Mijn bezittingen</h3>
            <p>Bekijk en verkoop je assets.</p>
        </a>
        <a href="diamond_shop.php" class="action-card">
            <div class="stat-icon">💎</div>
            <h3>Diamond Shop</h3>
            <p>Koop nieuwe assets met diamanten.</p>
        </a>
    </div>
</section>

<section class="section">
    <h2>🔍 Filter</h2>
    <form method="GET" class="market-filter">
        <select name="asset">
            <option value="">Alle types</option>
            <?php foreach ($assets as $a): ?>
                <option value="<?= htmlspecialchars($a['key']) ?>" <?= $filterAsset === $a['key'] ? 'selected' : '' ?>>
                    <?= $a['icon'] ?> <?= htmlspecialchars($a['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="sort">
            <option value="newest"     <?= $sort === 'newest'     ? 'selected' : '' ?>>Nieuwste eerst</option>
            <option value="price_low"  <?= $sort === 'price_low'  ? 'selected' : '' ?>>Prijs: laag → hoog</option>
            <option value="price_high" <?= $sort === 'price_high' ? 'selected' : '' ?>>Prijs: hoog → laag</option>
        </select>
        <button type="submit" class="btn btn-gold">Filter</button>
        <a href="market.php" class="btn btn-outline">Reset</a>
    </form>
</section>

<section class="section">
    <h2>🏪 Listings (<?= count($listings) ?>)</h2>

    <?php if (empty($listings)): ?>
        <p class="muted">Geen listings beschikbaar. Wees de eerste die iets te koop zet!</p>
    <?php else: ?>
        <div class="market-listings">
            <?php foreach ($listings as $l):
                $canAfford = (int)$user['diamonds'] >= (int)$l['price_diamonds'];
                $isMine = (int)$l['seller_id'] === (int)$user['id'];

                $incomeIcon = match($l['income_type']) {
                    'eur'      => '💰',
                    'btc'      => '₿',
                    'clicks'   => '🖱️',
                    'diamonds' => '💎',
                    'bullets'  => '🎯',
                    default    => '📦',
                };
                $incomeDisplay = match($l['income_type']) {
                    'eur'      => '€' . number_format((int)$l['income_per_hour'], 0, ',', '.'),
                    'btc'      => '₿' . (function_exists('formatBtc') ? formatBtc((float)$l['income_per_hour']) : $l['income_per_hour']),
                    'clicks'   => number_format((int)$l['income_per_hour'], 0, ',', '.') . ' c',
                    'diamonds' => (int)$l['income_per_hour'] . ' 💎',
                    'bullets'  => number_format((int)$l['income_per_hour'], 0, ',', '.') . ' kogels',
                    default    => '—',
                };

                $vsSystem = (int)$l['base_price_diamonds'];
                $diff = (int)$l['price_diamonds'] - $vsSystem;
            ?>
                <div class="market-item" style="--asset-color: <?= htmlspecialchars($l['color']) ?>;">
                    <div class="mi-icon"><?= $l['icon'] ?></div>
                    <div class="mi-info">
                        <h3><?= htmlspecialchars($l['name']) ?></h3>
                        <div class="mi-meta">
                            <span><?= $l['flag'] ?> <?= htmlspecialchars($l['country_name']) ?></span>
                            <span><?= $incomeIcon ?> <?= $incomeDisplay ?>/u</span>
                            <span>👤 <?= htmlspecialchars($l['seller_name']) ?></span>
                        </div>
                    </div>
                    <div class="mi-price">
                        <div class="mi-price-value"><?= number_format((int)$l['price_diamonds'], 0, ',', '.') ?> 💎</div>
                        <?php if ($diff !== 0): ?>
                            <div class="mi-price-diff <?= $diff < 0 ? 'good' : 'bad' ?>">
                                <?= $diff > 0 ? '+' : '' ?><?= number_format($diff, 0, ',', '.') ?> vs systeem
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="mi-action">
                        <?php if ($isMine): ?>
                            <a href="my_assets.php" class="btn btn-outline">Jouw listing</a>
                        <?php else: ?>
                            <form method="POST" onsubmit="return confirm('Koop <?= htmlspecialchars($l['name']) ?> voor <?= (int)$l['price_diamonds'] ?> 💎?');">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="action" value="buy">
                                <input type="hidden" name="listing_id" value="<?= (int)$l['id'] ?>">
                                <button type="submit" class="btn btn-gold" <?= !$canAfford ? 'disabled' : '' ?>>
                                    <?= $canAfford ? '💎 Kopen' : 'Te weinig 💎' ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php if (!empty($recentSales)): ?>
<section class="section">
    <h2>📜 Recente verkopen</h2>
    <ul class="activity-list">
        <?php foreach ($recentSales as $s): ?>
            <li>
                <span>
                    <?= $s['icon'] ?>
                    <strong><?= htmlspecialchars($s['buyer_name']) ?></strong>
                    kocht <strong><?= htmlspecialchars($s['name']) ?></strong>
                    van <?= htmlspecialchars($s['seller_name']) ?>
                    voor <strong style="color:#b9f2ff;"><?= number_format((int)$s['price_diamonds'], 0, ',', '.') ?> 💎</strong>
                </span>
                <time><?= date('d M H:i', strtotime($s['sold_at'])) ?></time>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>