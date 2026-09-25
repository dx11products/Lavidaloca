<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = currentUser($pdo);
$country = getCountry($pdo, $user['current_country']);
$myCars = getUserGarage($pdo, $user['id']);

$error = null;
$result = null;

// Verkoop een auto
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $garageId = (int)($_POST['garage_id'] ?? 0);
    $csrf = $_POST['csrf'] ?? '';

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } else {
        $stmt = $pdo->prepare("
            SELECT ug.*, ct.name, ct.icon, cp.value
            FROM user_garage ug
            JOIN car_types ct ON ct.`key` = ug.car_key
            LEFT JOIN car_prices cp ON cp.car_key = ug.car_key AND cp.country_key = ug.country_key
            WHERE ug.id = ? AND ug.user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$garageId, $user['id']]);
        $car = $stmt->fetch();

        if (!$car) {
            $error = 'Auto niet gevonden.';
        } else {
            $sellPrice = (int)floor(($car['value'] ?? 0) * CAR_SELL_PERCENT / 100);

            $pdo->beginTransaction();
            try {
                $pdo->prepare("DELETE FROM user_garage WHERE id = ?")->execute([$garageId]);
                $pdo->prepare("UPDATE users SET money = money + ? WHERE id = ?")
                    ->execute([$sellPrice, $user['id']]);

                logActivity($pdo, $user['id'],
                    "💰 {$car['name']} verkocht voor €" . number_format($sellPrice, 0, ',', '.'));

                $pdo->commit();

                $result = "{$car['icon']} {$car['name']} verkocht voor €" . number_format($sellPrice, 0, ',', '.');
                $user = currentUser($pdo);
                $myCars = getUserGarage($pdo, $user['id']);
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Verkoop mislukt.';
            }
        }
    }
}

// Statistieken
$totalValue = 0;
foreach ($myCars as $c) {
    $totalValue += (int)($c['current_value'] ?? 0);
}

$pageTitle = 'Garage — Vendetta';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Jouw <span>Garage</span></h1>
    <p>Alle autos die je hebt gestolen. Verkoop ze voor <?= CAR_SELL_PERCENT ?>% van hun waarde.</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($result): ?>
    <div class="alert alert-success">💰 <?= htmlspecialchars($result) ?></div>
<?php endif; ?>

<!-- Stats -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon">🚗</div>
        <div class="stat-value"><?= count($myCars) ?></div>
        <div class="stat-label">Autos in bezit</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">💎</div>
        <div class="stat-value gold">€<?= number_format($totalValue, 0, ',', '.') ?></div>
        <div class="stat-label">Totale waarde</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">💰</div>
        <div class="stat-value gold">€<?= number_format((int)floor($totalValue * CAR_SELL_PERCENT / 100), 0, ',', '.') ?></div>
        <div class="stat-label">Verkoopwaarde</div>
    </div>
</div>

<!-- Autos -->
<section class="section">
    <h2>Jouw wagens (<?= count($myCars) ?>)</h2>

    <?php if (empty($myCars)): ?>
        <p class="muted">
            Nog geen autos. Ga naar <a href="cars.php">Auto stelen</a> om te beginnen.
        </p>
    <?php else: ?>
        <div class="car-grid">
            <?php foreach ($myCars as $c):
                $sellPrice = (int)floor(($c['current_value'] ?? 0) * CAR_SELL_PERCENT / 100);
                $color = rarityColor($c['rarity']);
            ?>
            <div class="car-card rarity-<?= $c['rarity'] ?>">
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

                <div class="car-location">
                    <?= $c['flag'] ?> <?= htmlspecialchars($c['country_name']) ?>
                </div>

                <div class="car-value">
                    <span class="car-value-label">Verkoopprijs</span>
                    <strong class="car-value-amount">€<?= number_format($sellPrice, 0, ',', '.') ?></strong>
                </div>

                <form method="POST" onsubmit="return confirm('Weet je zeker dat je deze auto wil verkopen voor €<?= number_format($sellPrice, 0, ',', '.') ?>?');">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="garage_id" value="<?= (int)$c['id'] ?>">
                    <button type="submit" class="btn btn-gold btn-full">Verkopen</button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>