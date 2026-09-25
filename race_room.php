<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = currentUser($pdo);
$raceId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT r.*, u.username AS host_name
    FROM races r
    JOIN users u ON u.id = r.host_id
    WHERE r.id = ? LIMIT 1
");
$stmt->execute([$raceId]);
$race = $stmt->fetch();

if (!$race) redirect('race.php');

$participants = getRaceParticipants($pdo, $raceId);
$isParticipant = isInRace($pdo, $user['id'], $raceId);

$error = null;
$result = null;

// === RACE STARTEN (host only) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'start') {
    $csrf = $_POST['csrf'] ?? '';
    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } elseif ((int)$race['host_id'] !== (int)$user['id']) {
        $error = 'Alleen de host kan de race starten.';
    } elseif (count($participants) < RACE_MIN_PLAYERS) {
        $error = 'Er zijn minimaal ' . RACE_MIN_PLAYERS . ' spelers nodig.';
    } elseif ($race['status'] !== 'waiting') {
        $error = 'Deze race is al gestart of afgelopen.';
    } else {
        // Haal alle auto's + upgrades op
        $upgrades = getAllUpgrades($pdo);
        $racers = [];

        foreach ($participants as $p) {
            // Haal volledige auto-info
            $stmt = $pdo->prepare("
                SELECT ug.*, ct.name AS car_name, ct.icon AS car_icon, ct.rarity,
                       cp.value AS current_value
                FROM user_garage ug
                JOIN car_types ct ON ct.`key` = ug.car_key
                LEFT JOIN car_prices cp ON cp.car_key = ug.car_key AND cp.country_key = ug.country_key
                WHERE ug.id = ?
            ");
            $stmt->execute([$p['garage_id']]);
            $car = $stmt->fetch();

            if (!$car) continue;

            $perf = calculateCarPerformance($car, $upgrades);

            $racers[] = [
                'user_id'   => $p['user_id'],
                'garage_id' => $p['garage_id'],
                'username'  => $p['username'],
                'car_name'  => $car['car_name'],
                'car_icon'  => $car['car_icon'],
                'total_perf' => $perf['total'],
            ];
        }

        // Simuleer
        $simulated = simulateRace($racers);

        // Verwerk resultaten
        $pdo->beginTransaction();
        try {
            // Pot
            $pot = (int)$race['prize_pool'];
            $rake = (int)floor($pot * RACE_RAKE_PERCENT / 100);
            $netPrize = $pot - $rake;

            // Prijzen: 1e = 70%, 2e = 30%, 3e = 0%
            $firstPrize  = (int)floor($netPrize * 0.70);
            $secondPrize = (int)floor($netPrize * 0.30);

            // Update race
            $pdo->prepare("
                UPDATE races
                SET status = 'finished', winner_id = ?, started_at = NOW(), finished_at = NOW()
                WHERE id = ?
            ")->execute([$simulated[0]['user_id'], $raceId]);

            // Verwerk elke deelnemer
            foreach ($simulated as $r) {
                $prize = 0;
                if ($r['position'] === 1) $prize = $firstPrize;
                elseif ($r['position'] === 2) $prize = $secondPrize;

                // Update race_participants
                $pdo->prepare("
                    UPDATE race_participants
                    SET finish_time_ms = ?, position = ?
                    WHERE race_id = ? AND user_id = ?
                ")->execute([$r['time_ms'], $r['position'], $raceId, $r['user_id']]);

                // Geef geld + XP
                if ($prize > 0) {
                    $pdo->prepare("UPDATE users SET money = money + ?, xp = xp + ? WHERE id = ?")
                        ->execute([$prize, RACE_XP_WIN, $r['user_id']]);
                } else {
                    $pdo->prepare("UPDATE users SET xp = xp + ? WHERE id = ?")
                        ->execute([RACE_XP_LOSS, $r['user_id']]);
                }

                // Update auto statistieken
                $pdo->prepare("
                    UPDATE user_garage
                    SET races_won = races_won + ?, races_lost = races_lost + ?
                    WHERE id = ?
                ")->execute([
                    $r['position'] === 1 ? 1 : 0,
                    $r['position'] === 1 ? 0 : 1,
                    $r['garage_id']
                ]);

                // Race history
                $pdo->prepare("
                    INSERT INTO race_history (race_id, user_id, car_name, position, prize, race_type)
                    VALUES (?, ?, ?, ?, ?, ?)
                ")->execute([$raceId, $r['user_id'], $r['car_name'], $r['position'], $prize, $race['race_type']]);

                // Notificatie
                notify($pdo, $r['user_id'],
                    "🏁 Race afgelopen — positie {$r['position']}, prijs: €" . number_format($prize, 0, ',', '.'),
                    '🏁');

                logActivity($pdo, $r['user_id'],
                    "🏁 Race: positie {$r['position']} in " . $r['car_name']);
            }

            $pdo->commit();

            $result = $simulated;
            $race['status'] = 'finished';
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Kon race niet starten.';
        }
    }
}

// Refresh
$stmt = $pdo->prepare("SELECT * FROM races WHERE id = ? LIMIT 1");
$stmt->execute([$raceId]);
$race = $stmt->fetch();

$participants = getRaceParticipants($pdo, $raceId);

$pageTitle = 'Race — Vendetta';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>🏁 <span>Race</span></h1>
    <p>
        <?php if ($race['race_type'] === 'sprint'): ?>Sprint
        <?php elseif ($race['race_type'] === 'circuit'): ?>Circuit
        <?php else: ?>Drag
        <?php endif; ?>
        — Host: <?= htmlspecialchars($race['host_name']) ?>
        — Status: <?= $race['status'] ?>
    </p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Pot info -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon">💰</div>
        <div class="stat-value gold">€<?= number_format($race['prize_pool'], 0, ',', '.') ?></div>
        <div class="stat-label">Totale pot</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">🏆</div>
        <div class="stat-value gold">€<?= number_format((int)floor($race['prize_pool'] * (100 - RACE_RAKE_PERCENT) / 100 * 0.70), 0, ',', '.') ?></div>
        <div class="stat-label">1e prijs (70%)</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">🥈</div>
        <div class="stat-value">€<?= number_format((int)floor($race['prize_pool'] * (100 - RACE_RAKE_PERCENT) / 100 * 0.30), 0, ',', '.') ?></div>
        <div class="stat-label">2e prijs (30%)</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">👥</div>
        <div class="stat-value"><?= count($participants) ?> / <?= $race['max_players'] ?></div>
        <div class="stat-label">Deelnemers</div>
    </div>
</div>

<!-- Resultaat -->
<?php if ($result): ?>
<section class="section">
    <h2>🏆 Eindstand</h2>
    <div class="race-results">
        <?php foreach ($result as $r): ?>
            <div class="race-result-row <?= $r['user_id'] === $user['id'] ? 'is-me' : '' ?>">
                <div class="race-pos">
                    <?php if ($r['position'] === 1): ?>🥇
                    <?php elseif ($r['position'] === 2): ?>🥈
                    <?php elseif ($r['position'] === 3): ?>🥉
                    <?php else: ?>#<?= $r['position'] ?>
                    <?php endif; ?>
                </div>
                <div class="race-user">
                    <strong><?= htmlspecialchars($r['username']) ?></strong>
                    <small><?= $r['car_icon'] ?> <?= htmlspecialchars($r['car_name']) ?></small>
                </div>
                <div class="race-time">
                    <span><?= number_format($r['time_ms'] / 1000, 2) ?>s</span>
                    <small>prestatie <?= $r['performance'] ?></small>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- Deelnemers -->
<section class="section">
    <h2>Deelnemers (<?= count($participants) ?>)</h2>
    <div class="race-results">
        <?php foreach ($participants as $p): ?>
            <div class="race-result-row <?= (int)$p['user_id'] === (int)$user['id'] ? 'is-me' : '' ?>">
                <div class="race-pos">
                    <?= $p['car_icon'] ?>
                </div>
                <div class="race-user">
                    <strong><?= htmlspecialchars($p['username']) ?></strong>
                    <small><?= htmlspecialchars($p['car_name']) ?></small>
                </div>
                <?php if ($p['position']): ?>
                    <div class="race-time">
                        <span>Positie <?= (int)$p['position'] ?></span>
                    </div>
                <?php else: ?>
                    <div class="race-time">
                        <span class="muted">Wacht...</span>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Start knop -->
<?php if ($race['status'] === 'waiting' && (int)$race['host_id'] === (int)$user['id']): ?>
<section class="section">
    <form method="POST">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="start">
        <button type="submit" class="btn btn-gold btn-large btn-full"
                <?= count($participants) < RACE_MIN_PLAYERS ? 'disabled' : '' ?>>
            <?php if (count($participants) < RACE_MIN_PLAYERS): ?>
                Wacht op spelers (<?= count($participants) ?>/<?= RACE_MIN_PLAYERS ?>)
            <?php else: ?>
                🏁 START DE RACE
            <?php endif; ?>
        </button>
    </form>
</section>
<?php endif; ?>

<?php if ($race['status'] === 'waiting' && (int)$race['host_id'] !== (int)$user['id']): ?>
<section class="section">
    <div class="alert alert-success">
        Wachten tot de host de race start...
    </div>
</section>
<?php endif; ?>

<section class="section">
    <a href="race.php" class="btn btn-outline btn-full">← Terug naar races</a>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>