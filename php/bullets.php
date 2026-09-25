<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = currentUser($pdo);
$country = getCountry($pdo, $user['current_country']);
$allFactories = getAllFactories($pdo);
$myFactories = getUserFactories($pdo, $user['id'], $user['current_country']);
$activeProduction = getActiveBulletProduction($pdo, $user['id']);
$myAmmo = getUserAmmo($pdo, $user['id']);
$totalAmmo = getTotalAmmo($myAmmo);
$userFamily = getUserFamily($pdo, $user['id']);

$bulletStatus = getBulletStatus($pdo, $user['id']);
$recentProduction = getRecentBulletProduction($pdo, $user['id'], 8);
$nextUpgrade = getNextBulletUpgrade($pdo, $user['id']);

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf = $_POST['csrf'] ?? '';

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    }
    // Handmatige productie
    elseif ($action === 'produce_manual') {
        $amount = (int)($_POST['amount'] ?? 0);
        $res = produceBullets($pdo, $user['id'], $amount);
        if (isset($res['error'])) {
            $error = $res['error'];
        } else {
            $success = "🔫 " . number_format($res['amount'], 0, ',', '.') . " kogels geproduceerd voor €" .
                       number_format($res['cost'], 0, ',', '.') . "!";
            $user = currentUser($pdo);
            $myAmmo = getUserAmmo($pdo, $user['id']);
            $totalAmmo = getTotalAmmo($myAmmo);
            $bulletStatus = getBulletStatus($pdo, $user['id']);
            $recentProduction = getRecentBulletProduction($pdo, $user['id'], 8);
        }
    }
    // Upgrade limiet
    elseif ($action === 'upgrade_limit') {
        $res = upgradeFamilyBulletLimit($pdo, $user['id']);
        if (isset($res['error'])) {
            $error = $res['error'];
        } else {
            $success = "🎯 Limiet geüpgraded naar level {$res['level']}! Nieuwe limiet: " .
                       number_format($res['new_limit'], 0, ',', '.') . " kogels/dag.";
            $user = currentUser($pdo);
            $userFamily = getUserFamily($pdo, $user['id']);
            $bulletStatus = getBulletStatus($pdo, $user['id']);
            $nextUpgrade = getNextBulletUpgrade($pdo, $user['id']);
        }
    }
    // Fabriek kopen (bestaande)
    elseif ($action === 'buy_factory') {
        $factoryKey = $_POST['factory_key'] ?? '';
        $factory = getFactory($pdo, $factoryKey);

        if (!$factory) {
            $error = 'Onbekende fabriek.';
        } elseif (count($myFactories) > 0) {
            $error = 'Je hebt al een kogelfabriek in dit land.';
        } elseif ($user['money'] < $factory['price']) {
            $error = 'Je hebt niet genoeg geld.';
        } else {
            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE users SET money = money - ? WHERE id = ?")
                    ->execute([$factory['price'], $user['id']]);
                $pdo->prepare("INSERT INTO user_factories (user_id, country_key, factory_key) VALUES (?, ?, ?)")
                    ->execute([$user['id'], $user['current_country'], $factoryKey]);

                logActivity($pdo, $user['id'],
                    "🏭 {$factory['name']} gekocht in {$country['name']} voor €" . number_format($factory['price'], 0, ',', '.'));
                $pdo->commit();

                $success = "Je hebt een {$factory['name']} geopend!";
                $user = currentUser($pdo);
                $myFactories = getUserFactories($pdo, $user['id'], $user['current_country']);
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Aankoop mislukt.';
            }
        }
    }
    // Fabriek productie (bestaande)
    elseif ($action === 'produce') {
        $factoryKey = $_POST['factory_key'] ?? '';
        $factory = getFactory($pdo, $factoryKey);

        $has = false;
        foreach ($myFactories as $f) {
            if ($f['factory_key'] === $factoryKey) { $has = true; break; }
        }

        if (!$factory || !$has) {
            $error = 'Je hebt deze fabriek niet.';
        } elseif ($user['money'] < $factory['material_cost']) {
            $error = 'Je hebt niet genoeg geld voor materialen (€' . number_format($factory['material_cost'], 0, ',', '.') . ').';
        } else {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM user_bullet_production
                WHERE user_id = ? AND factory_key = ? AND collected = 0
            ");
            $stmt->execute([$user['id'], $factoryKey]);
            if ((int)$stmt->fetchColumn() > 0) {
                $error = 'Deze fabriek is al bezig.';
            } else {
                $pdo->beginTransaction();
                try {
                    $pdo->prepare("UPDATE users SET money = money - ? WHERE id = ?")
                        ->execute([$factory['material_cost'], $user['id']]);

                    $readyAt = date('Y-m-d H:i:s', time() + ($factory['process_time_minutes'] * 60));
                    $pdo->prepare("
                        INSERT INTO user_bullet_production (user_id, factory_key, ready_at, batch_amount)
                        VALUES (?, ?, ?, ?)
                    ")->execute([$user['id'], $factoryKey, $readyAt, $factory['yield_per_batch']]);

                    $pdo->commit();
                    logActivity($pdo, $user['id'],
                        "🔫 Kogelproductie gestart in {$factory['name']} ({$factory['yield_per_batch']} kogels)");
                    $success = "Productie gestart! Klaar over {$factory['process_time_minutes']} minuten.";
                    $user = currentUser($pdo);
                    $activeProduction = getActiveBulletProduction($pdo, $user['id']);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Productie starten mislukt.';
                }
            }
        }
    }
    // Ophalen (bestaande)
    elseif ($action === 'collect') {
        $prodId = (int)($_POST['prod_id'] ?? 0);
        $stmt = $pdo->prepare("
            SELECT ubp.*, bf.name AS factory_name
            FROM user_bullet_production ubp
            JOIN bullet_factories bf ON bf.`key` = ubp.factory_key
            WHERE ubp.id = ? AND ubp.user_id = ? AND ubp.collected = 0
            LIMIT 1
        ");
        $stmt->execute([$prodId, $user['id']]);
        $prod = $stmt->fetch();

        if (!$prod) {
            $error = 'Productie niet gevonden.';
        } elseif (strtotime($prod['ready_at']) > time()) {
            $error = 'Deze batch is nog niet klaar.';
        } else {
            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE user_bullet_production SET collected = 1 WHERE id = ?")
                    ->execute([$prodId]);
                addAmmo($pdo, $user['id'], 'pistool', (int)$prod['batch_amount']);

                logActivity($pdo, $user['id'],
                    "✅ {$prod['batch_amount']} kogels geproduceerd");
                $pdo->commit();

                $success = "Je hebt {$prod['batch_amount']} kogels geproduceerd!";
                $activeProduction = getActiveBulletProduction($pdo, $user['id']);
                $myAmmo = getUserAmmo($pdo, $user['id']);
                $totalAmmo = getTotalAmmo($myAmmo);
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Ophalen mislukt.';
            }
        }
    }
}

