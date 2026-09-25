<?php
$adminPageTitle = 'Economie';
require __DIR__ . '/includes/admin_header.php';

$stats = getAdminStats($pdo);

// Top economie
$topMoney = $pdo->query("SELECT id, username, money FROM users ORDER BY money DESC LIMIT 10")->fetchAll();
$topBank = $pdo->query("SELECT id, username, bank_money FROM users ORDER BY bank_money DESC LIMIT 10")->fetchAll();
$topBtc = $pdo->query("SELECT id, username, btc FROM users ORDER BY btc DESC LIMIT 10")->fetchAll();
$topClicks = $pdo->query("SELECT id, username, clicks FROM users ORDER BY clicks DESC LIMIT 10")->fetchAll();

// Recente aanpassingen
$stmt = $pdo->query("
    SELECT al.*, u.username AS admin_name, t.username AS target_name
    FROM admin_log al
    LEFT JOIN users u ON u.id = al.admin_id
    LEFT JOIN users t ON t.id = al.target_id AND al.target_type = 'user'
    WHERE al.action LIKE 'adjust_%' OR al.action LIKE 'mass_%'
    ORDER BY al.id DESC
    LIMIT 20
");
$recentAdjustments = $stmt->fetchAll();
?>

<h1 class="admin-title">💰 Economie overzicht</h1>

<div class="admin-stat-grid">
    <div class="admin-stat">
        <div class="as-icon">💵</div>
        <div class="as-value">€<?= number_format($stats['total_money'], 0, ',', '.') ?></div>
        <div class="as-label">Cash in omloop</div>
    </div>
    <div class="admin-stat">
        <div class="as-icon">🏦</div>
        <div class="as-value">€<?= number_format($stats['total_bank'], 0, ',', '.') ?></div>
        <div class="as-label">Op de bank</div>
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
</div>

<div class="admin-split">
    <section class="admin-section">
        <h2>💰 Top 10 Cash</h2>
        <ol class="top-list">
            <?php foreach ($topMoney as $u): ?>
                <li>
                    <a href="user_edit.php?id=<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['username']) ?></a>
                    <strong>€<?= number_format((int)$u['money'], 0, ',', '.') ?></strong>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>

    <section class="admin-section">
        <h2>🏦 Top 10 Bank</h2>
        <ol class="top-list">
            <?php foreach ($topBank as $u): ?>
                <li>
                    <a href="user_edit.php?id=<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['username']) ?></a>
                    <strong>€<?= number_format((int)$u['bank_money'], 0, ',', '.') ?></strong>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>

    <section class="admin-section">
        <h2>₿ Top 10 BTC</h2>
        <ol class="top-list">
            <?php foreach ($topBtc as $u): ?>
                <li>
                    <a href="user_edit.php?id=<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['username']) ?></a>
                    <strong style="color:#f7931a;"><?= formatBtc((float)$u['btc']) ?></strong>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>

    <section class="admin-section">
        <h2>🖱️ Top 10 Clicks</h2>
        <ol class="top-list">
            <?php foreach ($topClicks as $u): ?>
                <li>
                    <a href="user_edit.php?id=<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['username']) ?></a>
                    <strong style="color:#4a9dff;"><?= number_format((int)$u['clicks']) ?></strong>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>
</div>

<section class="admin-section">
    <h2>📜 Recente economie aanpassingen</h2>
    <?php if (empty($recentAdjustments)): ?>
        <p class="muted">Geen aanpassingen.</p>
    <?php else: ?>
        <ul class="admin-log-list">
            <?php foreach ($recentAdjustments as $log): ?>
                <li>
                    <strong><?= htmlspecialchars($log['admin_name'] ?? '?') ?></strong>
                    → <strong><?= htmlspecialchars($log['target_name'] ?? "user #{$log['target_id']}") ?></strong>
                    — <?= htmlspecialchars($log['action']) ?>
                    <?php if ($log['details']): ?> (<?= htmlspecialchars($log['details']) ?>)<?php endif; ?>
                    <time><?= date('d M H:i', strtotime($log['created_at'])) ?></time>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>