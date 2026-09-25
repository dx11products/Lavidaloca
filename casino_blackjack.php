<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = currentUser($pdo);
$country = getCountry($pdo, $user['current_country']);

$owner = getCasinoOwner($pdo, $user['current_country'], 'blackjack');
if (!$owner) redirect('casino.php');
if ((int)$owner['user_id'] === (int)$user['id']) redirect('casino.php');

$error = null;
$finished = false;

if (!isset($_SESSION['bj_game'])) $_SESSION['bj_game'] = null;

// ============================================================
// START
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'start') {
    $csrf = $_POST['csrf'] ?? '';
    $bet = (int)($_POST['bet'] ?? 0);

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } else {
        $check = validateCasinoBet($pdo, $user, $user['current_country'], 'blackjack', $bet);
        if (isset($check['error'])) {
            $error = $check['error'];
        } else {
            $deck = bjNewDeck();
            $player = [array_pop($deck), array_pop($deck)];
            $dealer = [array_pop($deck), array_pop($deck)];

            $pVal = bjHandValue($player);

            if ($pVal === 21) {
                // Directe blackjack — 2.5x payout
                $payout = (int)floor($bet * 2.5);
                $res = settleCasinoGame($pdo, $user['id'], 'blackjack', $user['current_country'], $bet, $payout,
                    "Blackjack! Player: " . bjFormatHand($player));

                $_SESSION['bj_result'] = [
                    'type'    => 'blackjack',
                    'payout'  => $payout,
                    'net'     => $payout - $bet,
                    'player'  => bjFormatHand($player),
                    'dealer'  => bjFormatHand($dealer),
                    'bet'     => $bet,
                    'jackpot' => $res['jackpot'] ?? null,
                ];
                unset($_SESSION['bj_game']);
                $finished = true;
            } else {
                $_SESSION['bj_game'] = [
                    'bet'    => $bet,
                    'deck'   => $deck,
                    'player' => $player,
                    'dealer' => $dealer,
                ];
            }
        }
    }
}

// ============================================================
// HIT
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'hit') {
    $csrf = $_POST['csrf'] ?? '';
    if (hash_equals(csrf_token(), $csrf) && !empty($_SESSION['bj_game'])) {
        $g = &$_SESSION['bj_game'];
        $g['player'][] = array_pop($g['deck']);
        $pVal = bjHandValue($g['player']);

        if ($pVal > 21) {
            $res = settleCasinoGame($pdo, $user['id'], 'blackjack', $user['current_country'], $g['bet'], 0,
                "Bust: " . bjFormatHand($g['player']));

            $_SESSION['bj_result'] = [
                'type'    => 'bust',
                'payout'  => 0,
                'net'     => -$g['bet'],
                'player'  => bjFormatHand($g['player']),
                'dealer'  => bjFormatHand($g['dealer']),
                'bet'     => $g['bet'],
                'jackpot' => $res['jackpot'] ?? null,
            ];
            unset($_SESSION['bj_game']);
            $finished = true;
        }
    }
}

// ============================================================
// STAND
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'stand') {
    $csrf = $_POST['csrf'] ?? '';
    if (hash_equals(csrf_token(), $csrf) && !empty($_SESSION['bj_game'])) {
        $g = &$_SESSION['bj_game'];

        while (bjHandValue($g['dealer']) < 17) {
            $g['dealer'][] = array_pop($g['deck']);
        }

        $pVal = bjHandValue($g['player']);
        $dVal = bjHandValue($g['dealer']);

        if ($dVal > 21) {
            $payout = $g['bet'] * 2;
            $type = 'dealer_bust';
        } elseif ($pVal > $dVal) {
            $payout = $g['bet'] * 2;
            $type = 'win';
        } elseif ($pVal === $dVal) {
            $payout = $g['bet'];
            $type = 'push';
        } else {
            $payout = 0;
            $type = 'loss';
        }

        $res = settleCasinoGame($pdo, $user['id'], 'blackjack', $user['current_country'], $g['bet'], (int)$payout,
            ucfirst($type) . ": Player " . bjFormatHand($g['player']) . " vs Dealer " . bjFormatHand($g['dealer']));

        $_SESSION['bj_result'] = [
            'type'    => $type,
            'payout'  => (int)$payout,
            'net'     => (int)$payout - $g['bet'],
            'player'  => bjFormatHand($g['player']),
            'dealer'  => bjFormatHand($g['dealer']),
            'bet'     => $g['bet'],
            'jackpot' => $res['jackpot'] ?? null,
        ];
        unset($_SESSION['bj_game']);
        $finished = true;
    }
}