$pageTitle = 'Kogelfabriek — Vendetta';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Kogel<span>fabriek</span></h1>
    <p><?= $country['flag'] ?> <?= htmlspecialchars($country['name']) ?> — produceer kogels voor gevechten.</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- Stats -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon">💰</div>
        <div class="stat-value gold">€<?= number_format($user['money'], 0, ',', '.') ?></div>
        <div class="stat-label">Cash</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">🔫</div>
        <div class="stat-value"><?= number_format($totalAmmo, 0, ',', '.') ?></div>
        <div class="stat-label">Kogels in bezit</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">🏭</div>
        <div class="stat-value"><?= count($myFactories) ?></div>
        <div class="stat-label">Fabrieken hier</div>
    </div>
</div>

<!-- HANDMATIGE PRODUCTIE -->
<section class="section">
    <h2>🔫 Handmatige productie</h2>
    <p class="muted">
        Produceer kogels direct — €<?= BULLET_COST_PER_UNIT ?> per kogel.
        Je limiet reset elke dag om middernacht.
    </p>

    <div class="bullet-manual-card">
        <!-- Progress -->
        <div class="bmc-header">
            <div>
                <strong style="color:var(--gold);font-size:1.2rem;">
                    <?= number_format($bulletStatus['used'], 0, ',', '.') ?>
                    /
                    <?= number_format($bulletStatus['limit'], 0, ',', '.') ?>
                </strong>
                <small class="muted" style="display:block;">
                    gebruikt vandaag · nog
                    <strong style="color:#58e08c;"><?= number_format($bulletStatus['remaining'], 0, ',', '.') ?></strong>
                    beschikbaar
                </small>
            </div>
            <div class="bmc-level">
                Level <?= $userFamily ? max(1, (int)($userFamily['bullet_limit_level'] ?? 1)) : max(1, (int)$user['bullet_limit_level']) ?>
            </div>
        </div>

        <div class="bar" style="margin:12px 0 18px;">
            <div class="bar-fill xp" style="width:<?= $bulletStatus['percent'] ?>%"></div>
        </div>

        <!-- Productie form -->
        <?php if ($bulletStatus['remaining'] > 0): ?>
            <form method="POST" class="casino-form">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="produce_manual">

                <div class="bet-input">
                    <label>Aantal kogels (max <?= number_format($bulletStatus['remaining'], 0, ',', '.') ?>)</label>
                    <input type="number"
                           name="amount"
                           id="bullet-amount"
                           min="1"
                           max="<?= $bulletStatus['remaining'] ?>"
                           value="<?= min(1000, $bulletStatus['remaining']) ?>"
                           required>
                </div>

                <div class="price-preview">
                    <span>Kosten:</span>
                    <strong>€<span id="bullet-cost"><?= number_format(min(1000, $bulletStatus['remaining']) * BULLET_COST_PER_UNIT, 0, ',', '.') ?></span></strong>
                </div>

                <div class="quick-buy-row" style="margin-bottom:12px;">
                    <button type="button" class="quick-buy-btn" onclick="setBulletAmount(100)">
                        <strong>100</strong><small>€<?= number_format(100 * BULLET_COST_PER_UNIT, 0, ',', '.') ?></small>
                    </button>
                    <button type="button" class="quick-buy-btn" onclick="setBulletAmount(1000)">
                        <strong>1.000</strong><small>€<?= number_format(1000 * BULLET_COST_PER_UNIT, 0, ',', '.') ?></small>
                    </button>
                    <button type="button" class="quick-buy-btn" onclick="setBulletAmount(10000)">
                        <strong>10.000</strong><small>€<?= number_format(10000 * BULLET_COST_PER_UNIT, 0, ',', '.') ?></small>
                    </button>
                    <button type="button" class="quick-buy-btn" onclick="setBulletAmount(<?= $bulletStatus['remaining'] ?>)">
                        <strong>MAX</strong><small><?= number_format($bulletStatus['remaining'], 0, ',', '.') ?></small>
                    </button>
                </div>

                <button type="submit" class="btn btn-gold btn-large btn-full">
                    🔫 Produceer kogels
                </button>
            </form>
        <?php else: ?>
            <div class="alert alert-error" style="margin:0;">
                🚫 Je dagelijkse limiet is bereikt. Kom morgen terug!
            </div>
        <?php endif; ?>
    </div>

    <!-- Upgrade limiet -->
    <?php if ($userFamily && $nextUpgrade): ?>
        <div class="bullet-upgrade-card">
            <div class="buc-header">
                <div class="buc-icon">🎯</div>
                <div>
                    <h3>Limiet upgraden</h3>
                    <p class="muted">Gebruik de familiekas om de dagelijkse limiet voor alle leden te verhogen.</p>
                </div>
            </div>

            <div class="buc-info">
                <div class="buc-row">
                    <span>Huidig limiet</span>
                    <strong><?= number_format($nextUpgrade['current_limit'], 0, ',', '.') ?> kogels/dag</strong>
                </div>
                <div class="buc-row highlight">
                    <span>Nieuw limiet (Level <?= $nextUpgrade['level'] ?>)</span>
                    <strong style="color:#58e08c;"><?= number_format($nextUpgrade['new_limit'], 0, ',', '.') ?> kogels/dag</strong>
                </div>
                <div class="buc-row">
                    <span>Kosten uit familiekas</span>
                    <strong style="color:<?= (int)$userFamily['money'] >= $nextUpgrade['cost'] ? '#58e08c' : '#ff5c5c' ?>;">
                        €<?= number_format($nextUpgrade['cost'], 0, ',', '.') ?>
                    </strong>
                </div>
                <div class="buc-row">
                    <span>Familiekas</span>
                    <strong style="color:var(--gold);">€<?= number_format($userFamily['money'], 0, ',', '.') ?></strong>
                </div>
            </div>

            <?php if ($userFamily['member_rank'] === 'baas'): ?>
                <form method="POST">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="action" value="upgrade_limit">
                    <button type="submit" class="btn btn-gold btn-full"
                            <?= (int)$userFamily['money'] < $nextUpgrade['cost'] ? 'disabled' : '' ?>
                            onclick="return confirm('€<?= number_format($nextUpgrade['cost'], 0, ',', '.') ?> uit de familiekas gebruiken voor de upgrade?');">
                        <?php if ((int)$userFamily['money'] < $nextUpgrade['cost']): ?>
                            ❌ Niet genoeg familiegeld
                        <?php else: ?>
                            🎯 Upgrade naar Level <?= $nextUpgrade['level'] ?>
                        <?php endif; ?>
                    </button>
                </form>
            <?php else: ?>
                <p class="muted" style="text-align:center;margin-top:12px;">
                    Alleen de <strong>baas</strong> kan de limiet upgraden.
                </p>
            <?php endif; ?>
        </div>
    <?php elseif (!$userFamily): ?>
        <div class="bullet-upgrade-card" style="text-align:center;padding:24px;">
            <p class="muted">👥 Sluit je aan bij een familie om je kogel-limiet te kunnen upgraden.</p>
            <a href="families.php" class="btn btn-outline" style="margin-top:12px;">Bekijk families →</a>
        </div>
    <?php endif; ?>
