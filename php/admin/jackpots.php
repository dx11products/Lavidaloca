<?php
$adminPageTitle = 'Jackpot beheer';
require __DIR__ . '/includes/admin_header.php';

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $key = $_POST['jackpot'] ?? '';
    $amount = (int)($_POST['amount'] ?? 0);
    $csrf = $_POST['csrf'] ?? '';

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } elseif ($action === 'set') {
        $pdo->prepare("UPDATE jackpot_pools SET current_amount = ? WHERE `key` = ?")
            ->execute([$amount, $key]);
        adminLog($pdo, $admin['id'], 'set_jackpot', 'jackpot', null, "{$key} = {$amount}");
        $success = "Jackpot {$key} gezet op €" . number_format($amount, 0, ',', '.');
    } elseif ($action === 'reset') {
        $jp = getJackpot($pdo, $key);
        if ($jp) {
            $pdo->prepare("UPDATE jackpot_pools SET current_amount = min_amount WHERE `key` = ?")
                ->execute([$key]);
            adminLog($pdo, $admin['id'], 'reset_jackpot', 'jackpot', null, $key);
            $success = "Jackpot {$key} gereset naar minimum.";
        }
    } elseif ($action === 'force_win') {
        $stmt = $pdo->prepare("SELECT id, username FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([trim($_POST['username'] ?? '')]);
        $u = $stmt->fetch();

        if (!$u) {
            $error = 'Speler niet gevonden.';
        } else {
            $jp = getJackpot($pdo, $key);
            if ($jp) {
                $amount = (int)$jp['current_amount'];
                $pdo->beginTransaction();
                try {
                    $pdo->prepare("UPDATE users SET money = money + ? WHERE id = ?")->execute([$amount, $u['id']]);
                    $pdo->prepare("
                        UPDATE jackpot_pools
                        SET current_amount = min_amount, last_won_by = ?, last_won_at = NOW(),
                            last_won_amount = ?, total_won = total_won + ?, win_count = win_count + 1
                        WHERE `key` = ?
                    ")->execute([$u['id'], $amount, $amount, $key]);
                    $pdo->prepare("
                        INSERT INTO jackpot_wins (jackpot_key, user_id, amount, game_key, bet)
                        VALUES (?, ?, ?, 'admin_forced', 0)
                    ")->execute([$key, $u['id'], $amount]);
                    $pdo->commit();

                    adminLog($pdo, $admin['id'], 'force_jackpot', 'user', $u['id'], "{$key} — €{$amount}");
                    $success = "Jackpot aan {$u['username']} gegeven: €" . number_format($amount, 0, ',', '.');
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Mislukt.';
                }
            }
        }
    }
}

$jackpots = getAllJackpots($pdo);
$recentWins = getJackpotWins($pdo, 15);
?>

<h1 class="admin-title">🎰 Jackpot beheer</h1>

<?php if ($error): ?><div class="admin-alert admin-alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="admin-alert admin-alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<section class="admin-section">
    <h2>🎰 Actieve jackpots</h2>
    <div class="admin-jackpot-grid">
        <?php foreach ($jackpots as $j): ?>
            <div class="admin-jackpot-card" style="--jp-color:<?= htmlspecialchars($j['color']) ?>;">
                <div class="ajp-icon"><?= $j['icon'] ?></div>
                <div class="ajp-name"><?= htmlspecialchars($j['name']) ?></div>
                <div class="ajp-amount"><?= formatJackpot((int)$j['current_amount']) ?></div>
                <div class="ajp-meta">
                    Minimum: <?= formatJackpot((int)$j['min_amount']) ?><br>
                    Kans: 1 op <?= number_format((int)$j['hit_chance'], 0, ',', '.') ?><br>
                    Gewonnen: <?= (int)$j['win_count'] ?>x
                </div>

                <form method="POST" class="admin-form" style="margin-top:12px;">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="jackpot" value="<?= htmlspecialchars($j['key']) ?>">

                    <input type="number" name="amount" placeholder="Nieuwe waarde (€)" style="margin-bottom:6px;">
                    <div class="admin-actions-row">
                        <button type="submit" name="action" value="set" class="btn btn-gold">🎯 Zet</button>
                        <button type="submit" name="action" value="reset" class="btn btn-outline"
                                onclick="return confirm('Reset naar minimum?');">🔄 Reset</button>
                    </div>
                </form>

                <details style="margin-top:10px;">
                    <summary class="btn btn-outline" style="width:100%;cursor:pointer;">👑 Forceer winst</summary>
                    <form method="POST" class="admin-form" style="margin-top:8px;">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <input type="hidden" name="jackpot" value="<?= htmlspecialchars($j['key']) ?>">
                        <input type="text" name="username" placeholder="Username" required style="margin-bottom:6px;">
                        <button type="submit" name="action" value="force_win" class="btn btn-gold btn-full">
                            Forceer winst
                        </button>
                    </form>
                </details>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="admin-section">
    <h2>📜 Recente jackpot winnaars</h2>
    <ul class="admin-log-list">
        <?php foreach ($recentWins as $w): ?>
            <li>
                <strong><?= htmlspecialchars($w['username']) ?></strong>
                won <strong style="color:<?= htmlspecialchars($w['color']) ?>;"><?= htmlspecialchars($w['jackpot_name']) ?></strong>
                — <strong style="color:#58e08c;"><?= formatJackpot((int)$w['amount']) ?></strong>
                <time><?= date('d M H:i', strtotime($w['won_at'])) ?></time>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>