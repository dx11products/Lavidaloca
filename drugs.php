<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = currentUser($pdo);
$country = getCountry($pdo, $user['current_country']);
$prices = getDrugPrices($pdo, $user['current_country']);
$userDrugs = getUserDrugs($pdo, $user['id']);
$totalDrugs = getTotalDrugCount($userDrugs);
$maxCapacity = maxDrugCapacity();

$error = null;
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $drugKey = $_POST['drug'] ?? '';
    $amount  = (int)($_POST['amount'] ?? 0);
    $csrf    = $_POST['csrf'] ?? '';

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } elseif (!isset(DRUGS[$drugKey]) || !isset($prices[$drugKey])) {
        $error = 'Onbekende drug.';
    } elseif ($amount < 1) {
        $error = 'Ongeldig aantal.';
    } else {
        $price = $prices[$drugKey];

        if ($action === 'buy') {
            if ($totalDrugs + $amount > $maxCapacity) {
                $error = "Je kunt maximaal {$maxCapacity} drugs dragen.";
            } else {
                $cost = $price['buy_price'] * $amount;
                if ($user['money'] < $cost) {
                    $error = 'Je hebt niet genoeg geld.';
                } else {
                    $pdo->beginTransaction();
                    try {
                        $pdo->prepare("UPDATE users SET money = money - ? WHERE id = ?")
                            ->execute([$cost, $user['id']]);
                        addDrug($pdo, $user['id'], $drugKey, $amount);

                        logActivity($pdo, $user['id'],
                            "💊 {$amount}x " . DRUGS[$drugKey]['name'] . " gekocht voor €" . number_format($cost, 0, ',', '.'));
                        $pdo->commit();

                        $result = "{$amount}x " . DRUGS[$drugKey]['name'] . " gekocht voor €" . number_format($cost, 0, ',', '.');
                        $user = currentUser($pdo);
                        $userDrugs = getUserDrugs($pdo, $user['id']);
                        $totalDrugs = getTotalDrugCount($userDrugs);
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $error = 'Aankoop mislukt.';
                    }
                }
            }
        } elseif ($action === 'sell') {
            if (($userDrugs[$drugKey] ?? 0) < $amount) {
                $error = 'Je hebt niet genoeg van deze drug.';
            } else {
                $gain = $price['sell_price'] * $amount;
                $pdo->beginTransaction();
                try {
                    if (removeDrug($pdo, $user['id'], $drugKey, $amount)) {
                        $pdo->prepare("UPDATE users SET money = money + ? WHERE id = ?")
                            ->execute([$gain, $user['id']]);

                        logActivity($pdo, $user['id'],
                            "💰 {$amount}x " . DRUGS[$drugKey]['name'] . " verkocht voor €" . number_format($gain, 0, ',', '.'));
                        $pdo->commit();

                        $result = "{$amount}x " . DRUGS[$drugKey]['name'] . " verkocht voor €" . number_format($gain, 0, ',', '.');
                        $user = currentUser($pdo);
                        $userDrugs = getUserDrugs($pdo, $user['id']);
                        $totalDrugs = getTotalDrugCount($userDrugs);
                    } else {
                        $pdo->rollBack();
                        $error = 'Verkoop mislukt.';
                    }
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Verkoop mislukt.';
                }
            }
        }
    }
}

$pageTitle = 'Drugs markt — Vendetta';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Drugs <span>markt</span></h1>
    <p><?= $country['flag'] ?> <?= htmlspecialchars($country['name']) ?> — koop laag, verkoop hoog in een ander land.</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($result): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($result) ?></div>
<?php endif; ?>

<!-- Info -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon">💰</div>
        <div class="stat-value gold">€<?= number_format($user['money'], 0, ',', '.') ?></div>
        <div class="stat-label">Cash</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">💊</div>
        <div class="stat-value"><?= $totalDrugs ?> / <?= $maxCapacity ?></div>
        <div class="stat-label">Drugs in bezit</div>
        <div class="bar"><div class="bar-fill xp" style="width:<?= round(($totalDrugs / $maxCapacity) * 100) ?>%"></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><?= $country['flag'] ?></div>
        <div class="stat-value"><?= htmlspecialchars($country['name']) ?></div>
        <div class="stat-label">Locatie</div>
    </div>
</div>

<!-- Drugs grid -->
<section class="section">
    <h2>Verkrijgbaar in <?= htmlspecialchars($country['name']) ?></h2>

    <div class="drug-grid">
        <?php foreach (DRUGS as $key => $drug):
            if (!isset($prices[$key])) continue;
            $p = $prices[$key];
            $have = $userDrugs[$key] ?? 0;
            $margin = $p['sell_price'] - $p['buy_price'];
            $marginPct = $p['buy_price'] > 0 ? round(($margin / $p['buy_price']) * 100) : 0;
        ?>
        <div class="drug-card">
            <div class="drug-head">
                <span class="drug-icon"><?= $drug['icon'] ?></span>
                <div>
                    <h3><?= htmlspecialchars($drug['name']) ?></h3>
                    <small class="muted">per <?= $drug['unit'] ?></small>
                </div>
            </div>

            <div class="drug-prices">
                <div class="drug-price-row">
                    <span>Koop</span>
                    <strong class="gold">€<?= number_format($p['buy_price'], 0, ',', '.') ?></strong>
                </div>
                <div class="drug-price-row">
                    <span>Verkoop</span>
                    <strong class="green">€<?= number_format($p['sell_price'], 0, ',', '.') ?></strong>
                </div>
                <div class="drug-price-row">
                    <span>Marge</span>
                    <strong class="<?= $margin > 0 ? 'green' : 'red' ?>">
                        <?= $margin > 0 ? '+' : '' ?>€<?= number_format($margin, 0, ',', '.') ?> (<?= $marginPct ?>%)
                    </strong>
                </div>
            </div>

            <?php if ($have > 0): ?>
                <div class="drug-have">In bezit: <strong><?= $have ?></strong></div>
            <?php endif; ?>

            <div class="drug-actions">
                <form method="POST">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="action" value="buy">
                    <input type="hidden" name="drug" value="<?= $key ?>">
                    <input type="number" name="amount" min="1" max="100" value="10" required>
                    <button type="submit" class="btn btn-gold">Kopen</button>
                </form>
                <?php if ($have > 0): ?>
                <form method="POST">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="action" value="sell">
                    <input type="hidden" name="drug" value="<?= $key ?>">
                    <input type="number" name="amount" min="1" max="<?= $have ?>" value="<?= min(10, $have) ?>" required>
                    <button type="submit" class="btn btn-outline">Verkopen</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>