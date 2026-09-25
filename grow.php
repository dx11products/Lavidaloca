<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = currentUser($pdo);
$country = getCountry($pdo, $user['current_country']);
$myHouse = getUserHouse($pdo, $user['id'], $user['current_country']);
$activePlants = getActivePlants($pdo, $user['id'], $user['current_country']);

$error = null;
$result = null;

// Plant kost
$costPerPlant = $myHouse
    ? getPlantCost($pdo, $user['current_country'], (int)$myHouse['yield_per_slot'])
    : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf   = $_POST['csrf'] ?? '';

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } elseif (!$myHouse) {
        $error = 'Je hebt geen huis in dit land.';
    }
    // === PLANT BULK ===
    elseif ($action === 'plant') {
        $count = (int)($_POST['count'] ?? 0);

        $slotsTotal = getEffectiveGrowSlots($pdo, $user['id'], $myHouse);
        $slotsUsed = countActivePlants($pdo, $user['id'], $user['current_country']);
        $slotsFree = max(0, $slotsTotal - $slotsUsed);

        if ($count < 1) {
            $error = 'Voer minimaal 1 plant in.';
        } elseif ($count > $slotsFree) {
            $error = "Je hebt maar {$slotsFree} vrije plekken.";
        } else {
            $res = plantBulk($pdo, $user['id'], $user['current_country'], $count,
                            (int)$myHouse['grow_time_minutes'], (int)$myHouse['yield_per_slot']);
            if (isset($res['error'])) {
                $error = $res['error'];
            } else {
                $result = "🌱 {$res['count']} planten geplant voor €" .
                          number_format($res['cost'], 0, ',', '.') .
                          " (€" . number_format($res['cost_per'], 0, ',', '.') . " per plant)";
                $activePlants = getActivePlants($pdo, $user['id'], $user['current_country']);
                $user = currentUser($pdo);
                logActivity($pdo, $user['id'],
                    "🌱 {$res['count']} planten geplant (€" . number_format($res['cost'], 0, ',', '.') . ")");
            }
        }
    }
    // === WATER ALLE ===
    elseif ($action === 'water_all') {
        $res = waterAllPlants($pdo, $user['id'], $user['current_country']);
        if ($res['watered'] > 0) {
            $result = "💧 {$res['watered']} planten water gegeven!" .
                      ($res['skipped'] > 0 ? " ({$res['skipped']} wachten nog)" : "");
            logActivity($pdo, $user['id'], "💧 {$res['watered']} planten water gegeven");
        } else {
            $error = 'Geen planten die nu water kunnen krijgen.';
        }
        $activePlants = getActivePlants($pdo, $user['id'], $user['current_country']);
    }
    // === WATER 1 ===
    elseif ($action === 'water_one') {
        $plantId = (int)($_POST['plant_id'] ?? 0);
        $res = waterPlant($pdo, $user['id'], $plantId);
        if (isset($res['error'])) {
            $error = $res['error'];
        } else {
            $result = "💧 Water gegeven ({$res['new_count']}/4)!" .
                      ($res['is_ready'] ? " Plant is KLAAR om te oogsten!" : "");
            $activePlants = getActivePlants($pdo, $user['id'], $user['current_country']);
        }
    }
    // === OOGST ALLES ===
    elseif ($action === 'harvest_all') {
        $res = harvestAllPlants($pdo, $user['id'], $user['current_country']);
        if (isset($res['error'])) {
            $error = $res['error'];
        } elseif ($res['count'] > 0) {
            $result = "🌿 " . number_format($res['total_yield'], 0, ',', '.') .
                      " wiet geoogst uit {$res['count']} planten!";
            logActivity($pdo, $user['id'],
                "🌿 {$res['total_yield']} wiet geoogst (bulk)");
        } else {
            $error = 'Geen planten klaar om te oogsten.';
        }
        $activePlants = getActivePlants($pdo, $user['id'], $user['current_country']);
    }
    // === OOGST 1 ===
    elseif ($action === 'harvest_one') {
        $plantId = (int)($_POST['plant_id'] ?? 0);
        $res = harvestPlant($pdo, $user['id'], $plantId);
        if (isset($res['error'])) {
            $error = $res['error'];
        } else {
            $result = "🌿 Je hebt {$res['yield']} wiet geoogst!";
            $activePlants = getActivePlants($pdo, $user['id'], $user['current_country']);
        }
    }
    // === CLEAR DEAD ===
    elseif ($action === 'clear_dead') {
        $res = clearDeadPlants($pdo, $user['id'], $user['current_country']);
        if ($res['cleared'] > 0) {
            $result = "🗑️ {$res['cleared']} verdroogde planten opgeruimd.";
        } else {
            $error = 'Geen verdroogde planten.';
        }
        $activePlants = getActivePlants($pdo, $user['id'], $user['current_country']);
    }
}

