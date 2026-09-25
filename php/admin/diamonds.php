<?php
$adminPageTitle = 'Diamanten beheer';
require __DIR__ . '/includes/admin_header.php';

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);
    $amount = (int)($_POST['amount'] ?? 0);
    $reason = trim($_POST['reason'] ?? 'Admin actie');
    $csrf = $_POST['csrf'] ?? '';

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } elseif ($userId <= 0) {
        $error = 'Geen speler gekozen.';
    } elseif ($action === 'add') {
        addDiamonds($pdo, $userId, $amount, 'admin_add', $reason);
        adminLog($pdo, $admin['id'], 'add_diamonds', 'user', $userId, "+{$amount} — {$reason}");
        $success = "{$amount} 💎 gegeven!";
    } elseif ($action === 'remove') {
        if (removeDiamonds($pdo, $userId, $amount, 'admin_remove', $reason)) {
            adminLog($pdo, $admin['id'], 'remove_diamonds', 'user', $userId, "-{$amount} — {$reason}");
            $success = "{$amount} 💎 afgenomen!";
        } else {
            $error = 'Niet genoeg diamanten om af te nemen.';
        }
    } elseif ($action === 'set') {
        $stmt = $pdo->prepare("UPDATE users SET diamonds = ? WHERE id = ?");
        $stmt->execute([$amount, $userId]);
        adminLog($pdo, $admin['id'], 'set_diamonds', 'user', $userId, "= {$amount}");
        $success = "Diamanten gezet op {$amount} 💎";
    }
}

// Top diamant houders
$top = $pdo->query("SELECT id, username, diamonds FROM users ORDER BY diamonds DESC LIMIT 15")->fetchAll();

// Recente transacties
$recent = $pdo->query("
    SELECT dt.*, u.username
    FROM diamond_transactions dt
    JOIN users u ON u.id = dt.user_id
    ORDER BY dt.id DESC LIMIT 20
")->fetchAll();
?>

<h1 class="admin-title">💎 Diamanten beheer</h1>

<?php if ($error): ?><div class="admin-alert admin-alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="admin-alert admin-alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<section class="admin-section">
    <h2>💎 Diamanten aanpassen</h2>
    <form method="POST" class="admin-form">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

        <label>Speler ID of username</label>
        <input type="text" name="user_id" placeholder="Bijv. 1 of Esosa" required>

        <label>Aantal diamanten</label>
        <input type="number" name="amount" min="1" required>

        <label>Reden (voor logboek)</label>
        <input type="text" name="reason" placeholder="Waarom geef je diamanten?">

        <div class="admin-actions-row">
            <button type="submit" name="action" value="add" class="btn btn-gold">➕ Toevoegen</button>
            <button type="submit" name="action" value="remove" class="btn btn-outline">➖ Afnemen</button>
            <button type="submit" name="action" value="set" class="btn btn-outline">🎯 Instellen op</button>
        </div>
    </form>
</section>

<div class="admin-split">
    <section class="admin-section">
        <h2>🏆 Top diamant houders</h2>
        <ol class="top-list">
            <?php foreach ($top as $u): ?>
                <li>
                    <a href="user_edit.php?id=<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['username']) ?></a>
                    <strong style="color:#b9f2ff;"><?= number_format((int)$u['diamonds'], 0, ',', '.') ?> 💎</strong>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>

    <section class="admin-section">
        <h2>📜 Recente transacties</h2>
        <ul class="admin-log-list">
            <?php foreach ($recent as $t): ?>
                <li>
                    <strong><?= htmlspecialchars($t['username']) ?></strong>
                    <span style="color:<?= (int)$t['amount'] > 0 ? '#58e08c' : '#ff5c5c' ?>;">
                        <?= (int)$t['amount'] > 0 ? '+' : '' ?><?= number_format((int)$t['amount'], 0, ',', '.') ?> 💎
                    </span>
                    <small><?= htmlspecialchars($t['description'] ?: $t['type']) ?></small>
                    <time><?= date('d M H:i', strtotime($t['created_at'])) ?></time>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
</div>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>