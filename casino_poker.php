<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = currentUser($pdo);
$country = getCountry($pdo, $user['current_country']);

$owner = getCasinoOwner($pdo, $user['current_country'], 'poker');
if (!$owner) redirect('casino.php');
if ((int)$owner['user_id'] === (int)$user['id']) redirect('casino.php');

$error = null;

// ============================================================
// START
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'start') {
    $csrf = $_POST['csrf'] ?? '';
    $bet = (int)($_POST['bet'] ?? 0);

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } else {
        $check = validateCasinoBet($pdo, $user, $user['current_country'], 'poker', $bet);
        if (isset($check['error'])) {
            $error = $check['error'];
        } else {
            $deck = pokerNewDeck();
            $player = array_splice($deck, 0, 5);
            $dealer = array_splice($deck, 0, 5);

            $_SESSION['poker_game'] = [
                'bet'    => $bet,
                'deck'   => $deck,
                'player' => $player,
                'dealer' => $dealer,
            ];
        }
    }
}

// ============================================================
// DRAW
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'draw') {
    $csrf = $_POST['csrf'] ?? '';
    if (hash_equals(csrf_token(), $csrf) && !empty($_SESSION['poker_game'])) {
        $g = &$_SESSION['poker_game'];
        $keep = $_POST['keep'] ?? [];

        $newHand = [];
        foreach ($g['player'] as $i => $card) {
            if (in_array((string)$i, $keep, true)) {
                $newHand[] = $card;
            } else {
                $newHand[] = array_pop($g['deck']);
            }
        }
        $g['player'] = $newHand;

        $pRank = pokerHandRank($g['player']);
        $dRank = pokerHandRank($g['dealer']);

        $payout = 0;
        $resultType = 'loss';

        if ($pRank > $dRank) {
            $multipliers = [-1 => 2, 0 => 2, 1 => 3, 2 => 4, 3 => 6, 4 => 8, 5 => 12, 6 => 20, 7 => 50, 8 => 100];
            $mult = $multipliers[$pRank] ?? 2;
            $payout = $g['bet'] * $mult;
            $resultType = 'win';
        } elseif ($pRank === $dRank) {
            $payout = $g['bet'];
            $resultType = 'push';
        }

        $res = settleCasinoGame($pdo, $user['id'], 'poker', $user['current_country'], $g['bet'], $payout,
            "Player: " . POKER_HAND_NAMES[$pRank] . " vs Dealer: " . POKER_HAND_NAMES[$dRank]);

        $_SESSION['poker_result'] = [
            'type'        => $resultType,
            'bet'         => $g['bet'],
            'payout'      => $payout,
            'net'         => $payout - $g['bet'],
            'player'      => $g['player'],
            'dealer'      => $g['dealer'],
            'player_rank' => $pRank,
            'dealer_rank' => $dRank,
            'jackpot'     => $res['jackpot'] ?? null,
        ];
        unset($_SESSION['poker_game']);

        $user = currentUser($pdo);
    }
}

if (isset($_GET['clear'])) {
    unset($_SESSION['poker_result']);
    redirect('casino_poker.php');
}

$activeGame = $_SESSION['poker_game'] ?? null;
$result = $_SESSION['poker_result'] ?? null;

$jackpots = getAllJackpots($pdo);

