<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = currentUser($pdo);
$myAmmo = getUserAmmo($pdo, $user['id']);
$totalAmmo = getTotalAmmo($myAmmo);

$pageTitle = 'Munitie — Vendetta';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Jouw <span>munitie</span></h1>
    <p>Kogels voor je wapens. Verbruikt bij elke aanval.</p>
</div>

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon">🔫</div>
        <div class="stat-value gold"><?= number_format($totalAmmo, 0, ',', '.') ?></div>
        <div class="stat-label">Totaal kogels</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">⚔️</div>
        <div class="stat-value"><?= AMMO_PER_ATTACK ?></div>
        <div class="stat-label">Kogels per aanval</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">💥</div>
        <div class="stat-value">+<?= AMMO_DAMAGE_BONUS ?></div>
        <div class="stat-label">Schade bonus</div>
    </div>
</div>

<section class="section">
    <h2>Voorraad</h2>
    <div class="info-list">
        <?php foreach (AMMO_TYPES as $key => $type): ?>
            <li>
                <span><?= $type['icon'] ?> <?= htmlspecialchars($type['name']) ?></span>
                <strong><?= number_format($myAmmo[$key] ?? 0, 0, ',', '.') ?></strong>
            </li>
        <?php endforeach; ?>
    </div>

    <?php if ($totalAmmo === 0): ?>
        <div class="alert alert-error" style="margin-top:16px;">
            Je hebt nog geen kogels. <a href="bullets.php">Open een kogelfabriek →</a>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>