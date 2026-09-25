<?php
$adminPageTitle = 'Casino beheer';
require __DIR__ . '/includes/admin_header.php';

// Totale omzet per game
$perGame = $pdo->query("
    SELECT game_key, COUNT(*) AS plays,
           SUM(bet) AS total_bets,
           SUM(CASE WHEN result = 'win' THEN payout ELSE 0 END) AS total_payouts,
           SUM(CASE WHEN result = 'loss' THEN bet ELSE 0 END) AS total_losses
    FROM casino_game_logs
    GROUP BY game_key
")->fetchAll();

// Top casino-eigenaars
$topOwners = $pdo->query("
    SELECT ce.owner_id, u.username, SUM(ce.net_profit) AS profit, COUNT(DISTINCT ce.game_key) AS games
    FROM casino_earnings ce
    JOIN users u ON u.id = ce.owner_id
    GROUP BY ce.owner_id, u.username
    ORDER BY profit DESC
    LIMIT 10
")->fetchAll();

// Recente spellen
$recent = $pdo->query("
    SELECT cgl.*, u.username, o.username AS owner_name
    FROM casino_game_logs cgl
    JOIN users u ON u.id = cgl.user_id
    LEFT JOIN users o ON o.id = cgl.owner_id
    ORDER BY cgl.id DESC
    LIMIT 25
")->fetchAll();
?>

<h1 class="admin-title">🃏 Casino beheer</h1>

<section class="admin-section">
    <h2>📊 Statistieken per spel</h2>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Spel</th>
                    <th>Rondes</th>
                    <th>Inzet totaal</th>
                    <th>Uitbetalingen</th>
                    <th>Verdiensten systeem</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($perGame as $g): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($g['game_key']) ?></strong></td>
                        <td class="num"><?= number_format((int)$g['plays']) ?></td>
                        <td class="num">€<?= number_format((int)$g['total_bets'], 0, ',', '.') ?></td>
                        <td class="num" style="color:#ff5c5c;">€<?= number_format((int)$g['total_payouts'], 0, ',', '.') ?></td>
                        <td class="num" style="color:#58e08c;">€<?= number_format((int)$g['total_losses'] - (int)$g['total_payouts'], 0, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="admin-section">
    <h2>👑 Top casino-eigenaars</h2>
    <ol class="top-list">
        <?php foreach ($topOwners as $o): ?>
            <li>
                <a href="user_edit.php?id=<?= (int)$o['owner_id'] ?>"><?= htmlspecialchars($o['username']) ?></a>
                <span style="color:#a08d75;"><?= (int)$o['games'] ?> casino's</span>
                <strong style="color:#58e08c;">€<?= number_format((int)$o['profit'], 0, ',', '.') ?></strong>
            </li>
        <?php endforeach; ?>
    </ol>
</section>

<section class="admin-section">
    <h2>📜 Recente casino activiteit</h2>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Speler</th>
                    <th>Spel</th>
                    <th>Inzet</th>
                    <th>Resultaat</th>
                    <th>Eigenaar</th>
                    <th>Eigenaar kreeg</th>
                    <th>Tijd</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['username']) ?></td>
                        <td><?= htmlspecialchars($r['game_key']) ?></td>
                        <td class="num">€<?= number_format((int)$r['bet'], 0, ',', '.') ?></td>
                        <td>
                            <span style="color:<?= $r['result'] === 'win' ? '#58e08c' : ($r['result'] === 'loss' ? '#ff5c5c' : '#a08d75') ?>;">
                                <?= strtoupper($r['result']) ?>
                            </span>
                        </td>
                        <td><?= $r['owner_name'] ? htmlspecialchars($r['owner_name']) : '—' ?></td>
                        <td class="num" style="color:#c9a44c;">€<?= number_format((int)$r['owner_share'], 0, ',', '.') ?></td>
                        <td><small><?= date('d M H:i', strtotime($r['created_at'])) ?></small></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>