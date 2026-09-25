<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = currentUser($pdo);
$rankData = getRankData((int)$user['xp'], $RANKS);
$country = getCountry($pdo, $user['current_country']);

if (function_exists('isInPrison') && isInPrison($user)) redirect('prison.php');

$hasHouse = userHasHouseInCountry($pdo, $user['id'], $user['current_country']);
$cars = getCarsInCountry($pdo, $user['current_country']);
$myBonuses = getEquippedBonuses($pdo, $user['id']);
$myAttack = 10 + (int)$myBonuses['attack'] + getClickWeaponBonus($pdo, $user['id']);

$cooldown = canStealCar($pdo, $user['id']);
$inHospital = isInHospital($user);

$levelMult = getCarLevelMultiplier($rankData['level']);

$result = null;
$error  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $carKey = $_POST['car_key'] ?? '';
    $csrf   = $_POST['csrf'] ?? '';

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } elseif (!$hasHouse) {
        $error = 'Je moet eerst een huis kopen in dit land.';
    } elseif ($inHospital) {
        $error = 'Je ligt in het ziekenhuis.';
    } elseif (!$cooldown['ok']) {
        $error = 'Wacht nog ' . $cooldown['wait'] . ' seconden.';
    } else {
        $carInfo = getCarValueInCountry($pdo, $carKey, $user['current_country']);
        if (!$carInfo) {
            $error = 'Deze auto is niet beschikbaar.';
        } elseif (countGarageCars($pdo, $user['id'], $user['current_country']) >= CAR_GARAGE_LIMIT) {
            $error = 'Je garage is vol.';
        } else {
            $baseChance = (int)$carInfo['steal_chance'];
            $attackBonus = (int)floor($myAttack / 4);
            $chance = max(5, min(95, $baseChance + $attackBonus));

            $roll = random_int(1, 100);
            $success = $roll <= $chance;

            if ($success) {
                $pdo->beginTransaction();
                try {
                    $xpGain = (int)floor(CAR_STEAL_XP * $levelMult);
                    $pdo->prepare("UPDATE users SET xp = xp + ? WHERE id = ?")->execute([$xpGain, $user['id']]);
                    addCarToGarage($pdo, $user['id'], $user['current_country'], $carKey);
                    recordSteal($pdo, $user['id']);

                    $carType = getCarType($pdo, $carKey);
                    logActivity($pdo, $user['id'], "🚗 {$carType['name']} gestolen in {$country['name']}");

                    $pdo->commit();

                    $result = ['success' => true, 'name' => $carType['name'], 'icon' => $carType['icon'], 'value' => $carInfo['value']];
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Steel mislukt.';
                }
            } else {
                $fine = random_int(500, 3000);
                $fine = min($fine, (int)$user['money']);

                $pdo->beginTransaction();
                try {
                    $pdo->prepare("UPDATE users SET money = money - ? WHERE id = ?")->execute([$fine, $user['id']]);
                    recordSteal($pdo, $user['id']);

                    $carType = getCarType($pdo, $carKey);
                    logActivity($pdo, $user['id'], "❌ Mislukte autodiefstal ({$carType['name']}) — €" . number_format($fine, 0, ',', '.'));
                    $pdo->commit();

                    $result = ['success' => false, 'name' => $carType['name'], 'fine' => $fine];
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Steel mislukt.';
                }
            }

            $user = currentUser($pdo);
            $cooldown = canStealCar($pdo, $user['id']);
        }
    }
}

$pageTitle = 'Auto stelen — Vendetta';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Auto <span>stelen</span></h1>
    <p><?= $country['flag'] ?> <?= htmlspecialchars($country['name']) ?> — steel wagens. Wachttijd: 30 seconden na elke poging.</p>
</div>

<div class="rank-multiplier-banner">
    <div class="rm-icon">📈</div>
    <div class="rm-info">
        <strong>Rank bonus: ×<?= number_format($levelMult, 2) ?></strong>
        <p class="muted">XP schaalt mee met je rank.</p>
    </div>
