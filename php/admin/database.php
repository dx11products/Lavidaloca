<?php
$adminPageTitle = 'Database tools';
require __DIR__ . '/includes/admin_header.php';

$error = null;
$result = null;

// Toegestane tabellen
$allowedTables = [
    'users', 'activity_log', 'admin_log', 'casino_game_logs', 'crime_logs',
    'diamond_transactions', 'forum_topics', 'forum_replies', 'jackpot_pools',
    'jackpot_wins', 'market_assets', 'market_listings', 'market_sales',
    'pm_messages', 'user_assets', 'user_likes',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    $table = $_POST['table'] ?? '';
    $query = trim($_POST['query'] ?? '');

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } elseif ($table && in_array($table, $allowedTables, true)) {
        try {
            $stmt = $pdo->query("SELECT * FROM `{$table}` ORDER BY id DESC LIMIT 50");
            $result = [
                'type'  => 'table',
                'table' => $table,
                'rows'  => $stmt->fetchAll(),
            ];
        } catch (Exception $e) {
            $error = 'Query mislukt: ' . $e->getMessage();
        }
    } elseif ($query !== '') {
        // Alleen SELECT queries toestaan
        if (stripos($query, 'SELECT') !== 0) {
            $error = 'Alleen SELECT queries zijn toegestaan.';
        } else {
            try {
                $stmt = $pdo->query($query);
                $result = [
                    'type'  => 'query',
                    'rows'  => $stmt->fetchAll(),
                ];
            } catch (Exception $e) {
                $error = 'Query mislukt: ' . $e->getMessage();
            }
        }
    } else {
        $error = 'Geen tabel of query opgegeven.';
    }
}
?>

<h1 class="admin-title">🗄️ Database tools</h1>

<?php if ($error): ?><div class="admin-alert admin-alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="admin-alert admin-alert-warning">
    ⚠️ <strong>Voorzichtig!</strong> Deze tool is read-only — je kunt alleen SELECT queries uitvoeren. Voor wijzigingen gebruik je phpMyAdmin.
</div>

<div class="admin-split">
    <section class="admin-section">
        <h2>📋 Bekijk een tabel</h2>
        <form method="POST" class="admin-form">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <select name="table" required>
                <option value="">-- Kies een tabel --</option>
                <?php foreach ($allowedTables as $t): ?>
                    <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-gold btn-full" style="margin-top:10px;">📋 Bekijk (laatste 50)</button>
        </form>
    </section>

    <section class="admin-section">
        <h2>🔍 Custom query</h2>
        <form method="POST" class="admin-form">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <textarea name="query" rows="4" placeholder="SELECT * FROM users LIMIT 10"
                      style="width:100%;padding:12px;background:var(--bg-0);border:1px solid var(--border);border-radius:var(--radius-sm);color:var(--text);font-family:monospace;font-size:13px;resize:vertical;"></textarea>
            <button type="submit" class="btn btn-gold btn-full" style="margin-top:10px;">▶️ Uitvoeren</button>
        </form>
    </section>
</div>

<?php if ($result): ?>
<section class="admin-section">
    <h2>📊 Resultaat (<?= count($result['rows']) ?> rijen)</h2>

    <?php if (empty($result['rows'])): ?>
        <p class="muted">Geen resultaten.</p>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <?php foreach (array_keys($result['rows'][0]) as $col): ?>
                            <th><?= htmlspecialchars($col) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($result['rows'] as $row): ?>
                        <tr>
                            <?php foreach ($row as $val): ?>
                                <td><?= htmlspecialchars(mb_substr((string)$val, 0, 80)) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>