<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = currentUser($pdo);
$currentCountry = getCountry($pdo, $user['current_country']);
$allCountries = getCountries($pdo);

$error = null;
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetKey = $_POST['country'] ?? '';
    $csrf = $_POST['csrf'] ?? '';

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } elseif ($targetKey === $user['current_country']) {
        $error = 'Je bent al in dit land.';
    } else {
        $target = getCountry($pdo, $targetKey);
        if (!$target) {
            $error = 'Onbekend land.';
        } elseif ($user['money'] < $target['travel_cost']) {
            $error = 'Je hebt niet genoeg geld voor deze reis.';
        } else {
            $pdo->prepare("UPDATE users SET money = money - ?, current_country = ? WHERE id = ?")
                ->execute([$target['travel_cost'], $targetKey, $user['id']]);

            logActivity($pdo, $user['id'],
                "✈️ Gereisd naar {$target['flag']} {$target['name']} voor €" . number_format($target['travel_cost'], 0, ',', '.'));

            $result = "Je bent aangekomen in {$target['flag']} {$target['name']}!";
            $user = currentUser($pdo);
            $currentCountry = $target;
        }
    }
}

$pageTitle = 'Vliegveld — Vendetta';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Het <span>Vliegveld</span></h1>
    <p>Reis naar andere landen om drugs te kopen en verkopen. Prijzen verschillen per land.</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($result): ?>
    <div class="alert alert-success">✈️ <?= htmlspecialchars($result) ?></div>
<?php endif; ?>

<!-- Huidige locatie -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon"><?= $currentCountry['flag'] ?></div>
        <div class="stat-value"><?= htmlspecialchars($currentCountry['name']) ?></div>
        <div class="stat-label">Huidige locatie</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">💰</div>
        <div class="stat-value gold">€<?= number_format($user['money'], 0, ',', '.') ?></div>
        <div class="stat-label">Cash beschikbaar</div>
    </div>
</div>

<!-- Landen -->
<section class="section">
    <h2>Beschikbare bestemmingen</h2>

    <div class="country-grid">
        <?php foreach ($allCountries as $c):
            $isCurrent = $c['key'] === $user['current_country'];
            $canAfford = $user['money'] >= $c['travel_cost'];
            $hasHouse  = getUserHouse($pdo, $user['id'], $c['key']);
        ?>
        <div class="country-card <?= $isCurrent ? 'current' : '' ?>">
            <div class="country-flag"><?= $c['flag'] ?></div>
            <h3><?= htmlspecialchars($c['name']) ?></h3>
            <p class="muted"><?= htmlspecialchars($c['description']) ?></p>

            <?php if ($hasHouse): ?>
                <div class="country-tag">🏠 Je hebt hier een huis</div>
            <?php endif; ?>

            <div class="country-footer">
                <?php if ($isCurrent): ?>
                    <div class="country-price">Huidige locatie</div>
                    <button class="btn btn-outline" disabled>Hier ben je</button>
                <?php else: ?>
                    <div class="country-price">
                        €<?= number_format($c['travel_cost'], 0, ',', '.') ?>
                    </div>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <input type="hidden" name="country" value="<?= htmlspecialchars($c['key']) ?>">
                        <button type="submit" class="btn btn-gold" <?= !$canAfford ? 'disabled' : '' ?>>
                            <?= !$canAfford ? 'Te duur' : 'Reizen' ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Snelle acties in huidig land -->
<section class="section">
    <h2>In <?= htmlspecialchars($currentCountry['name']) ?></h2>
    <div class="action-grid">
        <a href="drugs.php" class="action-card">
            <div class="stat-icon">💊</div>
            <h3>Drugs markt</h3>
            <p>Koop en verkoop drugs. Prijzen verschillen per land.</p>
        </a>
        <a href="houses.php" class="action-card">
            <div class="stat-icon">🏠</div>
            <h3>Huizen</h3>
            <p>Koop een huis om wiet te kweken en te verkopen.</p>
        </a>
        <a href="grow.php" class="action-card">
            <div class="stat-icon">🌿</div>
            <h3>Wiet kweken</h3>
            <p>Beheer je planten en oogst je wiet.</p>
        </a>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>