<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = currentUser($pdo);
$inHospital = isInHospital($user);
$secondsLeft = hospitalSecondsLeft($user);

$HEAL_COST_PER_HP = 15;   // Prijs per hersteld HP
$healCost = ((int)$user['max_health'] - (int)$user['health']) * $HEAL_COST_PER_HP;

$result = null;
$error  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf   = $_POST['csrf'] ?? '';

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } elseif ($action === 'heal') {
        $missing = (int)$user['max_health'] - (int)$user['health'];
        if ($missing <= 0) {
            $error = 'Je bent al volledig hersteld.';
        } elseif ($user['money'] < $healCost) {
            $error = 'Je hebt niet genoeg geld. Kosten: €' . number_format($healCost, 0, ',', '.');
        } else {
            $pdo->prepare("
                UPDATE users
                SET money = money - ?, health = max_health
                WHERE id = ?
            ")->execute([$healCost, $user['id']]);

            logActivity($pdo, $user['id'], "🏥 Volledig hersteld voor €" . number_format($healCost, 0, ',', '.'));
            $result = 'Je bent volledig hersteld!';
            $user = currentUser($pdo);
            $healCost = 0;
        }
    } elseif ($action === 'discharge') {
        // Vervroegd ontslag — kost geld
        if (!$inHospital) {
            $error = 'Je ligt niet in het ziekenhuis.';
        } else {
            $dischargeCost = 500 + ($secondsLeft * 5);
            if ($user['money'] < $dischargeCost) {
                $error = 'Je hebt niet genoeg geld. Kosten: €' . number_format($dischargeCost, 0, ',', '.');
            } else {
                $pdo->prepare("
                    UPDATE users
                    SET money = money - ?, hospital_until = NULL
                    WHERE id = ?
                ")->execute([$dischargeCost, $user['id']]);

                logActivity($pdo, $user['id'], "🚪 Vervroegd ontslag uit ziekenhuis voor €" . number_format($dischargeCost, 0, ',', '.'));
                $result = 'Je bent ontslagen uit het ziekenhuis!';
                $user = currentUser($pdo);
                $inHospital = false;
                $secondsLeft = 0;
            }
        }
    }

    // Herbereken kosten
    $missing = (int)$user['max_health'] - (int)$user['health'];
    $healCost = $missing * $HEAL_COST_PER_HP;
}

$pageTitle = 'Ziekenhuis — Vendetta';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Het <span>Ziekenhuis</span></h1>
    <p>Herstel je wonden zodat je weer ten strijde kunt trekken.</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($result): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($result) ?></div>
<?php endif; ?>

<?php if ($inHospital): ?>
    <div class="alert alert-error">
        🏥 Je ligt nog <strong><?= floor($secondsLeft / 60) ?> min <?= $secondsLeft % 60 ?> sec</strong> in het ziekenhuis.
    </div>
<?php endif; ?>

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon">❤️</div>
        <div class="stat-value"><?= (int)$user['health'] ?> / <?= (int)$user['max_health'] ?></div>
        <div class="stat-label">Gezondheid</div>
        <div class="bar">
            <div class="bar-fill health"
                 style="width:<?= round(($user['health'] / max(1, $user['max_health'])) * 100) ?>%"></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">💰</div>
        <div class="stat-value gold">€<?= number_format($user['money'], 0, ',', '.') ?></div>
        <div class="stat-label">Cash</div>
    </div>
</div>

<section class="section">
    <h2>Behandeling</h2>

    <?php if ((int)$user['health'] >= (int)$user['max_health']): ?>
        <div class="item-card" style="text-align:center;">
            <div class="stat-icon">✅</div>
            <h3 style="margin-bottom:8px;">Je bent in topconditie</h3>
            <p>Geen behandeling nodig. Ga op pad en verdien geld!</p>
            <a href="attack.php" class="btn btn-gold" style="margin-top:14px;">Ga aanvallen</a>
        </div>
    <?php else: ?>
        <div class="item-card">
            <h3>Volledige behandeling</h3>
            <p>Herstel je volledige gezondheid in één keer.</p>
            <div class="item-footer">
                <strong class="price">€<?= number_format($healCost, 0, ',', '.') ?></strong>
                <form method="POST">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="action" value="heal">
                    <button type="submit" class="btn btn-gold" <?= $user['money'] < $healCost ? 'disabled' : '' ?>>
                        <?= $user['money'] < $healCost ? 'Te duur' : 'Herstellen' ?>
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($inHospital): ?>
        <div class="item-card" style="margin-top:16px;">
            <h3>🚪 Vervroegd ontslag</h3>
            <p>Koop je vrijheid en verlaat het ziekenhuis onmiddellijk.</p>
            <?php $dischargeCost = 500 + ($secondsLeft * 5); ?>
            <div class="item-footer">
                <strong class="price">€<?= number_format($dischargeCost, 0, ',', '.') ?></strong>
                <form method="POST">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="action" value="discharge">
                    <button type="submit" class="btn btn-outline" <?= $user['money'] < $dischargeCost ? 'disabled' : '' ?>>
                        Ontslag kopen
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>