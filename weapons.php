<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = currentUser($pdo);
$rankData = getRankData((int)$user['xp'], $RANKS);

$weapons = getAllClickWeapons($pdo);
$myWeapons = getUserClickWeapons($pdo, $user['id']);
$myWeaponMap = [];
foreach ($myWeapons as $mw) {
    $myWeaponMap[$mw['weapon_key']] = (int)$mw['quantity'];
}

$totalBonus = getClickWeaponBonus($pdo, $user['id']);
$totalOwned = getTotalWeaponsOwned($pdo, $user['id']);
$topWeapons = getTopWeapons($pdo, $user['id'], 3);

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $key    = $_POST['weapon'] ?? '';
    $quantity = (int)($_POST['quantity'] ?? 1);
    $csrf   = $_POST['csrf'] ?? '';

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } elseif ($action === 'buy') {
        $res = buyWeaponBulk($pdo, $user['id'], $key, $quantity);
        if (isset($res['error'])) {
            $error = $res['error'];
        } else {
            $success = "🔫 {$res['quantity']}x {$res['weapon']} gekocht voor " .
                       number_format($res['cost'], 0, ',', '.') . " clicks!";
            $user = currentUser($pdo);

            // Refresh
            $myWeapons = getUserClickWeapons($pdo, $user['id']);
            $myWeaponMap = [];
            foreach ($myWeapons as $mw) {
                $myWeaponMap[$mw['weapon_key']] = (int)$mw['quantity'];
            }
            $totalBonus = getClickWeaponBonus($pdo, $user['id']);
            $totalOwned = getTotalWeaponsOwned($pdo, $user['id']);
            $topWeapons = getTopWeapons($pdo, $user['id'], 3);
        }
    }
}

$pageTitle = 'Wapens — Vendetta';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Wapens <span>arsenaal</span></h1>
    <p>Koop zoveel je wil — hoe meer wapens, hoe hoger je aanvalskracht.</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= $error ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- Stats -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon">🖱️</div>
        <div class="stat-value gold" id="my-clicks"><?= number_format((int)$user['clicks'], 0, ',', '.') ?></div>
        <div class="stat-label">Clicks beschikbaar</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">⚔️</div>
        <div class="stat-value" style="color:#ff5c5c;">+<?= number_format($totalBonus, 0, ',', '.') ?></div>
        <div class="stat-label">Totale aanval bonus</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">🔫</div>
        <div class="stat-value"><?= number_format($totalOwned, 0, ',', '.') ?></div>
        <div class="stat-label">Wapens in bezit</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">📦</div>
        <div class="stat-value"><?= count($myWeapons) ?> / <?= count($weapons) ?></div>
        <div class="stat-label">Verschillende types</div>
    </div>
</div>

<!-- Top wapens -->
<?php if (!empty($topWeapons)): ?>
<section class="section">
    <h2>🏆 Jouw sterkste wapens</h2>
    <div class="top-weapons">
        <?php foreach ($topWeapons as $tw):
            $power = (int)$tw['quantity'] * (int)$tw['attack_bonus'];
        ?>
            <div class="top-weapon-card">
                <span class="tw-icon"><?= $tw['icon'] ?></span>
                <div>
                    <strong><?= htmlspecialchars($tw['name']) ?></strong>
                    <small><?= number_format((int)$tw['quantity'], 0, ',', '.') ?>x · +<?= number_format($power, 0, ',', '.') ?> attack</small>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- Clicks kopen hint -->
<section class="section">
    <div class="clicks-hint-banner">
        <div class="chb-info">
            <span class="chb-icon">🖱️</span>
            <div>
                <strong>Te weinig clicks?</strong>
                <p class="muted">Koop clicks met geld of Bitcoin via de Clicks pagina.</p>
            </div>
        </div>
        <a href="clicks.php" class="btn btn-gold">Clicks kopen →</a>
    </div>
</section>