$slotsTotal = $myHouse ? getEffectiveGrowSlots($pdo, $user['id'], $myHouse) : 0;
$slotsUsed = countActivePlants($pdo, $user['id'], $user['current_country']);
$slotsFree = max(0, $slotsTotal - $slotsUsed);

// Plant stats
$readyPlants = 0;
$deadPlants = 0;
$needWater = 0;
foreach ($activePlants as $p) {
    $s = getPlantStatus($p);
    if ($s['is_ready'])   $readyPlants++;
    if ($s['is_dead'])    $deadPlants++;
    if ($s['can_water'])  $needWater++;
}

$pageTitle = 'Wiet kweken — Vendetta';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Wiet <span>kweken</span></h1>
    <p><?= $country['flag'] ?> <?= htmlspecialchars($country['name']) ?> — geef je planten 4x water in 1 uur.</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($result): ?>
    <div class="alert alert-success"><?= htmlspecialchars($result) ?></div>
<?php endif; ?>

<?php if (!$myHouse): ?>
    <div class="alert alert-error">
        Je hebt nog geen huis in <?= htmlspecialchars($country['name']) ?>.
        <a href="houses.php">Koop hier een huis →</a>
    </div>
<?php else: ?>

    <!-- Info banner -->
    <div class="tip-card" style="border-left-color:#58e08c;margin-bottom:20px;">
        <p>💧 <strong>Water systeem:</strong> Elke plant moet <strong>4x water</strong> krijgen
        binnen <strong>1 uur</strong>. Zonder water verdroogt de plant en ben je je geld kwijt!</p>
    </div>

    <!-- Stats -->
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon">💰</div>
            <div class="stat-value gold">€<?= number_format($user['money'], 0, ',', '.') ?></div>
            <div class="stat-label">Cash</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🌿</div>
            <div class="stat-value"><?= number_format($slotsUsed, 0, ',', '.') ?> / <?= number_format($slotsTotal, 0, ',', '.') ?></div>
            <div class="stat-label">Plantplekken</div>
            <div class="bar">
                <div class="bar-fill energy"
                     style="width:<?= $slotsTotal > 0 ? min(100, round(($slotsUsed / $slotsTotal) * 100)) : 0 ?>%"></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">💧</div>
            <div class="stat-value" style="color:#58e08c;"><?= $needWater ?></div>
            <div class="stat-label">Water nodig</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🌾</div>
            <div class="stat-value" style="color:#ffb040;"><?= $readyPlants ?></div>
            <div class="stat-label">Klaar om te oogsten</div>
        </div>
    </div>

    <!-- Snelle acties -->
    <section class="section">
        <h2>⚡ Snelle acties</h2>
        <div class="action-grid">
            <?php if ($needWater > 0): ?>
                <form method="POST" style="display:block;">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="action" value="water_all">
                    <button type="submit" class="action-card" style="width:100%;cursor:pointer;text-align:left;border:1px solid #58e08c;background:var(--bg-2);">
                        <div class="stat-icon">💧</div>
                        <h3>Water alle planten</h3>
                        <p><?= $needWater ?> planten kunnen nu water krijgen</p>
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($readyPlants > 0): ?>
                <form method="POST" style="display:block;">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="action" value="harvest_all">
                    <button type="submit" class="action-card" style="width:100%;cursor:pointer;text-align:left;border:1px solid var(--gold);background:var(--bg-2);">
                        <div class="stat-icon">🌾</div>
                        <h3>Oogst alles klaar</h3>
                        <p><?= $readyPlants ?> planten wachten op oogst</p>
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($deadPlants > 0): ?>
                <form method="POST" style="display:block;" onsubmit="return confirm('Alle verdroogde planten opruimen?');">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="action" value="clear_dead">
                    <button type="submit" class="action-card" style="width:100%;cursor:pointer;text-align:left;border:1px solid #ff5c5c;background:var(--bg-2);">
                        <div class="stat-icon">🗑️</div>
                        <h3>Ruim dode planten op</h3>
                        <p><?= $deadPlants ?> verdroogde planten</p>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </section>

    <!-- Nieuwe planten -->
    <?php if ($slotsFree > 0): ?>
        <section class="section">
            <h2>🌱 Nieuwe planten</h2>
            <div class="casino-card">
                <p class="muted">
                    Kost: <strong style="color:var(--gold);">€<?= number_format($costPerPlant, 0, ',', '.') ?></strong> per plant.
                    Je hebt <strong style="color:var(--gold);"><?= number_format($slotsFree, 0, ',', '.') ?></strong> vrije plekken.
                </p>

                <form method="POST" class="casino-form">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="action" value="plant">

                    <div class="bet-input">
                        <label>Aantal planten (max <?= number_format($slotsFree, 0, ',', '.') ?>)</label>
                        <input type="number" name="count" id="plant-count"
                               min="1" max="<?= $slotsFree ?>"
                               value="<?= min(10, $slotsFree) ?>" required>
                    </div>

                    <div class="price-preview">
                        <span>Totale kost:</span>
                        <strong>€<span id="plant-cost"><?= number_format(min(10, $slotsFree) * $costPerPlant, 0, ',', '.') ?></span></strong>
                    </div>

                    <div class="quick-buy-row">
                        <button type="button" class="quick-buy-btn" onclick="setPlantCount(10)">
                            <strong>10</strong>
                        </button>
                        <button type="button" class="quick-buy-btn" onclick="setPlantCount(100)">
                            <strong>100</strong>
                        </button>
                        <button type="button" class="quick-buy-btn" onclick="setPlantCount(1000)">
                            <strong>1.000</strong>
                        </button>
                        <button type="button" class="quick-buy-btn" onclick="setPlantCount(<?= min($slotsFree, 1000000) ?>)">
                            <strong>MAX</strong>
                            <small><?= number_format($slotsFree, 0, ',', '.') ?></small>
                        </button>
                    </div>

                    <button type="submit" class="btn btn-gold btn-large btn-full">
                        🌱 Plant nu
                    </button>
                </form>
            </div>
        </section>
    <?php endif; ?>

    <!-- Planten lijst -->
    <section class="section">
        <h2>Jouw planten (<?= count($activePlants) ?>)</h2>

        <?php if (empty($activePlants)): ?>
            <p class="muted">Nog geen planten. Plant er hierboven!</p>
        <?php else: ?>
            <div class="plant-grid">
                <?php foreach ($activePlants as $plant):
                    $s = getPlantStatus($plant);
                    $cssClass = $s['is_ready'] ? 'ready' : ($s['is_dead'] ? 'dead' : '');
                ?>
                <div class="plant-card <?= $cssClass ?>">
                    <div class="plant-icon">
                        <?= $s['is_dead'] ? '🥀' : ($s['is_ready'] ? '🌾' : '🌱') ?>
                    </div>

                    <h3>
                        <?php if ($s['is_dead']): ?>
                            Verdroogd
                        <?php elseif ($s['is_ready']): ?>
                            Klaar om te oogsten
                        <?php else: ?>
                            Groeit
                        <?php endif; ?>
                    </h3>

                    <!-- Water indicator -->
                    <div class="water-indicator">
                        <?php for ($i = 1; $i <= 4; $i++): ?>
                            <span class="water-drop <?= $i <= $s['water_count'] ? 'filled' : '' ?>">💧</span>
                        <?php endfor; ?>
                    </div>

                    <p class="muted">
                        Water: <strong><?= $s['water_count'] ?>/4</strong>
                    </p>

                    <?php if ($s['is_dead']): ?>
                        <button class="btn btn-outline btn-full" disabled>Verdroogd</button>

                    <?php elseif ($s['is_ready']): ?>
                        <div class="muted" style="font-size:.85rem;margin-bottom:8px;">
                            Opbrengst: <?= number_format((int)$plant['yield_amount'], 0, ',', '.') ?> wiet
                        </div>
                        <form method="POST">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                            <input type="hidden" name="action" value="harvest_one">
                            <input type="hidden" name="plant_id" value="<?= (int)$plant['id'] ?>">
                            <button type="submit" class="btn btn-gold btn-full">🌾 Oogsten</button>
                        </form>

                    <?php elseif ($s['can_water']): ?>
                        <form method="POST">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                            <input type="hidden" name="action" value="water_one">
                            <input type="hidden" name="plant_id" value="<?= (int)$plant['id'] ?>">
                            <button type="submit" class="btn btn-gold btn-full">💧 Water geven</button>
                        </form>

                    <?php else: ?>
                        <div class="muted" style="font-size:.8rem;">
                            Volgende water in <strong><?= $s['can_water_in'] ?>s</strong>
                        </div>
                    <?php endif; ?>

                    <?php if (!$s['is_dead'] && !$s['is_ready']): ?>
                        <div class="muted" style="font-size:.72rem;margin-top:8px;color:#ffb040;">
                            ⏱️ Verdroogt over <?= floor($s['seconds_left'] / 60) ?>m <?= $s['seconds_left'] % 60 ?>s
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <script>
    const PLANT_COST = <?= $costPerPlant ?>;

    function setPlantCount(n) {
        const input = document.getElementById('plant-count');
        const max = parseInt(input.max) || 0;
        input.value = Math.min(n, max);
        updatePlantCost();
    }

    function updatePlantCost() {
        const input = document.getElementById('plant-count');
        const el = document.getElementById('plant-cost');
        if (!input || !el) return;

        const count = parseInt(input.value) || 0;
        el.textContent = (count * PLANT_COST).toLocaleString('nl-NL');
    }

    document.getElementById('plant-count')?.addEventListener('input', updatePlantCost);
    </script>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>