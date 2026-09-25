<?php
$adminPageTitle = 'Markt beheer';
require __DIR__ . '/includes/admin_header.php';

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf = $_POST['csrf'] ?? '';
    $listingId = (int)($_POST['listing_id'] ?? 0);

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } elseif ($action === 'delete_listing') {
        $stmt = $pdo->prepare("SELECT seller_id, asset_key FROM market_listings WHERE id = ?");
        $stmt->execute([$listingId]);
        $l = $stmt->fetch();
        if ($l) {
            // Geef terug aan verkoper
            $pdo->prepare("DELETE FROM market_listings WHERE id = ?")->execute([$listingId]);
            $pdo->prepare("INSERT INTO user_assets (user_id, asset_key) VALUES (?, ?)")
                ->execute([$l['seller_id'], $l['asset_key']]);
            adminLog($pdo, $admin['id'], 'delete_listing', 'listing', $listingId);
            $success = 'Listing verwijderd en asset teruggegeven.';
        }
    }
}

$listings = $pdo->query("
    SELECT ml.*, ma.name, ma.icon, u.username AS seller_name, c.flag, c.name AS country_name
    FROM market_listings ml
    JOIN market_assets ma ON ma.`key` = ml.asset_key
    JOIN users u ON u.id = ml.seller_id
    JOIN countries c ON c.`key` = ml.country_key
    WHERE ml.status = 'active'
    ORDER BY ml.id DESC
    LIMIT 100
")->fetchAll();

$sales = $pdo->query("
    SELECT ms.*, ma.name, ma.icon, us.username AS seller_name, ub.username AS buyer_name
    FROM market_sales ms
    JOIN market_assets ma ON ma.`key` = ms.asset_key
    JOIN users us ON us.id = ms.seller_id
    JOIN users ub ON ub.id = ms.buyer_id
    ORDER BY ms.id DESC LIMIT 20
")->fetchAll();
?>

<h1 class="admin-title">🌍 Markt beheer</h1>

<?php if ($error): ?><div class="admin-alert admin-alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="admin-alert admin-alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<section class="admin-section">
    <h2>🏪 Actieve listings (<?= count($listings) ?>)</h2>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Asset</th>
                    <th>Verkoper</th>
                    <th>Land</th>
                    <th>Prijs</th>
                    <th>Datum</th>
                    <th>Actie</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($listings as $l): ?>
                    <tr>
                        <td>#<?= (int)$l['id'] ?></td>
                        <td><?= $l['icon'] ?> <?= htmlspecialchars($l['name']) ?></td>
                        <td>
                            <a href="user_edit.php?id=<?= (int)$l['seller_id'] ?>">
                                <?= htmlspecialchars($l['seller_name']) ?>
                            </a>
                        </td>
                        <td><?= $l['flag'] ?> <?= htmlspecialchars($l['country_name']) ?></td>
                        <td class="num" style="color:#b9f2ff;"><?= number_format((int)$l['price_diamonds'], 0, ',', '.') ?> 💎</td>
                        <td><small><?= date('d M H:i', strtotime($l['listed_at'])) ?></small></td>
                        <td>
                            <form method="POST" style="display:inline;"
                                  onsubmit="return confirm('Verwijder deze listing?');">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete_listing">
                                <input type="hidden" name="listing_id" value="<?= (int)$l['id'] ?>">
                                <button type="submit" class="btn-icon">🗑️</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="admin-section">
    <h2>📜 Recente verkopen</h2>
    <ul class="admin-log-list">
        <?php foreach ($sales as $s): ?>
            <li>
                <?= $s['icon'] ?>
                <strong><?= htmlspecialchars($s['buyer_name']) ?></strong>
                kocht <strong><?= htmlspecialchars($s['name']) ?></strong>
                van <?= htmlspecialchars($s['seller_name']) ?>
                voor <strong style="color:#b9f2ff;"><?= number_format((int)$s['price_diamonds'], 0, ',', '.') ?> 💎</strong>
                <time><?= date('d M H:i', strtotime($s['sold_at'])) ?></time>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>