</section>

<!-- Recente productie -->
<?php if (!empty($recentProduction)): ?>
<section class="section">
    <h2>📜 Recente productie</h2>
    <ul class="activity-list">
        <?php foreach ($recentProduction as $p): ?>
            <li>
                <span>
                    🔫 <strong style="color:var(--gold);">+<?= number_format((int)$p['amount'], 0, ',', '.') ?></strong> kogels
                    — <span style="color:#ff5c5c;">-€<?= number_format((int)$p['cost'], 0, ',', '.') ?></span>
                </span>
                <time><?= date('d M H:i', strtotime($p['created_at'])) ?></time>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<!-- Actieve fabriek-producties -->
<?php if (!empty($activeProduction)): ?>
<section class="section">
    <h2>🏭 Fabriek productie</h2>
    <div class="plant-grid">
        <?php foreach ($activeProduction as $prod):
            $ready = strtotime($prod['ready_at']) <= time();
        ?>
        <div class="plant-card <?= $ready ? 'ready' : '' ?>">
            <div class="plant-icon"><?= $ready ? '✅' : '🔧' ?></div>
            <h3><?= htmlspecialchars($prod['factory_name']) ?></h3>
            <p class="muted"><?= $ready ? 'Klaar om op te halen' : 'Klaar in: ' . timeUntil($prod['ready_at']) ?></p>
            <p><strong><?= number_format((int)$prod['batch_amount'], 0, ',', '.') ?> kogels</strong></p>
            <?php if ($ready): ?>
                <form method="POST">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="action" value="collect">
                    <input type="hidden" name="prod_id" value="<?= (int)$prod['id'] ?>">
                    <button type="submit" class="btn btn-gold btn-full">Ophalen</button>
                </form>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- Fabrieken kopen -->
