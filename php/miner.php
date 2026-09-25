<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = currentUser($pdo);
$minerId = (int)($_GET['id'] ?? 0);

// Haal miner op
$stmt = $pdo->prepare("
    SELECT um.*, m.name, m.icon, m.description, m.base_btc_per_hour,
           m.base_price, m.tier, m.min_rank,
           h.name AS house_name, uh.country_key
    FROM user_btc_miners um
    JOIN btc_miners m ON m.`key` = um.miner_key
    JOIN user_houses uh ON uh.id = um.house_id
    JOIN houses h ON h.`key` = uh.house_key
    WHERE um.id = ? AND um.user_id = ?
    LIMIT 1
");
$stmt->execute([$minerId, $user['id']]);
$miner = $stmt->fetch();

if (!$miner) redirect('btc.php');

$country = getCountry($pdo, $miner['country_key']);

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf = $_POST['csrf'] ?? '';

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } elseif ($action === 'upgrade') {
        $level = (int)$miner['level'];
        if ($level >= BTC_MAX_LEVEL) {
            $error = 'Max level bereikt.';
        } else {
            $cost = getUpgradeCost($miner);
            if ($user['money'] < $cost) {
                $error = 'Je hebt niet genoeg geld. Kosten: €' . number_format($cost, 0, ',', '.');
            } else {
                $pdo->beginTransaction();
                try {
                    $pdo->prepare("UPDATE users SET money = money - ? WHERE id = ?")
                        ->execute([$cost, $user['id']]);
                    $pdo->prepare("UPDATE user_btc_miners SET level = level + 1 WHERE id = ?")
                        ->execute([$minerId]);

                    logActivity($pdo, $user['id'], "🔧 {$miner['name']} geüpgraded naar level " . ($level + 1));
                    $pdo->commit();

                    $success = "Upgrade naar level " . ($level + 1) . " gelukt!";
                    $user = currentUser($pdo);

                    // Herlaad miner
                    $stmt = $pdo->prepare("
                        SELECT um.*, m.name, m.icon, m.description, m.base_btc_per_hour, m.base_price, m.tier
                        FROM user_btc_miners um
                        JOIN btc_miners m ON m.`key` = um.miner_key
                        WHERE um.id = ? LIMIT 1
                    ");
                    $stmt->execute([$minerId]);
                    $miner = $stmt->fetch();
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Upgrade mislukt.';
                }
            }
        }
    }
}

$hourly  = minerHourlyRate($miner);
$daily   = minerDailyRate($miner);
$level   = (int)$miner['level'];
$cost    = $level < BTC_MAX_LEVEL ? getUpgradeCost($miner) : 0;
$canAfford = $user['money'] >= $cost;

// Toekomstige opbrengst bij upgrade
$nextHourly = $level < BTC_MAX_LEVEL
    ? (float)$miner['base_btc_per_hour'] * (1 + $level * BTC_LEVEL_BONUS)
    : $hourly;
$nextDaily = $nextHourly * 24;

$pageTitle = 'Miner upgraden — Vendetta';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1><?= $miner['icon'] ?> <span><?= htmlspecialchars($miner['name']) ?></span></h1>
    <p><?= $country['flag'] ?> <?= htmlspecialchars($miner['house_name']) ?> — Level <?= $level ?></p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon">📈</div>
        <div class="stat-value" style="color:#f7931a;"><?= formatBtc($hourly) ?></div>
        <div class="stat-label">BTC per uur</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">📅</div>
        <div class="stat-value" style="color:#f7931a;"><?= formatBtc($daily) ?></div>
        <div class="stat-label">BTC per dag</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">💎</div>
        <div class="stat-value"><?= formatBtc((float)$miner['total_btc_mined']) ?></div>
        <div class="stat-label">Totaal gemined</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">⚙️</div>
        <div class="stat-value"><?= $level ?> / <?= BTC_MAX_LEVEL ?></div>
        <div class="stat-label">Level</div>
        <div class="bar">
            <div class="bar-fill xp" style="width:<?= round(($level / BTC_MAX_LEVEL) * 100) ?>%"></div>
        </div>
    </div>
</div>

<?php if ($level < BTC_MAX_LEVEL): ?>
<section class="section">
    <h2>🔧 Upgrade naar level <?= $level + 1 ?></h2>
    <div class="casino-card">
        <p class="muted">Bij elke upgrade stijgt de opbrengst met <?= (BTC_LEVEL_BONUS * 100) ?>%.</p>

        <div class="miner-upgrade-preview">
            <div class="upgrade-row">
                <span>Huidige opbrengst</span>
                <strong><?= formatBtc($hourly) ?> ₿/u</strong>
            </div>
            <div class="upgrade-row highlight">
                <span>Nieuwe opbrengst</span>
                <strong style="color:#58e08c;"><?= formatBtc($nextHourly) ?> ₿/u (+<?= formatBtc($nextHourly - $hourly) ?>)</strong>
            </div>
            <div class="upgrade-row">
                <span>Per dag na upgrade</span>
                <strong><?= formatBtc($nextDaily) ?> ₿</strong>
            </div>
        </div>

        <form method="POST" style="margin-top:16px;">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="upgrade">
            <button type="submit" class="btn btn-gold btn-large btn-full"
                    <?= !$canAfford ? 'disabled' : '' ?>>
                <?php if (!$canAfford): ?>
                    €<?= number_format($cost, 0, ',', '.') ?> — te duur
                <?php else: ?>
                    Upgraden voor €<?= number_format($cost, 0, ',', '.') ?>
                <?php endif; ?>
            </button>
        </form>
    </div>
</section>
<?php else: ?>
    <div class="alert alert-success">🏆 Deze miner is op maximaal niveau.</div>
<?php endif; ?>

<section class="section">
    <a href="btc.php" class="btn btn-outline btn-full">← Terug naar Bitcoin</a>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>