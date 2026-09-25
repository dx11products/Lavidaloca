<?php
$adminPageTitle = 'Speler bewerken';
require __DIR__ . '/includes/admin_header.php';

$userId = (int)($_GET['id'] ?? 0);
if ($userId === 0) redirect('users.php');

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$userId]);
$u = $stmt->fetch();
if (!$u) redirect('users.php');

$rankData = getRankData((int)$u['xp'], getRanksArray());

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf = $_POST['csrf'] ?? '';

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } else {
        if ($action === 'adjust_money') {
            $amount = (int)($_POST['amount'] ?? 0);
            $reason = trim($_POST['reason'] ?? 'Admin aanpassing');
            adminAdjustMoney($pdo, $userId, $amount, $reason);
            adminLog($pdo, $admin['id'], 'adjust_money', 'user', $userId, "€{$amount} — {$reason}");
            $success = "Geld aangepast: €{$amount}";
        } elseif ($action === 'adjust_btc') {
            $amount = (float)($_POST['amount'] ?? 0);
            $reason = trim($_POST['reason'] ?? 'Admin aanpassing');
            adminAdjustBtc($pdo, $userId, $amount, $reason);
            adminLog($pdo, $admin['id'], 'adjust_btc', 'user', $userId, "₿{$amount} — {$reason}");
            $success = "BTC aangepast: ₿{$amount}";
        } elseif ($action === 'adjust_clicks') {
            $amount = (int)($_POST['amount'] ?? 0);
            $reason = trim($_POST['reason'] ?? 'Admin aanpassing');
            adminAdjustClicks($pdo, $userId, $amount, $reason);
            adminLog($pdo, $admin['id'], 'adjust_clicks', 'user', $userId, "{$amount} clicks");
            $success = "Clicks aangepast: {$amount}";
        } elseif ($action === 'adjust_xp') {
            $amount = (int)($_POST['amount'] ?? 0);
            $reason = trim($_POST['reason'] ?? 'Admin aanpassing');
            adminAdjustXp($pdo, $userId, $amount, $reason);
            adminLog($pdo, $admin['id'], 'adjust_xp', 'user', $userId, "{$amount} XP");
            $success = "XP aangepast: {$amount}";
        } elseif ($action === 'set_energy') {
            $energy = (int)($_POST['energy'] ?? 0);
            $pdo->prepare("UPDATE users SET energy = ?, energy_updated = NOW() WHERE id = ?")
                ->execute([$energy, $userId]);
            adminLog($pdo, $admin['id'], 'set_energy', 'user', $userId, "Energie: {$energy}");
            $success = 'Energie ingesteld.';
        } elseif ($action === 'set_health') {
            $health = (int)($_POST['health'] ?? 0);
            $pdo->prepare("UPDATE users SET health = ?, hospital_until = NULL WHERE id = ?")
                ->execute([$health, $userId]);
            adminLog($pdo, $admin['id'], 'set_health', 'user', $userId, "Health: {$health}");
            $success = 'Health ingesteld.';
        } elseif ($action === 'toggle_admin') {
            if ($userId === (int)$admin['id']) {
                $error = 'Je kunt je eigen admin rechten niet aanpassen.';
            } else {
                $newVal = $u['is_admin'] ? 0 : 1;
                $pdo->prepare("UPDATE users SET is_admin = ? WHERE id = ?")->execute([$newVal, $userId]);
                adminLog($pdo, $admin['id'], $newVal ? 'grant_admin' : 'revoke_admin', 'user', $userId);
                $success = $newVal ? 'Admin rechten gegeven.' : 'Admin rechten afgenomen.';
            }
        } elseif ($action === 'save_notes') {
            $notes = trim($_POST['notes'] ?? '');
            $pdo->prepare("UPDATE users SET notes = ? WHERE id = ?")->execute([$notes, $userId]);
            adminLog($pdo, $admin['id'], 'save_notes', 'user', $userId);
            $success = 'Notities opgeslagen.';
        } elseif ($action === 'clear_cooldowns') {
            $pdo->prepare("DELETE FROM crime_cooldowns WHERE user_id = ?")->execute([$userId]);
            $pdo->prepare("UPDATE users SET in_prison = 0, prison_until = NULL, hospital_until = NULL WHERE id = ?")
                ->execute([$userId]);
            adminLog($pdo, $admin['id'], 'clear_cooldowns', 'user', $userId);
            $success = 'Cooldowns, prison en ziekenhuis gewist.';
        }

        // Refresh
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $u = $stmt->fetch();
        $rankData = getRankData((int)$u['xp'], getRanksArray());
    }
}
?>

<h1 class="admin-title">✏️ <?= htmlspecialchars($u['username']) ?> <small>#<?= (int)$u['id'] ?></small></h1>

