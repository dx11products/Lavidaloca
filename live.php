<?php
$adminPageTitle = 'Live activiteit';
require __DIR__ . '/includes/admin_header.php';

// Laatste 50 activiteiten
$activities = $pdo->query("
    SELECT al.*, u.username
    FROM activity_log al
    JOIN users u ON u.id = al.user_id
    ORDER BY al.id DESC LIMIT 50
")->fetchAll();

// Online users (laatste 5 min)
$online = $pdo->query("
    SELECT COUNT(*) FROM users WHERE last_login > NOW() - INTERVAL 5 MINUTE
")->fetchColumn();

// Vandaag actief
$todayActive = $pdo->query("
    SELECT COUNT(*) FROM users WHERE last_login > NOW() - INTERVAL 24 HOUR
")->fetchColumn();
?>

<h1 class="admin-title">🔴 Live activiteit</h1>

<div class="admin-stat-grid">
    <div class="admin-stat">
        <div class="as-icon">🟢</div>
        <div class="as-value"><?= (int)$online ?></div>
        <div class="as-label">Online (5 min)</div>
    </div>
    <div class="admin-stat">
        <div class="as-icon">📊</div>
        <div class="as-value"><?= (int)$todayActive ?></div>
        <div class="as-label">Actief (24u)</div>
    </div>
</div>

<section class="admin-section">
    <h2>📜 Live feed</h2>
    <p class="muted">Ververst automatisch elke 10 seconden.</p>
    <ul class="admin-log-list" id="live-feed">
        <?php foreach ($activities as $a): ?>
            <li>
                <strong><?= htmlspecialchars($a['username']) ?></strong>
                — <?= htmlspecialchars($a['message']) ?>
                <time><?= date('H:i:s', strtotime($a['created_at'])) ?></time>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<script>
setTimeout(() => location.reload(), 10000);
</script>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>