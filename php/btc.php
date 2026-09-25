<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = currentUser($pdo);

$miners = getUserMiners($pdo, $user['id']);
$transactions = getBtcTransactions($pdo, $user['id'], 15);

// Bereken totale uur/dag opbrengst
$totalHourly = 0;
$totalDaily = 0;
foreach ($miners as $m) {
    $totalHourly += minerHourlyRate($m);
    $totalDaily  += minerDailyRate($m);
}

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf = $_POST['csrf'] ?? '';

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } elseif ($action === 'sell') {
        $amount = (float)($_POST['amount'] ?? 0);
        $btc = (float)$user['btc'];

        if ($amount <= 0 || $amount > $btc) {
            $error = 'Ongeldig bedrag.';
        } else {
            $eur = btcToEur($amount);

            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE users SET btc = btc - ?, money = money + ?, total_btc_sold = total_btc_sold + ? WHERE id = ?")
                    ->execute([$amount, $eur, $amount, $user['id']]);

                addBtcTransaction($pdo, $user['id'], -$amount, 'sell', 'Verkocht voor €' . number_format($eur, 0, ',', '.'));
                logActivity($pdo, $user['id'], "₿ " . formatBtc($amount) . " BTC verkocht voor €" . number_format($eur, 0, ',', '.'));
                $pdo->commit();

                $success = "₿ " . formatBtc($amount) . " BTC verkocht voor €" . number_format($eur, 0, ',', '.');
                $user = currentUser($pdo);
                $transactions = getBtcTransactions($pdo, $user['id'], 15);
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Verkoop mislukt.';
            }
        }
    } elseif ($action === 'sell_all') {
        $btc = (float)$user['btc'];
        if ($btc <= 0) {
            $error = 'Je hebt geen BTC.';
        } else {
            $eur = btcToEur($btc);

            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE users SET btc = 0, money = money + ?, total_btc_sold = total_btc_sold + ? WHERE id = ?")
                    ->execute([$eur, $btc, $user['id']]);

                addBtcTransaction($pdo, $user['id'], -$btc, 'sell', 'Alles verkocht voor €' . number_format($eur, 0, ',', '.'));
                logActivity($pdo, $user['id'], "₿ " . formatBtc($btc) . " BTC verkocht voor €" . number_format($eur, 0, ',', '.'));
                $pdo->commit();

                $success = "₿ " . formatBtc($btc) . " BTC verkocht voor €" . number_format($eur, 0, ',', '.');
                $user = currentUser($pdo);
                $transactions = getBtcTransactions($pdo, $user['id'], 15);
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Verkoop mislukt.';
            }
        }
    }
}

$pageTitle = 'Bitcoin — Vendetta';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>₿ <span>Bitcoin</span></h1>
    <p>Verdien BTC via kluizen en mining. Verkoop voor euro's op de markt.</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- Balans -->
<div class="btc-hero">
    <div class="btc-hero-amount">
        <span class="btc-icon">₿</span>
        <span class="btc-balance"><?= formatBtc((float)$user['btc']) ?></span>
        <span class="btc-label">BTC</span>
    </div>
    <div class="btc-hero-eur">
        ≈ €<?= number_format(btcToEur((float)$user['btc']), 0, ',', '.') ?>
    </div>
    <div class="btc-hero-rate">
        1 BTC = €<?= number_format(BTC_SELL_RATE, 0, ',', '.') ?>
    </div>
</div>

<!-- Stats -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon">📈</div>
        <div class="stat-value" style="color:#f7931a;"><?= formatBtc($totalHourly) ?></div>
        <div class="stat-label">BTC per uur</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">📅</div>
        <div class="stat-value" style="color:#f7931a;"><?= formatBtc($totalDaily) ?></div>
        <div class="stat-label">BTC per dag</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">💎</div>
        <div class="stat-value"><?= count($miners) ?></div>
        <div class="stat-label">Miners actief</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">💰</div>
        <div class="stat-value gold">€<?= number_format($user['money'], 0, ',', '.') ?></div>
        <div class="stat-label">Cash</div>
    </div>
