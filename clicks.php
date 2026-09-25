<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = currentUser($pdo);
$rankData = getRankData((int)$user['xp'], $RANKS);

$stats = getClicksStats($pdo, $user['id']);
$purchases = getClicksPurchases($pdo, $user['id'], 15);

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $amount = (int)($_POST['amount'] ?? 0);
    $csrf   = $_POST['csrf'] ?? '';

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } elseif ($amount <= 0) {
        $error = 'Voer een geldig aantal in.';
    } elseif ($action === 'buy_eur') {
        $res = buyClicksWithEur($pdo, $user['id'], $amount);
        if (isset($res['error'])) {
            $error = $res['error'];
        } else {
            $success = "🖱️ Je hebt {$res['amount']} clicks gekocht voor €" . number_format($res['total'], 0, ',', '.') . "!";
            $user = currentUser($pdo);
            $stats = getClicksStats($pdo, $user['id']);
            $purchases = getClicksPurchases($pdo, $user['id'], 15);
        }
    } elseif ($action === 'buy_btc') {
        $res = buyClicksWithBtc($pdo, $user['id'], $amount);
        if (isset($res['error'])) {
            $error = $res['error'];
        } else {
            $success = "🖱️ Je hebt {$res['amount']} clicks gekocht voor ₿" . formatBtc($res['total']) . "!";
            $user = currentUser($pdo);
            $stats = getClicksStats($pdo, $user['id']);
            $purchases = getClicksPurchases($pdo, $user['id'], 15);
        }
    }
}

// Prijzen voor quick-buy
$quickAmounts = [10, 50, 100, 500];
$quickPrices = [];
foreach ($quickAmounts as $q) {
    $quickPrices[$q] = [
        'eur' => calculateClickPrice($q, 'eur'),
        'btc' => calculateClickPrice($q, 'btc'),
    ];
}

$pageTitle = 'Clicks kopen — Vendetta';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Clicks <span>kopen</span></h1>
    <p>Wapens worden alleen met clicks gekocht. Clicks kun je verdienen, of kopen met geld of BTC.</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- Balans -->
<div class="clicks-hero">
    <div class="clicks-balance">
        <span class="clicks-icon">🖱️</span>
        <span class="clicks-amount"><?= number_format((int)($user['clicks'] ?? 0), 0, ',', '.') ?></span>
        <span class="clicks-label">clicks</span>
    </div>
    <div class="clicks-sub">
        Besteedbaar aan wapens en uitrusting
    </div>
</div>

<!-- Stats -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon">💰</div>
        <div class="stat-value gold">€<?= number_format($user['money'], 0, ',', '.') ?></div>
        <div class="stat-label">Cash beschikbaar</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">₿</div>
        <div class="stat-value" style="color:#f7931a;"><?= formatBtc((float)($user['btc'] ?? 0)) ?></div>
        <div class="stat-label">BTC beschikbaar</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">📈</div>
        <div class="stat-value"><?= number_format($stats['clicks_earned'], 0, ',', '.') ?></div>
        <div class="stat-label">Clicks verdiend</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">🛒</div>
        <div class="stat-value"><?= number_format($stats['clicks_bought_eur'] + $stats['clicks_bought_btc'], 0, ',', '.') ?></div>
        <div class="stat-label">Clicks gekocht</div>
    </div>
</div>

<!-- Quick buy -->
<section class="section">
    <h2>⚡ Snel kopen</h2>
    <p class="muted">
        1 click = €<?= number_format(CLICK_PRICE_EUR, 0, ',', '.') ?> of ₿<?= formatBtc(CLICK_PRICE_BTC) ?>
        · Bulk korting: 10% bij 100+, 20% bij 500+, 30% bij 1.000+
    </p>

    <div class="quick-buy-grid">
        <?php foreach ($quickAmounts as $amount):
            $eurPrice = $quickPrices[$amount]['eur'];
            $btcPrice = $quickPrices[$amount]['btc'];
            $eurTotal = (int)ceil($eurPrice['total']);
            $btcTotal = round($btcPrice['total'], 8);
            $canEur = $user['money'] >= $eurTotal;
            $canBtc = (float)($user['btc'] ?? 0) >= $btcTotal;
        ?>
            <div class="quick-buy-card">
                <div class="quick-amount">
                    <strong><?= number_format($amount, 0, ',', '.') ?></strong>
                    <span>clicks</span>
                </div>

                <?php if ($eurPrice['discount_pct'] > 0): ?>
                    <div class="quick-discount">
                        -<?= (int)($eurPrice['discount_pct'] * 100) ?>% korting
                    </div>
                <?php endif; ?>

                <div class="quick-prices">
                    <div class="quick-price-row">
                        <span>€ Prijs</span>
                        <strong>€<?= number_format($eurTotal, 0, ',', '.') ?></strong>
                    </div>
                    <div class="quick-price-row">
                        <span>₿ Prijs</span>
                        <strong style="color:#f7931a;">₿<?= formatBtc($btcTotal) ?></strong>
                    </div>
                </div>

                <form method="POST" class="quick-buy-actions">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="amount" value="<?= $amount ?>">
                    <button type="submit" name="action" value="buy_eur" class="btn btn-gold"
                            <?= !$canEur ? 'disabled' : '' ?>>
                        €<?= number_format($eurTotal, 0, ',', '.') ?>
                    </button>
                    <button type="submit" name="action" value="buy_btc" class="btn btn-outline btc-btn"
                            <?= !$canBtc ? 'disabled' : '' ?>>
                        ₿<?= formatBtc($btcTotal) ?>
                    </button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Custom buy -->
