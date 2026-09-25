<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = currentUser($pdo);
$garageId = (int)($_GET['car'] ?? 0);

if ($garageId === 0) {
    // Toon lijst om auto te kiezen
    $myCars = getUserGarage($pdo, $user['id']);
    $pageTitle = 'Tuning — Vendetta';
    require __DIR__ . '/includes/header.php';
    ?>
    <div class="page-header">
        <h1>Auto <span>tuning</span></h1>
        <p>Kies een auto uit je garage om te upgraden.</p>
    </div>

    <?php if (empty($myCars)): ?>
        <div class="alert alert-error">
            🚗 Je hebt nog geen autos. <a href="cars.php">Steel er eerst een →</a>
        </div>
    <?php else: ?>
        <div class="car-grid">
            <?php foreach ($myCars as $c):
                $color = rarityColor($c['rarity']);
                $upgrades = getAllUpgrades($pdo);
                $perf = calculateCarPerformance($c, $upgrades);
            ?>
                <a href="tune.php?car=<?= (int)$c['id'] ?>" class="car-card rarity-<?= $c['rarity'] ?>">
                    <div class="car-head">
                        <span class="car-icon"><?= $c['icon'] ?></span>
                        <div>
                            <h3><?= htmlspecialchars($c['name']) ?></h3>
                            <small class="car-brand"><?= htmlspecialchars($c['brand']) ?></small>
                        </div>
                    </div>
                    <div class="car-rarity" style="color:<?= $color ?>;">
                        <?= rarityLabel($c['rarity']) ?>
                    </div>
                    <div class="car-value">
                        <span class="car-value-label">Prestatie</span>
                        <strong class="car-value-amount"><?= $perf['total'] ?></strong>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php require __DIR__ . '/includes/footer.php';
    exit;
}

// Auto ophalen
$stmt = $pdo->prepare("
    SELECT ug.*, ct.name AS car_name, ct.icon AS car_icon, ct.rarity, ct.brand,
           cp.value AS current_value
    FROM user_garage ug
    JOIN car_types ct ON ct.`key` = ug.car_key
    LEFT JOIN car_prices cp ON cp.car_key = ug.car_key AND cp.country_key = ug.country_key
    WHERE ug.id = ? AND ug.user_id = ?
    LIMIT 1
");
$stmt->execute([$garageId, $user['id']]);
$car = $stmt->fetch();

if (!$car) redirect('tune.php');

$upgrades = getAllUpgrades($pdo);
$perf = calculateCarPerformance($car, $upgrades);

$error = null;
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $upgradeKey = $_POST['upgrade'] ?? '';
    $csrf = $_POST['csrf'] ?? '';

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } else {
        // Zoek upgrade
        $upgrade = null;
        foreach ($upgrades as $u) {
            if ($u['key'] === $upgradeKey) { $upgrade = $u; break; }
        }

        if (!$upgrade) {
            $error = 'Onbekende upgrade.';
        } else {
            $currentLevel = (int)($car[$upgradeKey . '_level'] ?? 0);
            $cost = upgradeCost($upgrade, $currentLevel);

            if ($cost < 0) {
                $error = 'Deze upgrade zit al op maximaal niveau.';
            } elseif ($user['money'] < $cost) {
                $error = 'Je hebt niet genoeg geld. Kosten: €' . number_format($cost, 0, ',', '.');
            } else {
                $pdo->beginTransaction();
                try {
                    $pdo->prepare("UPDATE users SET money = money - ? WHERE id = ?")
                        ->execute([$cost, $user['id']]);

                    $pdo->prepare("
                        UPDATE user_garage
                        SET {$upgradeKey}_level = {$upgradeKey}_level + 1,
                            total_upgrade_cost = total_upgrade_cost + ?
                        WHERE id = ?
                    ")->execute([$cost, $garageId]);

                    logActivity($pdo, $user['id'],
                        "🔧 {$upgrade['name']} level " . ($currentLevel + 1) . " op {$car['car_name']} — €" . number_format($cost, 0, ',', '.'));
                    $pdo->commit();

                    $result = "{$upgrade['icon']} {$upgrade['name']} → level " . ($currentLevel + 1);
                    $user = currentUser($pdo);

                    // Refresh car
                    $stmt = $pdo->prepare("
                        SELECT ug.*, ct.name AS car_name, ct.icon AS car_icon, ct.rarity, ct.brand,
                               cp.value AS current_value
                        FROM user_garage ug
                        JOIN car_types ct ON ct.`key` = ug.car_key
                        LEFT JOIN car_prices cp ON cp.car_key = ug.car_key AND cp.country_key = ug.country_key
                        WHERE ug.id = ?
                    ");
                    $stmt->execute([$garageId]);
                    $car = $stmt->fetch();
                    $perf = calculateCarPerformance($car, $upgrades);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Upgrade mislukt.';
                }
            }
        }
    }
}

$pageTitle = 'Tuning — Vendetta';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1><?= $car['car_icon'] ?> <span><?= htmlspecialchars($car['car_name']) ?></span></h1>
    <p>Tune je auto voor betere prestaties in races.</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($result): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($result) ?></div>
<?php endif; ?>

<!-- Prestatie overzicht -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon">⚡</div>
        <div class="stat-value"><?= $perf['speed'] ?></div>
        <div class="stat-label">Snelheid</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">🎯</div>
        <div class="stat-value"><?= $perf['handling'] ?></div>
        <div class="stat-label">Handling</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">💨</div>
        <div class="stat-value"><?= $perf['nitro'] ?></div>
        <div class="stat-label">Nitro</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">🏁</div>
        <div class="stat-value gold"><?= $perf['total'] ?></div>
        <div class="stat-label">Totaal</div>
    </div>
</div>

<!-- Upgrades -->
<section class="section">
    <h2>Upgrades</h2>
    <div class="upgrade-grid">
        <?php foreach ($upgrades as $u):
            $level = (int)($car[$u['key'] . '_level'] ?? 0);
            $maxLevel = (int)$u['max_level'];
            $cost = upgradeCost($u, $level);
            $maxed = $level >= $maxLevel;
            $canAfford = !$maxed && $user['money'] >= $cost;
        ?>
            <div class="upgrade-card <?= $maxed ? 'maxed' : '' ?>">
                <div class="upgrade-head">
                    <span class="upgrade-icon"><?= $u['icon'] ?></span>
                    <h3><?= htmlspecialchars($u['name']) ?></h3>
                </div>
                <p class="muted"><?= htmlspecialchars($u['description']) ?></p>

                <div class="upgrade-levels">
                    <?php for ($i = 1; $i <= $maxLevel; $i++): ?>
                        <div class="upgrade-pip <?= $i <= $level ? 'filled' : '' ?>"></div>
                    <?php endfor; ?>
                </div>
                <div class="upgrade-level-text">
                    Level <?= $level ?> / <?= $maxLevel ?>
                </div>

                <?php if ($maxed): ?>
                    <button class="btn btn-outline btn-full" disabled>Max level bereikt</button>
                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <input type="hidden" name="action" value="upgrade">
                        <input type="hidden" name="upgrade" value="<?= $u['key'] ?>">
                        <button type="submit" class="btn btn-gold btn-full"
                                <?= !$canAfford ? 'disabled' : '' ?>>
                            <?php if (!$canAfford): ?>
                                €<?= number_format($cost, 0, ',', '.') ?> — te duur
                            <?php else: ?>
                                Upgrade — €<?= number_format($cost, 0, ',', '.') ?>
                            <?php endif; ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="section">
    <a href="tune.php" class="btn btn-outline btn-full">← Andere auto kiezen</a>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>