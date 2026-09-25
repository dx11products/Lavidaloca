<?php
$adminPageTitle = 'Broadcast';
require __DIR__ . '/includes/admin_header.php';

$error = null;
$success = null;
$sent = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } else {
        $message = trim($_POST['message'] ?? '');
        $icon = trim($_POST['icon'] ?? '📢');
        if (empty($message)) {
            $error = 'Bericht mag niet leeg zijn.';
        } else {
            $sent = broadcastMessage($pdo, $message, $icon);
            adminLog($pdo, $admin['id'], 'broadcast', null, null, "{$sent} users: {$message}");
            $success = "Bericht verstuurd naar {$sent} spelers!";
        }
    }
}
?>

<h1 class="admin-title">📢 Broadcast naar alle spelers</h1>

<?php if ($error): ?><div class="admin-alert admin-alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="admin-alert admin-alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<section class="admin-section">
    <form method="POST" class="admin-form">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

        <label>Icoon (emoji)</label>
        <input type="text" name="icon" value="📢" maxlength="8" style="max-width:100px;">

        <label>Bericht</label>
        <textarea name="message" rows="4" required placeholder="Typ het bericht dat alle spelers ontvangen..." maxlength="255"
                  style="width:100%;padding:14px;background:var(--bg-0);border:1px solid var(--border);border-radius:var(--radius-sm);color:var(--text);font-family:inherit;font-size:15px;resize:vertical;"></textarea>

        <button type="submit" class="btn btn-gold btn-large"
                onclick="return confirm('Bericht naar ALLE spelers sturen?');">
            📢 Verstuur naar iedereen
        </button>
    </form>
</section>

<section class="admin-section">
    <h2>💡 Voorbeelden</h2>
    <ul class="tip-list">
        <li><strong>🎉</strong> "Dubbel XP weekend! Alle crimes geven 2x XP!"</li>
        <li><strong>⚠️</strong> "Server onderhoud om 03:00. Duurt 15 minuten."</li>
        <li><strong>🎁</strong> "Iedereen krijgt €10.000 cadeau van de admin!"</li>
        <li><strong>🏴</strong> "Nieuw: 3 nieuwe heists toegevoegd!"</li>
        <li><strong>💰</strong> "Bank rente verhoogd naar 3% per dag!"</li>
    </ul>
</section>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>