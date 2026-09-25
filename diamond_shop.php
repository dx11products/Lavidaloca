<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = currentUser($pdo);
$rankData = getRankData((int)$user['xp'], $RANKS);
$country = getCountry($pdo, $user['current_country']);

$assets = getAllMarketAssets($pdo);

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $key = $_POST['asset_key'] ?? '';
    $csrf = $_POST['csrf'] ?? '';

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } elseif ($action === 'buy') {
        $res = buyAssetFromSystem($pdo, $user['id'], $key, $user['current_country']);
        if (isset($res['error'])) {
            $error = $res['error'];
        } else {
            $success = "{$res['asset']['icon']} {$res['asset']['name']} gekocht voor {$res['price']} 💎!";
            $user = currentUser($pdo);
        }
    }
}

$grouped = [];
foreach ($assets as $a) {
    $grouped[$a['category']][] = $a;
}

$catLabels = [
    'factory' => ['🏭 Fabrieken', '#a8a8a8'],
    'casino'  => ['🎰 Casino', '#c9a44c'],
    'vault'   => ['🏦 Kluizen', '#4a9dff'],
    'trade'   => ['🔫 Handel', '#c94a4a'],
    'special' => ['💎 Speciaal', '#b9f2ff'],
];

$pageTitle = 'Diamond Shop — Vendetta';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>💎 Diamond <span>Shop</span></h1>
    <p>Koop assets die passief geld, BTC, clicks, kogels of diamanten produceren.</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon">💎</div>
        <div class="stat-value" style="color:#b9f2ff;"><?= number_format($user['diamonds'], 0, ',', '.') ?></div>
        <div class="stat-label">Jouw diamanten</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">🌍</div>
        <div class="stat-value"><?= $country['flag'] ?> <?= htmlspecialchars($country['name']) ?></div>
        <div class="stat-label">Locatie (assets zijn per land)</div>
    </div>
</div>

<?php foreach ($grouped as $cat => $items):
    $info = $catLabels[$cat] ?? [ucfirst($cat), '#c9a44c'];
?>
<section class="section">
    <h2 style="color:<?= $info[1] ?>;"><?= $info[0] ?></h2>

    <div class="asset-grid">
        <?php foreach ($items as $a):
            $owned = countUserAssets($pdo, $user['id'], $a['key']);
            $canAfford = (int)$user['diamonds'] >= (int)$a['base_price_diamonds'];
            $locked = $rankData['level'] < (int)$a['min_rank'];
            $atMax = $owned >= (int)$a['max_per_user'];
            $disabled = $locked || $atMax || !$canAfford;

            $incomeIcon = match($a['income_type']) {
                'eur'      => '💰',
                'btc'      => '₿',
                'clicks'   => '🖱️',
                'diamonds' => '💎',
                'bullets'  => '🎯',
                default    => '📦',
            };

            $incomeDisplay = match($a['income_type']) {
                'eur'      => '€' . number_format((int)$a['income_per_hour'], 0, ',', '.'),
                'btc'      => '₿' . (function_exists('formatBtc') ? formatBtc((float)$a['income_per_hour']) : $a['income_per_hour']),
                'clicks'   => number_format((int)$a['income_per_hour'], 0, ',', '.') . ' clicks',
                'diamonds' => (int)$a['income_per_hour'] . ' 💎',
                'bullets'  => number_format((int)$a['income_per_hour'], 0, ',', '.') . ' kogels',
                default    => '—',
            };
        ?>
            <div class="asset-card" style="--asset-color: <?= htmlspecialchars($a['color']) ?>;">
                <div class="asset-icon"><?= $a['icon'] ?></div>
                <h3 style="color:<?= htmlspecialchars($a['color']) ?>;"><?= htmlspecialchars($a['name']) ?></h3>
                <p class="muted"><?= htmlspecialchars($a['description']) ?></p>

                <div class="asset-stats">
                    <div class="asset-stat">
                        <span><?= $incomeIcon ?> Per uur</span>
                        <strong><?= $incomeDisplay ?></strong>
                    </div>
                    <div class="asset-stat">
                        <span>📊 Bezit</span>
                        <strong><?= $owned ?> / <?= (int)$a['max_per_user'] ?></strong>
                    </div>
                    <?php if ($locked): ?>
                        <div class="asset-stat">
                            <span>🔒 Rank</span>
                            <strong><?= (int)$a['min_rank'] ?>+</strong>
                        </div>
                    <?php endif; ?>
                </div>

                <form method="POST" class="asset-buy-form">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="action" value="buy">
                    <input type="hidden" name="asset_key" value="<?= htmlspecialchars($a['key']) ?>">

                    <div class="asset-price"><?= number_format((int)$a['base_price_diamonds'], 0, ',', '.') ?> 💎</div>
                    <button type="submit" class="btn btn-gold btn-full" <?= $disabled ? 'disabled' : '' ?>>
                        <?php if ($locked): ?>
                            Rank <?= (int)$a['min_rank'] ?>+
                        <?php elseif ($atMax): ?>
                            Max bereikt
                        <?php elseif (!$canAfford): ?>
                            Te weinig 💎
                        <?php else: ?>
                            Kopen
                        <?php endif; ?>
                    </button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endforeach; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>