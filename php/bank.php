<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = currentUser($pdo);

// Achievement check bij laden (voor bank achievements)
$unlocked = checkAchievements($pdo, $user['id']);
$user = currentUser($pdo);

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $amount = (int)($_POST['amount'] ?? 0);
    $csrf   = $_POST['csrf'] ?? '';

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } elseif ($action === 'deposit') {
        $res = depositToBank($pdo, $user['id'], $amount);
        if (isset($res['error'])) $error = $res['error'];
        else {
            $success = $res['success'];
            logActivity($pdo, $user['id'], "🏦 €" . number_format($amount, 0, ',', '.') . " gestort op bank");
        }
    } elseif ($action === 'withdraw') {
        $res = withdrawFromBank($pdo, $user['id'], $amount);
        if (isset($res['error'])) $error = $res['error'];
        else {
            $success = $res['success'];
            logActivity($pdo, $user['id'], "🏦 €" . number_format($amount, 0, ',', '.') . " opgenomen van bank");
        }
    } elseif ($action === 'deposit_all') {
        $res = depositToBank($pdo, $user['id'], (int)$user['money']);
        if (isset($res['error'])) $error = $res['error'];
        else $success = $res['success'];
    }

    $user = currentUser($pdo);
    checkAchievements($pdo, $user['id']);
    $user = currentUser($pdo);
}

// Rente berekening
$dailyInterest = (int)floor($user['bank_money'] * BANK_INTEREST_RATE);

$pageTitle = 'Bank — Vendetta';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>De <span>Bank</span></h1>
    <p>Berg je geld veilig op en verdien <?= (BANK_INTEREST_RATE * 100) ?>% rente per dag.</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- Balans -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon">💵</div>
        <div class="stat-value gold">€<?= number_format($user['money'], 0, ',', '.') ?></div>
        <div class="stat-label">Cash in hand</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">🏦</div>
        <div class="stat-value gold">€<?= number_format($user['bank_money'], 0, ',', '.') ?></div>
        <div class="stat-label">Op de bank</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">📈</div>
        <div class="stat-value" style="color:#58e08c;">+€<?= number_format($dailyInterest, 0, ',', '.') ?></div>
        <div class="stat-label">Rente per dag</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">💰</div>
        <div class="stat-value">€<?= number_format($user['money'] + $user['bank_money'], 0, ',', '.') ?></div>
        <div class="stat-label">Totaal vermogen</div>
    </div>
</div>

<!-- Storten -->
<section class="section">
    <h2>💵 Storten</h2>
    <div class="casino-card">
        <p class="muted">Minimum storting: €<?= number_format(BANK_MIN_DEPOSIT, 0, ',', '.') ?>. Geld op de bank is veilig voor overvallen.</p>
        <form method="POST" class="casino-form">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="deposit">

            <div class="bet-input">
                <label>Bedrag (€)</label>
                <input type="number" name="amount" min="<?= BANK_MIN_DEPOSIT ?>"
                       max="<?= (int)$user['money'] ?>" value="<?= min(BANK_MIN_DEPOSIT, (int)$user['money']) ?>"
                       step="50" required>
            </div>

            <div class="bank-actions">
                <button type="submit" class="btn btn-gold btn-full"
                        <?= $user['money'] < BANK_MIN_DEPOSIT ? 'disabled' : '' ?>>
                    Storten
                </button>
                <button type="submit" name="action" value="deposit_all"
                        class="btn btn-outline btn-full"
                        onclick="return confirm('Alles (€<?= number_format($user['money'], 0, ',', '.') ?>) storten?');"
                        <?= $user['money'] < BANK_MIN_DEPOSIT ? 'disabled' : '' ?>>
                    Alles storten
                </button>
            </div>
        </form>
    </div>
</section>

<!-- Opnemen -->
<section class="section">
    <h2>🏦 Opnemen</h2>
    <div class="casino-card">
        <p class="muted">Haal geld van de bank om te gebruiken in het spel.</p>
        <form method="POST" class="casino-form">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="withdraw">

            <div class="bet-input">
                <label>Bedrag (€)</label>
                <input type="number" name="amount" min="100"
                       max="<?= (int)$user['bank_money'] ?>"
                       value="<?= min(BANK_MIN_DEPOSIT, (int)$user['bank_money']) ?>"
                       step="50" required>
            </div>

            <button type="submit" class="btn btn-gold btn-full"
                    <?= $user['bank_money'] < 100 ? 'disabled' : '' ?>>
                Opnemen
            </button>
        </form>
    </div>
</section>

<?php if (!empty($unlocked)): ?>
    <section class="section">
        <h2>🏆 Nieuwe achievements!</h2>
        <div class="achievement-grid">
            <?php foreach ($unlocked as $a): ?>
                <div class="achievement-card unlocked">
                    <div class="achievement-icon"><?= $a['icon'] ?></div>
                    <h3><?= htmlspecialchars($a['name']) ?></h3>
                    <p><?= htmlspecialchars($a['desc']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>