<!-- Wapens -->
<section class="section">
    <h2>🛒 Wapens kopen</h2>
    <p class="muted">Bulk korting: 10% bij 100+ · 20% bij 500+ · 30% bij 1.000+ · 40% bij 5.000+</p>

    <div class="weapon-grid">
        <?php foreach ($weapons as $w):
            $owned = $myWeaponMap[$w['key']] ?? 0;
            $locked = $rankData['level'] < (int)$w['min_rank'];
            $unitPrice = (int)$w['click_cost'];
            $unitPower = (int)$w['attack_bonus'];
            $maxAfford = $unitPrice > 0 ? (int)floor((int)$user['clicks'] / $unitPrice) : 0;
        ?>
            <div class="weapon-card bulk <?= $owned > 0 ? 'owned' : '' ?> <?= $locked ? 'locked' : '' ?>"
                 data-unit-price="<?= $unitPrice ?>"
                 data-unit-power="<?= $unitPower ?>"
                 data-max-afford="<?= $maxAfford ?>"
                 data-my-clicks="<?= (int)$user['clicks'] ?>">
                <div class="weapon-head">
                    <span class="weapon-icon"><?= $w['icon'] ?></span>
                    <div>
                        <h3><?= htmlspecialchars($w['name']) ?></h3>
                        <small class="muted"><?= htmlspecialchars($w['category']) ?></small>
                    </div>
                    <?php if ($owned > 0): ?>
                        <span class="weapon-owned-badge"><?= number_format($owned, 0, ',', '.') ?>x</span>
                    <?php endif; ?>
                </div>

                <p><?= htmlspecialchars($w['description']) ?></p>

                <div class="weapon-stats">
                    <div class="weapon-stat">
                        <span>⚔️ Per stuk</span>
                        <strong>+<?= number_format($unitPower, 0, ',', '.') ?></strong>
                    </div>
                    <div class="weapon-stat">
                        <span>🖱️ Prijs/stuk</span>
                        <strong class="clicks-price"><?= number_format($unitPrice, 0, ',', '.') ?></strong>
                    </div>
                    <?php if ($locked): ?>
                        <div class="weapon-stat">
                            <span>🔒 Rank</span>
                            <strong><?= (int)$w['min_rank'] ?>+</strong>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($locked): ?>
                    <button class="btn btn-outline btn-full" disabled>Rank <?= (int)$w['min_rank'] ?>+ nodig</button>
                <?php else: ?>

                    <!-- Snel-knoppen -->
                    <div class="quick-buy-row">
                        <?php foreach ([10, 50, 100, 500] as $qty):
                            $price = calculateWeaponPrice($qty, $unitPrice);
                            $canBuy = (int)$user['clicks'] >= $price['total'];
                        ?>
                            <form method="POST" class="quick-buy-form">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="action" value="buy">
                                <input type="hidden" name="weapon" value="<?= htmlspecialchars($w['key']) ?>">
                                <button type="submit" name="quantity" value="<?= $qty ?>"
                                        class="quick-buy-btn <?= !$canBuy ? 'disabled' : '' ?>"
                                        <?= !$canBuy ? 'disabled' : '' ?>>
                                    <strong><?= $qty ?>x</strong>
                                    <small><?= number_format($price['total'], 0, ',', '.') ?></small>
                                    <?php if ($price['discount_pct'] > 0): ?>
                                        <span class="discount-tag">-<?= (int)($price['discount_pct'] * 100) ?>%</span>
                                    <?php endif; ?>
                                </button>
                            </form>
                        <?php endforeach; ?>
                    </div>

                    <!-- HANDMATIG INVOEREN — altijd zichtbaar -->
                    <form method="POST" class="manual-buy-form" id="manual-form-<?= htmlspecialchars($w['key']) ?>">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <input type="hidden" name="action" value="buy">
                        <input type="hidden" name="weapon" value="<?= htmlspecialchars($w['key']) ?>">

                        <div class="manual-input-wrap">
                            <label class="manual-label">✏️ Eigen aantal</label>
                            <div class="manual-input-row">
                                <input type="number"
                                       name="quantity"
                                       class="manual-quantity-input"
                                       min="1"
                                       max="<?= WEAPON_MAX_BUY ?>"
                                       value="1"
                                       inputmode="numeric"
                                       data-weapon="<?= htmlspecialchars($w['key']) ?>">
                                <button type="submit" class="btn btn-gold manual-buy-btn"
                                        id="buy-btn-<?= htmlspecialchars($w['key']) ?>">
                                    Koop
                                </button>
                            </div>
                            <div class="manual-preview" id="preview-<?= htmlspecialchars($w['key']) ?>">
                                <div class="mp-row">
                                    <span>Kosten:</span>
                                    <strong class="mp-cost"><?= number_format($unitPrice, 0, ',', '.') ?> clicks</strong>
                                </div>
                                <div class="mp-row">
                                    <span>Attack bonus:</span>
                                    <strong class="mp-power" style="color:#ff5c5c;">+<?= number_format($unitPower, 0, ',', '.') ?></strong>
                                </div>
                                <div class="mp-row mp-discount-row" style="display:none;">
                                    <span>Korting:</span>
                                    <strong class="mp-discount" style="color:#58e08c;">0%</strong>
                                </div>
                            </div>
                        </div>
                    </form>

                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>