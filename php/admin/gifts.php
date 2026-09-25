<?php
$adminPageTitle = 'Gifts sturen';
require __DIR__ . '/includes/admin_header.php';

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $giftType = $_POST['gift_type'] ?? '';
    $amount = (int)($_POST['amount'] ?? 0);
    $message = trim($_POST['message'] ?? 'Cadeau van de admin!');

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } elseif ($username === '' || $amount <= 0) {
        $error = 'Vul alles in.';
    } else {
        $stmt = $pdo->prepare("SELECT id, username FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $u = $stmt->fetch();

        if (!$u) {
            $error = 'Speler niet gevonden.';
        } else {
            $uid = (int)$u['id'];
            $detail = '';

            if ($giftType === 'money') {
                $pdo->prepare("UPDATE users SET money = money + ? WHERE id = ?")->execute([$amount, $uid]);
                $detail = "€" . number_format($amount, 0, ',', '.');
            } elseif ($giftType === 'btc') {
                $btc = $amount / 100000;
                $pdo->prepare("UPDATE users SET btc = btc + ?, total_btc_earned = total_btc_earned + ? WHERE id = ?")
                    ->execute([$btc, $btc, $uid]);
                $detail = "₿" . $btc;
            } elseif ($giftType === 'clicks') {
                addClicks($pdo, $uid, $amount, 'admin_gift');
                $detail = "{$amount} clicks";
            } elseif ($giftType === 'diamonds') {
                addDiamonds($pdo, $uid, $amount, 'admin_gift', $message);
                $detail = "{$amount} 💎";
            } elseif ($giftType === 'energy') {
                $pdo->prepare("UPDATE users SET energy = LEAST(max_energy, energy + ?) WHERE id = ?")
                    ->execute([$amount, $uid]);
                $detail = "{$amount} energie";
            }

            if ($detail) {
                notify($pdo, $uid, "🎀 Cadeau van admin: {$detail} — {$message}", '🎀');
                logActivity($pdo, $uid, "🎀 Admin gift: {$detail}");
                adminLog($pdo, $admin['id'], 'send_gift', 'user', $uid, "{$giftType}: {$amount}");
                $success = "{$detail} naar {$u['username']} gestuurd!";
            }
        }
    }
}

// Recente gifts (laatste 10 admin actions)
$recentGifts = $pdo->query("
    SELECT al.*, u.username AS target_name
    FROM admin_log al
    LEFT JOIN users u ON u.id = al.target_id
    WHERE al.action = 'send_gift'
    ORDER BY al.id DESC LIMIT 10
")->fetchAll();
?>

<h1 class="admin-title">🎀 Gifts sturen</h1>

<?php if ($error): ?><div class="admin-alert admin-alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="admin-alert admin-alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<section class="admin-section">
    <h2>🎁 Stuur een cadeau</h2>
    <form method="POST" class="admin-form">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

        <label>Speler username</label>
        <input type="text" name="username" required placeholder="Bijv. Esosa">

        <label>Type gift</label>
        <select name="gift_type" required>
            <option value="money">💰 Geld (€)</option>
            <option value="btc">₿ Bitcoin (in 1/100.000)</option>
            <option value="clicks">🖱️ Clicks</option>
            <option value="diamonds">💎 Diamanten</option>
            <option value="energy">⚡ Energie</option>
        </select>

        <label>Aantal</label>
        <input type="number" name="amount" required min="1">

        <label>Persoonlijk bericht (optioneel)</label>
        <input type="text" name="message" placeholder="Bijv. Gefeliciteerd!" maxlength="200">

        <button type="submit" class="btn btn-gold btn-large btn-full">🎀 Verstuur cadeau</button>
    </form>
</section>

<section class="admin-section">
    <h2>📜 Recente gifts</h2>
    <ul class="admin-log-list">
        <?php foreach ($recentGifts as $g): ?>
            <li>
                <strong><?= htmlspecialchars($g['target_name'] ?? '?') ?></strong>
                — <?= htmlspecialchars($g['details']) ?>
                <time><?= date('d M H:i', strtotime($g['created_at'])) ?></time>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>