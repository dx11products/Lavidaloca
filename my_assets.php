<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = currentUser($pdo);
$myAssets = getUserAssets($pdo, $user['id']);
$myListings = getUserListings($pdo, $user['id']);

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf = $_POST['csrf'] ?? '';

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } elseif ($action === 'sell_system') {
        $id = (int)($_POST['asset_id'] ?? 0);
        $res = sellAssetToSystem($pdo, $user['id'], $id);
        if (isset($res['error'])) {
            $error = $res['error'];
        } else {
            $success = "💎 {$res['asset']} verkocht aan systeem voor {$res['received']} diamanten!";
            $user = currentUser($pdo);
            $myAssets = getUserAssets($pdo, $user['id']);
        }
    } elseif ($action === 'list_market') {
        $id = (int)($_POST['asset_id'] ?? 0);
        $price = (int)($_POST['price'] ?? 0);
        $res = listAssetOnMarket($pdo, $user['id'], $id, $price);
        if (isset($res['error'])) {
            $error = $res['error'];
        } else {
            $success = "💎 Te koop gezet voor {$res['price']} diamanten!";
            $myAssets = getUserAssets($pdo, $user['id']);
            $myListings = getUserListings($pdo, $user['id']);
        }
    } elseif ($action === 'cancel_listing') {
        $id = (int)($_POST['listing_id'] ?? 0);
        $res = cancelListing($pdo, $user['id'], $id);
        if (isset($res['error'])) {
            $error = $res['error'];
        } else {
            $success = "Listing geannuleerd.";
            $myAssets = getUserAssets($pdo, $user['id']);
            $myListings = getUserListings($pdo, $user['id']);
        }
    }
}

$pageTitle = 'Mijn Bezittingen — Vendetta';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>📦 Mijn <span>Bezittingen</span></h1>
    <p>Beheer je assets. Verkoop aan het systeem of zet ze op de markt voor meer diamanten.</p>
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
        <div class="stat-label">Diamanten</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">📦</div>
        <div class="stat-value"><?= count($myAssets) ?></div>
        <div class="stat-label">Assets in bezit</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">🏪</div>
        <div class="stat-value"><?= count($myListings) ?></div>
        <div class="stat-label">Actieve listings</div>
    </div>
</div>

<?php if (!empty($myListings)): ?>
<section class="section">
    <h2>🏪 Jouw listings</h2>
    <div class="market-listings">
        <?php foreach ($myListings as $l): ?>
            <div class="market-item" style="--asset-color: <?= htmlspecialchars($l['color']) ?>;">
                <div class="mi-icon"><?= $l['icon'] ?></div>
                <div class="mi-info">
                    <h3><?= htmlspecialchars($l['name']) ?></h3>
                    <div class="mi-meta">
                        <span><?= $l['flag'] ?> <?= htmlspecialchars($l['country_name']) ?></span>
                        <span>Geplaatst <?= date('d M H:i', strtotime($l['listed_at'])) ?></span>
                    </div>
                </div>
                <div class="mi-price">
                    <div class="mi-price-value"><?= number_format((int)$l['price_diamonds'], 0, ',', '.') ?> 💎</div>
                </div>
                <div class="mi-action">
                    <form method="POST" onsubmit="return confirm('Listing annuleren?');">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <input type="hidden" name="action" value="cancel_listing">
                        <input type="hidden" name="listing_id" value="<?= (int)$l['id'] ?>">
                        <button type="submit" class="btn btn-outline">Annuleren</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="section">
    <h2>📦 Jouw assets (<?= count($myAssets) ?>)</h2>

    <?php if (empty($myAssets)): ?>
        <p class="muted">Je hebt nog geen assets. <a href="diamond_shop.php">Koop er een in de Diamond Shop →</a></p>
    <?php else: ?>
        <div class="asset-grid">
            <?php foreach ($myAssets as $a):
                $incomeIcon = match($a['income_type']) {
                    'eur'      => '💰',
                    'btc'      => '₿',
                    'clicks'   => '🖱️',
                    'diamonds' => '💎',
                    'bullets'  => '🎯',
                    default    => '📦',
                };
            ?>
                <div class="asset-card" style="--asset-color: <?= htmlspecialchars($a['color']) ?>;">
                    <div class="asset-icon"><?= $a['icon'] ?></div>
                    <h3 style="color:<?= htmlspecialchars($a['color']) ?>;"><?= htmlspecialchars($a['name']) ?></h3>
                    <div class="asset-location"><?= $a['flag'] ?> <?= htmlspecialchars($a['country_name']) ?></div>

                    <div class="asset-stats">
                        <div class="asset-stat">
                            <span><?= $incomeIcon ?> Per uur</span>
                            <strong>
                                <?php
                                $rate = (float)$a['income_per_hour'];
                                echo match($a['income_type']) {
                                    'eur'      => '€' . number_format((int)$rate, 0, ',', '.'),
                                    'btc'      => '₿' . (function_exists('formatBtc') ? formatBtc($rate) : $rate),
                                    'clicks'   => number_format((int)$rate, 0, ',', '.') . ' c',
                                    'diamonds' => (int)$rate . ' 💎',
                                    'bullets'  => number_format((int)$rate, 0, ',', '.') . ' kogels',
                                    default    => '—',
                                };
                                ?>
                            </strong>
                        </div>
                    </div>

                    <div class="asset-actions">
                        <details>
                            <summary class="btn btn-outline">💎 Verkoop aan systeem</summary>
                            <div style="padding-top:10px;">
                                <p class="muted" style="font-size:.8rem;margin-bottom:8px;">
                                    Je krijgt direct een vaste prijs (lager dan markt).
                                </p>
                                <form method="POST" onsubmit="return confirm('Verkopen aan het systeem?');">
                                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="sell_system">
                                    <input type="hidden" name="asset_id" value="<?= (int)$a['id'] ?>">
                                    <button type="submit" class="btn btn-gold btn-full">Bevestig verkoop</button>
                                </form>
                            </div>
                        </details>

                        <details>
                            <summary class="btn btn-outline">🏪 Op markt zetten</summary>
                            <div style="padding-top:10px;">
                                <p class="muted" style="font-size:.8rem;margin-bottom:8px;">
                                    Zet je eigen prijs. Andere spelers kopen.
                                </p>
                                <form method="POST">
                                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="list_market">
                                    <input type="hidden" name="asset_id" value="<?= (int)$a['id'] ?>">
                                    <input type="number" name="price" min="1" placeholder="Prijs in 💎" required
                                           style="width:100%;padding:10px;margin-bottom:8px;background:var(--bg-0);border:1px solid var(--border);border-radius:4px;color:var(--text);">
                                    <button type="submit" class="btn btn-gold btn-full">Op markt zetten</button>
                                </form>
                            </div>
                        </details>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>