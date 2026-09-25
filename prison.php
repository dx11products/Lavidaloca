<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = currentUser($pdo);

// Als user niet in de gevangenis zit → terug naar dashboard
if (!isInPrison($user) && empty($user['prison_until'])) {
    redirect('dashboard.php');
}

$secondsLeft = prisonSecondsLeft($user);
$releaseCost = prisonReleaseCost($user);

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf   = $_POST['csrf'] ?? '';

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } elseif ($action === 'pay_fine') {
        if ($user['money'] < $releaseCost) {
            $error = 'Je hebt niet genoeg geld. Kosten: €' . number_format($releaseCost, 0, ',', '.');
        } else {
            $pdo->prepare("
                UPDATE users
                SET money = money - ?, in_prison = 0, prison_until = NULL,
                    prison_fine = 0, prison_reason = NULL
                WHERE id = ?
            ")->execute([$releaseCost, $user['id']]);

            logActivity($pdo, $user['id'], "🔓 Vrijgekocht voor €" . number_format($releaseCost, 0, ',', '.'));
            redirect('dashboard.php');
        }
    } elseif ($action === 'bribe') {
        $bribeCost = 5000;
        if ($user['money'] < $bribeCost) {
            $error = 'Smeergeld kost €' . number_format($bribeCost, 0, ',', '.') . '. Je hebt niet genoeg.';
        } elseif ((int)$user['corruption'] >= CORRUPTION_MAX) {
            $error = 'Je hebt maximale corruptie bereikt.';
        } else {
            $pdo->prepare("
                UPDATE users
                SET money = money - ?, corruption = corruption + 5,
                    in_prison = 0, prison_until = NULL, prison_fine = 0, prison_reason = NULL
                WHERE id = ?
            ")->execute([$bribeCost, $user['id']]);

            logActivity($pdo, $user['id'], "💼 Smeergeld betaald — corruptie +5");
            redirect('dashboard.php');
        }
    }
}

$user = currentUser($pdo);
$secondsLeft = prisonSecondsLeft($user);
$releaseCost = prisonReleaseCost($user);

$pageTitle = 'Gevangenis — Vendetta';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>De <span>Gevangenis</span></h1>
    <p><?= htmlspecialchars($user['prison_reason'] ?? 'Je bent opgepakt') ?></p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="prison-status">
    <div class="prison-icon">🚔</div>
    <h2>Je zit vast</h2>
    <p class="muted">Nog <strong style="color:var(--gold);"><?= floor($secondsLeft / 60) ?>m <?= $secondsLeft % 60 ?>s</strong> tot vrijlating.</p>
</div>

<section class="section">
    <h2>Je opties</h2>

    <div class="prison-options">
        <div class="prison-card">
            <div class="prison-card-icon">💸</div>
            <h3>Boete betalen</h3>
            <p class="muted">Betaal je boete + resterende tijd in één keer.</p>
            <div class="prison-card-cost">€<?= number_format($releaseCost, 0, ',', '.') ?></div>
            <form method="POST">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="pay_fine">
                <button type="submit" class="btn btn-gold btn-full"
                        <?= $user['money'] < $releaseCost ? 'disabled' : '' ?>>
                    <?= $user['money'] < $releaseCost ? 'Te weinig geld' : 'Betaal boete' ?>
                </button>
            </form>
        </div>

        <div class="prison-card bribe">
            <div class="prison-card-icon">💼</div>
            <h3>Smeergeld betalen</h3>
            <p class="muted">Koop een bewaker om — je komt direct vrij + corruptie +5.</p>
            <div class="prison-card-cost">€5.000</div>
            <form method="POST">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="bribe">
                <button type="submit" class="btn btn-outline btn-full"
                        <?= $user['money'] < 5000 || (int)$user['corruption'] >= CORRUPTION_MAX ? 'disabled' : '' ?>>
                    <?php if ((int)$user['corruption'] >= CORRUPTION_MAX): ?>
                        Max corruptie
                    <?php elseif ($user['money'] < 5000): ?>
                        Te weinig geld
                    <?php else: ?>
                        Betaal smeergeld
                    <?php endif; ?>
                </button>
            </form>
        </div>

        <div class="prison-card">
            <div class="prison-card-icon">⏳</div>
            <h3>Uitzitten</h3>
            <p class="muted">Wacht je tijd uit en kom gratis vrij.</p>
            <div class="prison-card-cost">Gratis</div>
            <a href="dashboard.php" class="btn btn-outline btn-full">Terug naar dashboard</a>
        </div>
    </div>
</section>

<section class="section">
    <h2>Corruptie status</h2>
    <div class="rank-progress">
        <div class="rank-row">
            <span>Huidige corruptie</span>
            <strong><?= (int)$user['corruption'] ?> / <?= CORRUPTION_MAX ?></strong>
        </div>
        <div class="bar">
            <div class="bar-fill xp" style="width:<?= min(100, (int)$user['corruption']) ?>%"></div>
        </div>
        <p class="muted" style="margin-top:10px;">
            Elke punt corruptie verlaagt je pakkans met <?= CORRUPTION_REDUCTION * 100 ?>%.
        </p>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>