$pageTitle = 'Poker — Vendetta';
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
    <h1>♠️ <span>Poker</span></h1>
    <p>5 kaarten. Vervang wat je wil. Beste hand wint.</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($result): ?>
    <div class="bj-result <?= $result['net'] > 0 ? 'win' : ($result['net'] < 0 ? 'loss' : 'push') ?>">
        <h2>
            <?php if ($result['type'] === 'win'): ?>🎉 Gewonnen!
            <?php elseif ($result['type'] === 'push'): ?>🤝 Gelijkspel
            <?php else: ?>😢 Verloren
            <?php endif; ?>
        </h2>

        <div class="poker-hands">
            <div>
                <span>Jouw hand:</span>
                <strong style="color:#58e08c;"><?= POKER_HAND_NAMES[$result['player_rank']] ?></strong>
                <div class="bj-cards">
                    <?php foreach ($result['player'] as $c): ?>
                        <div class="bj-card"><?= bjFormatCard($c) ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div>
                <span>Dealer:</span>
                <strong style="color:#ff5c5c;"><?= POKER_HAND_NAMES[$result['dealer_rank']] ?></strong>
                <div class="bj-cards">
                    <?php foreach ($result['dealer'] as $c): ?>
                        <div class="bj-card"><?= bjFormatCard($c) ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="bj-net">
            <?php if ($result['net'] > 0): ?>
                <span style="color:#58e08c;">+€<?= number_format($result['net'], 0, ',', '.') ?></span>
            <?php elseif ($result['net'] < 0): ?>
                <span style="color:#ff5c5c;">€<?= number_format($result['net'], 0, ',', '.') ?></span>
            <?php else: ?>
                <span style="color:#a08d75;">€0</span>
            <?php endif; ?>
        </div>

        <?php if (!empty($result['jackpot']) && !empty($result['jackpot']['won'])): ?>
            <div class="jackpot-win-alert">
                🎉 <strong>JACKPOT!</strong><br>
                Je won <strong style="color:<?= htmlspecialchars($result['jackpot']['color']) ?>;">
                    <?= htmlspecialchars($result['jackpot']['name']) ?>
                </strong>
                <span class="jwa-amount"><?= formatJackpot((int)$result['jackpot']['amount']) ?></span>
            </div>
        <?php endif; ?>

        <a href="?clear=1" class="btn btn-gold btn-large" style="margin-top:20px;">Nieuw spel</a>
    </div>
<?php endif; ?>

<?php if ($activeGame && !$result): ?>
    <section class="section">
        <div class="poker-table">
            <h2>Kies welke kaarten je wil houden</h2>
            <p class="muted">Vink aan wat je wil behouden. De rest wordt vervangen.</p>

            <form method="POST">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="draw">

                <div class="poker-keep-grid">
                    <?php foreach ($activeGame['player'] as $i => $c): ?>
                        <label class="poker-card-choice">
                            <input type="checkbox" name="keep[]" value="<?= $i ?>">
                            <div class="bj-card"><?= bjFormatCard($c) ?></div>
                            <span class="poker-keep-label">Houden</span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <button type="submit" class="btn btn-gold btn-large btn-full" style="margin-top:20px;">
                    🎴 Vervang geselecteerde kaarten
                </button>
            </form>

            <div class="poker-info">
                Jouw huidige hand: <strong><?= POKER_HAND_NAMES[pokerHandRank($activeGame['player'])] ?></strong>
                · Inzet: <strong>€<?= number_format((int)$activeGame['bet'], 0, ',', '.') ?></strong>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if (!$activeGame && !$result): ?>
    <section class="section">
        <div class="casino-card">
            <h2>Start een nieuw pokerspel</h2>
            <p class="muted">Je krijgt 5 kaarten en mag 1x kaarten vervangen.</p>

            <div class="bj-payouts">
                <div>👑 Royal Flush: <strong>100x</strong></div>
                <div>🔥 Straight Flush: <strong>50x</strong></div>
                <div>💰 Four of a Kind: <strong>20x</strong></div>
                <div>🏆 Full House: <strong>12x</strong></div>
                <div>🎴 Flush: <strong>8x</strong></div>
                <div>📏 Straight: <strong>6x</strong></div>
                <div>🎯 Three of a Kind: <strong>4x</strong></div>
                <div>✌️ Two Pair: <strong>3x</strong></div>
                <div>1️⃣ One Pair: <strong>2x</strong></div>
            </div>

            <form method="POST" class="casino-form">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="start">

                <div class="bet-input">
                    <label>Inzet (€)</label>
                    <input type="number" name="bet" min="<?= CASINO_MIN_BET ?>" max="<?= CASINO_MAX_BET ?>"
                           value="<?= CASINO_MIN_BET ?>" step="1000" required>
                </div>

                <button type="submit" class="btn btn-gold btn-large btn-full">🎴 Deel de kaarten</button>
            </form>
        </div>
    </section>
<?php endif; ?>

<script src="assets/js/jackpot.js"></script>

<?php require __DIR__ . '/includes/footer.php'; ?>