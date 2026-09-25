<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = currentUser($pdo);
$country = getCountry($pdo, $user['current_country']);

if (function_exists('isInPrison') && isInPrison($user)) {
    redirect('prison.php');
}

$myCars = getUserGarage($pdo, $user['id'], $user['current_country']);
$openRaces = getOpenRaces($pdo, $user['current_country']);
$history = getUserRaceHistory($pdo, $user['id'], 5);

$error = null;
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf = $_POST['csrf'] ?? '';

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } elseif (isInHospital($user)) {
        $error = 'Je ligt in het ziekenhuis.';
    } elseif ($user['energy'] < RACE_ENERGY_COST) {
        $error = 'Niet genoeg energie.';
    }
    // === RACE AANMAKEN ===
    elseif ($action === 'create') {
        $garageId  = (int)($_POST['garage_id'] ?? 0);
        $entryFee  = (int)($_POST['entry_fee'] ?? 0);
        $raceType  = $_POST['race_type'] ?? 'sprint';

        if ($entryFee < RACE_MIN_ENTRY || $entryFee > RACE_MAX_ENTRY) {
            $error = 'Inleg moet tussen €' . number_format(RACE_MIN_ENTRY, 0, ',', '.') . ' en €' . number_format(RACE_MAX_ENTRY, 0, ',', '.') . ' zijn.';
        } elseif ($user['money'] < $entryFee) {
            $error = 'Je hebt niet genoeg geld.';
        } else {
            // Check of garage_id van user is
            $stmt = $pdo->prepare("SELECT id FROM user_garage WHERE id = ? AND user_id = ? AND country_key = ? LIMIT 1");
            $stmt->execute([$garageId, $user['id'], $user['current_country']]);
            if (!$stmt->fetch()) {
                $error = 'Deze auto is niet in jouw garage in dit land.';
            } else {
                $pdo->beginTransaction();
                try {
                    // Aftrekken kosten
                    $pdo->prepare("UPDATE users SET money = money - ?, energy = ?, energy_updated = NOW() WHERE id = ?")
                        ->execute([$entryFee, $user['energy'] - RACE_ENERGY_COST, $user['id']]);

                    // Race aanmaken
                    $pdo->prepare("
                        INSERT INTO races (host_id, country_key, race_type, entry_fee, prize_pool, max_players)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ")->execute([$user['id'], $user['current_country'], $raceType, $entryFee, $entryFee, RACE_MAX_PLAYERS]);

                    $raceId = $pdo->lastInsertId();

                    // Voeg host toe als deelnemer
                    $pdo->prepare("
                        INSERT INTO race_participants (race_id, user_id, garage_id)
                        VALUES (?, ?, ?)
                    ")->execute([$raceId, $user['id'], $garageId]);

                    logActivity($pdo, $user['id'], "🏁 Race aangemaakt — inleg €" . number_format($entryFee, 0, ',', '.'));
                    $pdo->commit();

                    redirect("race_room.php?id=$raceId");
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Kon race niet aanmaken.';
                }
            }
        }
    }
    // === RACE JOINEN ===
    elseif ($action === 'join') {
        $raceId = (int)($_POST['race_id'] ?? 0);
        $garageId = (int)($_POST['garage_id'] ?? 0);

        $stmt = $pdo->prepare("SELECT * FROM races WHERE id = ? AND status = 'waiting' LIMIT 1");
        $stmt->execute([$raceId]);
        $race = $stmt->fetch();

        if (!$race) {
            $error = 'Race niet gevonden.';
        } elseif ($race['country_key'] !== $user['current_country']) {
            $error = 'Je moet in hetzelfde land zijn als de race.';
        } elseif ($user['money'] < $race['entry_fee']) {
            $error = 'Je hebt niet genoeg geld voor de inleg.';
        } elseif (isInRace($pdo, $user['id'], $raceId)) {
            $error = 'Je bent al in deze race.';
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM race_participants WHERE race_id = ?");
            $stmt->execute([$raceId]);
            if ((int)$stmt->fetchColumn() >= (int)$race['max_players']) {
                $error = 'Deze race zit vol.';
            } else {
                // Check auto
                $stmt = $pdo->prepare("SELECT id FROM user_garage WHERE id = ? AND user_id = ? AND country_key = ? LIMIT 1");
                $stmt->execute([$garageId, $user['id'], $user['current_country']]);
                if (!$stmt->fetch()) {
                    $error = 'Selecteer een geldige auto.';
                } else {
                    $pdo->beginTransaction();
                    try {
                        $pdo->prepare("UPDATE users SET money = money - ?, energy = ?, energy_updated = NOW() WHERE id = ?")
                            ->execute([$race['entry_fee'], $user['energy'] - RACE_ENERGY_COST, $user['id']]);
                        $pdo->prepare("UPDATE races SET prize_pool = prize_pool + ? WHERE id = ?")
                            ->execute([$race['entry_fee'], $raceId]);
                        $pdo->prepare("INSERT INTO race_participants (race_id, user_id, garage_id) VALUES (?, ?, ?)")
                            ->execute([$raceId, $user['id'], $garageId]);

                        logActivity($pdo, $user['id'], "🏁 Race gejoined — inleg €" . number_format($race['entry_fee'], 0, ',', '.'));
                        $pdo->commit();

                        redirect("race_room.php?id=$raceId");
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $error = 'Kon niet joinen.';
                    }
                }
            }
        }
    }
}

$pageTitle = 'Street racing — Vendetta';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Street <span>racing</span></h1>
    <p><?= $country['flag'] ?> <?= htmlspecialchars($country['name']) ?> — race voor geld en eer.</p>
</div>

<?php if (empty($myCars)): ?>
    <div class="alert alert-error">
        🚗 Je hebt geen autos in <?= htmlspecialchars($country['name']) ?>.
        <a href="cars.php">Steel eerst een auto →</a>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Stats -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon">💰</div>
        <div class="stat-value gold">€<?= number_format($user['money'], 0, ',', '.') ?></div>
        <div class="stat-label">Cash</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">⚡</div>
        <div class="stat-value"><?= (int)$user['energy'] ?></div>
        <div class="stat-label">Energie (<?= RACE_ENERGY_COST ?> per race)</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">🚗</div>
        <div class="stat-value"><?= count($myCars) ?></div>
        <div class="stat-label">Autos hier</div>
    </div>
</div>

<!-- Nieuwe race starten -->
<?php if (!empty($myCars)): ?>
<section class="section">
    <h2>🏁 Nieuwe race starten</h2>
    <div class="casino-card">
        <form method="POST" class="casino-form">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="create">

            <div class="bet-input">
                <label>Kies je auto</label>
                <select name="garage_id" required>
                    <?php foreach ($myCars as $c):
                        $perf = calculateCarPerformance($c, getAllUpgrades($pdo));
                    ?>
                        <option value="<?= (int)$c['id'] ?>">
                            <?= $c['icon'] ?> <?= htmlspecialchars($c['name']) ?>
                            — prestatie <?= $perf['total'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="bet-input">
                <label>Type race</label>
                <select name="race_type" required>
                    <option value="sprint">🏁 Sprint — kort en snel</option>
                    <option value="circuit">🔁 Circuit — bochtig parcours</option>
                    <option value="drag">💨 Drag — 1 rechte lijn</option>
                </select>
            </div>

            <div class="bet-input">
                <label>Inleg (€<?= number_format(RACE_MIN_ENTRY, 0, ',', '.') ?> – €<?= number_format(RACE_MAX_ENTRY, 0, ',', '.') ?>)</label>
                <input type="number" name="entry_fee" min="<?= RACE_MIN_ENTRY ?>" max="<?= RACE_MAX_ENTRY ?>"
                       value="<?= RACE_MIN_ENTRY ?>" step="100" required>
            </div>

            <button type="submit" class="btn btn-gold btn-full"
                    <?= $user['energy'] < RACE_ENERGY_COST ? 'disabled' : '' ?>>
                🏁 Start race (<?= RACE_ENERGY_COST ?>⚡)
            </button>
        </form>
    </div>
</section>
<?php endif; ?>

<!-- Open races -->
<section class="section">
    <h2>🏁 Open races (<?= count($openRaces) ?>)</h2>

    <?php if (empty($openRaces)): ?>
        <p class="muted">Geen open races in dit land. Start je eigen race hierboven!</p>
    <?php else: ?>
        <div class="race-list">
            <?php foreach ($openRaces as $r): ?>
                <div class="race-row">
                    <div class="race-info">
                        <h3>
                            <?php if ($r['race_type'] === 'sprint'): ?>🏁 Sprint
                            <?php elseif ($r['race_type'] === 'circuit'): ?>🔁 Circuit
                            <?php else: ?>💨 Drag
                            <?php endif; ?>
                        </h3>
                        <p class="muted">Host: <?= htmlspecialchars($r['host_name']) ?></p>
                        <div class="race-meta">
                            <span>💰 Inleg: €<?= number_format($r['entry_fee'], 0, ',', '.') ?></span>
                            <span>🏆 Pot: €<?= number_format($r['prize_pool'], 0, ',', '.') ?></span>
                            <span>👥 <?= $r['player_count'] ?> / <?= $r['max_players'] ?> spelers</span>
                        </div>
                    </div>
                    <div class="race-action">
                        <?php if ((int)$r['host_id'] === (int)$user['id']): ?>
                            <a href="race_room.php?id=<?= (int)$r['id'] ?>" class="btn btn-outline">Naar race</a>
                        <?php elseif (empty($myCars)): ?>
                            <button class="btn btn-gold" disabled>Geen auto</button>
                        <?php else: ?>
                            <form method="POST" style="display:flex;gap:8px;">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="action" value="join">
                                <input type="hidden" name="race_id" value="<?= (int)$r['id'] ?>">
                                <select name="garage_id" required style="padding:8px;background:var(--bg-0);border:1px solid var(--border);color:var(--text);border-radius:var(--radius-sm);">
                                    <?php foreach ($myCars as $c): ?>
                                        <option value="<?= (int)$c['id'] ?>">
                                            <?= $c['icon'] ?> <?= htmlspecialchars($c['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-gold"
                                        <?= $user['energy'] < RACE_ENERGY_COST ? 'disabled' : '' ?>>
                                    Join
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- Geschiedenis -->
<?php if (!empty($history)): ?>
<section class="section">
    <h2>📜 Laatste races</h2>
    <ul class="activity-list">
        <?php foreach ($history as $h): ?>
            <li>
                <span>
                    <?php if ($h['position'] === 1): ?>🥇
                    <?php elseif ($h['position'] === 2): ?>🥈
                    <?php elseif ($h['position'] === 3): ?>🥉
                    <?php else: ?>🏁
                    <?php endif; ?>
                    <strong><?= htmlspecialchars($h['car_name']) ?></strong>
                    — positie <?= (int)$h['position'] ?>
                    <?php if ($h['prize'] > 0): ?>
                        — <span style="color:#58e08c;">+€<?= number_format($h['prize'], 0, ',', '.') ?></span>
                    <?php endif; ?>
                </span>
                <time><?= date('d M H:i', strtotime($h['created_at'])) ?></time>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>