<?php
$adminPageTitle = 'Mass Acties';
require __DIR__ . '/includes/admin_header.php';

$error = null;
$success = null;
$affected = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } else {
        if ($action === 'mass_money') {
            $amount = (int)($_POST['amount'] ?? 0);
            $affected = massGiveMoney($pdo, $amount);
            adminLog($pdo, $admin['id'], 'mass_money', null, null, "€{$amount} aan {$affected} users");
            $success = "€" . number_format($amount, 0, ',', '.') . " gegeven aan {$affected} spelers!";
        } elseif ($action === 'mass_btc') {
            $amount = (float)($_POST['amount'] ?? 0);
            $affected = massGiveBtc($pdo, $amount);
            adminLog($pdo, $admin['id'], 'mass_btc', null, null, "₿{$amount} aan {$affected} users");
            $success = "₿{$amount} gegeven aan {$affected} spelers!";
        } elseif ($action === 'mass_clicks') {
            $amount = (int)($_POST['amount'] ?? 0);
            $affected = massGiveClicks($pdo, $amount);
            adminLog($pdo, $admin['id'], 'mass_clicks', null, null, "{$amount} clicks aan {$affected} users");
            $success = "{$amount} clicks gegeven aan {$affected} spelers!";
        } elseif ($action === 'mass_energy') {
            $affected = massFullEnergy($pdo);
            adminLog($pdo, $admin['id'], 'mass_energy', null, null, "{$affected} users");
            $success = "Alle energie hersteld voor {$affected} spelers!";
        } elseif ($action === 'mass_health') {
            $affected = massFullHealth($pdo);
            adminLog($pdo, $admin['id'], 'mass_health', null, null, "{$affected} users");
            $success = "Alle health hersteld voor {$affected} spelers!";
        } elseif ($action === 'mass_unban') {
            $affected = massUnbanAll($pdo);
            adminLog($pdo, $admin['id'], 'mass_unban', null, null, "{$affected} users");
            $success = "{$affected} spelers gedebandeerd!";
        }
    }
}
?>

<h1 class="admin-title">⚡ Mass Acties</h1>

<?php if ($error): ?><div class="admin-alert admin-alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="admin-alert admin-alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<div class="admin-alert admin-alert-warning">
    ⚠️ Deze acties hebben invloed op ALLE spelers. Gebruik met zorg!
</div>

<div class="mass-action-grid">
    <div class="mass-card">
        <h3>💰 Iedereen geld geven</h3>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="number" name="amount" placeholder="Bedrag (negatief = afnemen)" required
                   style="width:100%;padding:10px;margin-bottom:10px;background:var(--bg-0);border:1px solid var(--border);border-radius:4px;color:var(--text);">
            <button type="submit" name="action" value="mass_money" class="btn btn-gold btn-full"
                    onclick="return confirm('Geld naar ALLE spelers sturen?');">
                Toepassen
            </button>
        </form>
    </div>

    <div class="mass-card">
        <h3>₿ Iedereen BTC geven</h3>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="number" name="amount" step="0.00000001" placeholder="BTC bedrag" required
                   style="width:100%;padding:10px;margin-bottom:10px;background:var(--bg-0);border:1px solid var(--border);border-radius:4px;color:var(--text);">
            <button type="submit" name="action" value="mass_btc" class="btn btn-gold btn-full"
                    onclick="return confirm('BTC naar ALLE spelers sturen?');">
                Toepassen
            </button>
        </form>
    </div>

    <div class="mass-card">
        <h3>🖱️ Iedereen clicks geven</h3>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="number" name="amount" placeholder="Aantal clicks" required
                   style="width:100%;padding:10px;margin-bottom:10px;background:var(--bg-0);border:1px solid var(--border);border-radius:4px;color:var(--text);">
            <button type="submit" name="action" value="mass_clicks" class="btn btn-gold btn-full"
                    onclick="return confirm('Clicks naar ALLE spelers sturen?');">
                Toepassen
            </button>
        </form>
    </div>

    <div class="mass-card">
        <h3>⚡ Iedereen volle energie</h3>
        <p class="muted">Herstel alle energie voor alle spelers.</p>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <button type="submit" name="action" value="mass_energy" class="btn btn-gold btn-full"
                    onclick="return confirm('Alle energie herstellen?');">
                Toepassen
            </button>
        </form>
    </div>

    <div class="mass-card">
        <h3>❤️ Iedereen volle health</h3>
        <p class="muted">Herstel alle health en verwijder uit ziekenhuis.</p>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <button type="submit" name="action" value="mass_health" class="btn btn-gold btn-full"
                    onclick="return confirm('Alle health herstellen?');">
                Toepassen
            </button>
        </form>
    </div>

    <div class="mass-card">
        <h3>🔓 Iedereen deblokkeren</h3>
        <p class="muted">Haal alle bans weg.</p>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <button type="submit" name="action" value="mass_unban" class="btn btn-outline btn-full"
                    onclick="return confirm('Alle bans verwijderen?');">
                Toepassen
            </button>
        </form>
    </div>
</div>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>