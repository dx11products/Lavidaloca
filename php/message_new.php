<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = currentUser($pdo);
$error = null;
$toUser = null;

// Als er een ?to=ID is, laad die user
if (isset($_GET['to'])) {
    $toId = (int)$_GET['to'];
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$toId]);
    $toUser = $stmt->fetch();
    if ($toUser && (int)$toUser['id'] === (int)$user['id']) $toUser = null;
}

// Zoek
$searchResults = [];
$searchQuery = trim($_GET['q'] ?? '');
if ($searchQuery !== '' && !$toUser) {
    $searchResults = searchPlayers($pdo, $searchQuery, (int)$user['id'], 20);
}

$pageTitle = 'Nieuw bericht — Vendetta';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <a href="messages.php" class="back-link" style="display:inline-block;text-align:left;margin:0 0 10px 0;">← Terug naar inbox</a>
    <h1>✍️ <span>Nieuw bericht</span></h1>
    <p>Kies een speler en stuur een privébericht.</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (!$toUser): ?>
    <!-- Zoekfunctie -->
    <section class="section">
        <h2>🔍 Zoek een speler</h2>
        <form method="GET" class="pm-search-form">
            <input type="text" name="q" value="<?= htmlspecialchars($searchQuery) ?>"
                   placeholder="Typ een gebruikersnaam..." autofocus>
            <button type="submit" class="btn btn-gold">Zoek</button>
        </form>

        <?php if ($searchQuery !== ''): ?>
            <?php if (empty($searchResults)): ?>
                <p class="muted" style="margin-top:16px;">Geen spelers gevonden voor "<?= htmlspecialchars($searchQuery) ?>".</p>
            <?php else: ?>
                <div class="pm-search-results">
                    <?php foreach ($searchResults as $r):
                        $color = pmRankColor((int)$r['xp']);
                        $initial = strtoupper(mb_substr($r['username'], 0, 1));
                    ?>
                        <a href="message_new.php?to=<?= (int)$r['id'] ?>" class="pm-search-item">
                            <div class="pms-avatar" style="border-color:<?= $color ?>;"><?= $initial ?></div>
                            <div class="pms-info">
                                <strong style="color:<?= $color ?>;"><?= htmlspecialchars($r['username']) ?></strong>
                                <small><?= htmlspecialchars($r['rank_title']) ?> · <?= number_format((int)$r['xp']) ?> XP</small>
                            </div>
                            <span class="pms-arrow">→</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
<?php else: ?>
    <!-- Stuur formulier -->
    <section class="section">
        <h2>Bericht naar <span style="color:<?= pmRankColor(0) ?>;"><?= htmlspecialchars($toUser['username']) ?></span></h2>

        <div class="casino-card">
            <form method="POST" action="message_new.php?to=<?= (int)$toUser['id'] ?>" class="casino-form">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

                <div class="bet-input">
                    <label>Bericht (max <?= PM_BODY_MAX ?> tekens)</label>
                    <textarea name="body" rows="6" required maxlength="<?= PM_BODY_MAX ?>"
                              placeholder="Typ je bericht..."
                              style="padding:12px 14px;background:var(--bg-0);border:1px solid var(--border);border-radius:var(--radius-sm);color:var(--text);font-family:var(--font-body);font-size:16px;width:100%;resize:vertical;"></textarea>
                </div>

                <button type="submit" class="btn btn-gold btn-large btn-full">💌 Verstuur</button>
            </form>
        </div>
    </section>
<?php endif; ?>

<?php
// Verwerk POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $toUser) {
    $csrf = $_POST['csrf'] ?? '';
    $body = $_POST['body'] ?? '';

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } else {
        $res = sendPrivateMessage($pdo, (int)$user['id'], (int)$toUser['id'], $body);
        if (isset($res['error'])) {
            $error = $res['error'];
        } else {
            redirect("message.php?id={$res['conversation_id']}");
        }
    }
}
?>

<?php require __DIR__ . '/includes/footer.php'; ?>