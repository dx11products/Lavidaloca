<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = currentUser($pdo);
$rankData = getRankData((int)$user['xp'], $RANKS);

$boxes = getAllLootboxes($pdo);
$recentOpens = getRecentOpens($pdo, $user['id'], 8);
$legendaryDrops = getRecentLegendaryDrops($pdo, 8);

$pity = (int)($user['lootbox_pity'] ?? 0);
$pityPct = min(100, round(($pity / LOOTBOX_PITY_THRESHOLD) * 100));

$pageTitle = 'Loot Boxes — Vendetta';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Loot <span>Boxes</span></h1>
    <p>Extreme boxes met miljardenprijzen. Mythische en goddelijke beloningen wachten.</p>
</div>

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon">💰</div>
        <div class="stat-value gold" id="lb-money">€<?= number_format($user['money'], 0, ',', '.') ?></div>
        <div class="stat-label">Cash</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">₿</div>
        <div class="stat-value" style="color:#f7931a;" id="lb-btc"><?= formatBtc((float)($user['btc'] ?? 0)) ?></div>
        <div class="stat-label">Bitcoin</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">🖱️</div>
        <div class="stat-value" style="color:#4a9dff;" id="lb-clicks"><?= number_format((int)($user['clicks'] ?? 0), 0, ',', '.') ?></div>
        <div class="stat-label">Clicks</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">📦</div>
        <div class="stat-value"><?= number_format((int)($user['total_lootboxes_opened'] ?? 0)) ?></div>
        <div class="stat-label">Totaal geopend</div>
    </div>
</div>

<!-- Pity counter -->
<section class="section">
    <div class="pity-banner">
        <div class="pity-info">
            <span class="pity-icon">🍀</span>
            <div>
                <strong>Pity Systeem: <?= $pity ?> / <?= LOOTBOX_PITY_THRESHOLD ?></strong>
                <p class="muted">Na <?= LOOTBOX_PITY_THRESHOLD ?> opens zonder legendary+ krijg je gegarandeerd een legendarische beloning.</p>
            </div>
        </div>
        <div class="pity-bar-wrap">
            <div class="bar" style="width:200px;">
                <div class="bar-fill xp" style="width:<?= $pityPct ?>%"></div>
            </div>
            <span class="pity-pct"><?= $pityPct ?>%</span>
        </div>
    </div>
</section>

<section class="section">
    <h2>🎁 Beschikbare boxes</h2>

    <div class="lootbox-grid">
        <?php foreach ($boxes as $box):
            $locked = $rankData['level'] < (int)$box['min_rank'];
            $color = htmlspecialchars($box['color']);
            $eurPrice = (int)$box['price_eur'];
            $btcPrice = (float)$box['price_btc'];
            $clicksPrice = (int)$box['price_clicks'];
        ?>
            <div class="lootbox-card" style="--box-color: <?= $color ?>;" data-box="<?= htmlspecialchars($box['key']) ?>">
                <div class="lb-glow"></div>
                <div class="lootbox-icon"><?= $box['icon'] ?></div>
                <h3 style="color: <?= $color ?>;"><?= htmlspecialchars($box['name']) ?></h3>
                <p class="muted"><?= htmlspecialchars($box['description']) ?></p>

                <div class="lootbox-prices">
                    <div class="lb-price-row">
                        <span>💰 <?= $eurPrice > 999999999 ? '€' . number_format($eurPrice / 1000000000, 2, ',', '.') . ' mld' : '€' . number_format($eurPrice, 0, ',', '.') ?></span>
                        <button class="btn btn-gold btn-buy-lootbox"
                                data-box="<?= htmlspecialchars($box['key']) ?>"
                                data-method="eur"
                                <?= ($locked || $user['money'] < $eurPrice) ? 'disabled' : '' ?>>
                            Koop
                        </button>
                    </div>
                    <div class="lb-price-row">
                        <span>₿ <?= formatBtc($btcPrice) ?></span>
                        <button class="btn btn-outline btn-buy-lootbox btc-btn"
                                data-box="<?= htmlspecialchars($box['key']) ?>"
                                data-method="btc"
                                <?= ($locked || (float)($user['btc'] ?? 0) < $btcPrice) ? 'disabled' : '' ?>>
                            Koop
                        </button>
                    </div>
                    <div class="lb-price-row">
                        <span>🖱️ <?= $clicksPrice > 999999 ? number_format($clicksPrice / 1000000, 1, ',', '.') . 'M' : number_format($clicksPrice, 0, ',', '.') ?></span>
                        <button class="btn btn-outline clicks-btn"
                                data-box="<?= htmlspecialchars($box['key']) ?>"
                                data-method="clicks"
                                <?= ($locked || (int)($user['clicks'] ?? 0) < $clicksPrice) ? 'disabled' : '' ?>>
                            Koop
                        </button>
                    </div>
                </div>

                <?php if ($locked): ?>
                    <div class="lb-locked">🔒 Rank <?= (int)$box['min_rank'] ?>+</div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php if (!empty($legendaryDrops)): ?>
<section class="section">
    <h2>🌟 Wereldwijde top-drops</h2>
    <ul class="activity-list">
        <?php foreach ($legendaryDrops as $d):
            $r = LOOTBOX_RARITIES[$d['rarity']] ?? LOOTBOX_RARITIES['common'];
        ?>
            <li>
                <span>
                    <?= $d['box_icon'] ?>
                    <strong style="color:<?= $r['color'] ?>;">[<?= strtoupper($r['label']) ?>]</strong>
                    <strong><?= htmlspecialchars($d['username']) ?></strong>
                    — <?= htmlspecialchars($d['reward_label']) ?>
                </span>
                <time><?= date('d M H:i', strtotime($d['opened_at'])) ?></time>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<?php if (!empty($recentOpens)): ?>
<section class="section">
    <h2>📜 Jouw laatste opens</h2>
    <ul class="activity-list">
        <?php foreach ($recentOpens as $o):
            $r = LOOTBOX_RARITIES[$o['rarity']] ?? LOOTBOX_RARITIES['common'];
        ?>
            <li>
                <span>
                    <?= $o['box_icon'] ?>
                    <strong style="color:<?= $r['color'] ?>;"><?= $r['icon'] ?> <?= $r['label'] ?></strong>
                    — <?= htmlspecialchars($o['reward_label']) ?>
                </span>
                <time><?= date('d M H:i', strtotime($o['opened_at'])) ?></time>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<!-- Overlay -->
<div class="lootbox-overlay" id="lootbox-overlay">
    <div class="lootbox-reveal" id="lootbox-reveal">
        <div class="lr-spinner" id="lr-spinner">
            <div class="lrs-item">📦</div>
        </div>

        <div class="lr-result" id="lr-result" style="display:none;">
            <div class="lr-rarity" id="lr-rarity"></div>
            <div class="lr-drops" id="lr-drops"></div>
            <button class="btn btn-gold btn-large" id="lr-close" style="margin-top:20px;">Doorgaan</button>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>