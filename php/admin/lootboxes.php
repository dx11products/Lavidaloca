<?php
$adminPageTitle = 'Lootbox beheer';
require __DIR__ . '/includes/admin_header.php';

$error = null;
$success = null;

// Recente opens
$recent = $pdo->query("
    SELECT lo.*, u.username, lt.name AS box_name, lt.icon
    FROM lootbox_opens lo
    JOIN users u ON u.id = lo.user_id
    JOIN lootbox_types lt ON lt.`key` = lo.box_key
    ORDER BY lo.id DESC LIMIT 30
")->fetchAll();

// Stats per rarity
$perRarity = $pdo->query("
    SELECT rarity, COUNT(*) AS count
    FROM lootbox_opens
    GROUP BY rarity
    ORDER BY FIELD(rarity, 'divine','mythic','legendary','epic','rare','uncommon','common')
")->fetchAll();
?>

<h1 class="admin-title">🎁 Lootbox beheer</h1>

<section class="admin-section">
    <h2>📊 Verdeling per rarity</h2>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr><th>Rarity</th><th>Aantal</th></tr>
            </thead>
            <tbody>
                <?php foreach ($perRarity as $r):
                    $info = LOOTBOX_RARITIES[$r['rarity']] ?? ['label' => $r['rarity'], 'color' => '#888', 'icon' => '⚪'];
                ?>
                    <tr>
                        <td>
                            <strong style="color:<?= $info['color'] ?>;">
                                <?= $info['icon'] ?> <?= $info['label'] ?>
                            </strong>
                        </td>
                        <td class="num"><?= number_format((int)$r['count']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="admin-section">
    <h2>📜 Recente opens</h2>
    <ul class="admin-log-list">
        <?php foreach ($recent as $o):
            $info = LOOTBOX_RARITIES[$o['rarity']] ?? ['color' => '#888', 'label' => $o['rarity'], 'icon' => '⚪'];
        ?>
            <li>
                <strong><?= htmlspecialchars($o['username']) ?></strong>
                opende <?= $o['icon'] ?> <strong><?= htmlspecialchars($o['box_name']) ?></strong>
                → <strong style="color:<?= $info['color'] ?>;"><?= $info['label'] ?></strong>
                — <?= htmlspecialchars($o['reward_label']) ?>
                <time><?= date('d M H:i', strtotime($o['opened_at'])) ?></time>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>