<section class="section">
    <h2>🏭 Fabrieken kopen (passief)</h2>

    <?php if (count($myFactories) > 0): ?>
        <p class="muted">Je hebt al een fabriek in <?= htmlspecialchars($country['name']) ?>. Reizen naar een ander land voor meer.</p>
    <?php endif; ?>

    <div class="house-grid">
        <?php foreach ($allFactories as $f):
            $owned = false;
            foreach ($myFactories as $mf) {
                if ($mf['factory_key'] === $f['key']) { $owned = true; break; }
            }
            $hasAny = count($myFactories) > 0;
            $canAfford = $user['money'] >= $f['price'];
            $busy = false;
            foreach ($activeProduction as $p) {
                if ($p['factory_key'] === $f['key']) { $busy = true; break; }
            }
        ?>
        <div class="house-card <?= $owned ? 'owned' : '' ?>">
            <div class="house-head">
                <span class="house-icon">🔫</span>
                <h3><?= htmlspecialchars($f['name']) ?></h3>
            </div>
            <p><?= htmlspecialchars($f['description']) ?></p>

            <div class="house-stats">
                <div class="house-stat">
                    <span>📦</span>
                    <strong><?= number_format((int)$f['yield_per_batch'], 0, ',', '.') ?> kogels per batch</strong>
                </div>
                <div class="house-stat">
                    <span>⏱️</span>
                    <strong><?= (int)$f['process_time_minutes'] ?> min</strong>
                </div>
                <div class="house-stat">
                    <span>💵</span>
                    <strong>€<?= number_format($f['material_cost'], 0, ',', '.') ?> materiaal</strong>
                </div>
            </div>

            <div class="house-footer">
                <?php if ($owned): ?>
                    <span class="badge-equipped">In bezit</span>
                    <?php if ($busy): ?>
                        <button class="btn btn-outline" disabled>Bezig...</button>
                    <?php elseif ($user['money'] < $f['material_cost']): ?>
                        <button class="btn btn-gold" disabled>Te weinig geld</button>
                    <?php else: ?>
                        <form method="POST">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                            <input type="hidden" name="action" value="produce">
                            <input type="hidden" name="factory_key" value="<?= htmlspecialchars($f['key']) ?>">
                            <button type="submit" class="btn btn-gold">Productie starten</button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <strong class="price">€<?= number_format($f['price'], 0, ',', '.') ?></strong>
                    <form method="POST">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <input type="hidden" name="action" value="buy_factory">
                        <input type="hidden" name="factory_key" value="<?= htmlspecialchars($f['key']) ?>">
                        <button type="submit" class="btn btn-gold" <?= (!$canAfford || $hasAny) ? 'disabled' : '' ?>>
                            <?php if ($hasAny): ?>
                                Al een fabriek hier
                            <?php elseif (!$canAfford): ?>
                                Te duur
                            <?php else: ?>
                                Kopen
                            <?php endif; ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<script>
function setBulletAmount(n) {
    const input = document.getElementById('bullet-amount');
    if (!input) return;
    const max = parseInt(input.max) || 0;
    input.value = Math.min(n, max);
    updateBulletCost();
}

function updateBulletCost() {
    const input = document.getElementById('bullet-amount');
    const costEl = document.getElementById('bullet-cost');
    if (!input || !costEl) return;

    const amount = parseInt(input.value) || 0;
    costEl.textContent = (amount * <?= BULLET_COST_PER_UNIT ?>).toLocaleString('nl-NL');
}

document.getElementById('bullet-amount')?.addEventListener('input', updateBulletCost);
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
               