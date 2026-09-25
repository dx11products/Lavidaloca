<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = currentUser($pdo);
$error = null;
$result = null;

// Equip / unequip
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $itemId = (int)($_POST['item_id'] ?? 0);
    $csrf   = $_POST['csrf'] ?? '';

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } else {
        $stmt = $pdo->prepare("
            SELECT ui.*, i.category, i.name
            FROM user_items ui
            JOIN items i ON i.`key` = ui.item_key
            WHERE ui.id = ? AND ui.user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$itemId, $user['id']]);
        $row = $stmt->fetch();

        if (!$row) {
            $error = 'Item niet gevonden.';
        } elseif ($action === 'equip') {
            // Per categorie: eerst alles unequippen, dan dit equippen
            // (je kunt maar 1 wapen, 1 armor, 1 vehicle tegelijk dragen)
            $pdo->prepare("
                UPDATE user_items ui
                JOIN items i ON i.`key` = ui.item_key
                SET ui.equipped = 0
                WHERE ui.user_id = ? AND i.category = ?
            ")->execute([$user['id'], $row['category']]);

            $pdo->prepare("UPDATE user_items SET equipped = 1 WHERE id = ?")
                ->execute([$itemId]);

            logActivity($pdo, $user['id'], "⚔️ {$row['name']} uitgerust");
            $result = "{$row['name']} is nu uitgerust.";
        } elseif ($action === 'unequip') {
            $pdo->prepare("UPDATE user_items SET equipped = 0 WHERE id = ?")
                ->execute([$itemId]);
            $result = "{$row['name']} weggeborgen.";
        }
    }
}

$items   = getUserItems($pdo, $user['id']);
$bonuses = getEquippedBonuses($pdo, $user['id']);

$pageTitle = 'Inventory — Vendetta';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Jouw <span>Inventory</span></h1>
    <p>Beheer je bezittingen en rust items uit voor gevechten.</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($result): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($result) ?></div>
<?php endif; ?>

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon">⚔️</div>
        <div class="stat-value">+<?= (int)$bonuses['attack'] ?></div>
        <div class="stat-label">Aanval bonus</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">🛡️</div>
        <div class="stat-value">+<?= (int)$bonuses['defense'] ?></div>
        <div class="stat-label">Verdediging bonus</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">📦</div>
        <div class="stat-value"><?= count($items) ?></div>
        <div class="stat-label">Items in bezit</div>
    </div>
</div>

<section class="section">
    <h2>Bezittingen</h2>

    <?php if (empty($items)): ?>
        <p class="muted">Je hebt nog niets. Bezoek de <a href="shop.php">shop</a> om te beginnen.</p>
    <?php else: ?>
        <div class="item-grid">
            <?php foreach ($items as $item): ?>
                <div class="item-card <?= $item['equipped'] ? 'equipped' : '' ?>">
                    <div class="item-head">
                        <h3><?= htmlspecialchars($item['name']) ?></h3>
                        <?php if ($item['equipped']): ?>
                            <span class="badge-equipped">Uitgerust</span>
                        <?php endif; ?>
                        <?php if ($item['quantity'] > 1): ?>
                            <span class="badge-qty">x<?= (int)$item['quantity'] ?></span>
                        <?php endif; ?>
                    </div>
                    <p><?= htmlspecialchars($item['description']) ?></p>

                    <?php if ($item['attack_bonus'] > 0 || $item['defense_bonus'] > 0): ?>
                        <div class="item-bonus">
                            <?php if ($item['attack_bonus'] > 0): ?>
                                <span>⚔️ +<?= $item['attack_bonus'] ?> aanval</span>
                            <?php endif; ?>
                            <?php if ($item['defense_bonus'] > 0): ?>
                                <span>🛡️ +<?= $item['defense_bonus'] ?> verdediging</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="item-footer">
                        <form method="POST" style="width:100%;">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                            <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                            <?php if ($item['equipped']): ?>
                                <input type="hidden" name="action" value="unequip">
                                <button type="submit" class="btn btn-outline btn-full">Wegbergen</button>
                            <?php else: ?>
                                <input type="hidden" name="action" value="equip">
                                <button type="submit" class="btn btn-gold btn-full">Uitrusten</button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>