if (isset($_GET['clear'])) {
    unset($_SESSION['bj_result']);
    redirect('casino_blackjack.php');
}

$activeGame = $_SESSION['bj_game'] ?? null;
$result = $_SESSION['bj_result'] ?? null;

if ($finished || $result) {
    $user = currentUser($pdo);
}

$jackpots = getAllJackpots($pdo);

$pageTitle = 'Blackjack — Vendetta';
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
    <h1>🃏 <span>Blackjack</span></h1>
    <p>Speel tegen de dealer. Zo dicht mogelijk bij 21 zonder erover.</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Resultaat -->
<?php if ($result): ?>
    <div class="bj-result <?= $result['net'] > 0 ? 'win' : ($result['net'] < 0 ? 'loss' : 'push') ?>">
        <h2>
            <?php if ($result['type'] === 'blackjack'): ?>🎉 BLACKJACK!
            <?php elseif ($result['type'] === 'bust'): ?>💥 Bust!
            <?php elseif ($result['type'] === 'dealer_bust'): ?>🎉 Dealer bust!
            <?php elseif ($result['type'] === 'win'): ?>🎉 Gewonnen!
            <?php elseif ($result['type'] === 'push'): ?>🤝 Gelijkspel
            <?php else: ?>😢 Verloren
            <?php endif; ?>
        </h2>

        <div class="bj-final-hands">
            <div>
                <span>Jouw hand:</span>
                <strong><?= htmlspecialchars($result['player']) ?></strong>
            </div>
            <div>
                <span>Dealer:</span>
                <strong><?= htmlspecialchars($result['dealer']) ?></strong>
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

<!-- Actief spel -->
<?php if ($activeGame): ?>
    <section class="section">
        <div class="bj-table">
            <div class="bj-hand">
                <span class="bj-label">Dealer</span>
                <div class="bj-cards">
                    <div class="bj-card hidden">?</div>
                    <?php for ($i = 1; $i < count($activeGame['dealer']); $i++): ?>
                        <div class="bj-card"><?= bjFormatCard($activeGame['dealer'][$i]) ?></div>
                    <?php endfor; ?>
                </div>
            </div>

            <div class="bj-vs">VS</div>

            <div class="bj-hand">
                <span class="bj-label">Jij (<?= bjHandValue($activeGame['player']) ?>)</span>
                <div class="bj-cards">
                    <?php foreach ($activeGame['player'] as $c): ?>
                        <div class="bj-card"><?= bjFormatCard($c) ?></div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="bj-info">
                Inzet: <strong>€<?= number_format((int)$activeGame['bet'], 0, ',', '.') ?></strong>
            </div>

            <div class="bj-actions">
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <button type="submit" name="action" value="hit" class="btn btn-gold btn-large">➕ Hit</button>
                </form>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <button type="submit" name="action" value="stand" class="btn btn-outline btn-large">✋ Stand</button>
                </form>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- Start nieuw spel -->
<?php if (!$activeGame && !$result): ?>
    <section class="section">
        <div class="casino-card">
            <h2>Start een nieuw spel</h2>
            <p class="muted">Minimum €<?= number_format(CASINO_MIN_BET, 0, ',', '.') ?>, maximum €<?= number_format(CASINO_MAX_BET, 0, ',', '.') ?>.</p>

            <div class="bj-payouts">
                <div>🎉 Blackjack (21 direct): <strong>2.5x</strong></div>
                <div>✅ Gewonnen: <strong>2x</strong></div>
                <div>🤝 Gelijkspel: <strong>1x</strong> (inzet terug)</div>
                <div>💥 Bust / Verlies: <strong>0x</strong></div>
            </div>

            <form method="POST" class="casino-form">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="start">

                <div class="bet-input">
                    <label>Inzet (€)</label>
                    <input type="number" name="bet" min="<?= CASINO_MIN_BET ?>" max="<?= CASINO_MAX_BET ?>"
                           value="<?= CASINO_MIN_BET ?>" step="1000" required>
                </div>

                <button type="submit" class="btn btn-gold btn-large btn-full">🎲 Deel de kaarten</button>
            </form>
        </div>
    </section>
<?php endif; ?>

<script src="assets/js/jackpot.js"></script>

<?php require __DIR__ . '/includes/footer.php'; ?>