<?php if ($error): ?><div class="admin-alert admin-alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="admin-alert admin-alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<div class="user-edit-grid">
    <!-- Info -->
    <div class="user-edit-card">
        <h2>Speler info</h2>
        <ul class="info-list">
            <li><span>ID</span><strong><?= (int)$u['id'] ?></strong></li>
            <li><span>Naam</span><strong><?= htmlspecialchars($u['username']) ?></strong></li>
            <li><span>Email</span><strong><?= htmlspecialchars($u['email']) ?></strong></li>
            <li><span>Rank</span><strong><?= htmlspecialchars($rankData['name']) ?> (Lv <?= $rankData['level'] ?>)</strong></li>
            <li><span>XP</span><strong><?= number_format((int)$u['xp']) ?></strong></li>
            <li><span>Cash</span><strong>€<?= number_format((int)$u['money'], 0, ',', '.') ?></strong></li>
            <li><span>Bank</span><strong>€<?= number_format((int)$u['bank_money'], 0, ',', '.') ?></strong></li>
            <li><span>BTC</span><strong style="color:#f7931a;"><?= formatBtc((float)$u['btc']) ?></strong></li>
            <li><span>Clicks</span><strong style="color:#4a9dff;"><?= number_format((int)$u['clicks']) ?></strong></li>
            <li><span>Energie</span><strong><?= (int)$u['energy'] ?> / <?= (int)$u['max_energy'] ?></strong></li>
            <li><span>Health</span><strong><?= (int)$u['health'] ?> / <?= (int)$u['max_health'] ?></strong></li>
            <li><span>Status</span>
                <strong>
                    <?php if ($u['is_banned']): ?>
                        <span class="tag-banned">BANNED</span>
                        <?php if ($u['ban_reason']): ?><br><small><?= htmlspecialchars($u['ban_reason']) ?></small><?php endif; ?>
                    <?php else: ?>
                        <span class="tag-ok">Actief</span>
                    <?php endif; ?>
                </strong>
            </li>
            <li><span>Geregistreerd</span><strong><?= date('d M Y H:i', strtotime($u['created_at'])) ?></strong></li>
            <li><span>Laatste login</span><strong><?= $u['last_login'] ? date('d M Y H:i', strtotime($u['last_login'])) : '—' ?></strong></li>
        </ul>
    </div>

    <!-- Aanpassen -->
    <div class="user-edit-card">
        <h2>💰 Waarden aanpassen</h2>
        <form method="POST" class="admin-form">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

            <label>Geld aanpassen</label>
            <div class="input-row">
                <input type="number" name="amount" placeholder="+/- bedrag" required>
                <button type="submit" name="action" value="adjust_money" class="btn btn-gold">Toepassen</button>
            </div>

            <label>BTC aanpassen</label>
            <div class="input-row">
                <input type="number" name="amount" step="0.00000001" placeholder="+/- BTC" required>
                <button type="submit" name="action" value="adjust_btc" class="btn btn-gold">Toepassen</button>
            </div>

            <label>Clicks aanpassen</label>
            <div class="input-row">
                <input type="number" name="amount" placeholder="+/- clicks" required>
                <button type="submit" name="action" value="adjust_clicks" class="btn btn-gold">Toepassen</button>
            </div>

            <label>XP aanpassen</label>
            <div class="input-row">
                <input type="number" name="amount" placeholder="+/- XP" required>
                <button type="submit" name="action" value="adjust_xp" class="btn btn-gold">Toepassen</button>
            </div>
        </form>
    </div>

    <!-- Status -->
    <div class="user-edit-card">
        <h2>⚡ Status aanpassen</h2>
        <form method="POST" class="admin-form">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

            <label>Energie</label>
            <div class="input-row">
                <input type="number" name="energy" min="0" max="99999" value="<?= (int)$u['energy'] ?>">
                <button type="submit" name="action" value="set_energy" class="btn btn-gold">Zet</button>
            </div>

            <label>Health</label>
            <div class="input-row">
                <input type="number" name="health" min="0" max="99999" value="<?= (int)$u['health'] ?>">
                <button type="submit" name="action" value="set_health" class="btn btn-gold">Zet</button>
            </div>
        </form>

        <form method="POST" style="margin-top:16px;">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <button type="submit" name="action" value="clear_cooldowns" class="btn btn-outline btn-full">
                🔄 Wis alle cooldowns, prison & ziekenhuis
            </button>
        </form>
    </div>

    <!-- Gevaarlijk -->
    <div class="user-edit-card danger">
        <h2>⚠️ Gevaarlijke acties</h2>

        <form method="POST" style="margin-bottom:12px;">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <button type="submit" name="action" value="toggle_admin" class="btn btn-outline btn-full"
                    onclick="return confirm('Admin rechten aanpassen?');">
                <?= $u['is_admin'] ? '👤 Admin afnemen' : '👑 Admin geven' ?>
            </button>
        </form>

        <?php if ($u['is_banned']): ?>
            <form method="POST" style="margin-bottom:12px;">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="unban">
                <input type="hidden" name="user_id" value="<?= $userId ?>">
                <button type="submit" class="btn btn-outline btn-full">🔓 Deblokkeren</button>
            </form>
        <?php else: ?>
            <form method="POST" style="margin-bottom:12px;">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="ban">
                <input type="hidden" name="user_id" value="<?= $userId ?>">
                <input type="text" name="reason" placeholder="Reden..." required style="margin-bottom:8px;">
                <input type="number" name="hours" placeholder="Uren (0 = permanent)" value="0">
                <button type="submit" class="btn btn-outline btn-full" style="color:#ff5c5c;border-color:#ff5c5c;">
                    🚫 Bannen
                </button>
            </form>
        <?php endif; ?>

        <form method="POST" onsubmit="return confirm('Weet je ZEKER dat je deze speler definitief wil verwijderen?');">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="user_id" value="<?= $userId ?>">
            <button type="submit" class="btn btn-full" style="background:#8b1a1a;color:white;">
                🗑️ Verwijder account
            </button>
        </form>
    </div>

    <!-- Notities -->
    <div class="user-edit-card">
        <h2>📝 Admin notities</h2>
        <form method="POST" class="admin-form">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <textarea name="notes" rows="6" placeholder="Interne notities over deze speler..."
                      style="width:100%;padding:12px;background:var(--bg-0);border:1px solid var(--border);border-radius:var(--radius-sm);color:var(--text);font-family:inherit;font-size:14px;resize:vertical;"><?= htmlspecialchars($u['notes'] ?? '') ?></textarea>
            <button type="submit" name="action" value="save_notes" class="btn btn-gold btn-full" style="margin-top:10px;">
                💾 Opslaan
            </button>
        </form>
    </div>
</div>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>