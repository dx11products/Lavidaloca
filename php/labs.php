<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = currentUser($pdo);
$country = getCountry($pdo, $user['current_country']);
$allLabs = getAllLabs($pdo);
$myLabs = getUserLabs($pdo, $user['id'], $user['current_country']);
$activeProduction = getActiveProduction($pdo, $user['id']);

$error = null;
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf = $_POST['csrf'] ?? '';

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    }
    // === LAB KOPEN ===
    elseif ($action === 'buy_lab') {
        $labKey = $_POST['lab_key'] ?? '';
        $lab = getLab($pdo, $labKey);

        if (!$lab) {
            $error = 'Onbekend lab.';
        } elseif (hasLabInCountry($pdo, $user['id'], $user['current_country'], $labKey)) {
            $error = 'Je hebt dit lab al in dit land.';
        } elseif ($user['money'] < $lab['price']) {
            $error = 'Je hebt niet genoeg geld.';
        } else {
            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE users SET money = money - ? WHERE id = ?")
                    ->execute([$lab['price'], $user['id']]);
                $pdo->prepare("INSERT INTO user_labs (user_id, country_key, lab_key) VALUES (?, ?, ?)")
                    ->execute([$user['id'], $user['current_country'], $labKey]);

                logActivity($pdo, $user['id'],
                    "🧪 {$lab['name']} gekocht in {$country['name']} voor €" . number_format($lab['price'], 0, ',', '.'));
                $pdo->commit();

                $result = "Je hebt een {$lab['name']} geopend!";
                $user = currentUser($pdo);
                $myLabs = getUserLabs($pdo, $user['id'], $user['current_country']);
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Aankoop mislukt.';
            }
        }
    }
    // === PRODUCTIE STARTEN ===
    elseif ($action === 'produce') {
        $labKey = $_POST['lab_key'] ?? '';
        $lab = getLab($pdo, $labKey);

        if (!$lab || !hasLabInCountry($pdo, $user['id'], $user['current_country'], $labKey)) {
            $error = 'Je hebt dit lab niet in dit land.';
        } else {
            // Check of er al een actieve batch is
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM user_lab_production
                WHERE user_id = ? AND lab_key = ? AND collected = 0
            ");
            $stmt->execute([$user['id'], $labKey]);
            if ((int)$stmt->fetchColumn() > 0) {
                $error = 'Dit lab is al bezig met een productie.';
            } else {
                $readyAt = date('Y-m-d H:i:s', time() + ($lab['process_time_minutes'] * 60));
                $pdo->prepare("
                    INSERT INTO user_lab_production (user_id, lab_key, ready_at, batch_amount)
                    VALUES (?, ?, ?, ?)
                ")->execute([$user['id'], $labKey, $readyAt, $lab['batch_size']]);

                logActivity($pdo, $user['id'],
                    "🧪 Productie gestart in {$lab['name']} ({$lab['batch_size']}x {$lab['lab_type']})");
                $result = "Productie gestart! Klaar over {$lab['process_time_minutes']} minuten.";
                $activeProduction = getActiveProduction($pdo, $user['id']);
            }
        }
    }
    // === OOGSTEN ===
    elseif ($action === 'collect') {
        $prodId = (int)($_POST['prod_id'] ?? 0);
        $stmt = $pdo->prepare("
            SELECT ulp.*, l.lab_type, l.name AS lab_name
            FROM user_lab_production ulp
            JOIN labs l ON l.`key` = ulp.lab_key
            WHERE ulp.id = ? AND ulp.user_id = ? AND ulp.collected = 0
            LIMIT 1
        ");
        $stmt->execute([$prodId, $user['id']]);
        $prod = $stmt->fetch();

        if (!$prod) {
            $error = 'Productie niet gevonden.';
        } elseif (strtotime($prod['ready_at']) > time()) {
            $error = 'Deze batch is nog niet klaar.';
        } else {
            $drugKey = $prod['lab_type']; // cocaine / meth / xtc
            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE user_lab_production SET collected = 1 WHERE id = ?")
                    ->execute([$prodId]);
                addDrug($pdo, $user['id'], $drugKey, (int)$prod['batch_amount']);

                logActivity($pdo, $user['id'],
                    "✅ {$prod['batch_amount']}x {$drugKey} geproduceerd in {$prod['lab_name']}");
                $pdo->commit();

                $result = "Je hebt {$prod['batch_amount']}x " . ucfirst($drugKey) . " geproduceerd!";
                $activeProduction = getActiveProduction($pdo, $user['id']);
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Oogsten mislukt.';
            }
        }
    }
}

