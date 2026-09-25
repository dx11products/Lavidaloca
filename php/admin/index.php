<?php
$adminPageTitle = 'Dashboard';
require __DIR__ . '/includes/admin_header.php';

$stats = getAdminStats($pdo);
$recentLogs = getAdminLogs($pdo, 10);

// Recente users
$stmt = $pdo->query("SELECT id, username, created_at, money FROM users ORDER BY id DESC LIMIT 10");
$newUsers = $stmt->fetchAll();
?>

<h1 class="admin-title">📊 Dashboard</h1>

<div class="admin-stat-grid">
    <div class="admin-stat">
        <div class="as-icon">👥</div>
        <div class="as-value"><?= number_format($stats['total_users']) ?></div>
        <div class="as-label">Totaal spelers</div>
        <div class="as-sub">+<?= $stats['new_today'] ?> vandaag</div>
    </div>
    <div class="admin-stat">
        <div class="as-icon">🟢</div>
        <div class="as-value"><?= number_format($stats['active_24h']) ?></div>
        <div class="as-label">Actief (24u)</div>
        <div class="as-sub"><?= $stats['active_7d'] ?> in 7 dagen</div>
    </div>
    <div class="admin-stat">
        <div class="as-icon">💰</div>
        <div class="as-value">€<?= number_format($stats['total_money'], 0, ',', '.') ?></div>
        <div class="as-label">Cash in omloop</div>
        <div class="as-sub">€<?= number_format($stats['total_bank'], 0, ',', '.') ?> op bank</div>
    </div>
    <div class="admin-stat">
        <div class="as-icon">₿</div>
        <div class="as-value" style="color:#f7931a;"><?= formatBtc($stats['total_btc']) ?></div>
        <div class="as-label">BTC in omloop</div>
    </div>
    <div class="admin-stat">
        <div class="as-icon">🖱️</div>
        <div class="as-value" style="color:#4a9dff;"><?= number_format($stats['total_clicks']) ?></div>
        <div class="as-label">Clicks in omloop</div>
    </div>
    <div class="admin-stat">
        <div class="as-icon">🚫</div>
        <div class="as-value" style="color:#ff5c5c;"><?= $stats['banned'] ?></div>
        <div class="as-label">Gebandeerd</div>
    </div>
    <div class="admin-stat">
        <div class="as-icon">🎯</div>
        <div class="as-value"><?= number_format($stats['crimes_today']) ?></div>
        <div class="as-label">Crimes vandaag</div>
    </div>
    <div class="admin-stat">
        <div class="as-icon">⚔️</div>
        <div class="as-value"><?= number_format($stats['attacks_today']) ?></div>
        <div class="as-label">Aanvallen vandaag</div>
    </div>
    <div class="admin-stat">
        <div class="as-icon">🏴</div>
        <div class="as-value"><?= number_format($stats['heists_today']) ?></div>
        <div class="as-label">Heists vandaag</div>
    </div>
    <div class="admin-stat">
        <div class="as-icon">🎁</div>
        <div class="as-value"><?= number_format($stats['lootboxes_today']) ?></div>
        <div class="as-label">Lootboxes vandaag</div>
    </div>
    <div class="admin-stat">
        <div class="as-icon">💬</div>
        <div class="as-value"><?= number_format($stats['forum_topics']) ?></div>
        <div class="as-label">Forum topics</div>
        <div class="as-sub"><?= number_format($stats['forum_replies']) ?> replies</div>
    </div>
    <div class="admin-stat">
        <div class="as-icon">💌</div>
        <div class="as-value"><?= number_format($stats['pm_messages']) ?></div>
        <div class="as-label">Privéberichten</div>
    </div>
</div>

<div class="admin-split">
    <section class="admin-section">
        <h2>📋 Recente admin acties</h2>
        <?php if (empty($recentLogs)): ?>
            <p class="muted">Nog geen admin acties.</p>
        <?php else: ?>
            <ul class="admin-log-list">
                <?php foreach ($recentLogs as $log): ?>
                    <li>
                        <strong><?= htmlspecialchars($log['admin_name'] ?? 'Onbekend') ?></strong>
                        — <?= htmlspecialchars($log['action']) ?>
                        <?php if ($log['target_type']): ?>
                            (<?= htmlspecialchars($log['target_type']) ?> #<?= (int)$log['target_id'] ?>)
                        <?php endif; ?>
                        <time><?= date('d M H:i', strtotime($log['created_at'])) ?></time>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="admin-section">
        <h2>🆕 Nieuwste spelers</h2>
        <ul class="admin-log-list">
            <?php foreach ($newUsers as $u): ?>
                <li>
                    <a href="user_edit.php?id=<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['username']) ?></a>
                    — €<?= number_format((int)$u['money'], 0, ',', '.') ?>
                    <time><?= date('d M H:i', strtotime($u['created_at'])) ?></time>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
</div>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>