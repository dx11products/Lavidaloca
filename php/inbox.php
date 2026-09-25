<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = currentUser($pdo);

$items = getInboxItems($pdo, $user['id']);
$unopened = countUnopenedInbox($pdo, $user['id']);

$error = null;
$success = null;
$openedReward = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $inboxId = (int)($_POST['inbox_id'] ?? 0);
    $csrf = $_POST['csrf'] ?? '';

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } elseif ($action === 'open' && $inboxId > 0) {
        $res = openVaultFromInbox($pdo, $user['id'], $inboxId);
        if (!$res['success']) {
            $error = $res['error'];
        } else {
            $openedReward = $res;
            $user = currentUser($pdo);
            $items = getInboxItems($pdo, $user['id']);
            $unopened = countUnopenedInbox($pdo, $user['id']);
        }
    }
}

$pageTitle = 'Inbox — Vendetta';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>📬 <span>Inbox</span></h1>
    <p>Alle kluiscodes die je hebt ontvangen. Open een kluis voor geld, BTC of clicks!</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($openedReward): ?>
    <div class="vault-open-reveal">
        <div class="vor-icon"><?= $openedReward['vault_icon'] ?></div>
        <h2><?= htmlspecialchars($openedReward['vault_name']) ?> gekraakt!</h2>
        <?php if ($openedReward['reward_type'] === 'eur'): ?>
            <div class="vor-reward gold">
                💰 €<?= number_format($openedReward['reward_value'], 0, ',', '.') ?>
            </div>
        <?php elseif ($openedReward['reward_type'] === 'btc'): ?>
            <div class="vor-reward btc">
                ₿ <?= formatBtc($openedReward['reward_value']) ?>
            </div>
        <?php else: ?>
            <div class="vor-reward clicks">
                🖱️ <?= number_format((int)$openedReward['reward_value'], 0, ',', '.') ?> clicks
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Stats -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon">📬</div>
        <div class="stat-value" style="color:<?= $unopened > 0 ? '#58e08c' : 'var(--text)'; ?>;">
            <?= $unopened ?>
        </div>
        <div class="stat-label">Ongeopende codes</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">🔓</div>
        <div class="stat-value"><?= count(array_filter($items, fn($i) => (int)$i['opened'] === 1)) ?></div>
        <div class="stat-label">Geopend</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">💰</div>
        <div class="stat-value gold">€<?= number_format($user['money'], 0, ',', '.') ?></div>
        <div class="stat-label">Cash</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">₿</div>
        <div class="stat-value" style="color:#f7931a;"><?= formatBtc((float)($user['btc'] ?? 0)) ?></div>
        <div class="stat-label">BTC</div>
    </div>
</div>

<!-- Inbox -->
<section class="section">
    <h2>📥 Jouw codes (<?= count($items) ?>)</h2>

    <?php if (empty($items)): ?>
        <p class="muted">
            Je hebt nog geen kluiscodes. Verdien ze via:
            <a href="crimes.php">crimes</a>,
            <a href="attack.php">gevechten</a>,
            <a href="heists.php">heists</a> of
            <a href="leaderboard.php">profielen liken</a>.
        </p>
    <?php else: ?>
        <div class="inbox-list">
            <?php foreach ($items as $item):
                $isOpened = (int)$item['opened'] === 1;
                $rarityColors = [
                    'common'    => '#8a8a8a',
                    'uncommon'  => '#58e08c',
                    'rare'      => '#4a9dff',
                    'epic'      => '#b06aff',
                    'legendary' => '#ffb040',
                ];
                $color = $rarityColors[$item['rarity']] ?? '#8a8a8a';
            ?>
                <div class="inbox-item <?= $isOpened ? 'opened' : 'unopened' ?>"
                     style="border-left-color:<?= $color ?>;">
                    <div class="inbox-icon"><?= $item['icon'] ?></div>
                    <div class="inbox-info">
                        <h3>
                            <?= htmlspecialchars($item['vault_name']) ?>
                            <span class="vault-rarity" style="color:<?= $color ?>;">
                                <?= strtoupper($item['rarity']) ?>
                            </span>
                        </h3>
                        <div class="inbox-code">
                            <span class="code-label">Code:</span>
                            <code><?= htmlspecialchars($item['code']) ?></code>
                        </div>
                        <div class="inbox-meta">
                            <?php if ($isOpened): ?>
                                <span style="color:#58e08c;">
                                    ✅ Geopend — 
                                    <?php if ($item['reward_type'] === 'eur'): ?>
                                        €<?= number_format((float)$item['reward_value'], 0, ',', '.') ?>
                                    <?php elseif ($item['reward_type'] === 'btc'): ?>
                                        ₿<?= formatBtc((float)$item['reward_value']) ?>
                                    <?php else: ?>
                                        <?= number_format((int)$item['reward_value'], 0, ',', '.') ?> clicks
                                    <?php endif; ?>
                                </span>
                                <time><?= date('d M H:i', strtotime($item['opened_at'])) ?></time>
                            <?php else: ?>
                                <span class="muted">Ontvangen: <?= date('d M H:i', strtotime($item['received_at'])) ?></span>
                                <span class="muted">Bron: <?= htmlspecialchars($item['source']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="inbox-action">
                        <?php if ($isOpened): ?>
                            <span class="badge-equipped">Geopend</span>
                        <?php else: ?>
                            <form method="POST">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="action" value="open">
                                <input type="hidden" name="inbox_id" value="<?= (int)$item['id'] ?>">
                                <button type="submit" class="btn btn-gold">🔓 Openen</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- Waarde per tier info -->
<section class="section">
    <h2>💎 Kluis tiers</h2>
    <div class="vault-rewards-grid">
        <?php foreach (VAULT_REWARDS as $tier => $rewards): ?>
            <div class="vault-reward-card">
                <h3>Tier <?= $tier ?></h3>
                <div class="vr-row"><span>💰 Geld</span><strong>€<?= number_format($rewards['eur'][0], 0, ',', '.') ?> – €<?= number_format($rewards['eur'][1], 0, ',', '.') ?></strong></div>
                <div class="vr-row"><span>₿ BTC</span><strong style="color:#f7931a;"><?= formatBtc($rewards['btc'][0]) ?> – <?= formatBtc($rewards['btc'][1]) ?></strong></div>
                <div class="vr-row"><span>🖱️ Clicks</span><strong style="color:#4a9dff;"><?= number_format($rewards['clicks'][0], 0, ',', '.') ?> – <?= number_format($rewards['clicks'][1], 0, ',', '.') ?></strong></div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>