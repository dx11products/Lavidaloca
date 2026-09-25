<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user     = currentUser($pdo);
$rankData = getRankData((int)$user['xp'], $RANKS);
$result   = null;
$error    = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemKey = $_POST['item_key'] ?? '';
    $csrf    = $_POST['csrf'] ?? '';

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM items WHERE `key` = ? LIMIT 1");
        $stmt->execute([$itemKey]);
        $item = $stmt->fetch();

        if (!$item) {
            $error = 'Onbekend item.';
        } elseif ($rankData['level'] < $item['min_rank']) {
            $error = 'Je rank is te laag voor dit item.';
        } elseif ($user['money'] < $item['price']) {
            $error = 'Je hebt niet genoeg geld.';
        } else {
            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE users SET money = money - ? WHERE id = ?")
                    ->execute([$item['price'], $user['id']]);

                // Bestaat al?
                $stmt = $pdo->prepare("SELECT id, quantity FROM user_items WHERE user_id = ? AND item_key = ?");
                $stmt->execute([$user['id'], $item['key']]);
                $existing = $stmt->fetch();

                if ($existing) {
                    $pdo->prepare("UPDATE user_items SET quantity = quantity + 1 WHERE id = ?")
                        ->execute([$existing['id']]);
                } else {
                    $pdo->prepare("INSERT INTO user_items (user_id, item_key) VALUES (?, ?)")
                        ->execute([$user['id'], $item['key']]);
                }

                logActivity($pdo, $user['id'], "🛒 {$item['name']} gekocht voor €" . number_format($item['price'], 0, ',', '.'));
                $pdo->commit();

                $result = ['name' => $item['name'], 'price' => $item['price']];
                $user = currentUser($pdo);
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Aankoop mislukt. Probeer opnieuw.';
            }
        }
    }
}

$shopItems = getShopItems($pdo);

$pageTitle = 'Shop — Familia La Vida Loca';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>De <span>Shop</span></h1>
    <p>Koop wapens, bescherming en voertuigen om sterker te worden.</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($result): ?>
    <div class="alert alert-success">
        ✅ Je hebt <strong><?= htmlspecialchars($result['name']) ?></strong> gekocht voor
        <strong>€<?= number_format($result['price'], 0, ',', '.') ?></strong>.
        Bekijk je bezittingen op de <a href="inventory.php">inventory</a> pagina.
    </div>
<?php endif; ?>

<?php foreach ($SHOP_CATEGORIES as $catKey => $cat): ?>
    <?php if (empty($shopItems[$catKey])) continue; ?>
    <section class="section">
        <h2><?= $cat['icon'] ?> <?= $cat['name'] ?></h2>
        <div class="item-grid">
            <?php foreach ($shopItems[$catKey] as $item):
                $locked    = $rankData['level'] < $item['min_rank'];
                $tooPoor   = $user['money'] < $item['price'];
                $disabled  = $locked || $tooPoor;
            ?>
            <div class="item-card <?= $disabled ? 'disabled' : '' ?>">
                <div class="item-head">
                    <h3><?= htmlspecialchars($item['name']) ?></h3>
                    <?php if ($locked): ?>
                        <span class="lock">🔒 Rank <?= $item['min_rank'] ?>+</span>
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
                    <strong class="price">€<?= number_format($item['price'], 0, ',', '.') ?></strong>
                    <form method="POST">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <input type="hidden" name="item_key" value="<?= htmlspecialchars($item['key']) ?>">
                        <button type="submit" class="btn btn-gold" <?= $disabled ? 'disabled' : '' ?>>
                            <?= $locked ? 'Vergrendeld' : ($tooPoor ? 'Te duur' : 'Kopen') ?>
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endforeach; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>