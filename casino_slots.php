<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = currentUser($pdo);
$country = getCountry($pdo, $user['current_country']);

$owner = getCasinoOwner($pdo, $user['current_country'], 'slots');
if (!$owner) redirect('casino.php');
if ((int)$owner['user_id'] === (int)$user['id']) redirect('casino.php');

$error = null;
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    $bet = (int)($_POST['bet'] ?? 0);

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } else {
        $check = validateCasinoBet($pdo, $user, $user['current_country'], 'slots', $bet);
        if (isset($check['error'])) {
            $error = $check['error'];
        } else {
            $symbols = [];
            for ($i = 0; $i < 3; $i++) {
                $symbols[] = SLOT_SYMBOLS[array_rand(SLOT_SYMBOLS)];
            }

            $payout = 0;
            $winType = 'loss';

            if ($symbols[0] === $symbols[1] && $symbols[1] === $symbols[2]) {
                $multiplier = SLOT_PAYOUTS[$symbols[0]] ?? 2;
                $payout = $bet * $multiplier;
                $winType = 'jackpot';
            } elseif ($symbols[0] === $symbols[1] || $symbols[1] === $symbols[2] || $symbols[0] === $symbols[2]) {
                $payout = (int)floor($bet * 1.5);
                $winType = 'small_win';
            }

            $res = settleCasinoGame($pdo, $user['id'], 'slots', $user['current_country'], $bet, $payout,
                "Symbols: " . implode(' ', $symbols));

            $result = [
                'symbols' => $symbols,
                'bet'     => $bet,
                'payout'  => $payout,
                'net'     => $payout - $bet,
                'type'    => $winType,
                'jackpot' => $res['jackpot'] ?? null,
            ];

            $user = currentUser($pdo);
        }
    }
}

$jackpots = getAllJackpots($pdo);

$pageTitle = 'Slots — Vendetta';
require __DIR__ . '/includes/header.php';
?>

<!-- Jackpot mini bar -->
<div class="jackpot-mini-bar">
    <?php foreach ($jackpots as $j): ?>
        <div class="jmb-item" data-jackpot="<?= htmlspecialchars($j['key']) ?>">
            <span class="jmb-icon"><?= $j['icon'] ?></span>
            <span class="jmb-name"><?= htmlspecialchars($j['name']) ?></span>
            <strong class="jp-amount" style="color:<?= htmlspecialchars($j['color']) ?>;"
                    data-amount="<?= (int)$j['current_amount'] ?>">
                <?= formatJackpot((int)$j['current_amount']) ?>
            </strong>
        </div>
    <?php endforeach; ?>
</div>

<div class="page-header">
    <a href="casino.php" class="back-link" style="display:inline-block;text-align:left;margin:0 0 10px 0;">← Terug naar casino</a>
    <h1>🎰 <span>Slot Machine</span></h1>
    <p>Match 3 symbolen voor een mega payout!</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($result): ?>
    <div class="slots-result <?= $result['net'] > 0 ? 'win' : 'loss' ?>">
        <div class="slots-reels-result">
            <?php foreach ($result['symbols'] as $s): ?>
                <div class="slot-symbol-result"><?= $s ?></div>
            <?php endforeach; ?>
        </div>

        <h2>
            <?php if ($result['type'] === 'jackpot'): ?>🎉 JACKPOT!
            <?php elseif ($result['type'] === 'small_win'): ?>🎉 Gewonnen!
            <?php else: ?>😢 Verloren
            <?php endif; ?>
        </h2>

        <div class="slots-net <?= $result['net'] > 0 ? 'positive' : 'negative' ?>">
            <?= $result['net'] > 0 ? '+' : '' ?>€<?= number_format($result['net'], 0, ',', '.') ?>
        </div>

        <?php if (!empty($result['jackpot']) && !empty($result['jackpot']['won'])): ?>
            <div class="jackpot-win-alert" style="margin-top:20px;">
                🎉 <strong>PROGRESSIEVE JACKPOT!</strong><br>
                Je won <strong style="color:<?= htmlspecialchars($result['jackpot']['color']) ?>;">
                    <?= htmlspecialchars($result['jackpot']['name']) ?>
                </strong>
                <span class="jwa-amount"><?= formatJackpot((int)$result['jackpot']['amount']) ?></span>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<section class="section">
    <div class="slots-machine">
        <div class="slots-reels">
            <div class="slot-reel">🍒</div>
            <div class="slot-reel">💎</div>
            <div class="slot-reel">7️⃣</div>
        </div>

        <div class="slots-payouts">
            <h3>💰 Uitbetalingen</h3>
            <div class="slot-payout-row"><span>7️⃣ 7️⃣ 7️⃣</span><strong>20x</strong></div>
            <div class="slot-payout-row"><span>💎 💎 💎</span><strong>12x</strong></div>
            <div class="slot-payout-row"><span>⭐ ⭐ ⭐</span><strong>8x</strong></div>
            <div class="slot-payout-row"><span>🔔 🔔 🔔</span><strong>5x</strong></div>
            <div class="slot-payout-row"><span>🍋 🍋 🍋</span><strong>3x</strong></div>
            <div class="slot-payout-row"><span>🍒 🍒 🍒</span><strong>2x</strong></div>
            <div class="slot-payout-row"><span>2 dezelfde</span><strong>1.5x</strong></div>
        </div>

        <form method="POST" class="casino-form">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

            <div class="bet-input">
                <label>Inzet (€)</label>
                <input type="number" name="bet" min="<?= CASINO_MIN_BET ?>" max="<?= CASINO_MAX_BET ?>"
                       value="<?= CASINO_MIN_BET ?>" step="1000" required>
            </div>

            <button type="submit" class="btn btn-gold btn-large btn-full">🎰 TREK AAN DE HENDEL</button>
        </form>
    </div>
</section>

<script src="assets/js/jackpot.js"></script>

<?php require __DIR__ . '/includes/footer.php'; ?>