<section class="section">
    <h2>🎯 Eigen aantal</h2>
    <div class="clicks-shop-grid">
        <!-- EUR -->
        <div class="clicks-shop-card">
            <div class="shop-head">
                <span class="shop-icon">💵</span>
                <h3>Koop met geld</h3>
            </div>
            <p class="muted">Betaal met euro's uit je cash.</p>

            <form method="POST" class="custom-buy-form">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="buy_eur">

                <div class="bet-input">
                    <label>Aantal clicks (max <?= number_format(CLICK_MAX_BUY, 0, ',', '.') ?>)</label>
                    <input type="number" name="amount" min="<?= CLICK_MIN_BUY ?>"
                           max="<?= CLICK_MAX_BUY ?>" value="10" required
                           oninput="updatePricePreview(this, 'eur')">
                </div>

                <div class="price-preview" id="preview-eur">
                    <span>Prijs:</span>
                    <strong>€<?= number_format(CLICK_PRICE_EUR * 10, 0, ',', '.') ?></strong>
                </div>

                <button type="submit" class="btn btn-gold btn-full">Koop met geld</button>
            </form>
        </div>

        <!-- BTC -->
        <div class="clicks-shop-card btc">
            <div class="shop-head">
                <span class="shop-icon">₿</span>
                <h3>Koop met Bitcoin</h3>
            </div>
            <p class="muted">Betaal met BTC uit je wallet.</p>

            <form method="POST" class="custom-buy-form">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="buy_btc">

                <div class="bet-input">
                    <label>Aantal clicks (max <?= number_format(CLICK_MAX_BUY, 0, ',', '.') ?>)</label>
                    <input type="number" name="amount" min="<?= CLICK_MIN_BUY ?>"
                           max="<?= CLICK_MAX_BUY ?>" value="10" required
                           oninput="updatePricePreview(this, 'btc')">
                </div>

                <div class="price-preview" id="preview-btc">
                    <span>Prijs:</span>
                    <strong style="color:#f7931a;">₿<?= formatBtc(CLICK_PRICE_BTC * 10) ?></strong>
                </div>

                <button type="submit" class="btn btn-gold btn-full btc-btn">Koop met BTC</button>
            </form>
        </div>
    </div>
</section>

<!-- Geschiedenis -->
<?php if (!empty($purchases)): ?>
<section class="section">
    <h2>📜 Clicks geschiedenis</h2>
    <ul class="activity-list">
        <?php foreach ($purchases as $p): ?>
            <li>
                <span>
                    <?php if ($p['method'] === 'eur'): ?>
                        💵 <strong>+<?= number_format((int)$p['amount'], 0, ',', '.') ?></strong> clicks
                        — betaald: €<?= number_format((int)$p['paid_eur'], 0, ',', '.') ?>
                    <?php elseif ($p['method'] === 'btc'): ?>
                        ₿ <strong>+<?= number_format((int)$p['amount'], 0, ',', '.') ?></strong> clicks
                        — betaald: ₿<?= formatBtc((float)$p['paid_btc']) ?>
                    <?php else: ?>
                        🎁 <strong>+<?= number_format((int)$p['amount'], 0, ',', '.') ?></strong> clicks
                        — <?= htmlspecialchars($p['description'] ?? 'Beloning') ?>
                    <?php endif; ?>
                </span>
                <time><?= date('d M H:i', strtotime($p['created_at'])) ?></time>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<!-- Info -->
<section class="section">
    <div class="tip-card">
        <p>💡 <strong>Tip:</strong> Clicks kunnen <strong>niet verkocht</strong> worden.
        Besteed ze aan wapens in <a href="weapons.php">Wapens</a> om je aanvalskracht te verhogen.
        Verdien gratis clicks via kluizen, crimes, gevechten en likes.</p>
    </div>
</section>

<script>
function updatePricePreview(input, method) {
    const amount = parseInt(input.value) || 0;
    const preview = document.getElementById('preview-' + method);
    if (!preview) return;

    const unit = method === 'eur' ? <?= CLICK_PRICE_EUR ?> : <?= CLICK_PRICE_BTC ?>;

    // Bulk korting
    let discount = 0;
    <?php foreach (CLICK_BULK_TIERS as $threshold => $pct): ?>
    if (amount >= <?= $threshold ?>) discount = <?= $pct ?>;
    <?php endforeach; ?>

    const total = amount * unit * (1 - discount);
    const strong = preview.querySelector('strong');

    if (method === 'eur') {
        strong.textContent = '€' + Math.ceil(total).toLocaleString('nl-NL');
    } else {
        strong.textContent = '₿' + total.toFixed(8).replace(/\.?0+$/, '') || '0';
    }

    // Toon korting
    let discountEl = preview.querySelector('.discount-tag');
    if (discount > 0) {
        if (!discountEl) {
            discountEl = document.createElement('small');
            discountEl.className = 'discount-tag';
            preview.appendChild(discountEl);
        }
        discountEl.textContent = '-' + (discount * 100) + '% korting!';
    } else if (discountEl) {
        discountEl.remove();
    }
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>