</div>

<!-- Verkoop -->
<?php if ((float)$user['btc'] > 0): ?>
<section class="section">
    <h2>💱 Verkoop BTC</h2>
    <div class="casino-card">
        <form method="POST" class="casino-form">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="sell">

            <div class="bet-input">
                <label>Bedrag in BTC (max <?= formatBtc((float)$user['btc']) ?>)</label>
                <input type="number" name="amount" min="0.00000001" step="0.00000001"
                       max="<?= (float)$user['btc'] ?>"
                       value="<?= (float)$user['btc'] ?>" required>
            </div>

            <div class="bank-actions">
                <button type="submit" class="btn btn-gold btn-full">Verkoop</button>
                <button type="submit" name="action" value="sell_all" class="btn btn-outline btn-full"
                        onclick="return confirm('Alles verkopen voor €<?= number_format(btcToEur((float)$user['btc']), 0, ',', '.') ?>?');">
                    Alles verkopen
                </button>
            </div>
        </form>
    </div>
</section>
<?php endif; ?>

<!-- Miners -->
<section class="section">
    <h2>⛏️ Jouw miners (<?= count($miners) ?>)</h2>

    <?php if (empty($miners)): ?>
        <p class="muted">
            Je hebt nog geen miners. Koop een huis en plaats een miner via
            <a href="houses.php">Huizen</a> → bekijk je huis → Miner.
        </p>
    <?php else: ?>
        <div class="miner-grid">
            <?php foreach ($miners as $m):
                $c = getCountry($pdo, $m['country_key']);
            ?>
                <div class="miner-card">
                    <div class="miner-head">
                        <span class="miner-icon"><?= $m['icon'] ?></span>
                        <div>
                            <h3><?= htmlspecialchars($m['name']) ?></h3>
                            <small class="muted">
                                <?= $c['flag'] ?> <?= htmlspecialchars($m['house_name']) ?>
                            </small>
                        </div>
                        <span class="miner-level">Lv <?= (int)$m['level'] ?></span>
                    </div>

                    <div class="miner-stats">
                        <div class="miner-stat">
                            <span>📈 Per uur</span>
                            <strong><?= formatBtc(minerHourlyRate($m)) ?> ₿</strong>
                        </div>
                        <div class="miner-stat">
                            <span>📅 Per dag</span>
                            <strong><?= formatBtc(minerDailyRate($m)) ?> ₿</strong>
                        </div>
                        <div class="miner-stat">
                            <span>💎 Totaal</span>
                            <strong><?= formatBtc((float)$m['total_btc_mined']) ?> ₿</strong>
                        </div>
                    </div>

                    <?php if ((int)$m['level'] < BTC_MAX_LEVEL): ?>
                        <?php $cost = getUpgradeCost($m); ?>
                        <a href="miner.php?id=<?= (int)$m['id'] ?>" class="btn btn-gold btn-full">
                            🔧 Upgraden — €<?= number_format($cost, 0, ',', '.') ?>
                        </a>
                    <?php else: ?>
                        <button class="btn btn-outline btn-full" disabled>🏆 Max level</button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- Transacties -->
<?php if (!empty($transactions)): ?>
<section class="section">
    <h2>📜 BTC transacties</h2>
    <ul class="activity-list">
        <?php foreach ($transactions as $t):
            $isPositive = (float)$t['amount'] > 0;
        ?>
            <li>
                <span>
                    <strong><?= htmlspecialchars($t['type']) ?></strong>
                    — <?= htmlspecialchars($t['description']) ?>
                    <span style="color:<?= $isPositive ? '#58e08c' : '#ff5c5c' ?>;">
                        <?= $isPositive ? '+' : '' ?><?= formatBtc((float)$t['amount']) ?> ₿
                    </span>
                </span>
                <time><?= date('d M H:i', strtotime($t['created_at'])) ?></time>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>