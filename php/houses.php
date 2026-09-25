<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = currentUser($pdo);
$rankData = getRankData((int)$user['xp'], $RANKS);
$country = getCountry($pdo, $user['current_country']);
$allHouses = getAllHouses($pdo);
$myHouseHere = getUserHouse($pdo, $user['id'], $user['current_country']);
$myHouses = getUserHouses($pdo, $user['id']);

// BTC miners
$btcMiners = function_exists('getAllBtcMiners') ? getAllBtcMiners($pdo) : [];

$error = null;
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf = $_POST['csrf'] ?? '';

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    }
    // === HUIS KOPEN ===
    elseif ($action === 'buy_house') {
        $houseKey = $_POST['house'] ?? '';

        if ($myHouseHere) {
            $error = 'Je hebt al een huis in dit land.';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM houses WHERE `key` = ? LIMIT 1");
            $stmt->execute([$houseKey]);
            $house = $stmt->fetch();

            if (!$house) {
                $error = 'Onbekend huis.';
            } elseif ($user['money'] < $house['price']) {
                $error = 'Je hebt niet genoeg geld.';
            } else {
                $pdo->beginTransaction();
                try {
                    $pdo->prepare("UPDATE users SET money = money - ? WHERE id = ?")
                        ->execute([$house['price'], $user['id']]);
                    $pdo->prepare("INSERT INTO user_houses (user_id, country_key, house_key) VALUES (?, ?, ?)")
                        ->execute([$user['id'], $user['current_country'], $house['key']]);

                    logActivity($pdo, $user['id'],
                        "🏠 {$house['name']} gekocht in {$country['name']} voor €" . number_format($house['price'], 0, ',', '.'));
                    checkAchievements($pdo, $user['id']);
                    $pdo->commit();

                    $result = "Je hebt een {$house['name']} gekocht in {$country['flag']} {$country['name']}!";
                    $user = currentUser($pdo);
                    $myHouseHere = getUserHouse($pdo, $user['id'], $user['current_country']);
                    $myHouses = getUserHouses($pdo, $user['id']);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Aankoop mislukt.';
                }
            }
        }
    }
    // === MINER KOPEN ===
    elseif ($action === 'buy_miner') {
        $houseId = (int)($_POST['house_id'] ?? 0);
        $minerKey = $_POST['miner_key'] ?? '';

        // Check of huis van user is
        $stmt = $pdo->prepare("SELECT id FROM user_houses WHERE id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$houseId, $user['id']]);
        if (!$stmt->fetch()) {
            $error = 'Huis niet gevonden.';
        } elseif (getMinerForHouse($pdo, $houseId)) {
            $error = 'Dit huis heeft al een miner.';
        } else {
            $miner = getBtcMiner($pdo, $minerKey);
            if (!$miner) {
                $error = 'Onbekende miner.';
            } elseif ($rankData['level'] < (int)$miner['min_rank']) {
                $error = 'Je rank is te laag voor deze miner.';
            } elseif ($user['money'] < (int)$miner['base_price']) {
                $error = 'Je hebt niet genoeg geld.';
            } else {
                $pdo->beginTransaction();
                try {
                    $pdo->prepare("UPDATE users SET money = money - ? WHERE id = ?")
                        ->execute([$miner['base_price'], $user['id']]);
                    $pdo->prepare("
                        INSERT INTO user_btc_miners (user_id, house_id, miner_key, level, last_production_at)
                        VALUES (?, ?, ?, 1, NOW())
                    ")->execute([$user['id'], $houseId, $minerKey]);

                    logActivity($pdo, $user['id'],
                        "⛏️ {$miner['name']} geplaatst in huis #{$houseId} voor €" . number_format($miner['base_price'], 0, ',', '.'));
                    $pdo->commit();

                    $result = "{$miner['icon']} {$miner['name']} geplaatst!";
                    $user = currentUser($pdo);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Kon miner niet plaatsen: ' . $e->getMessage();
                }
            }
        }
    }
}

$pageTitle = 'Huizen — Vendetta';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Huizen in <span><?= htmlspecialchars($country['name']) ?></span> <?= $country['flag'] ?></h1>
    <p>Koop een huis voor wietkweek en Bitcoin mining. Elk huis heeft zijn eigen ruimte.</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($result): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($result) ?></div>
<?php endif; ?>

<?php if ($myHouseHere): ?>
    <div class="alert alert-success">
        Je bezit een <strong><?= htmlspecialchars($myHouseHere['name']) ?></strong> in dit land
        (sinds <?= date('d M Y', strtotime($myHouseHere['bought_at'])) ?>).
        <a href="grow.php">Ga wiet kweken →</a>
    </div>
<?php endif; ?>

<!-- Stats -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon">💰</div>
        <div class="stat-value gold">€<?= number_format($user['money'], 0, ',', '.') ?></div>
        <div class="stat-label">Cash</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">🏠</div>
        <div class="stat-value"><?= count($myHouses) ?></div>
        <div class="stat-label">Huizen in bezit</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><?= $country['flag'] ?></div>
        <div class="stat-value"><?= htmlspecialchars($country['name']) ?></div>
        <div class="stat-label">Locatie</div>
    </div>
</div>

<!-- Beschikbare huizen -->
<section class="section">
    <h2>Beschikbare huizen</h2>

    <?php if ($myHouseHere): ?>
        <p class="muted">Je hebt al een huis in <?= htmlspecialchars($country['name']) ?>. Reizen naar een ander land om daar een huis te kopen.</p>
    <?php endif; ?>

    <div class="house-grid">
        <?php foreach ($allHouses as $h):
            $canAfford = $user['money'] >= $h['price'];
            $disabled  = $myHouseHere || !$canAfford;
        ?>
        <div class="house-card <?= $disabled ? 'disabled' : '' ?>">
            <div class="house-head">
                <span class="house-icon">🏠</span>
                <h3><?= htmlspecialchars($h['name']) ?></h3>
            </div>
            <p><?= htmlspecialchars($h['description']) ?></p>

            <div class="house-stats">
                <div class="house-stat">
                    <span>🌿</span>
                    <strong><?= (int)$h['grow_slots'] ?> planten</strong>
                </div>
                <div class="house-stat">
                    <span>⏱️</span>
                    <strong><?= (int)$h['grow_time_minutes'] ?> min</strong>
                </div>
                <div class="house-stat">
                    <span>📦</span>
                    <strong><?= (int)$h['yield_per_slot'] ?> wiet/plant</strong>
                </div>
            </div>

            <div class="house-footer">
                <strong class="price">€<?= number_format($h['price'], 0, ',', '.') ?></strong>
                <form method="POST">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="action" value="buy_house">
                    <input type="hidden" name="house" value="<?= htmlspecialchars($h['key']) ?>">
                    <button type="submit" class="btn btn-gold" <?= $disabled ? 'disabled' : '' ?>>
                        <?php if ($myHouseHere): ?>
                            Al in bezit
                        <?php elseif (!$canAfford): ?>
                            Te duur
                        <?php else: ?>
                            Kopen
                        <?php endif; ?>
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Jouw huizen + miners -->
<?php if (!empty($myHouses)): ?>
<section class="section">
    <h2>⛏️ Jouw huizen & miners</h2>

    <?php foreach ($myHouses as $mh):
        $c = getCountry($pdo, $mh['country_key']);
        $houseId = (int)$mh['id'];
        $miner = function_exists('getMinerForHouse') ? getMinerForHouse($pdo, $houseId) : null;
    ?>
        <div class="house-widget">
            <div class="house-widget-head">
                <span><?= $c['flag'] ?> <?= htmlspecialchars($c['name']) ?></span>
                <strong><?= htmlspecialchars($mh['name']) ?></strong>
            </div>

            <?php if ($miner): ?>
                <div class="house-miner-active">
                    <span class="miner-icon"><?= $miner['icon'] ?></span>
                    <div>
                        <strong><?= htmlspecialchars($miner['name']) ?></strong>
                        <small>Lv <?= (int)$miner['level'] ?> · <?= formatBtc(minerHourlyRate($miner)) ?> ₿/u · <?= formatBtc(minerDailyRate($miner)) ?> ₿/dag</small>
                    </div>
                    <a href="miner.php?id=<?= (int)$miner['id'] ?>" class="btn btn-outline">Beheer</a>
                </div>
            <?php else: ?>
                <details class="house-miner-buy">
                    <summary>➕ Miner plaatsen</summary>
                    <div class="miner-options">
                        <?php if (empty($btcMiners)): ?>
                            <p class="muted">Geen miners beschikbaar.</p>
                        <?php else: ?>
                            <?php foreach ($btcMiners as $bm):
                                $canAfford = $user['money'] >= (int)$bm['base_price'];
                                $rankOK = $rankData['level'] >= (int)$bm['min_rank'];
                                $disabled = !$canAfford || !$rankOK;
                            ?>
                                <form method="POST" class="miner-option">
                                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="buy_miner">
                                    <input type="hidden" name="house_id" value="<?= $houseId ?>">
                                    <input type="hidden" name="miner_key" value="<?= htmlspecialchars($bm['key']) ?>">

                                    <div class="miner-option-info">
                                        <span class="miner-icon"><?= $bm['icon'] ?></span>
                                        <div>
                                            <strong><?= htmlspecialchars($bm['name']) ?></strong>
                                            <small><?= formatBtc((float)$bm['base_btc_per_hour']) ?> ₿/u<?= !$rankOK ? ' · Rank ' . (int)$bm['min_rank'] . '+' : '' ?></small>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-gold" <?= $disabled ? 'disabled' : '' ?>>
                                        €<?= number_format((int)$bm['base_price'], 0, ',', '.') ?>
                                    </button>
                                </form>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </details>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>