$pageTitle = 'Labs — Vendetta';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Drugs <span>laboratoria</span></h1>
    <p><?= $country['flag'] ?> <?= htmlspecialchars($country['name']) ?> — produceer cocaïne, meth en XTC in bulk.</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($result): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($result) ?></div>
<?php endif; ?>

<!-- Stats -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon">💰</div>
        <div class="stat-value gold">€<?= number_format($user['money'], 0, ',', '.') ?></div>
        <div class="stat-label">Cash</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">🧪</div>
        <div class="stat-value"><?= count($myLabs) ?></div>
        <div class="stat-label">Labs in <?= htmlspecialchars($country['name']) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">⚙️</div>
        <div class="stat-value"><?= count($activeProduction) ?></div>
        <div class="stat-label">Actieve batches</div>
    </div>
</div>

<!-- Actieve producties -->
<?php if (!empty($activeProduction)): ?>
<section class="section">
    <h2>Actieve producties</h2>
    <div class="plant-grid">
        <?php foreach ($activeProduction as $prod):
            $ready = strtotime($prod['ready_at']) <= time();
        ?>
        <div class="plant-card <?= $ready ? 'ready' : '' ?>">
            <div class="plant-icon"><?= $ready ? '✅' : '⚗️' ?></div>
            <h3><?= htmlspecialchars($prod['lab_name']) ?></h3>
            <p class="muted">
                <?= $ready ? 'Klaar om te oogsten' : 'Klaar in: ' . timeUntil($prod['ready_at']) ?>
            </p>
            <p><strong><?= (int)$prod['batch_amount'] ?>x <?= htmlspecialchars($prod['lab_type']) ?></strong></p>
            <?php if ($ready): ?>
                <form method="POST">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="action" value="collect">
                    <input type="hidden" name="prod_id" value="<?= (int)$prod['id'] ?>">
                    <button type="submit" class="btn btn-gold btn-full">Oogsten</button>
                </form>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- Beschikbare labs -->
<section class="section">
    <h2>Beschikbare labs</h2>

    <div class="house-grid">
        <?php foreach ($allLabs as $lab):
            $owned = hasLabInCountry($pdo, $user['id'], $user['current_country'], $lab['key']);
            $canAfford = $user['money'] >= $lab['price'];
            $busy = false;
            foreach ($activeProduction as $p) {
                if ($p['lab_key'] === $lab['key']) { $busy = true; break; }
            }
        ?>
        <div class="house-card <?= $owned ? 'owned' : '' ?>">
            <div class="house-head">
                <span class="house-icon">
                    <?= $lab['lab_type'] === 'cocaine' ? '❄️' : ($lab['lab_type'] === 'meth' ? '💎' : '💊') ?>
                </span>
                <h3><?= htmlspecialchars($lab['name']) ?></h3>
            </div>
            <p><?= htmlspecialchars($lab['description']) ?></p>

            <div class="house-stats">
                <div class="house-stat">
                    <span>📦</span>
                    <strong><?= (int)$lab['batch_size'] ?>x <?= htmlspecialchars($lab['lab_type']) ?> per batch</strong>
                </div>
                <div class="house-stat">
                    <span>⏱️</span>
                    <strong><?= (int)$lab['process_time_minutes'] ?> min per batch</strong>
                </div>
            </div>

            <div class="house-footer">
                <?php if ($owned): ?>
                    <span class="badge-equipped">In bezit</span>
                    <?php if ($busy): ?>
                        <button class="btn btn-outline" disabled>Bezig...</button>
                    <?php else: ?>
                        <form method="POST">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                            <input type="hidden" name="action" value="produce">
                            <input type="hidden" name="lab_key" value="<?= htmlspecialchars($lab['key']) ?>">
                            <button type="submit" class="btn btn-gold">Productie starten</button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <strong class="price">€<?= number_format($lab['price'], 0, ',', '.') ?></strong>
                    <form method="POST">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <input type="hidden" name="action" value="buy_lab">
                        <input type="hidden" name="lab_key" value="<?= htmlspecialchars($lab['key']) ?>">
                        <button type="submit" class="btn btn-gold" <?= !$canAfford ? 'disabled' : '' ?>>
                            <?= !$canAfford ? 'Te duur' : 'Kopen' ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>