</div>

<?php if (!$hasHouse): ?>
    <div class="alert alert-error">🏠 Je hebt geen huis in <?= htmlspecialchars($country['name']) ?>. <a href="houses.php">Koop eerst een huis →</a></div>
<?php endif; ?>

<?php if ($inHospital): ?>
    <div class="alert alert-error">🏥 Je ligt in het ziekenhuis. <a href="hospital.php">Herstel eerst →</a></div>
<?php endif; ?>

<?php if (!$cooldown['ok'] && $hasHouse && !$inHospital): ?>
    <div class="alert alert-error">⏱️ Wacht nog <strong><?= $cooldown['wait'] ?>s</strong> voor je weer steelt.</div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($result): ?>
    <div class="alert <?= $result['success'] ? 'alert-success' : 'alert-error' ?>">
        <?php if ($result['success']): ?>
            <?= $result['icon'] ?> <strong><?= htmlspecialchars($result['name']) ?></strong> gestolen! Waarde: <strong>€<?= number_format($result['value'], 0, ',', '.') ?></strong>.
            <br>Ga naar je <a href="garage.php">garage</a> om te verkopen.
        <?php else: ?>
            ❌ Betrapt bij stelen van <strong><?= htmlspecialchars($result['name']) ?></strong>. Boete: €<?= number_format($result['fine'], 0, ',', '.') ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon">🅿️</div>
        <div class="stat-value"><?= countGarageCars($pdo, $user['id'], $user['current_country']) ?> / <?= CAR_GARAGE_LIMIT ?></div>
        <div class="stat-label">Garage ruimte</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">⏱️</div>
        <div class="stat-value">30s</div>
        <div class="stat-label">Wachttijd</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">⚔️</div>
        <div class="stat-value">+<?= (int)floor($myAttack / 4) ?>%</div>
        <div class="stat-label">Bonus kans</div>
    </div>
</div>

<section class="section">
    <h2>Beschikbare autos in <?= htmlspecialchars($country['name']) ?></h2>

    <?php if (empty($cars)): ?>
        <p class="muted">Geen autos beschikbaar.</p>
    <?php else: ?>
        <div class="car-grid">
            <?php foreach ($cars as $c):
                $disabled = !$hasHouse || $inHospital || !$cooldown['ok']
                            || countGarageCars($pdo, $user['id'], $user['current_country']) >= CAR_GARAGE_LIMIT;
                $baseChance = (int)$c['steal_chance'];
                $finalChance = max(5, min(95, $baseChance + (int)floor($myAttack / 4)));
                $color = rarityColor($c['rarity']);
            ?>
            <div class="car-card <?= $disabled ? 'disabled' : '' ?> rarity-<?= $c['rarity'] ?>">
                <div class="car-head">
                    <span class="car-icon"><?= $c['icon'] ?></span>
                    <div>
                        <h3><?= htmlspecialchars($c['name']) ?></h3>
                        <small class="car-brand"><?= htmlspecialchars($c['brand']) ?></small>
                    </div>
                </div>
                <div class="car-rarity" style="color:<?= $color ?>;"><?= rarityLabel($c['rarity']) ?></div>
                <div class="car-value">
                    <span class="car-value-label">Waarde</span>
                    <strong class="car-value-amount">€<?= number_format($c['value'], 0, ',', '.') ?></strong>
                </div>
                <div class="car-chance">
                    <span>Kans</span>
                    <strong class="<?= $finalChance >= 60 ? 'good' : ($finalChance >= 40 ? 'medium' : 'bad') ?>"><?= $finalChance ?>%</strong>
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="car_key" value="<?= htmlspecialchars($c['key']) ?>">
                    <button type="submit" class="btn btn-gold btn-full" <?= $disabled ? 'disabled' : '' ?>>
                        <?= $cooldown['ok'] ? 'Stelen' : '⏱️ ' . $cooldown['wait'] . 's' ?>
                    </button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>