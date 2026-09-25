<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

// Alleen user 1 mag dit zien
if ((int)$user['id'] !== 1) redirect('dashboard.php');

$stmt = $pdo->query("SELECT * FROM cron_log ORDER BY id DESC LIMIT 50");
$logs = $stmt->fetchAll();

// Actieve events
$stmt = $pdo->query("
    SELECT * FROM world_events
    WHERE is_active = 1 AND expires_at > NOW()
    ORDER BY id DESC
");
$activeEvents = $stmt->fetchAll();

// Huidig seizoen
$stmt = $pdo->query("SELECT * FROM season_status WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
$season = $stmt->fetch();

$pageTitle = 'Cron log — Vendetta';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Cron <span>log</span></h1>
    <p>Laatste 50 cron-activiteiten en actieve events.</p>
</div>

<!-- Huidig seizoen -->
<?php if ($season): ?>
<section class="section">
    <h2>🍂 Huidig seizoen</h2>
    <div class="alert alert-success">
        <strong><?= htmlspecialchars($season['season_name']) ?></strong>
        — Effect: <strong><?= htmlspecialchars($season['effect_key']) ?> +<?= (int)$season['effect_value'] ?>%</strong>
        <br><small class="muted">Eindigt op <?= date('d M Y H:i', strtotime($season['ends_at'])) ?></small>
    </div>
</section>
<?php endif; ?>

<!-- Actieve events -->
<?php if (!empty($activeEvents)): ?>
<section class="section">
    <h2>🌟 Actieve wereld-events</h2>
    <?php foreach ($activeEvents as $ev): ?>
        <div class="alert alert-success" style="margin-bottom:10px;">
            <?= $ev['icon'] ?> <strong><?= htmlspecialchars($ev['title']) ?></strong>
            — <?= htmlspecialchars($ev['description']) ?>
            <br><small class="muted">Verloopt: <?= date('d M H:i', strtotime($ev['expires_at'])) ?></small>
        </div>
    <?php endforeach; ?>
</section>
<?php endif; ?>

<!-- Recente runs -->
<section class="section">
    <h2>Recente runs</h2>
    <ul class="activity-list">
        <?php foreach ($logs as $l): ?>
            <li>
                <span>
                    <strong><?= htmlspecialchars($l['job_name']) ?></strong>
                    — <?= htmlspecialchars($l['message']) ?>
                    <?php if ($l['affected'] > 0): ?>
                        <small class="muted">(<?= (int)$l['affected'] ?>)</small>
                    <?php endif; ?>
                </span>
                <time><?= date('d M H:i:s', strtotime($l['ran_at'])) ?></time>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>